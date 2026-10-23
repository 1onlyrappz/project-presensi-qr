<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_GURU_PIKET = 'guru_piket';

    public const ROLE_WALI_KELAS = 'wali_kelas';

    public const ROLE_ORANG_TUA = 'orang_tua';

    public const ROLE_SISWA = 'siswa';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'kelas_id',
        'siswa_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Daftar peran yang boleh dipakai beserta labelnya.
     *
     * @return array<string, string>
     */
    public static function daftarRole(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_GURU_PIKET => 'Guru Piket',
            self::ROLE_WALI_KELAS => 'Wali Kelas',
            self::ROLE_ORANG_TUA => 'Orang Tua/Wali',
            self::ROLE_SISWA => 'Siswa',
        ];
    }

    /**
     * Peran yang boleh membuka laporan, rekap, dan data siswa.
     *
     * @return list<string>
     */
    public static function peranStaf(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_GURU_PIKET, self::ROLE_WALI_KELAS];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isGuruPiket(): bool
    {
        return $this->role === self::ROLE_GURU_PIKET;
    }

    public function isWaliKelas(): bool
    {
        return $this->role === self::ROLE_WALI_KELAS;
    }

    public function isOrangTua(): bool
    {
        return $this->role === self::ROLE_ORANG_TUA;
    }

    public function isSiswa(): bool
    {
        return $this->role === self::ROLE_SISWA;
    }

    /**
     * True untuk admin, guru piket, dan wali kelas (petugas sekolah).
     */
    public function isStaf(): bool
    {
        return in_array($this->role, self::peranStaf(), true);
    }

    /**
     * Label peran yang enak dibaca, mis. "Wali Kelas".
     */
    public function roleLabel(): string
    {
        return self::daftarRole()[$this->role] ?? 'Peran tidak dikenal';
    }

    /**
     * Kelas yang menjadi tanggung jawab wali kelas.
     *
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * Data siswa untuk akun dengan peran "siswa".
     *
     * @return BelongsTo<Siswa, $this>
     */
    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    /**
     * Anak-anak dari akun orang tua (bisa lebih dari satu).
     *
     * @return BelongsToMany<Siswa, $this>
     */
    public function anak(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'orang_tua_siswa')
            ->withTimestamps();
    }

    /**
     * ID siswa yang boleh dilihat akun ini.
     *
     * @return list<int>
     */
    public function idSiswaTerhubung(): array
    {
        if ($this->isSiswa()) {
            return $this->siswa_id ? [(int) $this->siswa_id] : [];
        }

        if ($this->isOrangTua()) {
            return $this->anak()->pluck('siswa.id')->map(fn ($id) => (int) $id)->all();
        }

        return [];
    }

    /**
     * Apakah akun ini boleh melihat data siswa tersebut.
     */
    public function bisaLihatSiswa(Siswa $siswa): bool
    {
        if ($this->isAdmin() || $this->isGuruPiket()) {
            return true;
        }

        if ($this->isWaliKelas()) {
            return $this->kelas_id !== null && (int) $siswa->kelas_id === (int) $this->kelas_id;
        }

        return in_array((int) $siswa->id, $this->idSiswaTerhubung(), true);
    }

    /**
     * Apakah akun ini boleh melihat data satu kelas.
     */
    public function bisaLihatKelas(?int $kelasId): bool
    {
        if ($this->isAdmin() || $this->isGuruPiket()) {
            return true;
        }

        if ($this->isWaliKelas()) {
            return $this->kelas_id !== null && (int) $kelasId === (int) $this->kelas_id;
        }

        return false;
    }

    /**
     * Apakah akun ini boleh menyetujui surat izin siswa tersebut.
     */
    public function bisaProsesIzin(Siswa $siswa): bool
    {
        return $this->isAdmin()
            || ($this->isWaliKelas() && $this->bisaLihatSiswa($siswa));
    }

    /**
     * Query siswa yang boleh dilihat akun ini.
     */
    public function querySiswa(): \Illuminate\Database\Eloquent\Builder
    {
        $q = Siswa::query();

        if ($this->isWaliKelas()) {
            // Kelas kosong -> tidak ada siswa sama sekali (bukan semua siswa).
            $q->where('kelas_id', $this->kelas_id ?? 0);
        } elseif ($this->isOrangTua() || $this->isSiswa()) {
            $q->whereIn('id', $this->idSiswaTerhubung());
        }

        return $q;
    }

    /**
     * @return HasMany<Aktivitas, $this>
     */
    public function aktivitas(): HasMany
    {
        return $this->hasMany(Aktivitas::class);
    }

    /**
     * Catat satu baris ke jejak aktivitas.
     */
    public function catatAktivitas(string $jenis, string $keterangan, ?string $ip = null): void
    {
        Aktivitas::create([
            'user_id' => $this->id,
            'jenis' => $jenis,
            'keterangan' => mb_substr($keterangan, 0, 255),
            'ip' => $ip,
        ]);
    }
}
