<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tabel siswa.
 *
 * Migrasi ini aman untuk database baru maupun database lama: kalau tabelnya
 * sudah ada, tidak ada yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('siswa')) {
            return;
        }

        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nis')->unique();
            $table->string('nisn')->nullable()->unique();
            $table->string('nama');
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->enum('jenis_kelamin', ['L', 'P'])->default('L');
            $table->string('kode_qr')->unique();
            $table->string('foto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
