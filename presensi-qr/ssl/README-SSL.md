# SSL/HTTPS untuk Presensi QR

Kamera browser hanya mau menyala di **secure context**, yaitu:

- `https://...` (sertifikat dipercaya), atau
- `http://localhost` / `http://127.0.0.1` di komputer yang menjalankan aplikasi.

Alamat `http://192.168.x.x:8000` dari ponsel **selalu diblokir** browser. Karena itu,
scan dari ponsel, tablet, laptop lain, atau PC lain membutuhkan HTTPS.

> **Penting:** sertifikat SSL yang dipercaya publik tidak bisa disertakan di dalam ZIP.
> Sertifikat seperti itu diterbitkan untuk **domain milik sekolah** di server hosting
> (cPanel AutoSSL / Let's Encrypt). Untuk jaringan lokal, aplikasi ini menyediakan
> pembuat sertifikat lokal. Jangan pernah membagikan private key atau memakai
> sertifikat lokal untuk situs produksi.

---

## A. Paling mudah untuk produksi: cPanel AutoSSL / Let's Encrypt

Gunakan cara ini kalau aplikasi sudah diunggah ke hosting dan punya domain, misalnya
`presensi.smknuungaran.sch.id`.

1. Arahkan domain/subdomain ke hosting dan tunggu DNS aktif.
2. Di cPanel buka **SSL/TLS Status**.
3. Klik **Run AutoSSL** untuk domain tersebut.
4. Buka `https://domain-sekolah` dan pastikan ikon gembok muncul tanpa peringatan.
5. Isi `.env` produksi:

```ini
APP_URL=https://presensi.smknuungaran.sch.id
APP_ENV=production
APP_DEBUG=false
FORCE_HTTPS=true
SECURITY_HSTS=true
SESSION_SECURE_COOKIE=true
# Kosongkan kalau Apache hosting langsung melayani request.
# Isi hanya kalau ada reverse proxy/Cloudflare, misal: TRUSTED_PROXIES=127.0.0.1
TRUSTED_PROXIES=
```

6. Jalankan:

```bash
php artisan optimize:clear
php artisan optimize
```

7. Pastikan HTTP otomatis dialihkan ke HTTPS. Berkas `public/.htaccess` sudah berisi
   pengalihan untuk domain non-localhost. Kalau hosting memakai Nginx, tambahkan
   pengalihan di konfigurasi Nginx atau pakai `certbot --nginx --redirect`.

Panduan cPanel dan VPS lebih lengkap ada di [`../PANDUAN-HOSTING.md`](../PANDUAN-HOSTING.md).

---

## B. HTTPS untuk jaringan lokal (tanpa domain)

Cocok untuk mencoba scan dari HP di lab/ruang guru sebelum aplikasi dipasang di hosting.

### B.1 Jalankan sekali klik

1. Pastikan `setup.bat` sudah pernah dijalankan dan aplikasi bisa dibuka di
   `http://localhost:8000`.
2. Klik dua kali **`mulai-https.bat`** di folder proyek.
3. Tunggu sampai muncul alamat seperti:

```text
HTTPS AKTIF
  Komputer ini : https://localhost:8443
  Ponsel/tablet: https://192.168.1.25:8443
```

4. Di komputer server, buka `https://localhost:8443`.
5. Dari ponsel/tablet yang tersambung ke Wi-Fi yang sama, buka alamat IP LAN di atas.

Skrip akan:

- menjalankan Laravel di `127.0.0.1:8000`;
- mengunduh Caddy resmi sekali saja (sekitar 18 MB) kalau belum ada;
- membuat sertifikat lokal untuk `localhost`, `127.0.0.1`, nama komputer, dan IP LAN;
- menjalankan reverse proxy HTTPS di port **8443**;
- menyalin sertifikat root ke `ssl/caddy-root-ca.crt`.

Tekan **Ctrl+C** di jendela skrip untuk menghentikan HTTPS.

### B.2 Agar ponsel/tablet tidak menampilkan peringatan sertifikat

Sertifikat lokal dibuat oleh **Caddy Local Authority**. Di komputer server, Caddy
biasanya sudah memasang root certificate otomatis. Di perangkat lain, pasang file
`ssl/caddy-root-ca.crt` satu kali:

**Android**

1. Kirim `caddy-root-ca.crt` ke ponsel (USB, email, atau Google Drive).
2. Buka **Settings** > **Security** > **Encryption & credentials** >
   **Install a certificate** > **CA certificate**.
3. Pilih file tersebut. Jika muncul peringatan, pilih tetap lanjut.
4. Nama sertifikat bisa diberi label `Presensi QR`.

**iPhone/iPad**

1. Kirim file ke perangkat, lalu buka filenya dan ikuti profil yang muncul.
2. Buka **Settings** > **General** > **VPN & Device Management**, pilih profil
   `Caddy Local Authority`, lalu **Install**.
3. Buka **Settings** > **General** > **About** > **Certificate Trust Settings**,
   aktifkan **Full Trust** untuk sertifikat Caddy.
4. Ulangi pengecekan: buka alamat HTTPS lagi, ikon gembok harus muncul.

**Windows (komputer lain)**

1. Klik dua kali `caddy-root-ca.crt`.
2. Pilih **Install Certificate** > **Current User** > **Place all certificates in
   the following store** > **Trusted Root Certification Authorities**.
3. Tutup dan buka ulang browser.

**Firefox**

Firefox kadang memakai penyimpanan sertifikat sendiri. Buka
`about:preferences#privacy` > **Certificates** > **View Certificates** >
**Authorities** > **Import**, lalu pilih `caddy-root-ca.crt` dan centang
**Trust this CA to identify websites**.

### B.3 Jika ponsel tetap tidak bisa membuka alamat

- Pastikan ponsel dan komputer berada di Wi-Fi/jaringan yang sama.
- Cek IP komputer dengan `ipconfig`; jangan memakai IP `127.0.0.1` dari ponsel.
- Izinkan **Caddy** di Windows Defender Firewall untuk **Private networks**, atau
  buka port TCP 8443:
  ```powershell
  # Jalankan PowerShell sebagai Administrator
  netsh advfirewall firewall add rule name="Presensi QR HTTPS 8443" dir=in action=allow protocol=TCP localport=8443
  ```
- Jangan memakai alamat `http://IP:8000` dari ponsel; browser akan memblokir kamera.
- Kalau IP komputer berubah (DHCP), jalankan `mulai-https.bat` lagi. Sertifikat baru
  mungkin perlu dipasang ulang di perangkat.

### B.4 Mengganti port

```bat
mulai-https.bat -Port 9443
```

Atau lewat PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\ssl\mulai-https.ps1 -Port 9443 -AppPort 8000
```

Gunakan port di atas 1024 agar tidak bentrok dengan IIS/web server lain.

---

## C. Cara manual (kalau Caddy tidak bisa diunduh otomatis)

1. Unduh Caddy untuk Windows dari <https://caddyserver.com/download>.
2. Ekstrak `caddy.exe`, lalu letakkan di folder yang masuk `PATH`, misalnya
   `C:\caddy\caddy.exe` dan tambahkan `C:\caddy` ke **Environment Variables > Path**.
3. Jalankan lagi `mulai-https.bat`. Skrip akan mendeteksi `caddy` dari `PATH`.

Alternatif lain: gunakan **Laragon** dengan fitur SSL, **XAMPP + mkcert**, atau
**nginx/Caddy** di server. Yang penting alamat yang dibuka harus `https://` dan
sertifikatnya dipercaya perangkat.

---

## D. Uji setelah HTTPS aktif

1. Buka `https://alamat/health` -> `{"status":"ok"}`.
2. Login sebagai admin atau guru piket.
3. Buka menu **Scan presensi**.
4. Klik **Nyalakan kamera**; browser harus meminta izin kamera, bukan memblokir.
5. Arahkan kartu QR; hasil masuk/pulang muncul.
6. Cek **Laporan presensi** di komputer lain.

Kalau kamera tetap diblokir, buka konsol browser (F12) dan cari pesan
`secure context`, `getUserMedia`, atau `NotAllowedError`.

---

## E. Keamanan dan produksi

- Jangan memakai sertifikat lokal/Caddy untuk situs yang bisa diakses publik.
- Jangan menaruh private key (`*.key`, `*.pem`, `*.pfx`) di folder `public/`.
- Folder `ssl/.caddy/`, `ssl/.tools/`, dan file `ssl/caddy-root-ca.crt` hanya untuk
  komputer pengembangan. Folder/file itu tidak disertakan di ZIP dan sudah masuk
  `.gitignore`.
- Di produksi, gunakan sertifikat dari penyedia hosting. Aktifkan
  `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, dan `SECURITY_HSTS=true`
  **setelah** HTTPS benar-benar berjalan.
- HSTS membuat browser selalu memakai HTTPS. Jangan aktifkan sebelum sertifikat
  produksi valid, karena sulit dibatalkan.
- Setelah mengganti `.env`, jalankan `php artisan optimize:clear && php artisan optimize`.
