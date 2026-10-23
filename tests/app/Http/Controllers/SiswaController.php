<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiswaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'kelas_id' => ['nullable', 'integer'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Siswa::with('kelas')->cari($request->input('cari'));

        if ($user->isWaliKelas()) {
            // Wali kelas hanya bisa melihat siswa di kelas yang diampu.
            $query->where('kelas_id', $user->kelas_id ?? 0);
        } elseif ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        $siswa = $query->orderBy('nama')->paginate(15)->withQueryString();

        if ($user->isWaliKelas()) {
            $kelas = Kelas::whereKey($user->kelas_id ?? 0)->get();
        } else {
            $kelas = Kelas::orderBy('nama_kelas')->get();
        }

        return view('siswa.index', compact('siswa', 'kelas'));
    }

    public function create()
    {
        $kelas = Kelas::orderBy('nama_kelas')->get();

        return view('siswa.create', compact('kelas'));
    }

    public function store(Request $request)
    {
        $request->validate($this->aturan());

        $data = $request->only('nis', 'nama', 'kelas_id', 'jenis_kelamin');

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('siswa', 'public');
        }

        // kode_qr dibuat otomatis lewat model event (lihat Siswa::boot())
        $siswa = Siswa::create($data);

        $request->user()?->catatAktivitas(
            'siswa_buat',
            "Siswa ditambahkan: {$siswa->nama} ({$siswa->nis})",
            $request->ip()
        );

        return redirect()->route('siswa.index')
            ->with('success', 'Siswa berhasil ditambahkan.')
            ->with('siswa_baru', $siswa->id);
    }

    public function edit(Siswa $siswa)
    {
        $kelas = Kelas::orderBy('nama_kelas')->get();

        return view('siswa.edit', compact('siswa', 'kelas'));
    }

    public function update(Request $request, Siswa $siswa)
    {
        $request->validate($this->aturan($siswa));

        $data = $request->only('nis', 'nama', 'kelas_id', 'jenis_kelamin');

        if ($request->hasFile('foto')) {
            if ($siswa->foto) {
                Storage::disk('public')->delete($siswa->foto);
            }
            $data['foto'] = $request->file('foto')->store('siswa', 'public');
        }

        $siswa->update($data);

        $request->user()?->catatAktivitas(
            'siswa_ubah',
            "Siswa diubah: {$siswa->nama} ({$siswa->nis})",
            $request->ip()
        );

        return redirect()->route('siswa.index')->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Request $request, Siswa $siswa)
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        if ($siswa->foto) {
            Storage::disk('public')->delete($siswa->foto);
        }

        $nama = $siswa->nama;
        $nis = $siswa->nis;

        // Lepas tautan akun siswa/orang tua sebelum menghapus data siswa.
        $siswa->orangTua()->detach();
        User::where('siswa_id', $siswa->id)->update(['siswa_id' => null]);

        $siswa->delete();

        $request->user()?->catatAktivitas('siswa_hapus', "Siswa dihapus: {$nama} ({$nis})", $request->ip());

        return back()->with('success', 'Data siswa berhasil dihapus.');
    }

    // Buat ulang kode QR (kartu hilang / bocor). Kartu lama langsung tidak berlaku.
    public function buatUlangQr(Request $request, Siswa $siswa)
    {
        $siswa->update(['kode_qr' => Siswa::buatKodeQr()]);

        $request->user()?->catatAktivitas(
            'siswa_ubah',
            "Kode QR dibuat ulang: {$siswa->nama} ({$siswa->nis})",
            $request->ip()
        );

        return redirect()->route('siswa.edit', $siswa)
            ->with('success', 'Kode QR baru dibuat. Kartu lama tidak berlaku lagi, silakan cetak ulang kartunya.');
    }

    // Kartu pelajar satu siswa, siap cetak.
    public function kartu(Request $request, Siswa $siswa)
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! ($user->isWaliKelas() && $user->bisaLihatSiswa($siswa))) {
            abort(403);
        }

        $daftar = collect([$siswa->load('kelas')]);
        $massal = false;

        return view('siswa.kartu', compact('daftar', 'massal'));
    }

    // Kartu pelajar seluruh siswa dalam satu kelas (cetak massal, A4).
    public function kartuKelas(Request $request)
    {
        $user = $request->user();

        // Hanya administrator dan wali kelas yang boleh mencetak kartu.
        if (! $user->isAdmin() && ! $user->isWaliKelas()) {
            abort(403, 'Anda tidak punya akses untuk mencetak kartu.');
        }

        $request->validate(['kelas_id' => ['required', 'integer', 'exists:kelas,id']]);

        $kelasId = (int) $request->kelas_id;

        if ($user->isWaliKelas() && (int) $user->kelas_id !== $kelasId) {
            abort(403);
        }

        $daftar = Siswa::with('kelas')->where('kelas_id', $kelasId)->orderBy('nama')->get();

        if ($daftar->isEmpty()) {
            return back()->with('error', 'Kelas ini belum memiliki siswa.');
        }

        $massal = true;

        return view('siswa.kartu', compact('daftar', 'massal'));
    }

    protected function aturan(?Siswa $siswa = null): array
    {
        $nisUnik = 'unique:siswa,nis' . ($siswa ? ',' . $siswa->id : '');

        return [
            'nis' => ['required', 'string', 'max:30', $nisUnik],
            'nama' => ['required', 'string', 'max:100'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            // Hanya JPG/PNG/WEBP (SVG ditolak karena bisa memuat script).
            'foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
