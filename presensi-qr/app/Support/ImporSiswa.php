<?php

namespace App\Support;

/**
 * Pembacaan berkas CSV (dari Excel) untuk impor data siswa.
 *
 * Berkas bisa memakai pemisah koma (,), titik koma (;), atau tab.
 * Pemisah otomatis dikenali dari baris pertama.
 *
 * Nama kolom dibaca dengan bebas: "NIS", "No. Induk", "Nama Lengkap",
 * "Kelas", "JK", "Jenis Kelamin", dan sejenisnya.
 */
class ImporSiswa
{
    /**
     * Baca isi berkas CSV.
     *
     * @return array{
     *     pemisah: string,
     *     ada_header: bool,
     *     kolom: array<string,int>,
     *     baris: array<int,array{no:int,data:array<int,string>}>,
     *     jumlah_kolom: int
     * }
     */
    public static function baca(string $konten): array
    {
        // Buang BOM UTF-8 (dibuat Excel) supaya judul kolom tidak kacau.
        $konten = preg_replace('/^\xEF\xBB\xBF/', '', $konten);

        $pemisah = self::deteksiPemisah($konten);
        $baris = self::bacaBaris($konten, $pemisah);

        if ($baris === []) {
            return [
                'pemisah' => $pemisah,
                'ada_header' => false,
                'kolom' => self::kolomDefault(),
                'baris' => [],
                'jumlah_kolom' => 0,
            ];
        }

        [$adaHeader, $kolom] = self::petakanKolom($baris[0]['data']);

        $data = $adaHeader ? array_slice($baris, 1) : $baris;

        return [
            'pemisah' => $pemisah,
            'ada_header' => $adaHeader,
            'kolom' => $kolom,
            'baris' => array_values($data),
            'jumlah_kolom' => max(array_map('count', $baris) ?: [0]),
        ];
    }

    /**
     * Ambil nilai satu kolom dari satu baris.
     */
    public static function nilai(array $baris, array $kolom, string $nama): ?string
    {
        if (! isset($kolom[$nama])) {
            return null;
        }

        $index = $kolom[$nama];

        if (! array_key_exists($index, $baris)) {
            return null;
        }

        $nilai = trim((string) $baris[$index]);

        return $nilai === '' ? null : $nilai;
    }

    /**
     * Terjemahkan beberapa penulisan jenis kelamin menjadi L atau P.
     */
    public static function normalisasiJk(?string $jk): ?string
    {
        if ($jk === null) {
            return null;
        }

        return match (self::normalisasi($jk)) {
            'l', 'lk', 'lakilaki', 'pria', 'cowok' => 'L',
            'p', 'pr', 'perempuan', 'wanita', 'cewek' => 'P',
            default => null,
        };
    }

    /**
     * Samakan nama kelas agar "x tjkt 2" dan "X TJKT 2" dianggap sama.
     */
    public static function normalisasi(?string $teks): string
    {
        $teks = mb_strtolower(trim((string) $teks));

        return preg_replace('/[^a-z0-9]+/', '', $teks);
    }

    /**
     * Kenali pemisah kolom dari baris pertama yang tidak kosong.
     */
    protected static function deteksiPemisah(string $konten): string
    {
        $baris = '';

        foreach (preg_split('/\r\n|\r|\n/', $konten) as $baris) {
            if (trim($baris) !== '') {
                break;
            }
        }

        $kandidat = [
            ',' => substr_count((string) $baris, ','),
            ';' => substr_count((string) $baris, ';'),
            "\t" => substr_count((string) $baris, "\t"),
        ];

        arsort($kandidat);
        $terbaik = array_key_first($kandidat);

        return $kandidat[$terbaik] > 0 ? (string) $terbaik : ',';
    }

    /**
     * Baca semua baris CSV (baris kosong dilewati).
     */
    protected static function bacaBaris(string $konten, string $pemisah): array
    {
        $baris = [];
        $handle = fopen('php://memory', 'r+');

        fwrite($handle, $konten);
        rewind($handle);

        $no = 0;

        while (($row = fgetcsv($handle, 0, $pemisah, '"', '\\')) !== false) {
            $no++;

            // Baris kosong (atau yang cuma berisi satu kolom kosong) dilewati.
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $baris[] = [
                'no' => $no,
                'data' => array_map(fn ($sel) => trim((string) $sel), $row),
            ];
        }

        fclose($handle);

        return $baris;
    }

    /**
     * Petakan judul kolom ke nama kolom yang dipakai aplikasi.
     *
     * Kalau baris pertama tidak terlihat seperti judul kolom, berkas dianggap
     * tanpa judul dan urutannya: NIS, Nama, Kelas, Jenis Kelamin.
     *
     * @return array{0: bool, 1: array<string,int>}
     */
    protected static function petakanKolom(array $pertama): array
    {
        $sinonim = [
            'nis' => ['nis', 'nisn', 'noinduk', 'nomorinduk', 'nomor', 'no'],
            'nama' => ['nama', 'namalengkap', 'namasiswa', 'namasantri', 'name'],
            'kelas' => ['kelas', 'namakelas', 'kelasid', 'kelassiswa', 'rombel', 'room'],
            'jenis_kelamin' => ['jk', 'jeniskelamin', 'gender', 'lp', 'kelamin', 'jenis'],
        ];

        $peta = [];

        foreach ($pertama as $index => $sel) {
            $nama = self::normalisasi($sel);

            if ($nama === '') {
                continue;
            }

            foreach ($sinonim as $kolom => $daftar) {
                if (! isset($peta[$kolom]) && in_array($nama, $daftar, true)) {
                    $peta[$kolom] = $index;
                }
            }
        }

        // Judul kolom dianggap sah kalau ada kolom nama dan minimal satu lagi.
        $adaHeader = isset($peta['nama']) && (isset($peta['nis']) || isset($peta['kelas']));

        if ($adaHeader) {
            return [true, $peta];
        }

        return [false, self::kolomDefault()];
    }

    protected static function kolomDefault(): array
    {
        return [
            'nis' => 0,
            'nama' => 1,
            'kelas' => 2,
            'jenis_kelamin' => 3,
        ];
    }
}
