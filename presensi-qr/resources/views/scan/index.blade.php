@extends('layouts.panel')
@section('title', 'Scan presensi')
@section('subtitle', 'Arahkan kartu pelajar ke kamera. Scan pertama dicatat masuk, scan berikutnya dicatat pulang.')

@section('content')
@php
    $hostSekarang = request()->getHost();
    $hostLokal = in_array($hostSekarang, ['localhost', '127.0.0.1', '::1'], true);
@endphp
@if (!request()->isSecure() && !$hostLokal)
    <div class="alert alert-warn" role="alert" style="margin-bottom:16px">
        <x-icon name="alert" />
        <div class="grow">
            <strong>Kamera diblokir karena halaman ini masih HTTP.</strong>
            Buka lewat <code>https://</code> (jalankan <code>mulai-https.bat</code> di komputer server
            atau aktifkan AutoSSL/Let's Encrypt di hosting). Panduan: <code>ssl/README-SSL.md</code>.
        </div>
    </div>
@endif
<div id="scan-app" class="scan-grid"
     data-url="{{ route('scan.proses') }}"
     data-login-url="{{ route('login') }}"
     data-local-lib="{{ asset('vendor/html5-qrcode.min.js') }}">

    <section aria-label="Kamera">
        <div class="viewfinder" id="viewfinder">
            <div id="reader"></div>
            <div class="vf-corners"></div>
            <div class="vf-idle" id="vf-idle">
                <x-icon name="camera" />
                <p id="vf-idle-text">Kamera belum aktif.</p>
                <button type="button" class="btn" id="btn-start"><x-icon name="camera" /> Nyalakan kamera</button>
            </div>
        </div>

        <div class="cam-bar">
            <select class="select" id="camera-select" aria-label="Pilih kamera" hidden></select>
            <button type="button" class="btn btn-ghost" id="btn-stop" hidden>Matikan kamera</button>
            <button type="button" class="btn btn-ghost btn-icon sound-toggle" id="btn-sound" aria-pressed="true" aria-label="Suara notifikasi aktif" title="Suara notifikasi">
                <x-icon name="volume" />
            </button>
        </div>

        <div class="alert alert-warn" id="cam-warning" role="alert" hidden style="margin-top:14px">
            <x-icon name="alert" />
            <div class="grow" id="cam-warning-text"></div>
        </div>
    </section>

    <section aria-label="Hasil scan">
        <div class="result" id="result" data-s="idle" role="status" aria-live="polite">
            <div class="r-head">
                <span class="r-icon" id="r-icon"><x-icon name="scan" /></span>
                <div>
                    <div class="r-title" id="r-title">Siap memindai</div>
                    <div class="r-msg" id="r-msg">Hasil scan akan tampil di sini.</div>
                </div>
            </div>
            <div class="r-who">
                <div class="r-name" id="r-name"></div>
                <div class="r-meta" id="r-meta"></div>
            </div>
        </div>

        <form class="manual" id="manual-form" autocomplete="off" data-no-busy>
            <label class="sr-only" for="manual-code" style="position:absolute;left:-9999px">Kode kartu</label>
            <input class="input" id="manual-code" type="text" placeholder="Ketik atau scan kode kartu (SIS-XXXXXXXX)" maxlength="100" autocapitalize="characters" spellcheck="false">
            <button class="btn btn-ghost" type="submit"><x-icon name="keyboard" /> Kirim</button>
        </form>
        <p class="hint" style="margin-top:6px">Cadangan jika kamera bermasalah. Alat scanner USB juga bisa dipakai: klik kolom ini lalu scan kartunya.</p>

        <div class="card card-pad" style="margin-top:18px">
            <h2 style="font-size:16px;font-weight:650;margin-bottom:6px">Riwayat sesi ini</h2>
            <p class="muted small" id="history-empty">Belum ada scan.</p>
            <ul class="history-list" id="history" hidden></ul>
        </div>
    </section>
</div>
@endsection

@section('scripts')
{{-- Pembaca QR: pakai salinan lokal bila ada (jaringan tanpa internet), kalau tidak ambil dari CDN. --}}
@if (file_exists(public_path('vendor/html5-qrcode.min.js')))
    <script src="{{ asset('vendor/html5-qrcode.min.js') }}" defer></script>
@else
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js" defer></script>
@endif
<script src="{{ asset('js/scan.js') }}" defer></script>
@endsection
