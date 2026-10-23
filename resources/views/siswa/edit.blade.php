@extends('layouts.panel')
@section('title', 'Edit siswa')
@section('subtitle', $siswa->nama)

@section('actions')
    <a href="{{ route('siswa.kartu', $siswa->id) }}" target="_blank" rel="noopener" class="btn btn-soft"><x-icon name="idcard" /> Cetak kartu</a>
@endsection

@section('content')
<div class="card card-pad" style="max-width:760px">
    @include('siswa._form', ['siswa' => $siswa, 'action' => route('siswa.update', $siswa->id), 'method' => 'PUT'])
</div>

<div class="card card-pad" style="max-width:760px;margin-top:18px">
    <h2 style="font-size:16.5px;font-weight:650">Kode QR</h2>
    <p class="muted" style="margin:4px 0 12px">Kode ini tercetak di kartu pelajar dan tidak bisa diedit. Jika kartu hilang, buat kode baru supaya kartu lama tidak bisa dipakai untuk presensi.</p>
    <p><code class="kode">{{ $siswa->kode_qr }}</code></p>
    <form action="{{ route('siswa.qr-baru', $siswa->id) }}" method="POST" style="margin-top:14px" data-confirm="Buat kode QR baru untuk {{ $siswa->nama }}? Kartu yang lama langsung tidak berlaku.">
        @csrf
        <button type="submit" class="btn btn-danger-ghost"><x-icon name="refresh" /> Buat ulang kode QR</button>
    </form>
</div>
@endsection
