<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\Siswa;
use App\Models\SuratIzin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Siswa dan orang tua hanya melihat data dirinya / anaknya.
        if ($user->isSiswa() || $user->isOrangTua()) {
            return $this->dashboardPribadi($user);
        }

        // Wali kelas hanya melihat kelas yang menjadi tanggung jawabnya.
        if ($user->isWaliKelas()) {
            return $this->dashboardWaliKelas($user);
        }

        return $this->dashboardSekolah();
    }

    /**
     * Dashboard administrator & guru piket: ringkasan seluruh sekolah.
     */
    protected function dashboardSekolah()
    {
        $hariIni = Carbon::now(config('presensi.timezone'))->toDateString();

        $totalSiswa = Siswa::count();
        $totalKelas = Kelas::count();

        // Satu query agregat untuk semua angka ringkasan.
        $agg = Presensi::where('tanggal', $hariIni)
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN jam_masuk IS NOT NULL THEN 1 ELSE 0 END) as scan,
                SUM(CASE WHEN status = 'telat' THEN 1 ELSE 0 END) as telat,
                SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN jam_pulang IS NOT NULL THEN 1 ELSE 0 END) as pulang")
            ->first();

        $hadir = (int) ($agg->scan ?? 0);          // semua yang sudah scan masuk (tepat waktu + telat)
        $telat = (int) ($agg->telat ?? 0);
        $tepat = $hadir - $telat;
        $izin = (int) ($agg->izin ?? 0);
        $sakit = (int) ($agg->sakit ?? 0);
        $sudahPulang = (int) ($agg->pulang ?? 0);
        // Siswa yang sedang izin/sakit tidak dihitung sebagai belum hadir.
        $belumHadir = max(0, $totalSiswa - $hadir - $izin - $sakit);

        // Angka untuk grafik batang kehadiran.
        $persenHadir = $totalSiswa > 0 ? (int) round($hadir / $totalSiswa * 100) : 0;
        $lebarTepat = $totalSiswa > 0 ? round($tepat / $totalSiswa * 100, 2) : 0;
        $lebarTelat = $totalSiswa > 0 ? round($telat / $totalSiswa * 100, 2) : 0;

        // Rekap per kelas: satu query GROUP BY (sebelumnya satu query per kelas).
        $hadirPerKelas = DB::table('presensi')
            ->join('siswa', 'siswa.id', '=', 'presensi.siswa_id')
            ->where('presensi.tanggal', $hariIni)
            ->whereNotNull('presensi.jam_masuk')
            ->groupBy('siswa.kelas_id')
            ->selectRaw('siswa.kelas_id as kelas_id, COUNT(*) as total')
            ->pluck('total', 'kelas_id');

        $rekapKelas = Kelas::withCount('siswa')->orderBy('nama_kelas')->get()->map(function ($kelas) use ($hadirPerKelas) {
            $hadirKelas = (int) ($hadirPerKelas[$kelas->id] ?? 0);

            return [
                'id' => $kelas->id,
                'nama_kelas' => $kelas->nama_kelas,
                'total_siswa' => $kelas->siswa_count,
                'hadir' => $hadirKelas,
                'persen' => $kelas->siswa_count > 0 ? (int) round($hadirKelas / $kelas->siswa_count * 100) : 0,
            ];
        });

        // Aktivitas scan terakhir hari ini (baris izin/sakit tidak termasuk).
        $terbaru = Presensi::with('siswa.kelas')
            ->where('tanggal', $hariIni)
            ->whereNotNull('jam_masuk')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('dashboard', compact(
            'totalSiswa',
            'totalKelas',
            'hadir',
            'tepat',
            'telat',
            'izin',
            'sakit',
            'sudahPulang',
            'belumHadir',
            'persenHadir',
            'lebarTepat',
            'lebarTelat',
            'rekapKelas',
            'terbaru'
        ));
    }

    /**
     * Dashboard wali kelas: hanya kelas yang diampu.
     */
    protected function dashboardWaliKelas(User $user)
    {
        $hariIni = Carbon::now(config('presensi.timezone'))->toDateString();
        $kelas = $user->kelas;

        if (! $kelas) {
            return view('dashboard.wali_kelas', [
                'kelas' => null,
                'totalSiswa' => 0,
                'ringkas' => [
                    'hadir' => 0, 'telat' => 0, 'izin' => 0, 'sakit' => 0,
                    'belum_hadir' => 0, 'belum_pulang' => 0,
                ],
                'persenHadir' => 0,
                'siswaHariIni' => collect(),
                'terbaru' => collect(),
                'tren' => collect(),
                'izinPending' => 0,
            ]);
        }

        $ids = Siswa::where('kelas_id', $kelas->id)->pluck('id');
        $totalSiswa = $ids->count();

        $agg = Presensi::whereIn('siswa_id', $ids)
            ->where('tanggal', $hariIni)
            ->selectRaw("SUM(CASE WHEN jam_masuk IS NOT NULL THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status = 'telat' THEN 1 ELSE 0 END) as telat,
                SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN jam_masuk IS NOT NULL AND jam_pulang IS NULL THEN 1 ELSE 0 END) as belum_pulang")
            ->first();

        $ringkas = [
            'hadir' => (int) ($agg->hadir ?? 0),
            'telat' => (int) ($agg->telat ?? 0),
            'izin' => (int) ($agg->izin ?? 0),
            'sakit' => (int) ($agg->sakit ?? 0),
            'belum_pulang' => (int) ($agg->belum_pulang ?? 0),
            'belum_hadir' => 0,
        ];

        $ringkas['belum_hadir'] = max(
            0,
            $totalSiswa - $ringkas['hadir'] - $ringkas['izin'] - $ringkas['sakit']
        );

        $persenHadir = $totalSiswa > 0 ? (int) round($ringkas['hadir'] / $totalSiswa * 100) : 0;

        // Status tiap siswa hari ini (untuk daftar cepat wali kelas).
        $siswaHariIni = Siswa::with(['presensi' => fn ($q) => $q->where('tanggal', $hariIni)])
            ->where('kelas_id', $kelas->id)
            ->orderBy('nama')
            ->get();

        $terbaru = Presensi::with('siswa')
            ->whereIn('siswa_id', $ids)
            ->where('tanggal', $hariIni)
            ->whereNotNull('jam_masuk')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        // Kehadiran 7 hari terakhir (termasuk hari ini).
        $tanggalTren = collect(range(6, 0))
            ->map(fn ($mundur) => Carbon::now(config('presensi.timezone'))->subDays($mundur)->toDateString());

        $hitungTren = Presensi::whereIn('siswa_id', $ids)
            ->whereBetween('tanggal', [$tanggalTren->first(), $tanggalTren->last()])
            ->whereNotNull('jam_masuk')
            ->groupBy('tanggal')
            ->selectRaw('tanggal, COUNT(*) as jumlah')
            ->pluck('jumlah', 'tanggal');

        $tren = $tanggalTren->map(fn ($tanggal) => [
            'tanggal' => $tanggal,
            'label' => Carbon::parse($tanggal)->translatedFormat('D'),
            'jumlah' => (int) ($hitungTren[$tanggal] ?? 0),
            'persen' => $totalSiswa > 0 ? (int) round(((int) ($hitungTren[$tanggal] ?? 0)) / $totalSiswa * 100) : 0,
        ]);

        $izinPending = SuratIzin::whereIn('siswa_id', $ids)
            ->where('status', SuratIzin::STATUS_PENDING)
            ->count();

        return view('dashboard.wali_kelas', compact(
            'kelas',
            'totalSiswa',
            'ringkas',
            'persenHadir',
            'siswaHariIni',
            'terbaru',
            'tren',
            'izinPending'
        ));
    }

    /**
     * Dashboard siswa / orang tua: ringkasan kehadiran pribadi.
     */
    protected function dashboardPribadi(User $user)
    {
        $zona = config('presensi.timezone');
        $sekarang = Carbon::now($zona);
        $hariIni = $sekarang->toDateString();
        $bulan = $sekarang->format('Y-m');
        [$dari, $sampai] = $this->rentangBulan($bulan);

        $daftarSiswa = $user->querySiswa()->with('kelas')->orderBy('nama')->get();

        if ($daftarSiswa->isEmpty()) {
            return view('dashboard.pribadi', [
                'daftar' => collect(),
                'terbaru' => collect(),
                'hariEfektif' => 0,
                'bulan' => $bulan,
                'namaBulan' => $sekarang->translatedFormat('F Y'),
                'izinPending' => 0,
            ]);
        }

        $ids = $daftarSiswa->pluck('id')->all();

        $hariIniPerSiswa = Presensi::whereIn('siswa_id', $ids)
            ->where('tanggal', $hariIni)
            ->get()
            ->keyBy('siswa_id');

        $agg = Presensi::whereIn('siswa_id', $ids)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->groupBy('siswa_id')
            ->selectRaw("siswa_id,
                SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status = 'telat' THEN 1 ELSE 0 END) as telat,
                SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit")
            ->get()
            ->keyBy('siswa_id');

        $hariEfektif = $this->hariEfektif($bulan, $dari, $sampai);

        $daftar = $daftarSiswa->map(function ($siswa) use ($hariIniPerSiswa, $agg, $hariEfektif) {
            $a = $agg->get($siswa->id);
            $hadir = (int) ($a->hadir ?? 0);
            $telat = (int) ($a->telat ?? 0);
            $izin = (int) ($a->izin ?? 0);
            $sakit = (int) ($a->sakit ?? 0);
            $alpa = max(0, $hariEfektif - $hadir - $telat - $izin - $sakit);

            return [
                'siswa' => $siswa,
                'hari_ini' => $hariIniPerSiswa->get($siswa->id),
                'hadir' => $hadir,
                'telat' => $telat,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'persen' => $hariEfektif > 0 ? round(($hadir + $telat) / $hariEfektif * 100, 1) : 0,
            ];
        });

        $terbaru = Presensi::with('siswa.kelas')
            ->whereIn('siswa_id', $ids)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $izinPending = SuratIzin::whereIn('siswa_id', $ids)
            ->where('status', SuratIzin::STATUS_PENDING)
            ->count();

        return view('dashboard.pribadi', [
            'daftar' => $daftar,
            'terbaru' => $terbaru,
            'hariEfektif' => $hariEfektif,
            'bulan' => $bulan,
            'namaBulan' => $sekarang->translatedFormat('F Y'),
            'izinPending' => $izinPending,
        ]);
    }

    /**
     * Jumlah hari efektif bulan berjalan, sama seperti di halaman rekap.
     */
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
}
