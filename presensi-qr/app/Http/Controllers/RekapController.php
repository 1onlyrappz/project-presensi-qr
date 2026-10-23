<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap bulanan per kelas: jumlah hadir, telat, izin, sakit, dan alpa
 * untuk setiap siswa dalam satu bulan. Angka ini yang dipakai untuk absensi
 * pada rapor.
 *
 * Sumber data:
 * - hadir & telat : hasil scan QR (tabel presensi).
 * - izin & sakit  : status manual, dibuat dari surat izin yang disetujui
 *                   atau ditandai petugas di halaman laporan harian.
 * - alpa          : hari efektif dikurangi seluruh hadir/telat/izin/sakit.
 */
class RekapController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);

        $bulan = $filter['bulan'] ?? Carbon::now(config('presensi.timezone'))->format('Y-m');
        $kelasId = $this->kelasUntukUser($request, $filter['kelas_id'] ?? null);

        [$dari, $sampai] = $this->rentangBulan($bulan);

        $baris = $this->dataSiswa($dari, $sampai, $kelasId);
        $hariEfektifOtomatis = $this->hariEfektifOtomatis($dari, $sampai);
        $hariEfektifManual = $this->hariEfektifManual($bulan);
        $hariEfektif = $hariEfektifManual ?? $hariEfektifOtomatis;

        $baris = $this->hitungAlpa($baris, $hariEfektif);

        $user = $request->user();
        $kelas = $user->isWaliKelas()
            ? Kelas::whereKey($user->kelas_id ?? 0)->get()
            : Kelas::orderBy('nama_kelas')->get();
        $rekapKelas = $kelasId ? collect() : $this->rekapPerKelas($baris);
        $ringkas = $this->ringkasan($baris, $hariEfektif);
        $namaBulan = $this->namaBulan($bulan);
        $kelasTerkunci = $user->isWaliKelas();

        return view('rekap.index', compact(
            'baris',
            'kelas',
            'bulan',
            'kelasId',
            'hariEfektif',
            'hariEfektifOtomatis',
            'hariEfektifManual',
            'rekapKelas',
            'ringkas',
            'namaBulan',
            'kelasTerkunci'
        ));
    }

    /**
     * Wali kelas selalu dibatasi ke kelasnya sendiri.
     */
    protected function kelasUntukUser(Request $request, ?int $kelasId): ?int
    {
        $user = $request->user();

        if ($user?->isWaliKelas()) {
            // 0 = akun belum ditautkan ke kelas, jadi tidak ada data tampil.
            return $user->kelas_id ? (int) $user->kelas_id : 0;
        }

        return $kelasId !== null ? (int) $kelasId : null;
    }

    /**
     * Unduh rekap sebagai CSV (siap dipakai di rapor).
     */
    public function export(Request $request): StreamedResponse
    {
        $filter = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);

        $bulan = $filter['bulan'] ?? Carbon::now(config('presensi.timezone'))->format('Y-m');
        $kelasId = $this->kelasUntukUser($request, $filter['kelas_id'] ?? null);

        [$dari, $sampai] = $this->rentangBulan($bulan);

        $baris = $this->hitungAlpa(
            $this->dataSiswa($dari, $sampai, $kelasId),
            $this->hariEfektifManual($bulan) ?? $this->hariEfektifOtomatis($dari, $sampai)
        );

        // Excel di Windows berbahasa Indonesia memakai titik koma (;) sebagai pemisah kolom.
        $pemisah = $request->input('pemisah') === 'titik_koma' ? ';' : ',';
        $nama = 'rekap_bulanan_' . $bulan . (($kelasId !== null && $kelasId !== 0) ? '_kelas_' . $kelasId : '') . '.csv';

        return response()->streamDownload(function () use ($baris, $pemisah, $bulan, $kelasId) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 supaya Excel membaca huruf (é, ñ, dll.) dengan benar.
            fwrite($out, "\xEF\xBB\xBF");

            $tulis = fn (array $row) => fputcsv($out, $row, $pemisah, '"', '\\');

            $tulis([
                'No', 'NIS', 'Nama', 'Kelas', 'Hadir', 'Telat', 'Izin', 'Sakit', 'Alpa',
                'Hari Efektif', 'Persentase Kehadiran',
            ]);

            $no = 0;

            foreach ($baris as $b) {
                $no++;

                $tulis([
                    $no,
                    $this->aman($b->nis),
                    $this->aman($b->nama),
                    $this->aman($b->nama_kelas),
                    (int) $b->hadir,
                    (int) $b->telat,
                    (int) $b->izin,
                    (int) $b->sakit,
                    (int) $b->alpa,
                    (int) $b->hari_efektif,
                    $b->persen . '%',
                ]);
            }

            fclose($out);
        }, $nama, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Menyesuaikan jumlah hari efektif bulan ini (khusus administrator).
     *
     * Nilai kosong menghapus penyesuaian dan mengembalikan hitungan otomatis.
     */
    public function aturHariEfektif(Request $request)
    {
        $data = $request->validate([
            'bulan' => ['required', 'date_format:Y-m'],
            'jumlah_hari' => ['nullable', 'integer', 'min:0', 'max:31'],
        ]);

        $tahun = (int) substr($data['bulan'], 0, 4);
        $bulan = (int) substr($data['bulan'], 5, 2);

        if ($data['jumlah_hari'] === null || $data['jumlah_hari'] === '') {
            DB::table('bulan_efektif')->where('tahun', $tahun)->where('bulan', $bulan)->delete();

            return back()->with('success', 'Jumlah hari efektif dikembalikan ke hitungan otomatis.');
        }

        DB::table('bulan_efektif')->updateOrInsert(
            ['tahun' => $tahun, 'bulan' => $bulan],
            [
                'jumlah_hari' => (int) $data['jumlah_hari'],
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return back()->with('success', 'Jumlah hari efektif bulan ini disimpan.');
    }

    /**
     * Data kehadiran tiap siswa dalam satu bulan.
     */
    protected function dataSiswa(string $dari, string $sampai, ?int $kelasId)
    {
        return DB::table('siswa as s')
            ->join('kelas as k', 'k.id', '=', 's.kelas_id')
            ->leftJoin('presensi as p', function ($j) use ($dari, $sampai) {
                $j->on('p.siswa_id', '=', 's.id')
                    ->whereBetween('p.tanggal', [$dari, $sampai]);
            })
            ->when($kelasId !== null, fn ($q) => $q->where('s.kelas_id', $kelasId))
            ->groupBy('s.id', 's.nis', 's.nama', 's.kelas_id', 'k.nama_kelas')
            ->orderBy('k.nama_kelas')
            ->orderBy('s.nama')
            ->selectRaw('s.id as id, s.nis as nis, s.nama as nama, s.kelas_id as kelas_id, k.nama_kelas as nama_kelas,
                SUM(CASE WHEN p.status = \'hadir\' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN p.status = \'telat\' THEN 1 ELSE 0 END) as telat,
                SUM(CASE WHEN p.status = \'izin\' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN p.status = \'sakit\' THEN 1 ELSE 0 END) as sakit')
            ->get();
    }

    /**
     * Menghitung alpa dan persentase kehadiran tiap siswa.
     */
    protected function hitungAlpa($baris, int $hariEfektif)
    {
        foreach ($baris as $b) {
            $hadir = (int) $b->hadir;
            $telat = (int) $b->telat;
            $izin = (int) $b->izin;
            $sakit = (int) $b->sakit;

            $b->hadir = $hadir;
            $b->telat = $telat;
            $b->izin = $izin;
            $b->sakit = $sakit;
            $b->alpa = max(0, $hariEfektif - $hadir - $telat - $izin - $sakit);
            $b->hari_efektif = $hariEfektif;
            $b->persen = $hariEfektif > 0
                ? round(($hadir + $telat) / $hariEfektif * 100, 1)
                : 0;
        }

        return $baris;
    }

    /**
     * Ringkasan seluruh kelas (dipakai saat semua kelas ditampilkan).
     */
    protected function rekapPerKelas($baris)
    {
        return $baris->groupBy('kelas_id')->map(function ($anggota, $kelasId) {
            $hadir = $anggota->sum(fn ($b) => (int) $b->hadir);
            $telat = $anggota->sum(fn ($b) => (int) $b->telat);
            $izin = $anggota->sum(fn ($b) => (int) $b->izin);
            $sakit = $anggota->sum(fn ($b) => (int) $b->sakit);
            $alpa = $anggota->sum(fn ($b) => (int) $b->alpa);
            $total = $hadir + $telat;

            return [
                'kelas_id' => $kelasId,
                'nama_kelas' => $anggota->first()->nama_kelas,
                'siswa' => $anggota->count(),
                'hadir' => $hadir,
                'telat' => $telat,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'persen' => $total > 0 && $anggota->first()->hari_efektif > 0
                    ? round($total / ($anggota->count() * $anggota->first()->hari_efektif) * 100, 1)
                    : 0,
            ];
        })->values();
    }

    /**
     * Angka ringkasan untuk kartu statistik.
     */
    protected function ringkasan($baris, int $hariEfektif): array
    {
        $hadir = $baris->sum(fn ($b) => (int) $b->hadir);
        $telat = $baris->sum(fn ($b) => (int) $b->telat);
        $izin = $baris->sum(fn ($b) => (int) $b->izin);
        $sakit = $baris->sum(fn ($b) => (int) $b->sakit);
        $alpa = $baris->sum(fn ($b) => (int) $b->alpa);
        $siswa = $baris->count();

        return [
            'siswa' => $siswa,
            'hadir' => $hadir,
            'telat' => $telat,
            'izin' => $izin,
            'sakit' => $sakit,
            'alpa' => $alpa,
            'hari_efektif' => $hariEfektif,
            'persen' => $siswa > 0 && $hariEfektif > 0
                ? round((($hadir + $telat) / ($siswa * $hariEfektif)) * 100, 1)
                : 0,
        ];
    }

    /**
     * Jumlah hari yang tercatat ada aktivitas presensi di bulan itu.
     *
     * Ini dipakai sebagai jumlah hari efektif kalau Administrator belum
     * menyetel sendiri. Tanggal tanpa aktivitas (misal hari libur nasional)
     * otomatis tidak dihitung.
     */
    protected function hariEfektifOtomatis(string $dari, string $sampai): int
    {
        return (int) DB::table('presensi')
            ->whereBetween('tanggal', [$dari, $sampai])
            ->distinct()
            ->count('tanggal');
    }

    /**
     * Jumlah hari efektif yang disetel manual oleh Administrator.
     */
    protected function hariEfektifManual(string $bulan): ?int
    {
        $nilai = DB::table('bulan_efektif')
            ->where('tahun', (int) substr($bulan, 0, 4))
            ->where('bulan', (int) substr($bulan, 5, 2))
            ->value('jumlah_hari');

        return $nilai === null ? null : (int) $nilai;
    }

    /**
     * Rentang tanggal (awal & akhir bulan) dalam format Y-m-d.
     */
    protected function rentangBulan(string $bulan): array
    {
        $awal = Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfMonth();

        return [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()];
    }

    protected function namaBulan(string $bulan): string
    {
        $awal = Carbon::createFromFormat('Y-m-d', $bulan . '-01');

        return $awal->translatedFormat('F Y');
    }

    // Cegah CSV/formula injection: sel yang diawali = + - @ dianggap rumus oleh Excel.
    protected function aman(?string $nilai): string
    {
        $nilai = (string) $nilai;

        return preg_match('/^[=+\-@\t\r]/', $nilai) ? "'" . $nilai : $nilai;
    }
}
