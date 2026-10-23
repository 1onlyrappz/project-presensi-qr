<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah tiga jenis akun baru:
 *
 * - wali_kelas : satu akun terhubung ke satu kelas (users.kelas_id).
 * - siswa      : satu akun terhubung ke satu data siswa (users.siswa_id).
 * - orang_tua  : boleh terhubung ke beberapa siswa (tabel orang_tua_siswa),
 *                misalnya orang tua yang punya dua anak di sekolah ini.
 *
 * Foreign key hanya dipasang di MySQL/MariaDB. SQLite (dipakai saat
 * pengujian) tidak bisa menambah foreign key ke tabel yang sudah ada,
 * jadi untuk SQLite relasinya dijaga oleh aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'kelas_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('kelas_id')->nullable()->after('role');
                $table->index('kelas_id');
            });
        }

        if (! Schema::hasColumn('users', 'siswa_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('siswa_id')->nullable()->after('kelas_id');
                $table->index('siswa_id');
            });
        }

        if (! Schema::hasTable('orang_tua_siswa')) {
            Schema::create('orang_tua_siswa', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
                $table->timestamps();

                // Satu orang tua hanya sekali terhubung ke siswa yang sama.
                $table->unique(['user_id', 'siswa_id']);
            });
        }

        if (DB::getDriverName() === 'mysql') {
            // Kalau kelas/siswa dihapus, akunnya tetap ada tetapi tautannya
            // dilepas supaya administrator bisa menautkan ulang.
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('kelas_id')->references('id')->on('kelas')->nullOnDelete();
            });

            Schema::table('users', function (Blueprint $table) {
                $table->foreign('siswa_id')->references('id')->on('siswa')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['kelas_id']);
                $table->dropForeign(['siswa_id']);
            });
        }

        Schema::dropIfExists('orang_tua_siswa');

        if (Schema::hasColumn('users', 'siswa_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('siswa_id');
            });
        }

        if (Schema::hasColumn('users', 'kelas_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('kelas_id');
            });
        }
    }
};
