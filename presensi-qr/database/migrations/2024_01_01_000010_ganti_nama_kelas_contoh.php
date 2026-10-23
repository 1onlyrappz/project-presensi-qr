<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengganti nama kelas contoh bawaan ke jurusan TJKT.
 *
 * Migrasi ini hanya menyentuh kelas contoh (X IPA 1, X IPA 2, XI IPS 1).
 * Kalau kelas dengan nama baru sudah ada, kelas lama dibiarkan supaya data
 * asli sekolah tidak tertimpa. Pada database baru migrasi ini tidak
 * melakukan apa-apa karena kelas belum dibuat (lihat DatabaseSeeder).
 */
return new class extends Migration
{
    /**
     * Nama kelas lama dan penggantinya.
     *
     * @var array<string, string>
     */
    private array $peta = [
        'X IPA 1' => 'X TJKT 2',
        'X IPA 2' => 'XI TJKT 2',
        'XI IPS 1' => 'XII TJKT 2',
    ];

    public function up(): void
    {
        $this->gantiNama($this->peta);
    }

    public function down(): void
    {
        $this->gantiNama(array_flip($this->peta));
    }

    /**
     * Ganti nama kelas satu per satu tanpa menghapus siswanya.
     *
     * @param  array<string, string>  $peta
     */
    private function gantiNama(array $peta): void
    {
        if (! Schema::hasTable('kelas')) {
            return;
        }

        foreach ($peta as $lama => $baru) {
            $idLama = DB::table('kelas')->where('nama_kelas', $lama)->value('id');

            if (! $idLama) {
                continue;
            }

            // Jangan menimpa kalau kelas dengan nama baru sudah dibuat manual.
            if (DB::table('kelas')->where('nama_kelas', $baru)->exists()) {
                continue;
            }

            DB::table('kelas')->where('id', $idLama)->update(['nama_kelas' => $baru]);
        }
    }
};
