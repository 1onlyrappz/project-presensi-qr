<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengajuan surat izin siswa.
 *
 * Surat yang berstatus "disetujui" otomatis mencatat hari izin/sakit di
 * tabel presensi, sehingga langsung masuk ke rekap bulanan untuk rapor.
 */
class SuratIzin extends Model
{
    use HasFactory;

    protected $table = 'surat_izin';

    public const STATUS_PENDING = 'pending';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';

    protected $fillable = [
        'siswa_id',
        'jenis_izin',
        'tanggal_mulai',
        'tanggal_selesai',
        'nomor_surat',
        'alasan',
        'lampiran',
        'status',
        'catatan',
        'diajukan_oleh',
        'diproses_oleh',
        'diproses_at',
    ];

    /**
     * Tanggal disalin ke objek Carbon supaya bisa diformat langsung di view.
     *
     * Hanya kolom waktu proses yang di-cast. Kolom tanggal (tanggal_mulai dan
     * tanggal_selesai) sengaja dibiarkan sebagai teks Y-m-d supaya pencarian
     * rentang bulan tetap presisi di semua jenis database.
     */
    protected $casts = [
        'diproses_at' => 'datetime',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function pengaju()
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function pemroses()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    /**
     * Daftar jenis izin beserta labelnya.
     */
    public static function daftarJenis(): array
    {
        return [
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            'cuti' => 'Cuti',
            'darurat' => 'Keadaan darurat',
        ];
    }

    public function getLabelJenisAttribute(): string
    {
        return self::daftarJenis()[$this->jenis_izin] ?? $this->jenis_izin;
    }

    public function getLabelStatusAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DISETUJUI => 'Disetujui',
            self::STATUS_DITOLAK => 'Ditolak',
            default => 'Menunggu persetujuan',
        };
    }

    public function getBadgeStatusAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DISETUJUI => 'badge-ok',
            self::STATUS_DITOLAK => 'badge-bad',
            default => 'badge-warn',
        };
    }

    /**
     * Status presensi yang dipakai untuk surat ini (sakit atau izin).
     */
    public function getStatusAbsenAttribute(): string
    {
        return $this->jenis_izin === 'sakit'
            ? Presensi::STATUS_SAKIT
            : Presensi::STATUS_IZIN;
    }

    /**
     * Jumlah hari sekolah yang tercakup surat ini.
     *
     * Hanya hari kerja (Senin-Jumat, diatur di config/presensi.php) yang
     * dihitung, karena hari libur bukan hari sekolah.
     */
    public function getJumlahHariAttribute(): int
    {
        $mulai = Carbon::parse($this->tanggal_mulai);
        $selesai = Carbon::parse($this->tanggal_selesai);
        $hariKerja = config('presensi.hari_kerja', [1, 2, 3, 4, 5]);
        $jumlah = 0;

        while ($mulai->lte($selesai)) {
            if (in_array((int) $mulai->dayOfWeek, $hariKerja, true)) {
                $jumlah++;
            }

            $mulai->addDay();
        }

        return $jumlah;
    }

    /**
     * Nama berkas lampiran (foto surat), kalau ada.
     */
    public function getLampiranUrlAttribute(): ?string
    {
        return $this->lampiran ? asset('storage/' . $this->lampiran) : null;
    }
}
