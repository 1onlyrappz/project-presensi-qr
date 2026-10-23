<#
  Setup otomatis Presensi QR untuk Windows (XAMPP / Laragon).
  Jalankan lewat setup.bat (klik ganda), atau:
      powershell -ExecutionPolicy Bypass -File .\setup.ps1

  Proyek Laravel 12 sudah lengkap di folder ini. Skrip ini:
  memasang dependensi Composer, menyiapkan .env, membuat database MySQL,
  menjalankan migrasi + seeder, dan membuat tautan storage.
#>
param(
    [string]$Database = "presensi_qr",
    [string]$DbUser = "root",
    [string]$DbPassword = ""
)

$ErrorActionPreference = "Stop"
$Source = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $Source

function Step($t) { Write-Host "`n==> $t" -ForegroundColor Cyan }
function Fail($t) { Write-Host "`nGAGAL: $t" -ForegroundColor Red; exit 1 }
function TulisEnv($path, $text) {
    [System.IO.File]::WriteAllText($path, $text, (New-Object System.Text.UTF8Encoding($false)))
}

Step "Memeriksa PHP dan Composer"
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) { Fail "php tidak ditemukan di PATH. XAMPP: tambahkan C:\xampp\php ke PATH. Laragon: buka Terminal dari Laragon." }
$composer = Get-Command composer -ErrorAction SilentlyContinue
if (-not $composer) { Fail "composer tidak ditemukan. Pasang dari https://getcomposer.org (Laragon sudah menyertakannya)." }

$ver = & php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;"
Write-Host "PHP $ver"
if ([version]$ver -lt [version]"8.2") { Fail "Butuh PHP 8.2 atau lebih baru (terdeteksi $ver). Perbarui XAMPP/Laragon." }

$missing = @()
foreach ($ext in @("mbstring", "openssl", "pdo_mysql", "fileinfo", "xml", "ctype", "json", "tokenizer", "iconv", "curl")) {
    $ok = & php -r "echo extension_loaded('$ext') ? '1' : '0';"
    if ($ok -ne "1") { $missing += $ext }
}
if ($missing.Count -gt 0) { Fail ("Ekstensi PHP belum aktif: " + ($missing -join ", ") + ". Aktifkan di php.ini, lalu restart Apache/PHP.") }

Step "Menyiapkan file .env"
if (-not (Test-Path ".env")) {
    if (-not (Test-Path ".env.example")) { Fail ".env.example tidak ditemukan." }
    Copy-Item ".env.example" ".env"
    Write-Host "File .env dibuat dari .env.example."
}
$envText = Get-Content ".env" -Raw
function Set-Env([string]$key, [string]$value) {
    $pattern = "(?m)^#?\s*" + [regex]::Escape($key) + "=.*$"
    $safe = $value.Replace('$', '$$')
    if ($script:envText -match $pattern) { $script:envText = [regex]::Replace($script:envText, $pattern, "$key=$safe") }
    else { $script:envText = $script:envText.TrimEnd() + "`r`n$key=$value`r`n" }
}
Set-Env "DB_CONNECTION" "mysql"
Set-Env "DB_HOST" "127.0.0.1"
Set-Env "DB_PORT" "3306"
Set-Env "DB_DATABASE" $Database
Set-Env "DB_USERNAME" $DbUser
Set-Env "DB_PASSWORD" $DbPassword
Set-Env "PRESENSI_NAMA_SEKOLAH" "`"SMK Nu Ungaran`""
TulisEnv ".env" $envText

Step "Memasang dependensi Composer"
& composer install --no-interaction --prefer-dist
if ($LASTEXITCODE -ne 0) { Fail "composer install gagal. Periksa koneksi internet lalu ulangi." }

Step "Membuat APP_KEY"
$envText = Get-Content ".env" -Raw
if ($envText -match "(?m)^APP_KEY=\s*$") {
    & php artisan key:generate --force
    if ($LASTEXITCODE -ne 0) { Fail "php artisan key:generate gagal." }
}

Step "Membuat database '$Database'"
$mysql = $null
$cmd = Get-Command mysql -ErrorAction SilentlyContinue
if ($cmd) { $mysql = $cmd.Source }
if (-not $mysql -and (Test-Path "C:\xampp\mysql\bin\mysql.exe")) { $mysql = "C:\xampp\mysql\bin\mysql.exe" }
if (-not $mysql) {
    $lg = Get-ChildItem "C:\laragon\bin\mysql" -Recurse -Filter mysql.exe -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($lg) { $mysql = $lg.FullName }
}
$sql = "CREATE DATABASE IF NOT EXISTS ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$dbOk = $false
if ($mysql) {
    $mysqlArgs = @("-u", $DbUser, "-e", $sql)
    if ($DbPassword -ne "") { $mysqlArgs = @("-u", $DbUser, "-p$DbPassword", "-e", $sql) }
    & $mysql @mysqlArgs
    $dbOk = ($LASTEXITCODE -eq 0)
}
if (-not $dbOk) {
    Write-Host "Database belum bisa dibuat otomatis. Buat manual lewat phpMyAdmin: nama '$Database', collation utf8mb4_unicode_ci." -ForegroundColor Yellow
    Read-Host "Tekan Enter setelah database dibuat (pastikan MySQL menyala)"
}

Step "Migrasi database + data awal"
& php artisan migrate --force
if ($LASTEXITCODE -ne 0) { Fail "php artisan migrate gagal. Periksa DB_* di .env dan pastikan MySQL menyala." }
& php artisan db:seed --force
if ($LASTEXITCODE -ne 0) { Fail "php artisan db:seed gagal." }

Step "Membuat tautan penyimpanan"
& php artisan storage:link
if ($LASTEXITCODE -ne 0) { Write-Host "storage:link gagal. Jalankan terminal sebagai Administrator lalu ulangi: php artisan storage:link" -ForegroundColor Yellow }

Step "Membersihkan cache"
& php artisan optimize:clear

Write-Host "`nSELESAI." -ForegroundColor Green
Write-Host "Jalankan:  php artisan serve"
Write-Host "Buka:      http://localhost:8000"
Write-Host ""
Write-Host "Untuk scan dari ponsel/tablet/PC lain:" -ForegroundColor Yellow
Write-Host "  1. Klik dua kali mulai-https.bat (membuat sertifikat lokal + HTTPS)."
Write-Host "  2. Buka alamat https://IP-LAN:8443 yang muncul."
Write-Host "  3. Pasang ssl\caddy-root-ca.crt di perangkat (panduan: ssl\README-SSL.md)."
Write-Host "Untuk hosting, pasang AutoSSL/Let's Encrypt seperti di PANDUAN-HOSTING.md."
Write-Host ""
Write-Host "Akun demo (lokal): admin@sekolah.test / password"
Write-Host "                   guru@sekolah.test / password"
Write-Host "                   walikelas@sekolah.test / password"
Write-Host "                   orangtua@sekolah.test / password"
Write-Host "                   siswa@sekolah.test / password"
Write-Host "Segera ganti kata sandi akun demo lewat menu Akun." -ForegroundColor Yellow
