<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Support\ImporSiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor data siswa dari berkas Excel (CSV).
 *
 * Dipakai untuk memasukkan ratusan siswa sekaligus, supaya tidak harus
 * menambah satu per satu. Setelah impor, kode QR tiap siswa dibuat otomatis.
 */
class SiswaImportController extends Controller
{
    public function index()
    {
        $totalSiswa = Siswa::count();

        return view('siswa.import', compact('totalSiswa'));
    }

    /**
     * Unduh template CSV yang tinggal diisi di Excel.
     */
    public function template(): StreamedResponse
    {
        $baris = [
            ['NIS', 'Nama', 'Kelas', 'Jenis Kelamin'],
            ['2026001', 'Ahmad Fauzi', 'X TJKT 2', 'L'],
            ['2026002', 'Siti Aminah', 'X TJKT 2', 'P'],
        ];

        return response()->streamDownload(function () use ($baris) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 + pemisah titik koma: langsung rapi saat dibuka di Excel.
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($baris as $row) {
                fputcsv($out, $row, ';', '"', '\\');
            }

            fclose($out);
        }, 'template_import_siswa.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Proses berkas yang diunggah.
     */
    public function proses(Request $request)
    {
        $request->validate([
            'berkas' => ['required', 'file', 'max:4096'],
        ]);

        $berkas = $request->file('berkas');
        $ekstensi = strtolower($berkas->getClientOriginalExtension());

        if (! in_array($ekstensi, ['csv', 'txt'], true)) {
            return back()
                ->with('error', 'Berkas harus berformat CSV (bisa dibuat dari Excel: Save As > CSV).')
                ->withInput();
        }

        $konten = file_get_contents($berkas->getRealPath());

        if (trim($konten) === '') {
            return back()->with('error', 'Berkas kosong. Isi data siswa dulu, lalu unggah kembali.')->withInput();
        }

        $hasil = $this->impor($konten, [
            'buat_kelas' => $request->boolean('buat_kelas'),
            'perbarui' => $request->boolean('perbarui'),
        ]);

        if ($hasil['berhasil'] > 0 || $hasil['diperbarui'] > 0) {
            $request->user()?->catatAktivitas(
                'siswa_import',
                "Impor siswa: {$hasil['berhasil']} baru, {$hasil['diperbarui']} diperbarui, {$hasil['gagal']} gagal",
                $request->ip()
            );
        }

        return back()->with('import_hasil', $hasil);
    }

    /**
     * Simpan isi berkas ke database.
     *
     * @return array<string,mixed>
     */
    protected function impor(string $konten, array $opsi): array
    {
        $berkas = ImporSiswa::baca($konten);

        $hasil = [
            'total' => count($berkas['baris']),
            'berhasil' => 0,
            'diperbarui' => 0,
            'dilewati' => 0,
            'gagal' => 0,
            'kelas_baru' => [],
            'errors' => [],
            'pemisah' => $berkas['pemisah'],
            'ada_header' => $berkas['ada_header'],
        ];

        if ($hasil['total'] === 0) {
            return $hasil;
        }

        // Cache nama kelas (huruf kecil) supaya kelas yang sudah ada tidak dibuat dua kali.
        $kelasCache = [];

        foreach (Kelas::get() as $k) {
            $kelasCache[ImporSiswa::normalisasi($k->nama_kelas)] = $k->id;
        }

        $nisDiBerkas = [];

        DB::transaction(function () use ($berkas, $opsi, &$hasil, &$kelasCache, &$nisDiBerkas) {
            foreach ($berkas['baris'] as $baris) {
                $no = $baris['no'];
                $data = $baris['data'];

                $nis = ImporSiswa::nilai($data, $berkas['kolom'], 'nis');
                $nama = ImporSiswa::nilai($data, $berkas['kolom'], 'nama');
                $kelas = ImporSiswa::nilai($data, $berkas['kolom'], 'kelas');
                $jk = ImporSiswa::normalisasiJk(ImporSiswa::nilai($data, $berkas['kolom'], 'jenis_kelamin'));

                $galat = [];

                if ($nis === null) {
                    $galat[] = 'NIS kosong';
                } elseif (mb_strlen($nis) > 30) {
                    $galat[] = 'NIS lebih dari 30 karakter';
                } elseif (preg_match('/^\d+(\.\d+)?e\+?\d+$/i', $nis)) {
                    $galat[] = 'NIS tampak diubah Excel menjadi notasi ilmiah (simpan kolom NIS sebagai Teks)';
                }

                if ($nama === null) {
                    $galat[] = 'Nama kosong';
                } elseif (mb_strlen($nama) > 100) {
                    $galat[] = 'Nama lebih dari 100 karakter';
                }

                if ($kelas === null) {
                    $galat[] = 'Kelas kosong';
                } elseif (mb_strlen($kelas) > 50) {
                    $galat[] = 'Nama kelas lebih dari 50 karakter';
                }

                if ($nis !== null && isset($nisDiBerkas[$nis])) {
                    $galat[] = 'NIS yang sama sudah ada di baris ' . $nisDiBerkas[$nis] . ' pada berkas ini';
                }

                if ($galat !== []) {
                    $hasil['gagal']++;
                    $hasil['errors'][] = [
                        'baris' => $no,
                        'nis' => $nis ?? '-',
                        'nama' => $nama ?? '-',
                        'pesan' => implode('. ', $galat) . '.',
                    ];

                    continue;
                }

                $nisDiBerkas[$nis] = $no;

                // Kelas: pakai yang sudah ada, atau buat baru bila diizinkan.
                $kunciKelas = ImporSiswa::normalisasi($kelas);
                $kelasId = $kelasCache[$kunciKelas] ?? null;

                if ($kelasId === null) {
                    if (! $opsi['buat_kelas']) {
                        $hasil['gagal']++;
                        $hasil['errors'][] = [
                            'baris' => $no,
                            'nis' => $nis,
                            'nama' => $nama,
                            'pesan' => "Kelas \"{$kelas}\" belum ada. Buat kelasnya dulu atau centang \"Buat kelas baru otomatis\".",
                        ];

                        continue;
                    }

                    $kelasBaru = Kelas::create(['nama_kelas' => $kelas]);
                    $kelasCache[$kunciKelas] = $kelasBaru->id;
                    $kelasId = $kelasBaru->id;
                    $hasil['kelas_baru'][] = $kelas;
                }

                $siswa = Siswa::where('nis', $nis)->first();

                if ($siswa) {
                    if (! $opsi['perbarui']) {
                        $hasil['dilewati']++;
                        $hasil['errors'][] = [
                            'baris' => $no,
                            'nis' => $nis,
                            'nama' => $nama,
                            'pesan' => 'NIS sudah terdaftar, dilewati.',
                        ];

                        continue;
                    }

                    $siswa->update([
                        'nama' => $nama,
                        'kelas_id' => $kelasId,
                        'jenis_kelamin' => $jk ?? $siswa->jenis_kelamin,
                    ]);

                    $hasil['diperbarui']++;

                    continue;
                }

                Siswa::create([
                    'nis' => $nis,
                    'nama' => $nama,
                    'kelas_id' => $kelasId,
                    'jenis_kelamin' => $jk ?? 'L',
                ]);

                $hasil['berhasil']++;
            }
        });

        return $hasil;
    }
}
