<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#143832">
    <meta name="robots" content="noindex,nofollow">
    <title>Masuk - {{ config('presensi.nama_sekolah') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/sekolah.css') }}">
</head>
<body>
<div class="login">
    <section class="login-brand" aria-hidden="true">
        <div class="brand">
            <span class="brand-mark brand-mark-logo"><img class="logo" src="{{ asset('img/logo-sekolah.png') }}" alt="Logo {{ config('presensi.nama_sekolah') }}"></span>
            <span class="brand-name">{{ config('presensi.nama_sekolah') }}</span>
        </div>
        <div>
            <h1>Presensi siswa dengan kartu QR</h1>
            <p>Scan kartu pelajar di gerbang, jam masuk dan pulang tercatat otomatis.</p>
        </div>
        <span class="brand-sub">Untuk staf sekolah, wali kelas, orang tua, dan siswa</span>
    </section>

    <section class="login-form-wrap">
        <div class="login-card">
            <div class="login-mobile-brand">
                <span class="brand-mark brand-mark-logo"><img class="logo" src="{{ asset('img/logo-sekolah.png') }}" alt="Logo {{ config('presensi.nama_sekolah') }}"></span>
                <span class="brand-name">{{ config('presensi.nama_sekolah') }}</span>
            </div>

            <h2>Masuk</h2>
            <p class="sub">Masuk dengan akun sekolah Anda: administrator, guru piket, wali kelas, orang tua, atau siswa.</p>

            @php
                $errEmail = $errors->has('email');
                $errPassword = $errors->has('password');
            @endphp

            <form method="POST" action="{{ route('login.attempt') }}" data-busy-form>
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required
                        @if ($errEmail) aria-invalid="true" aria-describedby="email-error" @endif
                        @if (! $errPassword) autofocus @endif
                        autocomplete="username" inputmode="email" maxlength="255" placeholder="nama@sekolah.sch.id">
                    @error('email')
                        <p class="field-error" id="email-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div class="field">
                    <label for="password">Kata sandi</label>
                    <input class="input" type="password" id="password" name="password" required
                        @if ($errPassword) aria-invalid="true" aria-describedby="password-error" @endif
                        @if ($errPassword) autofocus @endif
                        autocomplete="current-password" enterkeyhint="go">
                    @error('password')
                        <p class="field-error" id="password-error" role="alert">{{ $message }}</p>
                    @enderror
                    <p class="hint capslock" data-capslock-for="password" hidden><x-icon name="alert" /> Caps Lock sedang aktif.</p>
                </div>
                <label class="check" style="margin-bottom:16px">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember'))> Ingat saya di perangkat ini
                </label>
                <button type="submit" class="btn btn-block">Masuk</button>
            </form>

            <p class="sub" style="margin-top:18px;text-align:center">Lupa kata sandi? Hubungi administrator sekolah.</p>
        </div>
        <p class="small muted" style="text-align:center;margin-top:22px">© {{ date('Y') }} {{ config('presensi.nama_sekolah') }} · Presensi QR</p>
    </section>
</div>
<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
