@extends('layouts.panel')
@section('title', 'Ganti kata sandi')
@section('subtitle', 'Gunakan minimal 8 karakter. Jangan pakai kata sandi bawaan.')

@section('content')
<div class="card card-pad" style="max-width:520px">
    <form action="{{ route('akun.update') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="field">
            <label for="password_lama">Kata sandi saat ini</label>
            <input class="input" type="password" id="password_lama" name="password_lama" required autofocus autocomplete="current-password"
                @if ($errors->has('password_lama')) aria-invalid="true" aria-describedby="password_lama-error" @endif>
            @error('password_lama')
                <p class="field-error" id="password_lama-error" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <div class="field">
            <label for="password">Kata sandi baru</label>
            <input class="input" type="password" id="password" name="password" required minlength="8" autocomplete="new-password"
                @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>
            @error('password')
                <p class="field-error" id="password-error" role="alert">{{ $message }}</p>
            @enderror
            <p class="hint">Minimal 8 karakter. Gunakan kombinasi huruf, angka, atau simbol.</p>
        </div>
        <div class="field">
            <label for="password_confirmation">Ulangi kata sandi baru</label>
            <input class="input" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn">Simpan kata sandi</button>
            <a href="{{ route('dashboard') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</div>
@endsection
