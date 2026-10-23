<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Presensi;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresensiController extends Controller
{
    // Validasi & normalisasi filter (tanggal salah format tidak lagi membuat error 500).
    protected function filter(Request $request): array
    {
        $v = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', 'in:hadir,telat,izin,sakit,alpa,belum_pulang,belum_hadir'],
        ]);

        $kelasId = $v['kelas_id'] ?? null;

        // Wali kelas terkunci pada kelas yang diampu. Kalau akunnya belum
        // ditautkan ke kelas, pakai 0 supaya tidak ada data yang tampil.
        if ($request->user()?->isWaliKelas()) {
            $kelasId = $request->user()->kelas_id ? (int) $request->user()->kelas_id : 0;
        }

        return [
            'tanggal' => $v['tanggal'] ?? Carbon::now(config('presensi.timezone'))->toDateString(),
            'kelas_id' => $kelasId,
            'status' => $v['status'] ?? null,
        ];
    }

    protected function presensiDasar(array $f)
    {
        $q = Presensi::where('tanggal', $f['tanggal']);

        if ($f['kelas_id'] !== null) {
            $q->whereHas('siswa', fn ($s) => $s->where('kelas_id', $f['kelas_id']));
        }

        return $q;
    }

    protected function presensiQuery(array $f)
    {
        $q = $this->presensiDasar($f)->with('siswa.kelas');

        if (in_array($f['status'], Presensi::statusManual(), true) || $f['status'] === 'hadir' || $f['status'] === 'telat') {
            $q->where('status', $f['status']);
        } elseif ($f['status'] === 'belum_pulang') {
            // Baris manual (izin/sakit/alpa) tidak punya jam masuk, jadi bukan "belum pulang".
            $q->whereNotNull('jam_masuk')->whereNull('jam_pulang');
        }

        return $q->orderByRaw("CASE WHEN jam_masuk IS NULL THEN 1 ELSE 0 END")
            ->orderBy('jam_masuk')->orderBy('id');
    }

    protected function belumHadirQuery(array $f)
    {
        $q = Siswa::with('kelas')
            ->whereDoesntHave('presensi', fn ($p) => $p->where('tanggal', $f['tanggal']));

        if ($f['kelas_id'] !== null) {
            $q->where('kelas_id', $f['kelas_id']);
        }

        return $q->orderBy('nama');
    }

    public function index(Request $request)
    {
        $f = $this->filter($request);
        $tanggal = $f['tanggal'];
        $modeBelumHadir = $f['status'] === 'belum_hadir';

        $data = $modeBelumHadir
            ? $this->belumHadirQuery($f)->paginate(20)->withQueryString()
            : $this->presensiQuery($f)->paginate(20)->withQueryString();

        // Ringkasan angka (mengikuti filter tanggal + kelas).
        $agg = $this->presensiDasar($f)
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status = 'telat' THEN 1 ELSE 0 END) as telat,
                SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN jam_masuk IS NOT NULL AND jam_pulang IS NULL THEN 1 ELSE 0 END) as belum_pulang")
            ->first();

        $totalSiswa = Siswa::when($f['kelas_id'] !== null, fn ($q) => $q->where('kelas_id', $f['kelas_id']))->count();

        $hadir = (int) ($agg->hadir ?? 0);
        $telat = (int) ($agg->telat ?? 0);
        $izin = (int) ($agg->izin ?? 0);
        $sakit = (int) ($agg->sakit ?? 0);

        $ringkas = [
            'total_siswa' => $totalSiswa,
            'hadir' => $hadir,
            'telat' => $telat,
            'izin' => $izin,
            'sakit' => $sakit,
            'belum_pulang' => (int) ($agg->belum_pulang ?? 0),
            // Siswa yang sudah izin/sakit tidak dihitung sebagai belum hadir.
            'belum_hadir' => max(0, $totalSiswa - $hadir - $telat - $izin - $sakit),
        ];

        $user = $request->user();

        // Wali kelas hanya melihat kelasnya; kelas lain tidak ditampilkan.
        $kelas = $user->isWaliKelas()
            ? Kelas::whereKey($user->kelas_id ?? 0)->get()
            : Kelas::orderBy('nama_kelas')->get();

        $statusAktif = $f['status'];
        $kelasTerkunci = $user->isWaliKelas();
        // Hanya petugas piket dan administrator yang boleh menandai status.
        $bolehUbah = $user->isAdmin() || $user->isGuruPiket();
        $bolehScan = $bolehUbah;

        return view('presensi.index', compact(
            'data',
            'kelas',
            'tanggal',
            'ringkas',
            'statusAktif',
            'modeBelumHadir',
            'kelasTerkunci',
            'bolehUbah',
            'bolehScan'
        ));
    }

    /**
     * Menandai status kehadiran manual (izin / sakit / alpa) untuk satu siswa
     * pada satu tanggal. Dipakai petugas saat siswa tidak scan.
     */
    public function setStatus(Request $request)
    {
        $data = $request->validate([
            'siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'in:izin,sakit,alpa'],
            'catatan' => ['nullable', 'string', 'max:200'],
        ]);

        $siswa = Siswa::findOrFail($data['siswa_id']);

        $ada = Presensi::where('siswa_id', $siswa->id)
            ->where('tanggal', $data['tanggal'])
            ->first();

        // Data scan tidak ditimpa: kehadiran yang sudah tercatat itu asli.
        if ($ada && $ada->jam_masuk !== null) {
            return back()->with('error', "{$siswa->nama} sudah melakukan scan pada tanggal itu, jadi statusnya tidak bisa diubah manual.");
        }

        $catatan = $data['catatan'] ?? null;

        if ($ada) {
            $ada->update([
                'status' => $data['status'],
                'catatan' => $catatan ?? $ada->catatan,
                // Status manual berdiri sendiri, tidak lagi terkait surat izin.
                'surat_izin_id' => null,
            ]);
        } else {
            Presensi::create([
                'siswa_id' => $siswa->id,
                'tanggal' => $data['tanggal'],
                'jam_masuk' => null,
                'jam_pulang' => null,
                'status' => $data['status'],
                'catatan' => $catatan,
            ]);
        }

        $request->user()?->catatAktivitas(
            'presensi_ubah',
            "Status kehadiran diubah: {$siswa->nama} ({$siswa->nis}) - " . Presensi::labelStatus($data['status']) . " pada {$data['tanggal']}",
            $request->ip()
        );

        return back()->with('success', "Status {$siswa->nama} pada {$data['tanggal']} ditandai " . strtolower(Presensi::labelStatus($data['status'])) . '.');
    }

    public function export(Request $request): StreamedResponse
    {
        $f = $this->filter($request);

        // Excel di Windows berbahasa Indonesia memakai titik koma (;) sebagai pemisah kolom.
        $pemisah = $request->input('pemisah') === 'titik_koma' ? ';' : ',';

        $nama = 'presensi_' . $f['tanggal'] . ($f['status'] ? '_' . $f['status'] : '') . '.csv';

        $baris = $f['status'] === 'belum_hadir'
            ? $this->belumHadirQuery($f)->orderBy('id')->lazy(500)
            : $this->presensiQuery($f)->lazy(500);

        $belumHadir = $f['status'] === 'belum_hadir';

        return response()->streamDownload(function () use ($baris, $belumHadir, $pemisah, $f) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 supaya Excel membaca huruf (é, ñ, dll.) dengan benar.
            fwrite($out, "\xEF\xBB\xBF");

            $tulis = fn (array $row) => fputcsv($out, $row, $pemisah, '"', '\\');

            $tulis(['NIS', 'Nama', 'Kelas', 'Jam Masuk', 'Jam Pulang', 'Status', 'Keterangan', 'Tanggal']);

            foreach ($baris as $r) {
                if ($belumHadir) {
                    $tulis([
                        $this->aman($r->nis),
                        $this->aman($r->nama),
                        $this->aman($r->kelas->nama_kelas ?? ''),
                        '', '', 'Belum presensi', '', $f['tanggal'],
                    ]);
                    continue;
                }

                $tulis([
                    $this->aman($r->siswa->nis ?? ''),
                    $this->aman($r->siswa->nama ?? ''),
                    $this->aman($r->siswa->kelas->nama_kelas ?? ''),
                    $r->jam_masuk_singkat ?? '',
                    $r->jam_pulang_singkat ?? '',
                    Presensi::labelStatus($r->status),
                    $this->aman($r->catatan ?? ''),
                    $f['tanggal'],
                ]);
            }

            fclose($out);
        }, $nama, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // Cegah CSV/formula injection: sel yang diawali = + - @ dianggap rumus oleh Excel.
    protected function aman(?string $nilai): string
    {
        $nilai = (string) $nilai;

        return preg_match('/^[=+\-@\t\r]/', $nilai) ? "'" . $nilai : $nilai;
    }
}
