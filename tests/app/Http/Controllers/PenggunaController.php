<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Manajemen akun pengguna.
 *
 * Hanya admin yang boleh masuk ke sini. Tidak ada pendaftaran publik:
 * akun hanya bisa dibuat oleh admin dari halaman ini.
 */
class PenggunaController extends Controller
{
    public function index()
    {
        $pengguna = User::withCount('aktivitas')
            ->with(['aktivitas' => fn ($q) => $q->latest()->limit(1), 'kelas', 'siswa', 'anak'])
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'guru_piket' THEN 1 WHEN 'wali_kelas' THEN 2 WHEN 'orang_tua' THEN 3 WHEN 'siswa' THEN 4 ELSE 5 END")
            ->orderBy('name')
            ->get();

        $kelas = Kelas::orderBy('nama_kelas')->get();
        $daftarSiswa = Siswa::with('kelas')->orderBy('nama')->get()
            ->groupBy(fn ($s) => $s->kelas->nama_kelas ?? 'Tanpa kelas');

        return view('pengguna.index', compact('pengguna', 'kelas', 'daftarSiswa'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::daftarRole()))],
            'password' => ['required', 'confirmed', Password::min(8)],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'siswa_id' => ['nullable', 'integer', 'exists:siswa,id'],
            'anak_ids' => ['nullable', 'array'],
            'anak_ids.*' => ['integer', 'exists:siswa,id'],
        ]);

        $data['name'] = trim($data['name']);
        $data['email'] = Str::lower(trim($data['email']));
        $this->pastikanEmailUnik($data['email']);

        $this->validasiTugas($request, $data, null);

        $pengguna = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
            'kelas_id' => $data['role'] === User::ROLE_WALI_KELAS ? ($data['kelas_id'] ?? null) : null,
            'siswa_id' => $data['role'] === User::ROLE_SISWA ? ($data['siswa_id'] ?? null) : null,
        ]);

        if ($data['role'] === User::ROLE_ORANG_TUA && ! empty($data['anak_ids'])) {
            $pengguna->anak()->sync($data['anak_ids']);
        }

        $this->sinkronWaliKelas($pengguna);

        $request->user()->catatAktivitas(
            'pengguna_buat',
            "Akun baru dibuat: {$pengguna->name} ({$pengguna->email}) - {$pengguna->roleLabel()}",
            $request->ip()
        );

        return back()->with('success', "Akun {$pengguna->name} berhasil dibuat.");
    }

    public function update(Request $request, User $pengguna)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($pengguna->id)],
            'role' => ['required', Rule::in(array_keys(User::daftarRole()))],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'siswa_id' => ['nullable', 'integer', 'exists:siswa,id'],
            'anak_ids' => ['nullable', 'array'],
            'anak_ids.*' => ['integer', 'exists:siswa,id'],
        ]);

        $data['name'] = trim($data['name']);
        $data['email'] = Str::lower(trim($data['email']));
        $this->pastikanEmailUnik($data['email'], $pengguna);

        // Admin tidak boleh mencabut haknya sendiri lalu terkunci dari sistem.
        if ($pengguna->id === $request->user()->id && $data['role'] !== User::ROLE_ADMIN) {
            return back()->with('error', 'Peran akun Anda sendiri tidak bisa diubah.');
        }

        // Selalu harus ada minimal satu admin.
        if ($pengguna->role === User::ROLE_ADMIN
            && $data['role'] !== User::ROLE_ADMIN
            && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->with('error', 'Ini akun admin terakhir. Sistem harus punya minimal satu administrator.');
        }

        $this->validasiTugas($request, $data, $pengguna);

        // Simpan nama lama untuk membersihkan penanda wali kelas yang memakai
        // nama tersebut (kalau nama ikut diubah).
        $namaLama = $pengguna->name;
        $kelasLama = $pengguna->kelas_id;
        $kelasBaru = $data['role'] === User::ROLE_WALI_KELAS ? ($data['kelas_id'] ?? null) : null;
        $siswaBaru = $data['role'] === User::ROLE_SISWA ? ($data['siswa_id'] ?? null) : null;

        $pengguna->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'kelas_id' => $kelasBaru,
            'siswa_id' => $siswaBaru,
        ]);

        if ($data['role'] === User::ROLE_ORANG_TUA) {
            $pengguna->anak()->sync($data['anak_ids'] ?? []);
        } else {
            $pengguna->anak()->detach();
        }

        // Bersihkan penanda wali kelas di kelas lama kalau akun ini pindah/berhenti jadi wali kelas.
        if ($kelasLama && $kelasLama !== $kelasBaru) {
            $kelasLamaObj = Kelas::find($kelasLama);
            if ($kelasLamaObj && $kelasLamaObj->wali_kelas === $namaLama) {
                $kelasLamaObj->update(['wali_kelas' => null]);
            }
        }

        $pengguna->load('kelas');
        $this->sinkronWaliKelas($pengguna);

        if (! empty($data['password'])) {
            $pengguna->forceFill([
                'password' => Hash::make($data['password']),
                // Putuskan sesi "ingat saya" lama pada perangkat pengguna.
                'remember_token' => null,
            ])->save();

            $request->user()->catatAktivitas(
                'pengguna_sandi',
                "Kata sandi {$pengguna->name} direset oleh {$request->user()->name}",
                $request->ip()
            );
        }

        $request->user()->catatAktivitas(
            'pengguna_ubah',
            "Akun diubah: {$pengguna->name} ({$pengguna->email}) - {$pengguna->roleLabel()}",
            $request->ip()
        );

        return back()->with('success', "Akun {$pengguna->name} berhasil diperbarui.");
    }

    /**
     * Cegah email ganda yang hanya berbeda huruf besar/kecil.
     *
     * Aturan unique bawaan sudah menangkap email yang sama persis; pemeriksaan
     * ini membuat perilakunya konsisten di MySQL maupun SQLite.
     */
    protected function pastikanEmailUnik(string $email, ?User $kecuali = null): void
    {
        $dipakai = User::whereRaw('lower(email) = ?', [$email])
            ->when($kecuali, fn ($q) => $q->where('id', '!=', $kecuali->id))
            ->exists();

        if ($dipakai) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'Email ini sudah dipakai akun lain.',
            ]);
        }
    }

    /**
     * Validasi tugas peran baru.
     */
    protected function validasiTugas(Request $request, array $data, ?User $pengguna): void
    {
        if ($data['role'] === User::ROLE_WALI_KELAS) {
            if (empty($data['kelas_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'kelas_id' => 'Pilih kelas untuk wali kelas.',
                ]);
            }

            $ada = User::where('role', User::ROLE_WALI_KELAS)
                ->where('kelas_id', $data['kelas_id'])
                ->when($pengguna, fn ($q) => $q->where('id', '!=', $pengguna->id))
                ->exists();

            if ($ada) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'kelas_id' => 'Kelas ini sudah punya wali kelas lain. Satu kelas cukup satu wali kelas.',
                ]);
            }
        }

        if ($data['role'] === User::ROLE_SISWA) {
            if (empty($data['siswa_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'siswa_id' => 'Pilih data siswa untuk akun ini.',
                ]);
            }

            $ada = User::where('role', User::ROLE_SISWA)
                ->where('siswa_id', $data['siswa_id'])
                ->when($pengguna, fn ($q) => $q->where('id', '!=', $pengguna->id))
                ->exists();

            if ($ada) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'siswa_id' => 'Sudah ada akun siswa untuk data siswa ini.',
                ]);
            }
        }

        if ($data['role'] === User::ROLE_ORANG_TUA) {
            if (empty($data['anak_ids'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'anak_ids' => 'Pilih minimal satu anak yang terhubung.',
                ]);
            }
        }
    }

    /**
     * Update kolom wali_kelas di tabel kelas biar tampilannya konsisten.
     */
    protected function sinkronWaliKelas(User $pengguna): void
    {
        if (! $pengguna->isWaliKelas() || ! $pengguna->kelas_id) {
            return;
        }

        $kelas = Kelas::find($pengguna->kelas_id);

        if (! $kelas) {
            return;
        }

        // Jika kelas sudah dipegang akun wali kelas lain, jangan timpa.
        // (Teks di kolom kelas bisa saja basi, jadi patokannya data akun.)
        $adaLain = User::where('role', User::ROLE_WALI_KELAS)
            ->where('kelas_id', $kelas->id)
            ->where('id', '!=', $pengguna->id)
            ->exists();

        if ($adaLain) {
            return;
        }

        $kelas->update(['wali_kelas' => $pengguna->name]);
    }

    public function destroy(Request $request, User $pengguna)
    {
        if ($pengguna->id === $request->user()->id) {
            return back()->with('error', 'Akun Anda sendiri tidak bisa dihapus.');
        }

        if ($pengguna->role === User::ROLE_ADMIN && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->with('error', 'Ini akun admin terakhir, tidak bisa dihapus.');
        }

        // Lepas penanda wali kelas kalau akun ini dihapus.
        if ($pengguna->isWaliKelas() && $pengguna->kelas_id) {
            $kelas = Kelas::find($pengguna->kelas_id);
            if ($kelas && $kelas->wali_kelas === $pengguna->name) {
                $kelas->update(['wali_kelas' => null]);
            }
        }

        $nama = $pengguna->name;
        $pengguna->delete();

        $request->user()->catatAktivitas('pengguna_hapus', "Akun dihapus: {$nama}", $request->ip());

        return back()->with('success', "Akun {$nama} berhasil dihapus.");
    }
}
