<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tabel presensi.
 *
 * Satu baris untuk satu siswa pada satu tanggal.
 * - status "hadir"/"telat": hasil scan QR (ada jam masuk).
 * - status "izin"/"sakit"/"alpa": diisi manual atau dari surat izin yang
 *   disetujui (tanpa jam masuk).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('presensi')) {
            return;
        }

        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->enum('status', ['hadir', 'telat', 'izin', 'sakit', 'alpa'])->default('hadir');
            $table->timestamps();

            // Satu siswa hanya punya satu baris presensi per tanggal
            $table->unique(['siswa_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};
