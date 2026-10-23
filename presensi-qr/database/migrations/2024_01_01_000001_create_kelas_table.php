<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tabel kelas.
 *
 * Migrasi ini aman untuk database baru maupun database lama: kalau tabelnya
 * sudah ada, tidak ada yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kelas')) {
            return;
        }

        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kelas');
            $table->string('wali_kelas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
