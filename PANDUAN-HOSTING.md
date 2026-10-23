# Panduan Hosting — Presensi QR SMK Nu Ungaran

Panduan ini menjelaskan cara memasang aplikasi di hosting sekolah sampai siap dipakai.
Ada dua pilihan: **shared hosting / cPanel** (paling umum, tanpa server sendiri) dan **VPS**.

> **Penting:** kamera untuk scan QR hanya menyala di alamat **HTTPS** (atau `http://localhost`).
> Jadi pasang sertifikat SSL sebelum memakai fitur scan dari ponsel.

---

## A. Yang dibutuhkan

| Kebutuhan | Keterangan |
|---|---|
| PHP | **8.2 atau lebih baru** |
| Laravel | **12.x** (sudah termasuk di paket) |
| Ekstensi PHP | `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `xml`, `ctype`, `json`, `tokenizer`, `iconv`, `curl`, `gd` (opsional, untuk foto) |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Web server | Apache (`mod_rewrite` aktif) atau Nginx |
| SSL/HTTPS | Wajib untuk kamera |
| Ruang disk | ± 100 MB untuk aplikasi + ruang untuk foto siswa |

Cek cepat setelah terpasang: buka `https://alamat-sekolah/health` → harus muncul `{"status":"ok"}`.

---

## B. Siapkan di komputer (sekali saja)

ZIP ini sudah berisi **proyek Laravel 12 lengkap** (`artisan`, `bootstrap/`, `config/`, `public/index.php`, dan seterusnya). Yang belum ada hanya folder `vendor/`.

```bash
# di folder proyek (folder yang berisi artisan)
composer install --no-dev --optimize-autoloader
```

Jika hosting **tidak punya** menu Terminal/SSH dan tidak bisa menjalankan Composer,
lakukan `composer install --no-dev` di komputer memakai **versi PHP yang sama** dengan hosting,
lalu ikut unggah folder `vendor/`.

Yang **tidak** perlu diunggah: `node_modules`, `.git`, dan file `.env` lokal.
Pustaka pemindai QR untuk mode offline sudah tersedia di `public/vendor/html5-qrcode.min.js`.

---

## C. Opsi 1 — Shared hosting / cPanel (disarankan)

### C.1 Siapkan alamat dan document root

Di cPanel buka **Domains** (atau **Subdomains**), buat:

- Domain: `presensi.sekolah.sch.id`
- Document Root: `/home/NAMA_USER/presensi/public`

Document root **harus** menunjuk ke folder `public`, bukan ke folder proyek. Ini yang membuat
file `.env`, `storage`, dan kode aplikasi tidak bisa diakses dari browser.

### C.2 Unggah aplikasi

1. Buka **File Manager**.
2. Buat folder `/home/NAMA_USER/presensi`.
3. Unggah berkas ZIP proyek ke folder itu, lalu **Extract**.
4. Pastikan strukturnya seperti ini:

```
/home/NAMA_USER/presensi/
├── app/  bootstrap/  config/  database/  lang/  public/  resources/  routes/  storage/  vendor/
├── artisan
├── composer.json  composer.lock
├── .env
└── .env.example
```

### C.3 Buat database

Buka **MySQL Databases**:

1. Buat database, misalnya `user_presensi`.
2. Buat pengguna dan kata sandi yang kuat.
3. Tambahkan pengguna ke database dengan hak **ALL PRIVILEGES**.

Catat nama database, pengguna, dan kata sandinya untuk langkah berikut.

### C.4 Isi `.env`

1. Salin `.env.production.example` menjadi `.env`.
2. Sesuaikan minimal bagian yang bertanda **GANTI**: `APP_URL`, `DB_*`, `ADMIN_*`.
3. Pastikan `APP_DEBUG=false` dan `APP_ENV=production`.

### C.5 Pasang aplikasi

Buka **Terminal** di cPanel (kalau tersedia), lalu:

```bash
cd ~/presensi

php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
php artisan optimize
```

Jika **tidak ada Terminal**, jalankan perintah yang sama lewat **Cron Jobs** satu kali
(interval "Once Per Minute", lalu hapus cron setelah selesai), atau lewat fitur
**PHP Composer**/_Setup Node.js App_ yang disediakan hosting.

> `--seed` membuat akun admin dari `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
> Kalau `ADMIN_PASSWORD` dikosongkan, seeder membuat kata sandi acak dan
> **menampilkannya sekali di layar** — catat sebelum menutup terminal.
> Setelah berhasil masuk, **hapus `ADMIN_PASSWORD` dari `.env`** (kalau tadi diisi).
>
> Akun contoh guru piket / wali kelas / siswa / orang tua **tidak** dibuat saat `APP_ENV=production`.
> Akun nyata dibuat lewat menu **Pengguna** setelah aplikasi online.

### C.6 Izin folder

Lewat File Manager, pastikan folder berikut bisa ditulis (biasanya 755 / 775):

```
storage/
storage/app/
storage/framework/
storage/framework/cache/
storage/framework/sessions/
storage/framework/views/
storage/logs/
bootstrap/cache/
```

### C.7 Aktifkan HTTPS (wajib untuk kamera)

Sertifikat SSL **harus diterbitkan di server/domain sekolah**. Sertifikat itu tidak
disertakan di ZIP karena terikat pada domain dan tidak boleh memakai private key
milik orang lain.

**Langkah cPanel (paling umum):**

1. Pastikan domain/subdomain sudah mengarah ke hosting (DNS aktif).
2. Buka cPanel > **SSL/TLS Status**.
3. Pilih domain `presensi.sekolah.sch.id`, klik **Run AutoSSL**.
4. Tunggu sampai status **Valid** dan tanggal kedaluwarsa tampil.
5. Buka `https://presensi.sekolah.sch.id` dan pastikan ikon gembok tidak
   menampilkan peringatan.

Kalau hosting tidak menyediakan AutoSSL, pasang **Let's Encrypt** lewat menu
*SSL/TLS* atau minta penyedia hosting memasangnya. Jangan memakai sertifikat
self-signed untuk produksi.

**Setelah HTTPS aktif**, sesuaikan `.env`:

```ini
APP_URL=https://presensi.sekolah.sch.id
APP_ENV=production
APP_DEBUG=false
FORCE_HTTPS=true
SECURITY_HSTS=true
SESSION_SECURE_COOKIE=true
# Kosongkan untuk Apache/cPanel biasa. Isi hanya jika ada reverse proxy.
TRUSTED_PROXIES=
```

Lalu:

```bash
php artisan optimize:clear
php artisan optimize
```

**Pengalihan HTTP ke HTTPS**

Blok ini sudah ada di `public/.htaccess` (dan tidak mengalihkan `localhost` agar
pengembangan lokal tetap bisa memakai `http://localhost`):

```apache
RewriteCond %{HTTPS} !=on
RewriteCond %{HTTP:X-Forwarded-Proto} !https [NC]
RewriteCond %{HTTP_HOST} !^(localhost|127\.0\.0\.1|\[::1\])(:[0-9]+)?$ [NC]
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

Kalau hosting memakai Nginx, gunakan `certbot --nginx --redirect` atau tambahkan
`return 301 https://$host$request_uri;` pada blok port 80.

**HSTS**

`SECURITY_HSTS=true` hanya mengirim header HSTS saat request sudah HTTPS. Aktifkan
setelah yakin semua perangkat bisa membuka HTTPS tanpa peringatan. HSTS sulit
dibatalkan, jadi jangan aktifkan bersamaan dengan sertifikat yang belum valid.

### C.8 Uji HTTPS dari ponsel sebelum sekolah memakai

Cara cepat tanpa domain (jaringan lokal):

1. Di komputer server, jalankan **`mulai-https.bat`** dari folder proyek.
2. Skrip membuat sertifikat lokal dan menampilkan `https://IP-LAN:8443`.
3. Pasang `ssl/caddy-root-ca.crt` di ponsel/tablet (lihat `ssl/README-SSL.md`).
4. Buka alamat HTTPS itu, login sebagai guru piket, lalu coba **Scan presensi**.
5. Jika berhasil, lanjutkan ke hosting produksi dengan AutoSSL. Sertifikat lokal
   **jangan** dipakai untuk domain publik.

### C.9 Kalau document root tidak bisa diubah

Sebagian hosting hanya mengizinkan `public_html` sebagai document root. Ada dua cara aman:

**Cara 1 (disarankan): pindahkan isi `public` ke `public_html`.**

1. Letakkan seluruh proyek di `/home/NAMA_USER/presensi` (di luar `public_html`).
2. Salin **isi** folder `public/` (termasuk `.htaccess`) ke `public_html/`.
3. Edit `public_html/index.php`, ubah dua baris ini:

```php
require __DIR__.'/../presensi/vendor/autoload.php';

$app = require_once __DIR__.'/../presensi/bootstrap/app.php';
```

**Cara 2: pengalihan lewat `.htaccess` di `public_html`.**

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/presensi/public/
    RewriteRule ^(.*)$ /presensi/public/$1 [L]
</IfModule>
```

Cara 2 hanya berfungsi kalau `AllowOverride` mengizinkan `RewriteRule` dan alamat `public/`
tidak dapat diakses langsung. Cara 1 lebih aman.

---

## D. Opsi 2 — VPS (Ubuntu + Nginx)

```bash
sudo apt update
sudo apt install nginx php8.2-fpm php8.2-mbstring php8.2-xml php8.2-curl \
     php8.2-zip php8.2-mysql php8.2-gd mysql-server certbot python3-certbot-nginx
```

Letakkan proyek di `/var/www/presensi`, lalu buat konfigurasi Nginx:

```nginx
server {
    listen 80;
    server_name presensi.sekolah.sch.id;
    root /var/www/presensi/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param HTTPS $https if_not_empty;
        fastcgi_param HTTP_X_FORWARDED_PROTO $scheme;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
sudo chown -R www-data:www-data /var/www/presensi/storage /var/www/presensi/bootstrap/cache
# --redirect membuat pengalihan HTTP -> HTTPS otomatis.
sudo certbot --nginx --redirect -d presensi.sekolah.sch.id

cd /var/www/presensi
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan key:generate
sudo -u www-data php artisan migrate --force --seed
sudo -u www-data php artisan storage:link
# Setelah certbot berhasil, isi .env:
# APP_URL=https://presensi.sekolah.sch.id
# FORCE_HTTPS=true
# SECURITY_HSTS=true
# SESSION_SECURE_COOKIE=true
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan optimize
```

Uji perpanjangan otomatis:

```bash
sudo certbot renew --dry-run
```

Sertifikat Let's Encrypt berlaku 90 hari; timer certbot biasanya memperbaruinya
otomatis. Jangan menonaktifkan timer tersebut.

Aplikasi ini **tidak memakai tugas terjadwal**, jadi tidak perlu cron `schedule:run`.

---

## E. Daftar periksa setelah online

1. `https://alamat-sekolah/health` → `{"status":"ok"}`.
2. Login memakai `ADMIN_EMAIL` dan kata sandi admin (dari `ADMIN_PASSWORD`, atau kata sandi acak yang tampil saat seeding).
3. **Ganti kata sandi** lewat menu **Akun**.
4. Hapus `ADMIN_PASSWORD` dari `.env` (kalau tadi diisi), lalu jalankan `php artisan optimize:clear && php artisan optimize`.
5. Buka **Data kelas** → sesuaikan nama kelas (bawaan: X TJKT 2, XI TJKT 2, XII TJKT 2) atau hapus data contoh. Logo sekolah bisa diganti di `public/img/logo-sekolah.png`.
6. **Data siswa → Import Excel** untuk memasukkan siswa sungguhan.
7. Cetak kartu: satu siswa atau satu kelas (A4, skala 100%, grafik latar belakang aktif).
8. Uji scan dari ponsel lewat **HTTPS**, lalu cek **Laporan presensi**.
9. Atur `PRESENSI_BATAS_TELAT` dan `PRESENSI_HARI_KERJA` sesuai jam sekolah.
10. Buat akun **Guru Piket** lewat menu **Pengguna**.
11. Buat akun **Wali Kelas** dan tautkan ke kelasnya, lalu akun **Siswa** dan **Orang Tua/Wali** (lihat bagian E.1).
12. Cadangkan database untuk pertama kali.

### E.1 Menyiapkan akun wali kelas, orang tua, dan siswa

Semua akun dibuat oleh Administrator di menu **Pengguna → Tambah akun**.

| Peran | Isian wajib | Catatan |
|---|---|---|
| **Wali Kelas** | Pilih **Kelas yang diampu** | Satu kelas hanya boleh punya satu wali kelas. Nama wali kelas otomatis tampil di data kelas. |
| **Siswa** | Pilih **Data siswa** | Satu data siswa hanya boleh punya satu akun siswa. |
| **Orang Tua/Wali** | Centang minimal satu **Anak yang terhubung** | Boleh lebih dari satu (kakak-adik). |

Hak akses singkat:

- **Wali Kelas** — dashboard, laporan, rekap + CSV, daftar siswa, cetak kartu kelas, dan menyetujui/menolak surat izin **hanya untuk kelasnya**. Tidak bisa menandai presensi atau mengubah data siswa.
- **Orang Tua/Wali** — dashboard, riwayat, dan rekap kehadiran **anaknya saja**, serta mengajukan surat izin untuk anak tersebut.
- **Siswa** — dashboard dan riwayat kehadiran **dirinya sendiri**, serta mengajukan surat izin sendiri.

Bagikan email dan kata sandi awal kepada masing-masing pengguna, dan minta mereka menggantinya lewat menu **Akun** setelah login pertama. Jika data siswa dihapus, tautan akun siswa/orang tua otomatis dilepas (akunnya tetap ada dan bisa ditautkan ulang ke siswa lain).

> **Penting:** peran **tidak** bisa dinaikkan sendiri oleh pengguna. Hanya Administrator yang bisa mengubah peran dan tautan akun.

---

## F. Perawatan rutin

| Kegiatan | Cara |
|---|---|
| Cadangkan database | cPanel → **Backup** → *Download a MySQL Database Backup*, atau `mysqldump -u user -p db > cadangan.sql` |
| Cadangkan foto & lampiran | unduh folder `storage/app/public` |
| Melihat galat | `storage/logs/laravel.log` (set `LOG_LEVEL=warning` agar tidak penuh) |
| Memperbarui aplikasi | timpa file `app/ config/ database/ lang/ public/ resources/ routes/`, lalu `composer install --no-dev`, `php artisan migrate --force`, `php artisan optimize` |
| Setelah mengganti `.env` | `php artisan optimize:clear && php artisan optimize` |
| Membersihkan cache tampilan | `php artisan view:clear` |

**Jangan menghapus folder `storage/`** — di situ foto siswa dan lampiran surat izin.

---

## G. Jika ada masalah

| Gejala | Penyebab & solusi |
|---|---|
| **403 Forbidden** | Document root belum menunjuk ke `public`, atau `mod_rewrite` mati. |
| **500 Server Error** | `APP_KEY` kosong, `.env` salah, atau folder `storage`/`bootstrap/cache` tidak bisa ditulis. Cek `storage/logs/laravel.log`. |
| **Halaman putih** | Sementara set `APP_DEBUG=true` untuk melihat pesan, lalu **kembalikan ke `false`**. |
| **419 Page Expired** | Cookie sesi ditolak. Samakan `APP_URL` dengan alamat asli, dan set `SESSION_SECURE_COOKIE=true` hanya jika sudah HTTPS. |
| **Login berhasil tapi kembali ke halaman login** | `SESSION_DOMAIN` salah atau cookie diblokir; kosongkan `SESSION_DOMAIN`. |
| **Foto siswa / lampiran tidak tampil** | Jalankan `php artisan storage:link`. |
| **Kamera tidak menyala** | Alamat masih `http://`. Pasang HTTPS (cPanel AutoSSL/Let's Encrypt atau `mulai-https.bat` untuk lokal). Pastikan ikon gembok muncul. |
| **Peringatan sertifikat di ponsel** | Sertifikat lokal belum dipercaya. Pasang `ssl/caddy-root-ca.crt` di perangkat (lihat `ssl/README-SSL.md`). Jangan lanjutkan di situs produksi. |
| **HTTPS aktif tetapi login berulang / 419** | `APP_URL` harus sama dengan alamat HTTPS, `SESSION_SECURE_COOKIE=true`, dan `FORCE_HTTPS=true`. Jika di belakang proxy, isi `TRUSTED_PROXIES`. Lalu `php artisan optimize:clear && php artisan optimize`. |
| **Halaman masih memakai http://** | Set `FORCE_HTTPS=true`, periksa `APP_URL`, dan jalankan `php artisan optimize:clear`. Untuk Nginx pastikan `fastcgi_param HTTPS`/`X-Forwarded-Proto` diteruskan. |
| **CSS/JS tidak termuat** | `APP_URL` masih `http://localhost`. Sesuaikan dengan domain asli. |
| **Upload gagal (413/422)** | Naikkan `upload_max_filesize=8M` dan `post_max_size=10M` di php.ini hosting. |
| **Jam presensi meleset** | Set `PRESENSI_TIMEZONE=Asia/Jakarta` dan `date.timezone` PHP. |
| **Scan ditolak "terlalu banyak permintaan"** | Batas 120 scan/menit; tunggu sebentar. |

---

## H. Keamanan

- `APP_DEBUG=false` dan `APP_ENV=production` di server.
- Selalu pakai HTTPS. Aktifkan `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, dan (setelah yakin) `SECURITY_HSTS=true`.
- Jangan memakai sertifikat lokal/self-signed untuk domain publik. Gunakan AutoSSL/Let's Encrypt.
- Kata sandi admin minimal 12 karakter dan tidak dipakai ulang.
- Hapus `ADMIN_PASSWORD` dari `.env` setelah akun dibuat.
- Jangan pernah mengunggah `.env` ke repositori publik.
- Buat akun **Guru Piket**, **Wali Kelas**, **Siswa**, dan **Orang Tua/Wali** secukupnya. Hak menyetujui surat izin ada pada Administrator dan wali kelas terkait; hak mengubah data master dan mengelola akun hanya untuk Administrator.
- Perbarui PHP dan Laravel secara berkala.
- Simpan cadangan database di tempat lain (bukan hanya di server yang sama).
