<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $produksi = app()->environment('production');

        // Akun admin. Kata sandi diambil dari ADMIN_PASSWORD di .env.
        // Jika kosong: di lokal dipakai "password", di production dibuat acak dan ditampilkan sekali.
        $email = env('ADMIN_EMAIL', 'admin@sekolah.test');
        $password = env('ADMIN_PASSWORD');

        if (! $password) {
            $password = $produksi ? Str::password(14, symbols: false) : 'password';
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Admin Sekolah', 'password' => Hash::make($password), 'role' => User::ROLE_ADMIN]
        );

        // Akun ini adalah administrator utama, apa pun yang terjadi pada kolom role.
        if ($admin->role !== User::ROLE_ADMIN) {
            $admin->forceFill(['role' => User::ROLE_ADMIN])->save();
        }

        if ($admin->wasRecentlyCreated) {
            $this->command?->info("Akun admin dibuat: {$email} / {$password}");
            $this->command?->warn('Segera ganti kata sandi lewat menu Akun setelah login.');
        }

        // Data contoh hanya di lingkungan non-production.
        if ($produksi) {
            return;
        }

        $namaDepan = ['Andi', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fitri', 'Gita', 'Hadi', 'Intan'];

        foreach (['X TJKT 2', 'XI TJKT 2', 'XII TJKT 2'] as $indeks => $nama) {
            $kelas = Kelas::firstOrCreate(['nama_kelas' => $nama]);

            for ($i = 1; $i <= 3; $i++) {
                Siswa::firstOrCreate(
                    ['nis' => sprintf('2026%02d%03d', $indeks + 1, $i)],
                    [
                        'nama' => $namaDepan[$indeks * 3 + $i - 1] . ' Contoh',
                        'kelas_id' => $kelas->id,
                        'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                    ]
                );
            }
        }

        // -----------------------------------------------------------------
        // Akun contoh untuk mencoba peran baru (hanya non-production).
        // Semua memakai kata sandi "password".
        // -----------------------------------------------------------------
        $sandiDemo = Hash::make('password');

        // Guru piket contoh (hanya non-production).
        $guruDemo = User::firstOrCreate(
            ['email' => 'guru@sekolah.test'],
            ['name' => 'Guru Piket Demo', 'password' => $sandiDemo, 'role' => User::ROLE_GURU_PIKET]
        );

        $guruDemo->forceFill([
            'role' => User::ROLE_GURU_PIKET,
            'kelas_id' => null,
            'siswa_id' => null,
        ])->save();

        $this->command?->info('Akun guru piket: guru@sekolah.test / password');

        $kelasX = Kelas::where('nama_kelas', 'X TJKT 2')->first();

        // Wali kelas X TJKT 2
        if ($kelasX) {
            $wali = User::firstOrCreate(
                ['email' => 'walikelas@sekolah.test'],
                ['name' => 'Sari Wali Kelas', 'password' => $sandiDemo, 'role' => User::ROLE_WALI_KELAS]
            );

            $wali->forceFill([
                'role' => User::ROLE_WALI_KELAS,
                'kelas_id' => $kelasX->id,
                'siswa_id' => null,
            ])->save();

            $kelasX->update(['wali_kelas' => $wali->name]);

            $this->command?->info('Akun wali kelas: walikelas@sekolah.test / password (X TJKT 2)');
        }

        $andi = Siswa::where('nis', '202601001')->first();

        if ($andi) {
            // Akun siswa untuk Andi Contoh
            $akunSiswa = User::firstOrCreate(
                ['email' => 'siswa@sekolah.test'],
                ['name' => $andi->nama, 'password' => $sandiDemo, 'role' => User::ROLE_SISWA]
            );

            $akunSiswa->forceFill([
                'role' => User::ROLE_SISWA,
                'siswa_id' => $andi->id,
                'kelas_id' => null,
            ])->save();

            $this->command?->info('Akun siswa: siswa@sekolah.test / password (' . $andi->nama . ')');

            // Akun orang tua/wali untuk Andi Contoh
            $orangTua = User::firstOrCreate(
                ['email' => 'orangtua@sekolah.test'],
                ['name' => 'Bapak/Ibu ' . $andi->nama, 'password' => $sandiDemo, 'role' => User::ROLE_ORANG_TUA]
            );

            $orangTua->forceFill([
                'role' => User::ROLE_ORANG_TUA,
                'kelas_id' => null,
                'siswa_id' => null,
            ])->save();

            $orangTua->anak()->sync([$andi->id]);

            $this->command?->info('Akun orang tua: orangtua@sekolah.test / password (' . $andi->nama . ')');
        }
    }
}
