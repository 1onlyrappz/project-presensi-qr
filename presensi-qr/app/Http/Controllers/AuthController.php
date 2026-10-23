<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login/logout untuk semua peran. Sengaja TIDAK ada pendaftaran publik:
 * akun hanya bisa dibuat oleh administrator.
 */
class AuthController extends Controller
{
    /** Percobaan per kombinasi email + IP dalam satu menit. */
    private const BATAS_EMAIL = 5;

    /** Percobaan per alamat IP dalam satu menit (melindungi dari sebaran email). */
    private const BATAS_IP = 60;

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Samakan huruf besar/kecil dan spasi agar akun tetap bisa masuk.
        $credentials['email'] = Str::lower(trim($credentials['email']));
        $ip = (string) $request->ip();

        $kunciEmail = 'login-email:' . $credentials['email'] . '|' . $ip;
        $kunciIp = 'login-ip:' . $ip;

        // Kunci sementara bila salah kata sandi terlalu sering.
        $tertunda = 0;

        foreach ([[$kunciEmail, self::BATAS_EMAIL], [$kunciIp, self::BATAS_IP]] as [$kunci, $batas]) {
            if (RateLimiter::tooManyAttempts($kunci, $batas)) {
                $tertunda = max($tertunda, RateLimiter::availableIn($kunci));
            }
        }

        if ($tertunda > 0) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan masuk. Demi keamanan, coba lagi dalam ' . $this->durasi($tertunda) . '.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($kunciEmail, 60);
            RateLimiter::hit($kunciIp, 60);

            // Pesan sengaja tidak menyebut apakah email terdaftar atau tidak.
            $pesan = 'Email atau kata sandi salah. Periksa kembali.';
            $sisa = max(0, self::BATAS_EMAIL - RateLimiter::attempts($kunciEmail));

            if ($sisa > 0 && $sisa <= 2) {
                $pesan .= " Sisa {$sisa} percobaan sebelum dibatasi sementara.";
            }

            throw ValidationException::withMessages([
                'email' => $pesan,
            ]);
        }

        RateLimiter::clear($kunciEmail);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Ubah jumlah detik menjadi kalimat yang mudah dibaca.
     */
    private function durasi(int $detik): string
    {
        $detik = max(1, $detik);
        $menit = intdiv($detik, 60);
        $sisa = $detik % 60;

        if ($menit === 0) {
            return "{$detik} detik";
        }

        return $sisa > 0 ? "{$menit} menit {$sisa} detik" : "{$menit} menit";
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
