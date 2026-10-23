@extends('layouts.panel')
@section('title', 'Tambah siswa')
@section('subtitle', 'Isi data siswa. Kartu pelajar bisa dicetak setelah disimpan.')

@section('content')
@if ($kelas->isEmpty())
    <div class="card">
        <div class="empty">
            <x-icon name="school" />
            <h3>Buat kelas dulu</h3>
            <p>Siswa harus masuk ke sebuah kelas. Tambahkan minimal satu kelas sebelum menambah siswa.</p>
            <a href="{{ route('kelas.index') }}" class="btn">Ke data kelas</a>
        </div>
    </div>
@else
    <div class="card card-pad" style="max-width:760px">
        @include('siswa._form', ['siswa' => null, 'action' => route('siswa.store'), 'method' => 'POST'])
    </div>
@endif
@endsection
