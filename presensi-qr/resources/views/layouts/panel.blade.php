<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#143832">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Dashboard') - {{ config('presensi.nama_sekolah') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/sekolah.css') }}">
    @yield('head')
</head>
<body>
<a href="#main" class="skip">Lewati ke konten</a>

<div class="shell">
    <aside class="side" id="side" aria-label="Menu utama">
        <a href="{{ route('dashboard') }}" class="brand">
            <span class="brand-mark brand-mark-logo"><img class="logo" src="{{ asset('img/logo-sekolah.png') }}" alt="Logo {{ config('presensi.nama_sekolah') }}"></span>
            <span>
                <span class="brand-name">{{ config('presensi.nama_sekolah') }}</span><br>
                <span class="brand-sub">Presensi QR</span>
            </span>
        </a>

        <div class="clock" data-clock data-tz="{{ config('presensi.timezone') }}">
            <b data-clock-time>--:--</b>
            <span data-clock-date></span>
        </div>

        <nav class="nav">
            <a href="{{ route('dashboard') }}" aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                <x-icon name="dashboard" /> Dashboard
            </a>

            @if (auth()->user()->isAdmin() || auth()->user()->isGuruPiket())
                <a href="{{ route('scan.index') }}" class="nav-scan" aria-current="{{ request()->routeIs('scan.*') ? 'page' : 'false' }}">
                    <x-icon name="scan" /> Scan presensi
                </a>
            @endif

            @if (auth()->user()->isAdmin() || auth()->user()->isGuruPiket() || auth()->user()->isWaliKelas())
                <a href="{{ route('presensi.index') }}" aria-current="{{ request()->routeIs('presensi.*') ? 'page' : 'false' }}">
                    <x-icon name="report" /> Laporan presensi
                </a>
                <a href="{{ route('rekap.index') }}" aria-current="{{ request()->routeIs('rekap.*') ? 'page' : 'false' }}">
                    <x-icon name="calendar" /> Rekap bulanan
                </a>
            @endif

            @if (auth()->user()->isSiswa() || auth()->user()->isOrangTua())
                <a href="{{ route('riwayat.index') }}" aria-current="{{ request()->routeIs('riwayat.*') ? 'page' : 'false' }}">
                    <x-icon name="calendar" /> Riwayat kehadiran
                </a>
            @endif

            <a href="{{ route('surat_izin.index') }}" aria-current="{{ request()->routeIs('surat_izin.*') ? 'page' : 'false' }}">
                <x-icon name="letter" /> Surat izin
            </a>

            @if (auth()->user()->isAdmin() || auth()->user()->isGuruPiket() || auth()->user()->isWaliKelas())
                <div class="nav-gap"></div>
                <a href="{{ route('siswa.index') }}" aria-current="{{ request()->routeIs('siswa.*') ? 'page' : 'false' }}">
                    <x-icon name="student" /> Data siswa
                </a>
            @endif

            @if (auth()->user()->isAdmin())
                <a href="{{ route('kelas.index') }}" aria-current="{{ request()->routeIs('kelas.*') ? 'page' : 'false' }}">
                    <x-icon name="school" /> Data kelas
                </a>
                <div class="nav-gap"></div>
                <a href="{{ route('pengguna.index') }}" aria-current="{{ request()->routeIs('pengguna.*') ? 'page' : 'false' }}">
                    <x-icon name="users" /> Pengguna
                </a>
                <a href="{{ route('aktivitas.index') }}" aria-current="{{ request()->routeIs('aktivitas.*') ? 'page' : 'false' }}">
                    <x-icon name="clock" /> Aktivitas
                </a>
            @endif
        </nav>

        <div class="side-foot">
            <div class="who">
                <span class="muted" style="color:var(--board-text);opacity:.75">Masuk sebagai</span>
                <strong>{{ auth()->user()->name ?? '' }}</strong>
                <span class="badge {{ auth()->user()->isAdmin() ? 'badge-ok' : (auth()->user()->isWaliKelas() ? 'badge-info' : 'badge-mute') }}">{{ auth()->user()->roleLabel() }}</span>
                @if (auth()->user()->isWaliKelas() && auth()->user()->kelas)
                    <span class="small muted" style="display:block;margin-top:4px">Wali {{ auth()->user()->kelas->nama_kelas }}</span>
                @elseif (auth()->user()->isSiswa() && auth()->user()->siswa && auth()->user()->siswa->kelas)
                    <span class="small muted" style="display:block;margin-top:4px">{{ auth()->user()->siswa->nama }} &middot; {{ auth()->user()->siswa->kelas->nama_kelas }}</span>
                @elseif (auth()->user()->isOrangTua() && auth()->user()->anak->isNotEmpty())
                    <span class="small muted" style="display:block;margin-top:4px">Orang tua: {{ auth()->user()->anak->pluck('nama')->take(2)->join(', ') }}{{ auth()->user()->anak->count() > 2 ? ' ...' : '' }}</span>
                @endif
            </div>
            <a href="{{ route('akun.edit') }}"><x-icon name="key" /> Ganti kata sandi</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><x-icon name="logout" /> Keluar</button>
            </form>
            <div class="small" style="margin-top:10px;color:var(--board-text);opacity:.5">
                © {{ date('Y') }} {{ config('presensi.nama_sekolah') }}
            </div>
        </div>
    </aside>
    <div class="backdrop" data-nav-close></div>

    <div class="main-col">
        <header class="topbar">
            <button type="button" class="btn btn-ghost btn-icon" data-nav-toggle aria-controls="side" aria-expanded="false" aria-label="Buka menu">
                <x-icon name="menu" />
            </button>
            <div class="t-title">@yield('title', 'Dashboard')</div>
            <div class="clock" data-clock data-tz="{{ config('presensi.timezone') }}">
                <b data-clock-time>--:--</b>
                <span data-clock-date></span>
            </div>
        </header>

        <main id="main" class="content">
            <div class="page-head">
                <div>
                    <h1>@yield('title', 'Dashboard')</h1>
                    @hasSection('subtitle')
                        <p>@yield('subtitle')</p>
                    @endif
                </div>
                @hasSection('actions')
                    <div class="actions">@yield('actions')</div>
                @endif
            </div>

            @if (session('success'))
                <div class="alert alert-ok" role="status" data-autohide>
                    <x-icon name="check" />
                    <div class="grow">{{ session('success') }}</div>
                    <button type="button" class="alert-close" data-alert-close aria-label="Tutup pesan"><x-icon name="x" /></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-bad" role="alert">
                    <x-icon name="alert" />
                    <div class="grow">{{ session('error') }}</div>
                    <button type="button" class="alert-close" data-alert-close aria-label="Tutup pesan"><x-icon name="x" /></button>
                </div>
            @endif

            @if ($errors->any() && ! old('_modal'))
                <div class="alert alert-bad" role="alert">
                    <x-icon name="alert" />
                    <div class="grow">
                        <strong>Data belum bisa disimpan. Periksa isian berikut:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

{{-- Dialog konfirmasi bersama untuk semua tombol hapus --}}
<dialog id="dlg-confirm" aria-labelledby="dlg-confirm-title">
    <div class="dlg-head"><h2 id="dlg-confirm-title">Konfirmasi</h2></div>
    <div class="dlg-body"><p id="dlg-confirm-msg"></p></div>
    <div class="dlg-foot">
        <button type="button" class="btn btn-ghost" data-confirm-no>Batal</button>
        <button type="button" class="btn btn-danger" data-confirm-yes>Ya, lanjutkan</button>
    </div>
</dialog>

<script src="{{ asset('js/app.js') }}"></script>
@yield('scripts')
</body>
</html>
