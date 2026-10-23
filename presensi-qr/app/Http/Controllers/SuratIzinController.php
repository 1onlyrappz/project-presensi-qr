<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\SuratIzin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pengajuan surat izin siswa.
 *
 * Semua peran boleh mengajukan (siswa: diri sendiri, orang tua: anaknya,
 * wali kelas: kelasnya, administrator/guru piket: siapa pun). Administrator
 * dan wali kelas terkait menyetujui/menolak. Surat yang disetujui otomatis
 * mencatat hari izin/sakit di tabel presensi supaya langsung terbaca di
 * rekap bulanan.
 */
class SuratIzinController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $filter = $request->validate([
            'status' => ['nullable', 'in:pending,disetujui,ditolak'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $filter['status'] ?? null;
        $kelasId = $filter['kelas_id'] ?? null;
        $bulan = $filter['bulan'] ?? null;
        $cari = $filter['cari'] ?? null;

        // Wali kelas terkunci ke kelasnya sendiri.
        if ($user->isWaliKelas()) {
            $kelasId = $user->kelas_id ? (int) $user->kelas_id : 0;
        }

        $query = SuratIzin::with('siswa.kelas', 'pengaju', 'pemroses');

        // Batasi berdasarkan peran.
        if ($user->isSiswa()) {
            $query->where('siswa_id', $user->siswa_id ?? 0);
        } elseif ($user->isOrangTua()) {
            $query->whereIn('siswa_id', $user->idSiswaTerhubung() ?: [0]);
        } elseif ($user->isWaliKelas()) {
            $query->whereHas('siswa', fn ($q) => $q->where('kelas_id', $user->kelas_id ?? 0));
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($kelasId !== null) {
            $query->whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelasId));
        }

        if ($bulan) {
            $query->whereBetween('tanggal_mulai', [
                Carbon::createFromFormat('Y-m-d', $bulan . '-01')->toDateString(),
                Carbon::createFromFormat('Y-m-d', $bulan . '-01')->endOfMonth()->toDateString(),
            ]);
        }

        if ($cari) {
            $query->where(function ($q) use ($cari) {
                $q->where('alasan', 'like', "%{$cari}%")
                    ->orWhere('nomor_surat', 'like', "%{$cari}%")
                    ->orWhereHas('siswa', fn ($s) => $s->cari($cari));
            });
        }

        $suratIzin = $query->latest()->paginate(15)->withQueryString();

        // Ringkasan jumlah surat per status (mengikuti filter status & kelas).
        $ringkasQuery = SuratIzin::when($status, fn ($q) => $q->where('status', $status));

        if ($user->isSiswa()) {
            $ringkasQuery->where('siswa_id', $user->siswa_id ?? 0);
        } elseif ($user->isOrangTua()) {
            $ringkasQuery->whereIn('siswa_id', $user->idSiswaTerhubung() ?: [0]);
        } elseif ($user->isWaliKelas()) {
            $ringkasQuery->whereHas('siswa', fn ($q) => $q->where('kelas_id', $user->kelas_id ?? 0));
        }

        if ($kelasId !== null) {
            $ringkasQuery->whereHas('siswa', fn ($q) => $q->where('kelas_id', $kelasId));
        }

        $ringkas = $ringkasQuery
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'disetujui' THEN 1 ELSE 0 END) as disetujui,
                SUM(CASE WHEN status = 'ditolak' THEN 1 ELSE 0 END) as ditolak")
            ->first();

        // Daftar kelas dan siswa untuk filter/ form sesuai peran.
        if ($user->isStaf() && ! $user->isWaliKelas()) {
            $kelas = Kelas::orderBy('nama_kelas')->get();
        } elseif ($user->isWaliKelas() && $user->kelas) {
            $kelas = collect([$user->kelas]);
        } else {
            $kelas = collect();
        }

        if ($user->isSiswa()) {
            $daftarSiswa = Siswa::with('kelas')->whereKey($user->siswa_id ?? 0)->get()->groupBy(fn ($s) => $s->kelas->nama_kelas ?? 'Tanpa kelas');
        } elseif ($user->isOrangTua()) {
            $daftarSiswa = Siswa::with('kelas')->whereIn('id', $user->idSiswaTerhubung() ?: [0])->orderBy('nama')->get()->groupBy(fn ($s) => $s->kelas->nama_kelas ?? 'Tanpa kelas');
        } elseif ($user->isWaliKelas()) {
            $daftarSiswa = Siswa::with('kelas')->where('kelas_id', $user->kelas_id ?? 0)->orderBy('nama')->get()->groupBy(fn ($s) => $s->kelas->nama_kelas ?? 'Tanpa kelas');
        } else {
            // Daftar siswa untuk dipilih di formulir (dikelompokkan per kelas).
            $daftarSiswa = Siswa::with('kelas')->orderBy('nama')->get()
                ->groupBy(fn ($s) => $s->kelas->nama_kelas ?? 'Tanpa kelas');
        }

        return view('surat_izin.index', compact(
            'suratIzin',
            'kelas',
            'daftarSiswa',
            'status',
            'kelasId',
            'bulan',
            'cari',
            'ringkas'
        ));
    }

    // Mencatat surat izin baru (bisa diajukan oleh petugas, wali kelas, orang tua, atau siswa).
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate($this->aturan());

        // Akun siswa selalu mengajukan untuk dirinya sendiri.
        if ($user->isSiswa()) {
            if (! $user->siswa_id) {
                abort(403, 'Akun Anda belum ditautkan ke data siswa. Hubungi Administrator.');
            }

            $data['siswa_id'] = (int) $user->siswa_id;
        }

        $siswa = Siswa::findOrFail((int) $data['siswa_id']);

        // Batasi siswa yang boleh diajukan untuknya.
        if ($user->isSiswa() && (int) $siswa->id !== (int) $user->siswa_id) {
            abort(403, 'Anda hanya bisa mengajukan izin untuk diri sendiri.');
        }

        if ($user->isOrangTua() && ! $user->bisaLihatSiswa($siswa)) {
            abort(403, 'Anda hanya bisa mengajukan izin untuk anak yang terhubung dengan akun Anda.');
        }

        if ($user->isWaliKelas() && ! $user->bisaLihatSiswa($siswa)) {
            abort(403, 'Anda hanya bisa mengajukan izin untuk siswa di kelas yang Anda ampu.');
        }
        // admin & guru piket boleh mengajukan untuk siapa pun (sudah dibatasi route).

        if ($request->hasFile('lampiran')) {
            $data['lampiran'] = $request->file('lampiran')->store('surat-izin', 'public');
        }

        $data['status'] = SuratIzin::STATUS_PENDING;
        $data['diajukan_oleh'] = $user->id;
        $data['siswa_id'] = (int) $data['siswa_id'];

        $surat = SuratIzin::create($data);

        $surat->loadMissing('siswa');

        $user->catatAktivitas(
            'izin_buat',
            "Surat izin diajukan: {$surat->siswa->nama} ({$surat->siswa->nis}) - {$surat->label_jenis}",
            $request->ip()
        );

        return back()->with('success', "Surat izin {$surat->label_jenis} untuk {$surat->siswa->nama} tersimpan. Menunggu persetujuan wali kelas atau administrator.");
    }

    // Persetujuan / penolakan surat izin (administrator atau wali kelas kelas terkait).
    public function proses(Request $request, SuratIzin $suratIzin)
    {
        $user = $request->user();
        $suratIzin->loadMissing('siswa');

        if (! $user->bisaProsesIzin($suratIzin->siswa)) {
            abort(403, 'Anda hanya bisa memproses surat izin untuk siswa di kelas yang Anda ampu.');
        }

        if ($suratIzin->status !== SuratIzin::STATUS_PENDING) {
            return back()->with('error', 'Surat izin ini sudah diproses, tidak bisa diproses ulang.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:disetujui,ditolak'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $disetujui = $data['status'] === SuratIzin::STATUS_DISETUJUI;

        DB::transaction(function () use ($suratIzin, $data, $disetujui, $user) {
            $suratIzin->update([
                'status' => $data['status'],
                'catatan' => $data['catatan'] ?? null,
                'diproses_oleh' => $user->id,
                'diproses_at' => Carbon::now(config('presensi.timezone')),
            ]);

            if ($disetujui) {
                $this->terapkanKePresensi($suratIzin);
            } else {
                $this->hapusPresensi($suratIzin);
            }
        });

        $nama = $suratIzin->siswa->nama;
        $jumlahHari = $suratIzin->jumlah_hari;

        $user->catatAktivitas(
            $disetujui ? 'izin_setuju' : 'izin_tolak',
            $disetujui
                ? "Surat izin disetujui: {$nama} - {$jumlahHari} hari ({$suratIzin->label_jenis})"
                : "Surat izin ditolak: {$nama} ({$suratIzin->label_jenis})",
            $request->ip()
        );

        return back()->with(
            'success',
            $disetujui
                ? "Surat izin disetujui. {$jumlahHari} hari tercatat sebagai izin di rekap bulanan."
                : 'Surat izin ditolak. Catatan izin di rekap bulanan dikembalikan.'
        );
    }

    // Menghapus surat izin (khusus administrator).
    public function destroy(Request $request, SuratIzin $suratIzin)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            abort(403);
        }

        $suratIzin->loadMissing('siswa');
        $nama = $suratIzin->siswa->nama;

        DB::transaction(function () use ($suratIzin) {
            $this->hapusPresensi($suratIzin);

            if ($suratIzin->lampiran) {
                Storage::disk('public')->delete($suratIzin->lampiran);
            }

            $suratIzin->delete();
        });

        $user->catatAktivitas('izin_hapus', "Surat izin dihapus: {$nama}", $request->ip());

        return back()->with('success', 'Surat izin dihapus.');
    }

    /**
     * Mencatat hari izin/sakit ke tabel presensi.
     *
     * Baris yang sudah berisi scan QR tidak disentuh: siswa yang hadir
     * tetap dihitung hadir, bukan izin.
     */
    protected function terapkanKePresensi(SuratIzin $surat): void
    {
        $status = $surat->status_absen;
        $catatan = 'Surat izin ' . strtolower($surat->label_jenis)
            . ($surat->nomor_surat ? ' (' . $surat->nomor_surat . ')' : '');

        foreach ($this->tanggalIzin($surat) as $tanggal) {
            $ada = Presensi::where('siswa_id', $surat->siswa_id)
                ->where('tanggal', $tanggal)
                ->first();

            if (! $ada) {
                Presensi::create([
                    'siswa_id' => $surat->siswa_id,
                    'tanggal' => $tanggal,
                    'jam_masuk' => null,
                    'jam_pulang' => null,
                    'status' => $status,
                    'surat_izin_id' => $surat->id,
                    'catatan' => $catatan,
                ]);

                continue;
            }

            // Baris manual lain (misalnya dari surat sebelumnya) -> pakai yang terbaru.
            if ($ada->jam_masuk === null && in_array($ada->status, Presensi::statusManual(), true)) {
                $ada->update([
                    'status' => $status,
                    'surat_izin_id' => $surat->id,
                    'catatan' => $catatan,
                ]);
            }
        }
    }

    /**
     * Menghapus catatan izin/sakit yang dibuat oleh surat ini.
     *
     * Scan QR yang sudah tercatat tidak dihapus.
     */
    protected function hapusPresensi(SuratIzin $surat): void
    {
        Presensi::where('surat_izin_id', $surat->id)
            ->whereNull('jam_masuk')
            ->whereIn('status', Presensi::statusManual())
            ->delete();
    }

    /**
     * Daftar tanggal (hari sekolah) yang tercakup dalam rentang surat.
     */
    protected function tanggalIzin(SuratIzin $surat): array
    {
        $mulai = Carbon::parse($surat->tanggal_mulai);
        $selesai = Carbon::parse($surat->tanggal_selesai);
        $hariKerja = config('presensi.hari_kerja', [1, 2, 3, 4, 5]);
        $tanggal = [];

        while ($mulai->lte($selesai)) {
            if (in_array((int) $mulai->dayOfWeek, $hariKerja, true)) {
                $tanggal[] = $mulai->toDateString();
            }

            $mulai->addDay();
        }

        return $tanggal;
    }

    protected function aturan(): array
    {
        return [
            'siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'jenis_izin' => ['required', 'in:sakit,izin,cuti,darurat'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'nomor_surat' => ['nullable', 'string', 'max:100'],
            'alasan' => ['required', 'string', 'max:1000'],
            // Hanya JPG/PNG/WEBP (SVG ditolak karena bisa memuat script).
            'lampiran' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
