<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Laporan & dashboard selalu memfilter berdasarkan tanggal saja;
    // index unik (siswa_id, tanggal) tidak membantu query seperti itu.
    public function up(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::table('presensi', function (Blueprint $table) {
            $table->dropIndex(['tanggal']);
        });
    }
};
