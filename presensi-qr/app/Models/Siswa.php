<?php

namespace App\Models;

use App\Support\QrSvg;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Siswa extends Model
{
    use HasFactory;

    protected $table = 'siswa';

    protected $fillable = [
        'nis',
        'nama',
        'kelas_id',
        'jenis_kelamin',
        'kode_qr',
        'foto',
    ];

    protected static function boot()
    {
        parent::boot();

        // Kode QR unik dibuat otomatis saat siswa baru dibuat.
        static::creating(function ($siswa) {
            if (empty($siswa->kode_qr)) {
                $siswa->kode_qr = self::buatKodeQr();
            }
        });
    }

    // Membuat kode QR acak yang belum dipakai siswa lain.
    public static function buatKodeQr(): string
    {
        do {
            $kode = 'SIS-' . strtoupper(Str::random(8));
        } while (self::where('kode_qr', $kode)->exists());

        return $kode;
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    public function presensi()
    {
        return $this->hasMany(Presensi::class);
    }

    /**
     * Akun orang tua/wali yang terhubung ke siswa ini.
     */
    public function orangTua()
    {
        return $this->belongsToMany(User::class, 'orang_tua_siswa')
            ->withTimestamps();
    }

    /**
     * Akun login milik siswa ini (kalau sudah dibuatkan).
     */
    public function akun()
    {
        return $this->hasOne(User::class);
    }

    // Pencarian nama / NIS. Karakter wildcard LIKE (% dan _) di-escape.
    public function scopeCari($query, ?string $kata)
    {
        if ($kata === null || $kata === '') {
            return $query;
        }

        $kata = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $kata);

        return $query->where(function ($q) use ($kata) {
            $q->where('nama', 'like', '%' . $kata . '%')
              ->orWhere('nis', 'like', '%' . $kata . '%');
        });
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }

    // Huruf awal nama untuk avatar ketika belum ada foto.
    public function getInisialAttribute(): string
    {
        $kata = preg_split('/\s+/', trim((string) $this->nama)) ?: [];
        $hasil = '';

        foreach (array_slice($kata, 0, 2) as $k) {
            $hasil .= mb_strtoupper(mb_substr($k, 0, 1));
        }

        return $hasil !== '' ? $hasil : '?';
    }

    public function getQrSvgAttribute(): string
    {
        return QrSvg::make($this->kode_qr);
    }
}
