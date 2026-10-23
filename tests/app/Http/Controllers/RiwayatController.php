<?php

namespace App\Http\Controllers;

use App\Models\Presensi;
use App\Models\SuratIzin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat kehadiran pribadi untuk akun siswa dan orang tua.
 *
 * Hanya menampilkan data siswa yang terhubung dengan akun tersebut.
 */
class RiwayatController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $filter = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'siswa_id' => ['nullable', 'integer'],
        ]);

        $bulan = $filter['bulan'] ?? Carbon::now(config('presensi.timezone'))->format('Y-m');
        [$dari, $sampai] = $this->rentangBulan($bulan);

        $daftarSiswa = $user->querySiswa()->with('kelas')->orderBy('nama')->get();

        if ($daftarSiswa->isEmpty()) {
            return view('riwayat.index', [
                'daftarSiswa' => $daftarSiswa,
                'siswa' => null,
                'bulan' => $bulan,
                'namaBulan' => $this->namaBulan($bulan),
                'baris' => collect(),
                'ringkas' => $this->ringkasanKosong(),
                'hariEfektif' => 0,
                'surat' => collect(),
            ]);
        }

        // Orang tua dengan lebih dari satu anak bisa memilih; ID di luar
        // daftar anaknya otomatis diabaikan.
        $siswa = $daftarSiswa->firstWhere('id', (int) $request->input('siswa_id'))
            ?? $daftarSiswa->first();

        $baris = Presensi::with('suratIzin')
            ->where('siswa_id', $siswa->id)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->orderBy('tanggal')
            ->get();

        $hariEfektif = $this->hariEfektif($bulan, $dari, $sampai);
        $ringkas = $this->ringkas($baris, $hariEfektif);

        $surat = SuratIzin::where('siswa_id', $siswa->id)
            ->whereBetween('tanggal_mulai', [$dari, $sampai])
            ->latest()
            ->get();

        return view('riwayat.index', [
            'daftarSiswa' => $daftarSiswa,
            'siswa' => $siswa,
            'bulan' => $bulan,
            'namaBulan' => $this->namaBulan($bulan),
            'baris' => $baris,
            'ringkas' => $ringkas,
            'hariEfektif' => $hariEfektif,
            'surat' => $surat,
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    protected function ringkas($baris, int $hariEfektif): array
    {
        $hadir = $baris->where('status', Presensi::STATUS_HADIR)->count();
        $telat = $baris->where('status', Presensi::STATUS_TELAT)->count();
        $izin = $baris->where('status', Presensi::STATUS_IZIN)->count();
        $sakit = $baris->where('status', Presensi::STATUS_SAKIT)->count();
        $alpa = max(0, $hariEfektif - $hadir - $telat - $izin - $sakit);

        return [
            'hadir' => $hadir,
            'telat' => $telat,
            'izin' => $izin,
            'sakit' => $sakit,
            'alpa' => $alpa,
            'persen' => $hariEfektif > 0 ? round(($hadir + $telat) / $hariEfektif * 100, 1) : 0,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    protected function ringkasanKosong(): array
    {
        return ['hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0, 'persen' => 0];
    }

    protected function hariEfektif(string $bulan, string $dari, string $sampai): int
    {
        $manual = DB::table('bulan_efektif')
            ->where('tahun', (int) substr($bulan, 0, 4))
            ->where('bulan', (int) substr($bulan, 5, 2))
            ->value('jumlah_hari');

        if ($manual !== null) {
            return (int) $manual;
        }

        return (int) DB::table('presensi')
            ->whereBetween('tanggal', [$dari, $sampai])
            ->distinct()
            ->count('tanggal');
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function rentangBulan(string $bulan): array
    {
        $awal = Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfMonth();

        return [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()];
    }

    protected function namaBulan(string $bulan): string
    {
        return Carbon::createFromFormat('Y-m-d', $bulan . '-01')->translatedFormat('F Y');
    }
}
