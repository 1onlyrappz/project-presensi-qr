<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('guru_piket')->index();
        });

        // Semua akun yang sudah ada sebelumnya adalah admin, karena saat itu
        // aplikasi hanya punya satu level pengguna. Jangan sampai ada admin
        // yang tiba-tiba berubah jadi guru piket (bisa mengunci akses).
        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
