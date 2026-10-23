@php
    /*
     | Kerangka bersama untuk semua halaman galat.
     | Sengaja tidak memakai layout panel supaya tetap tampil walau sesi
     | pengguna sudah berakhir atau database sedang bermasalah.
     */
    $kode = $kode ?? 500;
    $judul = $judul ?? 'Terjadi kesalahan';
    $pesan = $pesan ?? 'Silakan coba lagi beberapa saat lagi.';
    $aksi = $aksi ?? 'Kembali ke dashboard';
    $tujuan = $tujuan ?? url('/');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#143832">
    <title>{{ $kode }} - {{ $judul }} | {{ config('presensi.nama_sekolah') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/sekolah.css') }}">
    <style>
        .galat { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: var(--canvas); }
        .galat-kartu { width: min(520px, 100%); text-align: center; background: var(--surface); border: 1px solid var(--line); border-radius: var(--r-lg); padding: 36px 28px; box-shadow: var(--shadow); }
        .galat-merek { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 20px; font-weight: 700; }
        .galat-merek .brand-mark { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: #fff; color: var(--board); border: 1px solid var(--line); }
        .galat-merek .brand-mark .logo { width: 100%; height: 100%; object-fit: contain; padding: 4px; display: block; }
        .galat-kode { font-size: 58px; font-weight: 700; line-height: 1; letter-spacing: -.03em; color: var(--board); }
        .galat-kartu h1 { font-size: 22px; margin: 14px 0 8px; }
        .galat-kartu p { color: var(--ink-2, #4c5b5e); margin: 0 auto 22px; max-width: 42ch; }
    </style>
</head>
<body>
<div class="galat">
    <div class="galat-kartu">
        <span class="galat-merek">
            <span class="brand-mark brand-mark-logo"><img class="logo" src="{{ asset('img/logo-sekolah.png') }}" alt="Logo {{ config('presensi.nama_sekolah') }}"></span>
            <span>{{ config('presensi.nama_sekolah') }}</span>
        </span>
        <div class="galat-kode">{{ $kode }}</div>
        <h1>{{ $judul }}</h1>
        <p>{{ $pesan }}</p>
        <a class="btn" href="{{ $tujuan }}">{{ $aksi }}</a>
    </div>
</div>
</body>
</html>
