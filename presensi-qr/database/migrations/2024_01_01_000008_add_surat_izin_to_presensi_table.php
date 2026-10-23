<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Menambahkan status manual (izin / sakit / alpa) dan penanda surat izin
 * ke tabel presensi.
 *
 * Status lama "hadir" dan "telat" berasal dari scan QR. Tiga status baru
 * diisi manual oleh petugas (atau otomatis dari surat izin yang disetujui)
 * tanpa jam masuk, sehingga rekap bulanan bisa menghitung semua kategori.
 *
 * Karena itu kolom "jam_masuk" harus boleh kosong. Di database lama kolom
 * ini masih NOT NULL, jadi ikut dilonggarkan di sini.
 */
return new class extends Migration
{
    // Status yang boleh dipakai di tabel presensi.
    protected const STATUS = ['hadir', 'telat', 'izin', 'sakit', 'alpa'];

    public function up(): void
    {
        if (! Schema::hasTable('presensi')) {
            return;
        }

        $perluStatusBaru = ! $this->sudahPunyaStatusBaru();
        $perluJamKosong = ! $this->jamMasukBolehNull();

        if ($perluStatusBaru || $perluJamKosong) {
            $this->perbaikiKolom($perluStatusBaru, $perluJamKosong);
        }

        if (! Schema::hasColumn('presensi', 'surat_izin_id')) {
            Schema::table('presensi', function (Blueprint $table) {
                $table->foreignId('surat_izin_id')->nullable()->constrained('surat_izin')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('presensi', 'catatan')) {
            Schema::table('presensi', function (Blueprint $table) {
                $table->string('catatan', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('presensi')) {
            return;
        }

        if (Schema::hasColumn('presensi', 'catatan')) {
            Schema::table('presensi', function (Blueprint $table) {
                $table->dropColumn('catatan');
            });
        }

        if (Schema::hasColumn('presensi', 'surat_izin_id')) {
            Schema::table('presensi', function (Blueprint $table) {
                $table->dropForeign(['surat_izin_id']);
                $table->dropColumn('surat_izin_id');
            });
        }
    }

    /**
     * Menyesuaikan kolom status (dan jam_masuk) sesuai kebutuhan baru.
     */
    protected function perbaikiKolom(bool $perluStatusBaru, bool $perluJamKosong): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            if ($perluStatusBaru && $this->semuaStatusValid()) {
                DB::statement(
                    'ALTER TABLE `presensi` MODIFY `status` '
                    . "ENUM('hadir','telat','izin','sakit','alpa') NOT NULL DEFAULT 'hadir'"
                );
            }

            if ($perluJamKosong) {
                // Status izin/sakit/alpa dicatat tanpa jam masuk.
                DB::statement('ALTER TABLE `presensi` MODIFY `jam_masuk` TIME NULL');
            }

            return;
        }

        if ($driver === 'sqlite') {
            $this->bangunUlangSqlite();

            return;
        }

        // Driver lain (mis. pgsql): pakai skema Laravel.
        Schema::table('presensi', function (Blueprint $table) use ($perluStatusBaru, $perluJamKosong) {
            if ($perluStatusBaru) {
                $table->enum('status', self::STATUS)->default('hadir')->change();
            }

            if ($perluJamKosong) {
                $table->time('jam_masuk')->nullable()->change();
            }
        });
    }

    /**
     * True kalau kolom status sudah memperbolehkan semua status baru.
     */
    protected function sudahPunyaStatusBaru(): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $baris = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'presensi'");

            return $baris && str_contains(strtolower((string) $baris->sql), "'izin'");
        }

        $kolom = DB::selectOne("SHOW COLUMNS FROM `presensi` WHERE Field = 'status'");

        if (! $kolom) {
            return true;
        }

        $tipe = strtolower((string) $kolom->Type);

        // Kolom varchar masih perlu diubah ke enum supaya sama dengan
        // database yang baru dibuat. Kalau nanti ada nilai lama yang tidak
        // dikenal, kolomnya dibiarkan varchar (tidak hilang datanya).
        if (! str_contains($tipe, 'enum')) {
            return false;
        }

        foreach (self::STATUS as $status) {
            if (! str_contains($tipe, "'" . $status . "'")) {
                return false;
            }
        }

        return true;
    }

    /**
     * True kalau kolom jam_masuk sudah boleh kosong.
     */
    protected function jamMasukBolehNull(): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            foreach (DB::select('PRAGMA table_info("presensi")') as $kolom) {
                if ($kolom->name === 'jam_masuk') {
                    return (int) $kolom->notnull === 0;
                }
            }

            return true;
        }

        $kolom = DB::selectOne("SHOW COLUMNS FROM `presensi` WHERE Field = 'jam_masuk'");

        return $kolom ? strtolower((string) $kolom->Null) === 'yes' : true;
    }

    /**
     * True kalau semua nilai status yang sudah ada termasuk daftar status
     * yang sah. Kalau ada nilai lain, kolom dibiarkan apa adanya supaya
     * migrasi tidak gagal dan data lama tidak hilang.
     */
    protected function semuaStatusValid(): bool
    {
        $nilai = DB::table('presensi')->distinct()->pluck('status');

        foreach ($nilai as $status) {
            if (! in_array($status, self::STATUS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * SQLite tidak bisa mengubah kolom enum, jadi tabelnya dibangun ulang.
     *
     * Struktur tabel lama dibaca dulu supaya tidak ada kolom yang hilang.
     */
    protected function bangunUlangSqlite(): void
    {
        $kolomLama = DB::select('PRAGMA table_info("presensi")');
        $daftarKolom = array_map(fn ($c) => $c->name, $kolomLama);

        $definisi = [];

        foreach ($kolomLama as $c) {
            $nama = $c->name;
            $tipe = $c->type ?: 'varchar';
            $notNull = (int) $c->notnull === 1;
            $default = $c->dflt_value;

            if ($nama === 'id') {
                $definisi[] = '`id` integer NOT NULL PRIMARY KEY AUTOINCREMENT';

                continue;
            }

            if ($nama === 'status') {
                $definisi[] = "`status` varchar NOT NULL DEFAULT 'hadir'";

                continue;
            }

            if ($nama === 'jam_masuk') {
                $notNull = false;
            }

            $bagian = "`{$nama}` {$tipe}";

            if ($notNull) {
                $bagian .= ' NOT NULL';
            }

            if ($default !== null) {
                $bagian .= ' DEFAULT ' . $default;
            }

            $definisi[] = $bagian;
        }

        // Batasan nilai status.
        $daftar = implode(',', array_map(fn ($s) => "'" . $s . "'", self::STATUS));
        $definisi[] = "CHECK (\"status\" IN ({$daftar}))";

        // Batasan unik yang menempel pada tabel.
        foreach (DB::select('PRAGMA index_list("presensi")') as $index) {
            if ($index->origin !== 'u') {
                continue;
            }

            $kolomIndex = array_map(
                fn ($i) => '"' . $i->name . '"',
                DB::select('PRAGMA index_info("' . $index->name . '")')
            );

            if ($kolomIndex !== []) {
                $definisi[] = 'UNIQUE (' . implode(', ', $kolomIndex) . ')';
            }
        }

        // Relasi ke tabel lain.
        foreach (DB::select('PRAGMA foreign_key_list("presensi")') as $fk) {
            $kolomTujuan = $fk->to ?: $this->kunciUtama($fk->table);
            $hapus = strtoupper((string) $fk->on_delete);

            if (! in_array($hapus, ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'], true)) {
                $hapus = 'NO ACTION';
            }

            $definisi[] = 'FOREIGN KEY ("' . $fk->from . '") REFERENCES "' . $fk->table . '" ("' . $kolomTujuan . '") ON DELETE ' . $hapus;
        }

        // Index yang dibuat terpisah (bukan constraint tabel).
        // Dicatat lebih dulu karena akan hilang bersama tabel lama.
        $indexBuatan = [];

        foreach (DB::select('PRAGMA index_list("presensi")') as $index) {
            if ($index->origin !== 'c') {
                continue;
            }

            $kolomIndex = array_map(
                fn ($i) => '"' . $i->name . '"',
                DB::select('PRAGMA index_info("' . $index->name . '")')
            );

            if ($kolomIndex !== []) {
                $indexBuatan[] = [
                    'nama' => $index->name,
                    'unik' => (bool) $index->unique,
                    'kolom' => $kolomIndex,
                ];
            }
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('CREATE TABLE `presensi_baru` (' . implode(', ', $definisi) . ')');

        DB::statement(
            'INSERT INTO `presensi_baru` (`' . implode('`, `', $daftarKolom) . '`) '
            . 'SELECT `' . implode('`, `', $daftarKolom) . '` FROM `presensi`'
        );

        DB::statement('DROP TABLE `presensi`');
        DB::statement('ALTER TABLE `presensi_baru` RENAME TO `presensi`');

        foreach ($indexBuatan as $index) {
            DB::statement(
                'CREATE ' . ($index['unik'] ? 'UNIQUE ' : '') . 'INDEX IF NOT EXISTS "' . $index['nama'] . '" '
                . 'ON `presensi` (' . implode(', ', $index['kolom']) . ')'
            );
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }

    /**
     * Nama kolom utama tabel yang dirujuk foreign key.
     */
    protected function kunciUtama(string $tabel): string
    {
        foreach (DB::select('PRAGMA table_info("' . $tabel . '")') as $kolom) {
            if ((int) $kolom->pk > 0) {
                return $kolom->name;
            }
        }

        return 'id';
    }
};
