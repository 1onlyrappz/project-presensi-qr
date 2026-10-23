<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Presensi extends Model
{
    use HasFactory;

    protected $table = 'presensi';

    // Status dari scan QR (ada jam masuk).
    public const STATUS_HADIR = 'hadir';
    public const STATUS_TELAT = 'telat';

    // Status yang diisi manual / dari surat izin (tanpa jam masuk).
    public const STATUS_IZIN = 'izin';
    public const STATUS_SAKIT = 'sakit';
    public const STATUS_ALPA = 'alpa';

    protected $fillable = [
        'siswa_id',
        'tanggal',
        'jam_masuk',
        'jam_pulang',
        'status',
        'surat_izin_id',
        'catatan',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    // Surat izin yang menjadi asal status izin/sakit pada hari itu.
    public function suratIzin()
    {
        return $this->belongsTo(SuratIzin::class);
    }

    // Daftar status yang diisi manual (bukan hasil scan).
    public static function statusManual(): array
    {
        return [self::STATUS_IZIN, self::STATUS_SAKIT, self::STATUS_ALPA];
    }

    // Label status yang enak dibaca.
    public static function labelStatus(?string $status): string
    {
        return match ($status) {
            self::STATUS_HADIR => 'Tepat waktu',
            self::STATUS_TELAT => 'Telat',
            self::STATUS_IZIN => 'Izin',
            self::STATUS_SAKIT => 'Sakit',
            self::STATUS_ALPA => 'Alpa',
            default => 'Belum presensi',
        };
    }

    // Warna badge sesuai status.
    public static function badgeStatus(?string $status): string
    {
        return match ($status) {
            self::STATUS_HADIR => 'badge-ok',
            self::STATUS_TELAT => 'badge-warn',
            self::STATUS_IZIN => 'badge-info',
            self::STATUS_SAKIT => 'badge-info',
            self::STATUS_ALPA => 'badge-bad',
            default => 'badge-mute',
        };
    }

    // True kalau baris ini diisi manual (izin/sakit/alpa), bukan hasil scan.
    public function getManualAttribute(): bool
    {
        return in_array($this->status, self::statusManual(), true);
    }

    // Jam tanpa detik (07:15) untuk tampilan tabel.
    public function getJamMasukSingkatAttribute(): ?string
    {
        return $this->jam_masuk ? substr($this->jam_masuk, 0, 5) : null;
    }

    public function getJamPulangSingkatAttribute(): ?string
    {
        return $this->jam_pulang ? substr($this->jam_pulang, 0, 5) : null;
    }
}
