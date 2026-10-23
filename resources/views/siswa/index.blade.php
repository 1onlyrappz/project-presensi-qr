@extends('layouts.panel')
@section('title', 'Data siswa')
@php
    $waliKelas = auth()->user()->isWaliKelas();
    $kelasSendiri = $waliKelas ? auth()->user()->kelas_id : null;
    $kelasCetak = request('kelas_id', $kelasSendiri);
@endphp
@section('subtitle', $waliKelas ? 'Daftar siswa di kelas yang Anda ampu. Kartu pelajar bisa dicetak untuk kelas ini.' : 'Kelola siswa dan cetak kartu pelajar berisi kode QR.')

@section('actions')
    @if ($waliKelas)
        @if ($kelasCetak)
            <a href="{{ route('siswa.kartu-kelas', ['kelas_id' => $kelasCetak]) }}" target="_blank" rel="noopener" class="btn btn-ghost"><x-icon name="printer" /> Cetak kartu kelas</a>
        @endif
    @elseif (auth()->user()->isAdmin())
        @if (request('kelas_id'))
            <a href="{{ route('siswa.kartu-kelas', ['kelas_id' => request('kelas_id')]) }}" target="_blank" rel="noopener" class="btn btn-ghost"><x-icon name="printer" /> Cetak kartu kelas ini</a>
        @endif
        <a href="{{ route('siswa.import') }}" class="btn btn-soft"><x-icon name="upload" /> Import Excel</a>
        <a href="{{ route('siswa.create') }}" class="btn"><x-icon name="plus" /> Tambah siswa</a>
    @endif
@endsection

@section('content')
@if (session('siswa_baru'))
    <div class="alert alert-info" role="status">
        <x-icon name="idcard" />
        <div class="grow">Kartu pelajar siswa baru sudah siap dicetak.</div>
        <a class="btn btn-sm" href="{{ route('siswa.kartu', session('siswa_baru')) }}" target="_blank" rel="noopener">Cetak kartu</a>
    </div>
@endif

<form class="toolbar" method="GET" action="{{ route('siswa.index') }}" data-no-busy>
    <div class="field search">
        <label for="cari" class="muted small">Cari siswa</label>
        <div class="search-wrap">
            <x-icon name="search" />
            <input class="input" type="search" id="cari" name="cari" placeholder="Nama atau NIS" value="{{ request('cari') }}" maxlength="100">
        </div>
    </div>
    <div class="field fixed">
        <label for="kelas_id" class="muted small">Kelas</label>
        @if ($waliKelas)
            <input class="input" type="text" value="{{ $kelas->first()->nama_kelas ?? 'Belum ditautkan ke kelas' }}" disabled>
        @else
            <select class="select" id="kelas_id" name="kelas_id" data-autosubmit>
                <option value="">Semua kelas</option>
                @foreach ($kelas as $k)
                    <option value="{{ $k->id }}" @selected(request('kelas_id') == $k->id)>{{ $k->nama_kelas }}</option>
                @endforeach
            </select>
        @endif
    </div>
    <button class="btn btn-ghost" type="submit">Terapkan</button>
    @if (request('cari') || request('kelas_id'))
        <a href="{{ route('siswa.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="card">
    @if ($siswa->isEmpty())
        <div class="empty">
            <x-icon name="student" />
            @if (request('cari') || request('kelas_id'))
                <h3>Siswa tidak ditemukan</h3>
                <p>Coba kata kunci lain atau tampilkan semua kelas.</p>
                <a href="{{ route('siswa.index') }}" class="btn btn-soft">Tampilkan semua siswa</a>
            @else
                @if ($waliKelas)
                    <h3>Belum ada siswa di kelas ini</h3>
                    <p>Data siswa untuk kelas yang Anda ampu belum tersedia. Hubungi Administrator bila ini keliru.</p>
                @else
                    <h3>Belum ada siswa</h3>
                    <p>Tambahkan siswa satu per satu, atau impor sekaligus dari berkas Excel. Kode QR dibuat otomatis dan kartunya langsung bisa dicetak.</p>
                @endif
                @if (auth()->user()->isAdmin())
                    <span class="row-actions" style="justify-content:center">
                        <a href="{{ route('siswa.import') }}" class="btn btn-soft"><x-icon name="upload" /> Import Excel</a>
                        <a href="{{ route('siswa.create') }}" class="btn"><x-icon name="plus" /> Tambah siswa</a>
                    </span>
                @endif
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table class="table stack-sm">
                <thead>
                    <tr>
                        <th>Siswa</th><th>Kelas</th><th>Kode QR</th>
                        @if (auth()->user()->isAdmin() || $waliKelas)
                            <th class="right">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @foreach ($siswa as $s)
                    <tr>
                        <td class="cell-main">
                            <div class="person">
                                <span class="avatar">
                                    {{ $s->inisial }}
                                    @if ($s->foto_url)
                                        <img src="{{ $s->foto_url }}" alt="" loading="lazy" onerror="this.remove()">
                                    @endif
                                </span>
                                <div>
                                    <b>{{ $s->nama }}</b>
                                    <span class="small muted num">NIS {{ $s->nis }}</span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Kelas">{{ $s->kelas->nama_kelas ?? '-' }}</td>
                        <td data-label="Kode QR"><code class="kode">{{ $s->kode_qr }}</code></td>
                        @if (auth()->user()->isAdmin() || $waliKelas)
                            <td class="actions-cell">
                                <span class="row-actions">
                                    <a href="{{ route('siswa.kartu', $s->id) }}" target="_blank" rel="noopener" class="btn btn-soft btn-sm" title="Cetak kartu pelajar">
                                        <x-icon name="idcard" /> Kartu
                                    </a>
                                    @if (auth()->user()->isAdmin())
                                        <a href="{{ route('siswa.edit', $s->id) }}" class="btn btn-ghost btn-sm btn-icon" aria-label="Edit {{ $s->nama }}"><x-icon name="pencil" /></a>
                                        <form action="{{ route('siswa.destroy', $s->id) }}" method="POST" data-confirm="Hapus {{ $s->nama }}? Riwayat presensinya ikut terhapus dan tidak bisa dikembalikan.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger-ghost btn-sm btn-icon" aria-label="Hapus {{ $s->nama }}"><x-icon name="trash" /></button>
                                        </form>
                                    @endif
                                </span>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $siswa->links('vendor.pagination.sekolah') }}
    @endif
</div>
@endsection
