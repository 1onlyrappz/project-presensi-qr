@extends('layouts.panel')
@section('title', 'Aktivitas')
@section('subtitle', 'Jejak siapa yang melakukan apa. Berguna untuk tahu siapa yang scan di gerbang pada jam-jam tertentu.')

@section('content')
<form class="toolbar" method="GET" action="{{ route('aktivitas.index') }}" data-no-busy>
    <div class="field search">
        <label for="cari" class="muted small">Cari</label>
        <div class="search-wrap">
            <x-icon name="search" />
            <input class="input" type="search" id="cari" name="cari" placeholder="Nama orang atau keterangan" value="{{ request('cari') }}" maxlength="100">
        </div>
    </div>
    <div class="field fixed">
        <label for="jenis" class="muted small">Jenis</label>
        <select class="select" id="jenis" name="jenis" data-autosubmit>
            <option value="">Semua jenis</option>
            @foreach (\App\Models\Aktivitas::daftarJenis() as $nilai => $label)
                <option value="{{ $nilai }}" @selected(request('jenis') === $nilai)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-ghost" type="submit">Terapkan</button>
    @if (request('cari') || request('jenis'))
        <a href="{{ route('aktivitas.index') }}" class="btn btn-ghost">Reset</a>
    @endif
</form>

<div class="card">
    @if ($aktivitas->isEmpty())
        <div class="empty">
            <x-icon name="clock" />
            <h3>Belum ada aktivitas</h3>
            <p>Catatan muncul di sini setelah ada scan presensi atau perubahan data.</p>
        </div>
    @else
        <div class="table-wrap">
            <table class="table stack-sm">
                <thead>
                    <tr><th>Waktu</th><th>Jenis</th><th>Oleh</th><th>Keterangan</th><th>IP</th></tr>
                </thead>
                <tbody>
                @foreach ($aktivitas as $a)
                    <tr>
                        <td class="cell-main">
                            <b>{{ $a->created_at->translatedFormat('d M Y') }}</b>
                            <span class="small muted num">{{ $a->created_at->format('H:i:s') }}</span>
                        </td>
                        <td data-label="Jenis">
                            <span class="badge {{ \App\Models\Aktivitas::badge($a->jenis) }}">{{ \App\Models\Aktivitas::label($a->jenis) }}</span>
                        </td>
                        <td data-label="Oleh">{{ $a->user->name ?? 'Akun dihapus' }}</td>
                        <td data-label="Keterangan" class="small">{{ $a->keterangan }}</td>
                        <td data-label="IP" class="small muted num">{{ $a->ip ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $aktivitas->links('vendor.pagination.sekolah') }}
    @endif
</div>
@endsection
