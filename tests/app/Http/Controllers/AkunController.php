<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AkunController extends Controller
{
    public function edit()
    {
        return view('akun.edit');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            // Batalkan token "ingat saya" di perangkat lain.
            'remember_token' => Str::random(60),
        ])->save();

        // Ganti ID sesi setelah kata sandi berubah.
        $request->session()->regenerate();
        $request->user()->catatAktivitas('akun_sandi', 'Kata sandi akun diganti sendiri', $request->ip());

        return redirect()->route('akun.edit')->with('success', 'Kata sandi berhasil diganti. Gunakan kata sandi baru pada login berikutnya.');
    }
}
