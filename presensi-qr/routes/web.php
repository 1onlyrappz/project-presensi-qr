<?php

use App\Http\Controllers\AkunController;
use App\Http\Controllers\AktivitasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\RiwayatController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\SiswaImportController;
use App\Http\Controllers\SuratIzinController;
use App\Http\Middleware\AturBahasa;
use App\Http\Middleware\HeaderKeamanan;
use App\Http\Middleware\PunyaRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Halaman awal -> dashboard (yang belum login otomatis diarahkan ke /login)
Route::get('/', fn () => redirect()->route('dashboard'))
    ->middleware([AturBahasa::class, HeaderKeamanan::class]);

// Pemeriksaan ringan untuk pemantauan uptime hosting (tanpa login).
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
    } catch (\Throwable $e) {
        return response()->json(['status' => 'gagal'], 503);
    }

    return response()->json([
        'status' => 'ok',
        'sekolah' => config('presensi.nama_sekolah'),
        'waktu' => now()->toIso8601String(),
    ]);
})->middleware([AturBahasa::class, HeaderKeamanan::class])->name('health');

// Login (tanpa pendaftaran publik)
Route::middleware([AturBahasa::class, HeaderKeamanan::class, 'guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware([AturBahasa::class, HeaderKeamanan::class, 'auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Akun (ganti kata sandi sendiri) - untuk semua peran
    Route::get('/akun', [AkunController::class, 'edit'])->name('akun.edit');
    Route::put('/akun', [AkunController::class, 'update'])->name('akun.update');

    // Riwayat kehadiran pribadi untuk siswa & orang tua.
    Route::middleware(PunyaRole::class.':siswa,orang_tua')->group(function () {
        Route::get('/riwayat', [RiwayatController::class, 'index'])->name('riwayat.index');
    });

    // Surat izin: semua peran boleh mengajukan, daftar otomatis dibatasi
    // sesuai peran di controller (siswa: diri sendiri, orang tua: anaknya,
    // wali kelas: kelasnya, admin/guru piket: semua).
    Route::get('/surat-izin', [SuratIzinController::class, 'index'])->name('surat_izin.index');
    Route::post('/surat-izin', [SuratIzinController::class, 'store'])->name('surat_izin.store');

    // -----------------------------------------------------------------
    // Petugas sekolah: admin, guru piket, dan wali kelas.
    // Scan hanya untuk admin dan guru piket (diatur di bawah).
    // -----------------------------------------------------------------
    Route::middleware(PunyaRole::class.':admin,guru_piket,wali_kelas')->group(function () {
        // Laporan Presensi (wali kelas dibatasi ke kelasnya di controller)
        Route::get('/presensi', [PresensiController::class, 'index'])->name('presensi.index');
        Route::get('/presensi/export', [PresensiController::class, 'export'])->name('presensi.export');

        // Rekap bulanan per kelas (wali kelas dibatasi ke kelasnya di controller)
        Route::get('/rekap', [RekapController::class, 'index'])->name('rekap.index');
        Route::get('/rekap/export', [RekapController::class, 'export'])->name('rekap.export');

        // Daftar siswa (hanya bisa dilihat, bukan diubah - lihat route admin di bawah)
        Route::get('/siswa', [SiswaController::class, 'index'])->name('siswa.index');

        // Cetak kartu (admin atau wali kelas untuk kelasnya)
        Route::get('/siswa/kartu-kelas', [SiswaController::class, 'kartuKelas'])->name('siswa.kartu-kelas');
        Route::get('/siswa/{siswa}/kartu', [SiswaController::class, 'kartu'])->name('siswa.kartu');
    });

    // Scan & tandai presensi (admin dan guru piket)
    Route::middleware(PunyaRole::class.':admin,guru_piket')->group(function () {
        // Scan Presensi (kamera / scanner USB)
        Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
        Route::post('/scan/proses', [ScanController::class, 'proses'])->middleware('throttle:120,1')->name('scan.proses');

        // Tandai status manual (izin / sakit / alpa) untuk siswa yang tidak scan.
        Route::post('/presensi/status', [PresensiController::class, 'setStatus'])->name('presensi.status');
    });

    // Proses surat izin (admin atau wali kelas untuk kelas terkait)
    Route::middleware(PunyaRole::class.':admin,wali_kelas')->group(function () {
        Route::post('/surat-izin/{suratIzin}/proses', [SuratIzinController::class, 'proses'])->name('surat_izin.proses');
    });

    // -----------------------------------------------------------------
    // Khusus administrator: mengelola data master dan pengguna.
    // -----------------------------------------------------------------
    Route::middleware(PunyaRole::class.':admin')->group(function () {
        // Data Kelas
        Route::get('/kelas', [KelasController::class, 'index'])->name('kelas.index');
        Route::post('/kelas', [KelasController::class, 'store'])->name('kelas.store');
        Route::put('/kelas/{kelas}', [KelasController::class, 'update'])->name('kelas.update');
        Route::delete('/kelas/{kelas}', [KelasController::class, 'destroy'])->name('kelas.destroy');

        // Data Siswa
        Route::get('/siswa/tambah', [SiswaController::class, 'create'])->name('siswa.create');
        Route::post('/siswa', [SiswaController::class, 'store'])->name('siswa.store');

        // Impor siswa dari Excel (CSV)
        Route::get('/siswa/import', [SiswaImportController::class, 'index'])->name('siswa.import');
        Route::post('/siswa/import', [SiswaImportController::class, 'proses'])->name('siswa.import.proses');
        Route::get('/siswa/import/template', [SiswaImportController::class, 'template'])->name('siswa.import.template');

        Route::get('/siswa/{siswa}/edit', [SiswaController::class, 'edit'])->name('siswa.edit');
        Route::put('/siswa/{siswa}', [SiswaController::class, 'update'])->name('siswa.update');
        Route::delete('/siswa/{siswa}', [SiswaController::class, 'destroy'])->name('siswa.destroy');
        Route::post('/siswa/{siswa}/qr-baru', [SiswaController::class, 'buatUlangQr'])->name('siswa.qr-baru');

        // Hapus surat izin (proses persetujuan ada di grup staf di atas)
        Route::delete('/surat-izin/{suratIzin}', [SuratIzinController::class, 'destroy'])->name('surat_izin.destroy');

        // Jumlah hari efektif per bulan (untuk perhitungan alpa)
        Route::post('/rekap/hari-efektif', [RekapController::class, 'aturHariEfektif'])->name('rekap.hari-efektif');

        // Manajemen pengguna (tidak ada pendaftaran publik)
        Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
        Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
        Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'update'])->name('pengguna.update');
        Route::delete('/pengguna/{pengguna}', [PenggunaController::class, 'destroy'])->name('pengguna.destroy');

        // Jejak aktivitas
        Route::get('/aktivitas', [AktivitasController::class, 'index'])->name('aktivitas.index');
    });
});
