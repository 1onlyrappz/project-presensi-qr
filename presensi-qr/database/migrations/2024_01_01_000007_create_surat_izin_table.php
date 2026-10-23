<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Pengajuan surat izin siswa.
 *
 * Setelah disetujui, hari-hari izin otomatis dicatat di tabel "presensi"
 * dengan status izin/sakit, sehingga langsung masuk ke rekap bulanan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surat_izin')) {
            return;
        }

        Schema::create('surat_izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->enum('jenis_izin', ['sakit', 'izin', 'cuti', 'darurat']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('nomor_surat', 100)->nullable();
            $table->text('alasan');
            $table->string('lampiran', 255)->nullable();
            $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->text('catatan')->nullable();
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'tanggal_mulai']);
            $table->index('tanggal_mulai');
        });

        $this->pindahkanDataLama();
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_izin');
    }

    /*
     * Database lama (versi "pengajuan_izin") masih punya datanya sendiri.
     * Pindahkan supaya tidak hilang saat aplikasi diperbarui.
     */
    protected function pindahkanDataLama(): void
    {
        if (! Schema::hasTable('pengajuan_izin')) {
            return;
        }

        $kolom = ['siswa_id', 'jenis_izin', 'tanggal_mulai', 'tanggal_selesai', 'alasan', 'status', 'created_at', 'updated_at'];

        if (Schema::hasColumn('pengajuan_izin', 'lampiran')) {
            $kolom[] = 'lampiran';
        }

        // Hanya baris yang siswa-nya masih ada (kalau tidak, foreign key gagal).
        DB::statement(
            'INSERT INTO `surat_izin` (`' . implode('`, `', $kolom) . '`) '
            . 'SELECT p.`' . implode('`, p.`', $kolom) . '` '
            . 'FROM `pengajuan_izin` p INNER JOIN `siswa` s ON s.`id` = p.`siswa_id`'
        );
    }
};
