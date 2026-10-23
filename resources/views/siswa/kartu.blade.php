<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $massal ? 'Kartu pelajar ' . ($daftar->first()->kelas->nama_kelas ?? '') : 'Kartu pelajar - ' . $daftar->first()->nama }}</title>
    <link rel="stylesheet" href="{{ asset('css/kartu.css') }}">
    <style>
        @page { size: {{ $massal ? 'A4' : '85.6mm 54mm' }}; margin: {{ $massal ? '8mm' : '0' }}; }
    </style>
</head>
<body class="{{ $massal ? 'massal' : 'tunggal' }}">

<div class="toolbar-print no-print">
    <div>
        <strong>{{ $massal ? $daftar->count() . ' kartu' : 'Kartu pelajar' }}</strong>
        <div class="tip">
            Saat mencetak: skala 100%, margin "Tidak ada" (atau Default untuk A4), dan aktifkan "Grafik latar belakang".
        </div>
    </div>
    <span class="grow"></span>
    <button type="button" class="btn-print" onclick="window.print()">Cetak</button>
    <button type="button" class="btn-close" onclick="window.close()">Tutup</button>
</div>

<div class="sheet">
    @foreach ($daftar as $s)
        <article class="kartu">
            <header class="k-head">
                <span class="k-brand">
                    <span class="k-logo"><img src="{{ asset('img/logo-sekolah.png') }}" alt=""></span>
                    <span class="k-school">{{ config('presensi.nama_sekolah') }}</span>
                </span>
                <span class="k-title">KARTU PELAJAR</span>
            </header>
            <div class="k-body">
                <div class="k-foto">
                    <span>{{ $s->inisial }}</span>
                    @if ($s->foto_url)
                        <img src="{{ $s->foto_url }}" alt="" onerror="this.remove()">
                    @endif
                </div>
                <div class="k-info">
                    <b>{{ $s->nama }}</b>
                    <div>NIS {{ $s->nis }}</div>
                    <div>Kelas {{ $s->kelas->nama_kelas ?? '-' }}</div>
                </div>
                <div class="k-qr">
                    {!! $s->qr_svg !!}
                </div>
            </div>
            <footer class="k-foot">Bawa kartu ini setiap hari untuk presensi di gerbang</footer>
        </article>
    @endforeach
</div>

</body>
</html>
