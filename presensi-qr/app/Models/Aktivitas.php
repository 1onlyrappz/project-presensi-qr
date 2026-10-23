<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Jejak aktivitas: siapa melakukan apa, dan kapan.
 *
 * Dipakai untuk melihat siapa yang melakukan scan di gerbang, tanpa harus
 * menebak dari data presensi saja.
 */
class Aktivitas extends Model
{
    use HasFactory;

    protected $table = 'aktivitas';

    protected $fillable = [
        'user_id',
        'jenis',
        'keterangan',
        'ip',
    ];

    /**
     * Label yang enak dibaca untuk tiap jenis aktivitas.
     */
    public static function label(string $jenis): string
    {
        return self::daftarJenis()[$jenis] ?? $jenis;
    }

    /**
     * Daftar jenis aktivitas yang dipakai di filter.
     *
     * @return array<string, string>
     */
    public static function daftarJenis(): array
    {
        return [
            'scan_masuk' => 'Scan masuk',
            'scan_pulang' => 'Scan pulang',
            'scan_gagal' => 'Scan ditolak',
            'pengguna_buat' => 'Akun dibuat',
            'pengguna_ubah' => 'Akun diubah',
            'pengguna_hapus' => 'Akun dihapus',
            'pengguna_sandi' => 'Kata sandi direset',
            'kelas_buat' => 'Kelas ditambah',
            'kelas_ubah' => 'Kelas diubah',
            'kelas_hapus' => 'Kelas dihapus',
            'siswa_buat' => 'Siswa ditambah',
            'siswa_ubah' => 'Siswa diubah',
            'siswa_hapus' => 'Siswa dihapus',
            'siswa_import' => 'Siswa diimpor',
            'presensi_ubah' => 'Status kehadiran diubah',
            'izin_buat' => 'Surat izin diajukan',
            'izin_setuju' => 'Surat izin disetujui',
            'izin_tolak' => 'Surat izin ditolak',
            'izin_hapus' => 'Surat izin dihapus',
        ];
    }

    /**
     * Kelas badge CSS sesuai jenis aktivitas.
     */
    public static function badge(string $jenis): string
    {
        return match ($jenis) {
            'scan_masuk' => 'badge-ok',
            'scan_pulang' => 'badge-info',
            'scan_gagal' => 'badge-bad',
            'pengguna_buat', 'siswa_buat', 'kelas_buat' => 'badge-ok',
            'pengguna_ubah', 'siswa_ubah', 'kelas_ubah' => 'badge-warn',
            'pengguna_hapus', 'siswa_hapus', 'kelas_hapus' => 'badge-bad',
            'pengguna_sandi' => 'badge-mute',
            'siswa_import' => 'badge-info',
            'presensi_ubah' => 'badge-warn',
            'izin_buat' => 'badge-info',
            'izin_setuju' => 'badge-ok',
            'izin_tolak' => 'badge-bad',
            'izin_hapus' => 'badge-bad',
            default => 'badge-mute',
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
