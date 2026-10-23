<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Jumlah hari efektif (hari sekolah) per bulan.
 *
 * Secara otomatis rekap memakai jumlah tanggal yang tercatat ada aktivitas
 * presensi di bulan itu. Kalau sekolah punya hari libur nasional atau
 * kegiatan yang membuat sebagian kelas tidak scan, Administrator bisa
 * mengoreksi jumlah ini agar angka "alpa" sesuai untuk rapor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bulan_efektif')) {
            return;
        }

        Schema::create('bulan_efektif', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('tahun');
            $table->smallInteger('bulan');
            $table->smallInteger('jumlah_hari');
            $table->timestamps();

            $table->unique(['tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulan_efektif');
    }
};
