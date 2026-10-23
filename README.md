# Presensi QR — SMK Nu Ungaran

Aplikasi Laravel untuk mencatat kehadiran siswa memakai kartu pelajar ber-QR. Scan pertama pada hari itu dicatat **masuk** (atau **telat**), scan berikutnya setelah jeda dicatat **pulang**. Tersedia lima peran: **Administrator**, **Guru Piket**, **Wali Kelas**, **Orang Tua/Wali**, dan **Siswa**.

> Paket ini adalah **proyek Laravel 12 lengkap**. Folder `vendor/` tidak disertakan agar ZIP kecil; jalankan `composer install` sekali di komputer/hosting. Tidak perlu Node.js/npm — CSS dan JavaScript sudah ada di `public/`.

---

## 1. Kebutuhan

| Kebutuhan | Keterangan |
|---|---|
| PHP **8.2+** | Cek dengan `php -v` |
| Ekstensi PHP | `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `xml`, `ctype`, `json`, `tokenizer`, `iconv`, `curl` (semua aktif di XAMPP/Laragon terbaru) |
| Composer | https://getcomposer.org — Laragon sudah menyertakan |
| MySQL / MariaDB | XAMPP, Laragon, atau hosting |
| Browser modern | Chrome, Edge, Firefox, atau Safari 15.4+ |

Kamera hanya bisa dipakai di **HTTPS** atau `http://localhost`. Untuk scan dari HP/laptop/tablet/PC lain di jaringan sekolah atau internet, pasang HTTPS. Lihat **`ssl/README-SSL.md`** (lokal) atau **`PANDUAN-HOSTING.md`** (hosting cPanel/VPS). Sebelum HTTPS aktif, hanya komputer yang menjalankan `php artisan serve` di `http://localhost:8000` yang bisa memakai kamera.

---

## 2. Pemasangan cepat di Windows (XAMPP/Laragon)

1. Nyalakan **MySQL**.
2. Ekstrak ZIP, lalu klik dua kali **`setup.bat`**.
3. Setelah selesai, jalankan:
   ```bat
   php artisan serve
   ```
4. Buka **http://localhost:8000** dan login dengan akun contoh di bawah.

`setup.bat` akan menjalankan `composer install`, membuat `.env` bila belum ada, mengisi pengaturan sekolah, membuat database `presensi_qr` (bila MySQL dapat dihubungi), menjalankan migrasi + seeder, dan membuat tautan penyimpanan.

> Jika MySQL memakai sandi, jalankan lewat PowerShell:
> `powershell -ExecutionPolicy Bypass -File .\setup.ps1 -DbPassword "sandi_mysql"`

### Scan dari ponsel/tablet di jaringan lokal

Kamera browser membutuhkan HTTPS. Setelah `setup.bat` selesai:

1. Klik dua kali **`mulai-https.bat`**. Tunggu sampai muncul alamat `https://IP-LAN:8443`.
2. Di ponsel/tablet, pasang **`ssl/caddy-root-ca.crt`** sekali (langkah lengkap di `ssl/README-SSL.md`).
3. Buka alamat `https://IP-LAN:8443` dari perangkat yang tersambung ke Wi-Fi yang sama.
4. Login dan buka **Scan presensi**.

Skrip mengunduh Caddy resmi sekali (butuh internet), membuat sertifikat lokal, dan menjalankan HTTPS. Untuk hosting produksi, gunakan cPanel AutoSSL/Let's Encrypt seperti di `PANDUAN-HOSTING.md`.

---

## 3. Pemasangan manual

Buka terminal di folder proyek (folder yang berisi `artisan`).

```bash
composer install
copy .env.example .env          # Windows
# cp .env.example .env          # Linux/macOS

php artisan key:generate
```

Buat database MySQL bernama **`presensi_qr`** (collation `utf8mb4_unicode_ci`), lalu sesuaikan `.env`:

```ini
APP_NAME="Presensi QR"
APP_URL=http://localhost:8000
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=presensi_qr
DB_USERNAME=root
DB_PASSWORD=

PRESENSI_NAMA_SEKOLAH="SMK Nu Ungaran"
PRESENSI_BATAS_TELAT=07:30
PRESENSI_TIMEZONE=Asia/Jakarta
PRESENSI_HARI_KERJA=1,2,3,4,5
```

Jalankan:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

`storage:link` di Windows kadang perlu terminal **Run as Administrator**. Tanpa itu foto siswa tidak tampil (aplikasi tetap berjalan memakai inisial).

### XAMPP tanpa `php artisan serve`

Letakkan folder di `C:\xampp\htdocs\presensi-qr`, lalu buka:
`http://localhost/presensi-qr/public`.

### Laragon

Letakkan di `C:\laragon\www\presensi-qr`. Laragon dapat membuat alamat `.test`, tetapi kamera diblokir di alamat non-HTTPS selain `localhost`. Untuk scan dari jaringan lain di Laragon, pakai HTTPS lokal (`ssl/README-SSL.md`) atau aktifkan SSL di Laragon.

---

## 4. Akun contoh (hanya lingkungan lokal)

Dibuat oleh seeder saat `APP_ENV` **bukan** `production`. Semua memakai kata sandi **`password`**.

| Peran | Email | Tautan |
|---|---|---|
| Administrator | `admin@sekolah.test` | Semua data |
| Guru Piket | `guru@sekolah.test` | Petugas scan/laporan |
| Wali Kelas | `walikelas@sekolah.test` | X TJKT 2 |
| Orang Tua/Wali | `orangtua@sekolah.test` | Andi Contoh |
| Siswa | `siswa@sekolah.test` | Andi Contoh |

Data contoh berisi 3 kelas (**X TJKT 2, XI TJKT 2, XII TJKT 2**) dan 9 siswa. Di server produksi akun contoh **tidak dibuat**; Administrator membuat akun asli lewat menu **Pengguna**. Ganti kata sandi akun contoh sebelum dipakai sungguhan.

---

## 5. Hak akses lima peran

| Fitur | Administrator | Guru Piket | Wali Kelas | Orang Tua | Siswa |
|---|---|---|---|---|---|
| Dashboard | Sekolah | Sekolah | Kelas sendiri | Anak sendiri | Diri sendiri |
| Scan presensi | ✅ | ✅ | — | — | — |
| Laporan harian | ✅ | ✅ | Kelas sendiri (lihat) | — | — |
| Tandai izin/sakit/alpa | ✅ | ✅ | — | — | — |
| Rekap bulanan + CSV | ✅ | ✅ | Kelas sendiri | — | — |
| Atur hari efektif | ✅ | — | — | — | — |
| Lihat data siswa | ✅ | ✅ | Kelas sendiri | — | — |
| Tambah/ubah/hapus siswa | ✅ | — | — | — | — |
| Impor CSV | ✅ | — | — | — | — |
| Cetak kartu QR | ✅ | — | Kelas sendiri | — | — |
| Riwayat kehadiran | Semua | Semua | Kelas sendiri | Anak sendiri | Diri sendiri |
| Ajukan surat izin | ✅ | ✅ | ✅ | ✅ (anaknya) | ✅ (dirinya) |
| Setujui/tolak surat izin | ✅ | — | ✅ (kelasnya) | — | — |
| Hapus surat izin | ✅ | — | — | — | — |
| Data kelas | ✅ | — | — | — | — |
| Kelola pengguna | ✅ | — | — | — | — |
| Riwayat aktivitas | ✅ | — | — | — | — |
| Ganti kata sandi sendiri | ✅ | ✅ | ✅ | ✅ | ✅ |

Menu yang tidak menjadi hak suatu peran tidak ditampilkan; URL yang dibuka langsung tetap ditolak **403**.

Aturan penautan akun:

- Satu kelas hanya boleh punya **satu wali kelas**; nama wali kelas otomatis disalin ke data kelas.
- Satu data siswa hanya boleh punya **satu akun siswa**.
- Akun orang tua wajib terhubung ke **minimal satu siswa** (boleh kakak-adik).
- Administrator tidak bisa menurunkan/menghapus perannya sendiri, dan admin terakhir tidak bisa dihapus.

---

## 6. Pemakaian harian

1. **Data kelas** — sesuaikan kelas bila perlu.
2. **Data siswa** — tambah manual atau **Import Excel** (CSV). Kode QR dibuat otomatis.
3. **Cetak kartu** — satu siswa atau satu kelas (A4, skala 100%, grafik latar belakang aktif; ukuran CR80 85,6 × 54 mm).
4. **Scan presensi** — buka menu *Scan presensi*, izinkan kamera, tempelkan kartu. Bisa juga memakai kolom ketik/scan untuk scanner QR USB.
5. **Laporan** — filter tanggal/kelas/status, tandai izin/sakit/alpa, atau unduh CSV (pemisah `;` untuk Excel Indonesia).
6. **Rekap bulanan** — jumlah hadir, telat, izin, sakit, alpa, dan persentase untuk rapor. Administrator dapat mengoreksi jumlah hari efektif per bulan.
7. **Surat izin** — siswa/orang tua/wali kelas mengajukan; Administrator atau wali kelas terkait menyetujui. Hari sekolah yang disetujui otomatis dicatat, dan scan QR di hari yang sama tetap menang.
8. **Riwayat** — siswa dan orang tua melihat kehadiran sendiri/anaknya.

### Kartu hilang

**Data siswa → Edit → Buat ulang kode QR**. Kartu lama langsung tidak berlaku; cetak kartu baru.

### Kamera tidak menyala

| Cara akses | Kamera |
|---|---|
| `http://localhost:8000` di komputer yang sama | Berfungsi |
| `https://domain-sekolah` atau `https://IP-LAN:8443` (lokal) | Berfungsi |
| `http://192.168.x.x:8000` dari perangkat lain | Diblokir browser |
| Alamat `.test` tanpa HTTPS | Diblokir browser |

Solusi: pakai HTTPS. Untuk jaringan lokal, jalankan `mulai-https.bat` dan buka alamat HTTPS yang muncul (lihat `ssl/README-SSL.md`). Untuk hosting, pasang SSL (AutoSSL/Let's Encrypt). Bisa juga scan di komputer server dengan `localhost` atau pakai scanner QR USB.

---

## 7. Keamanan akun

- Kata sandi disimpan sebagai hash (bcrypt), tidak pernah ditampilkan di database.
- Login dibatasi **5 percobaan/menit per email + IP** dan **60 percobaan/menit per IP**. Pesan galat bersifat umum dan menyebut sisa percobaan.
- Email dinormalisasi menjadi huruf kecil; login tidak membedakan huruf besar/kecil.
- Ikon mata pada setiap kolom kata sandi menampilkan/menyembunyikan isian tanpa menghapus teks. Tombol dapat dipakai dengan keyboard dan memiliki label aksesibilitas.
- Halaman login menampilkan galat per kolom, fokus otomatis ke kolom bermasalah, dan peringatan **Caps Lock**.
- Setelah ganti kata sandi, sesi diperbarui dan token **“ingat saya” di perangkat lain dibatalkan**.
- Administrator dapat mereset kata sandi akun lain; token “ingat saya” akun itu ikut dibatalkan.
- Tidak ada pendaftaran publik. Akun hanya dibuat Administrator.
- Semua scan dan perubahan data penting dicatat di menu **Aktivitas**.

---

## 8. Migrasi database

Mulai versi ini, migrasi yang tersedia:

| Migrasi | Isi |
|---|---|
| `000001`–`000006` | Tabel kelas, siswa, presensi, peran, dan aktivitas |
| `000007` | Tabel `surat_izin` |
| `000008` | Kolom surat izin/catatan pada presensi, status lima nilai, jam masuk boleh kosong |
| `000009` | Tabel `bulan_efektif` (koreksi hari efektif) |
| `000010` | Ganti nama kelas contoh menjadi X/XI/XII TJKT 2 |
| `000011` | Kolom `users.kelas_id`/`siswa_id` dan tabel `orang_tua_siswa` |

**Database baru:** `php artisan migrate --seed`.

**Database lama yang sudah berisi data:**

1. **Cadangkan database** (phpMyAdmin → Export atau `mysqldump`).
2. Jalankan `php artisan migrate` **tanpa** `migrate:fresh`.
3. Jangan menjalankan `migrate:fresh` di server produksi — perintah itu menghapus semua data.

Migrasi `000008` mengubah status presensi menjadi `ENUM` hanya jika seluruh data lama valid; jika tidak, kolom dibiarkan agar data tidak hilang. Migrasi `000011` hanya menambah kolom/tabel. Akun lama otomatis tetap berperan Administrator sampai diubah lewat menu **Pengguna**.

---

## 9. Surat izin, rekap, dan impor

### Surat izin

Semua peran dapat mengajukan surat. Hanya **Administrator** dan **wali kelas terkait** yang dapat menyetujui/menolak; hanya Administrator yang dapat menghapus. Surat yang disetujui mencatat status `izin`/`sakit` pada hari sekolah saja (Senin–Jumat secara bawaan, dapat diubah lewat `PRESENSI_HARI_KERJA`). Akhir pekan dilewati. Scan QR pada hari yang sama menggantikan catatan izin.

### Rekap bulanan

Rumus **alpa** = `hari efektif − hadir − telat − izin − sakit` (tidak pernah negatif). Hari efektif dihitung dari tanggal berbeda yang memiliki presensi pada bulan itu; Administrator dapat menetapkannya manual. CSV memakai kolom:
`No;NIS;Nama;Kelas;Hadir;Telat;Izin;Sakit;Alpa;Persen`.

### Impor siswa (CSV)

Menu **Data siswa → Import Excel**. Unduh template, isi di Excel, simpan sebagai **CSV UTF-8** (pemisah koma atau titik koma), lalu unggah.

- Pemisah `,` / `;` / tab dan BOM UTF-8 dikenali otomatis.
- Judul kolom fleksibel (`nis`, `nama`, `kelas`, `jenis kelamin`, dan variasinya).
- Kelas baru dapat dibuat otomatis bila dicentang.
- NIS yang sudah ada dilewati; centang *Perbarui* untuk menimpa.
- Maksimal 2 MB / 5.000 baris. File `.xlsx` harus disimpan sebagai CSV terlebih dahulu.

---

## 10. Pengujian yang sudah dilakukan

| Pengujian | Hasil |
|---|---|
| PHPUnit (Laravel 12, SQLite) | **40 tes / 221 assertion lulus** |
| Uji HTTP semua peran + tautan internal | **150/150 pemeriksaan lulus** |
| Login tiap peran, akses 200/403, menu | Lulus |
| Ikon mata kata sandi, galat login, Caps Lock, rate limit | Lulus di Chromium |
| Ganti kata sandi + pembatalan token “ingat saya” | Lulus |
| Responsif 320/375/768 px, menu laci, dialog, tabel kartu | Tidak ada scroll horizontal |
| Lighthouse (halaman pengguna, mobile) | Accessibility **100**, Best Practices **100** |
| QR SVG, scan masuk/pulang, izin, rekap, CSV, impor | Lulus di PHPUnit |

**Belum diuji secara fisik:** pembacaan QR dengan kamera sungguhan, scanner QR USB, dan perangkat iOS/Safari. Semua logika scan dan pembuatan QR diuji otomatis, tetapi kondisi cahaya/kamera cetak tetap perlu dicoba di sekolah.

---

## 11. Keterbatasan

- Kode QR bersifat statis; foto kartu dapat dipakai. Petugas sebaiknya mencocokkan wajah dan memakai *Buat ulang kode QR* bila kartu bocor.
- Belum ada reset kata sandi lewat email; Administrator mereset lewat menu **Pengguna**.
- Belum ada kalender hari libur otomatis; hari efektif dapat dikoreksi manual per bulan.
- Impor hanya CSV, belum `.xlsx` langsung.
- Menghapus siswa ikut menghapus riwayat presensinya.
- Jam presensi mengikuti jam server; pastikan jam komputer/hosting benar.
- Scan kamera berjalan selama halaman terbuka; sesi login 12 jam (`SESSION_LIFETIME=720`).

---

## 12. Hosting

Panduan cPanel/VPS lengkap ada di **[PANDUAN-HOSTING.md](PANDUAN-HOSTING.md)**. Ringkasan produksi:

```bash
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
php artisan key:generate
# isi DB_*, APP_URL, dan ADMIN_* di .env
php artisan migrate --force --seed
php artisan storage:link
php artisan optimize
```

- Arahkan **document root** ke folder `public`, bukan folder proyek.
- Wajib **HTTPS** untuk kamera dari perangkat lain.
- Set `APP_ENV=production`, `APP_DEBUG=false`, `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true` setelah HTTPS aktif.
- Aktifkan `SECURITY_HSTS=true` hanya kalau sertifikat sudah valid di semua perangkat.
- Hapus `ADMIN_PASSWORD` dari `.env` setelah akun admin dibuat.
- Folder `storage/` dan `bootstrap/cache/` harus dapat ditulis.
- Cek `https://domain-sekolah/health` → `{"status":"ok"}`.
- **Jangan** menjalankan `migrate:fresh` di server yang sudah berisi data. Panduan SSL lengkap ada di `PANDUAN-HOSTING.md` dan `ssl/README-SSL.md`.

---

## 13. Struktur penting

```
app/                 controller, model, middleware, support QR/impor
bootstrap/           bootstrap Laravel + cache
config/              config Laravel + config/presensi.php
database/            migrasi, seeder, factory
lang/id/             pesan validasi Bahasa Indonesia
public/              CSS/JS/logo, index.php, .htaccess, QR reader lokal
resources/views/     seluruh tampilan Blade
routes/web.php       rute + pembatasan peran
storage/             foto siswa, sesi, cache, log (harus writable)
ssl/                 panduan + skrip HTTPS lokal (Caddy)
tests/               tes PHPUnit
mulai-https.bat      pintasan HTTPS lokal untuk scan dari HP/tablet
composer.json/lock   dependensi Laravel 12 + bacon-qr-code
```

Logo sekolah: `public/img/logo-sekolah.png`. Ganti file itu dengan PNG baru (sebaiknya berlatar terang) untuk memakai logo lain.
