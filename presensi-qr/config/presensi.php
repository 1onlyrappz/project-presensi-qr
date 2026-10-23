<?php

/*
|--------------------------------------------------------------------------
| Pengaturan Presensi
|--------------------------------------------------------------------------
| Semua nilai bisa diubah lewat file .env (lihat README).
*/

return [

    // Zona waktu untuk menentukan "hari ini", jam masuk, dan jam pulang.
    // Dipisahkan dari APP_TIMEZONE supaya presensi tetap benar walau config/app.php masih UTC.
    'timezone' => env('PRESENSI_TIMEZONE', 'Asia/Jakarta'),

    // Scan masuk SETELAH jam ini dianggap telat (format HH:MM, 24 jam).
    'batas_telat' => env('PRESENSI_BATAS_TELAT', '07:30'),

    // Scan kedua baru dihitung "pulang" setelah jeda ini (menit) sejak jam masuk.
    // Mencegah kartu yang tidak sengaja di-scan dua kali langsung tercatat pulang.
    'jeda_pulang_menit' => (int) env('PRESENSI_JEDA_PULANG_MENIT', 5),

    // Nama sekolah yang tampil di menu, halaman login, dan kartu pelajar.
    'nama_sekolah' => env('PRESENSI_NAMA_SEKOLAH', 'SMK Nu Ungaran'),

    // Bahasa tampilan (nama bulan, pesan validasi). Aplikasi ini khusus
    // sekolah Indonesia, jadi bawaannya "id" walau APP_LOCALE lupa diisi.
    'bahasa' => env('PRESENSI_BAHASA', 'id'),

    // Hari sekolah (1 = Senin ... 7 = Minggu, gaya Carbon dayOfWeek).
    // Dipakai untuk menghitung berapa hari yang tercakup surat izin.
    // Bisa diubah lewat .env, contoh: PRESENSI_HARI_KERJA=1,2,3,4,5,6
    // (kalau sekolah punya kelas hari Sabtu, tambahkan angka 6).
    'hari_kerja' => array_values(array_filter(
        array_map('intval', explode(',', (string) env('PRESENSI_HARI_KERJA', '1,2,3,4,5')))
    )) ?: [1, 2, 3, 4, 5],
];
