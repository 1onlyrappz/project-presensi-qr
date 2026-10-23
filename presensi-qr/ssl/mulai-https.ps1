<#
  Presensi QR - HTTPS lokal untuk jaringan sekolah (Windows).

  Skrip ini menjalankan Laravel di 127.0.0.1:8000, lalu Caddy sebagai
  reverse proxy HTTPS di port 8443. Caddy membuat sertifikat lokal untuk
  localhost, 127.0.0.1, nama komputer, dan IP LAN. Sertifikat ini hanya
  berlaku di jaringan lokal dan tidak bisa dipakai di internet.

  Pakai dari folder proyek:
      mulai-https.bat
  atau:
      powershell -ExecutionPolicy Bypass -File .\ssl\mulai-https.ps1

  Pertama kali, skrip mengunduh Caddy resmi (sekali saja, butuh internet).
  Jika komputer tidak boleh mengunduh, pasang Caddy sendiri dari
  https://caddyserver.com/download lalu jalankan skrip ini lagi.
#>
param(
    [int]$Port = 8443,
    [int]$AppPort = 8000,
    [string]$Ip = "",
    [switch]$TanpaBrowser
)

$ErrorActionPreference = "Stop"
$SslDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Root = Split-Path -Parent $SslDir
Set-Location $Root

function Langkah($t) { Write-Host "`n==> $t" -ForegroundColor Cyan }
function Info($t) { Write-Host "    $t" }
function Gagal($t) { Write-Host "`nGAGAL: $t" -ForegroundColor Red; exit 1 }
function Peringatan($t) { Write-Host "PERINGATAN: $t" -ForegroundColor Yellow }

function PortTerpakai([int]$p) {
    $c = New-Object System.Net.Sockets.TcpClient
    try {
        $c.Connect("127.0.0.1", $p)
        $c.Close()
        return $true
    } catch {
        return $false
    } finally {
        if ($c) { $c.Dispose() }
    }
}

function CariIpLan {
    try {
        $rute = Get-NetRoute -DestinationPrefix "0.0.0.0/0" -ErrorAction SilentlyContinue |
            Sort-Object RouteMetric, InterfaceMetric |
            Select-Object -First 1
        if ($rute) {
            $ip = Get-NetIPAddress -InterfaceIndex $rute.InterfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue |
                Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' -and $_.AddressState -eq 'Preferred' } |
                Select-Object -First 1 -ExpandProperty IPAddress
            if ($ip) { return $ip }
        }
    } catch { }

    try {
        $ip = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
            Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' -and $_.InterfaceAlias -notmatch 'Loopback' } |
            Sort-Object InterfaceIndex |
            Select-Object -First 1 -ExpandProperty IPAddress
        if ($ip) { return $ip }
    } catch { }

    return ""
}

function UnduhCaddy {
    $versi = "2.11.7"
    $arsitektur = if ($env:PROCESSOR_ARCHITECTURE -eq "ARM64" -or $env:PROCESSOR_ARCHITEW6432 -eq "ARM64") { "arm64" } else { "amd64" }
    $sha = if ($arsitektur -eq "arm64") {
        "5e232f5fd68968c288034d98f8b4a6b16f246da6d5f1d3a8c7543b83327f1de0bf5d01d627768fbfd1dd254d7d53eb94589a40b126c29def10ac01365a3fe982"
    } else {
        "c308154504e53755958ffc62d8a2657ab17eefd6dd25c45dbb2c3d93d29519971f77ca652f7d0f2bcb7f980b9ba3822478865e29969d0272d7613550a43152b1"
    }
    $asset = "caddy_${versi}_windows_${arsitektur}.zip"
    $url = "https://github.com/caddyserver/caddy/releases/download/v${versi}/${asset}"
    $tools = Join-Path $SslDir ".tools"
    $zip = Join-Path $tools $asset
    $exe = Join-Path $tools "caddy.exe"

    New-Item -ItemType Directory -Force -Path $tools | Out-Null
    if (Test-Path $exe) { return $exe }

    Write-Host "    Mengunduh Caddy $versi (sekali saja, sekitar 18 MB)..." -ForegroundColor DarkGray
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    try {
        Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing -TimeoutSec 180
    } catch {
        Gagal "Caddy belum ada dan unduhan otomatis gagal. Pasang Caddy dari https://caddyserver.com/download (pilih Windows amd64), taruh caddy.exe di PATH, lalu jalankan skrip ini lagi."
    }

    $hash = (Get-FileHash -LiteralPath $zip -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($hash -ne $sha) {
        Remove-Item -LiteralPath $zip -Force -ErrorAction SilentlyContinue
        Gagal "Berkas Caddy yang diunduh tidak lolos pemeriksaan SHA-256. Coba lagi atau pasang Caddy manual."
    }

    $extract = Join-Path $tools "caddy-temp"
    if (Test-Path $extract) { Remove-Item -Recurse -Force $extract }
    Expand-Archive -LiteralPath $zip -DestinationPath $extract -Force
    $found = Get-ChildItem $extract -Filter caddy.exe -Recurse -File | Select-Object -First 1
    if (-not $found) { Gagal "caddy.exe tidak ditemukan di dalam paket unduhan." }
    Move-Item -LiteralPath $found.FullName -Destination $exe -Force
    Remove-Item -Recurse -Force $extract
    Remove-Item -LiteralPath $zip -Force -ErrorAction SilentlyContinue
    return $exe
}

Langkah "Memeriksa PHP dan proyek Laravel"
$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCmd) { Gagal "php tidak ditemukan di PATH. XAMPP: tambahkan C:\xampp\php ke PATH. Laragon: buka Terminal dari Laragon." }
$php = $phpCmd.Source
if (-not (Test-Path (Join-Path $Root "artisan"))) { Gagal "artisan tidak ditemukan. Jalankan skrip ini dari folder proyek Laravel yang lengkap." }
if (-not (Test-Path (Join-Path $Root ".env"))) { Gagal ".env belum ada. Jalankan setup.bat terlebih dahulu." }
$envText = Get-Content (Join-Path $Root ".env") -Raw
if ($envText -notmatch "(?m)^APP_KEY=\S+") { Gagal "APP_KEY masih kosong. Jalankan: php artisan key:generate" }
$openssl = & $php -r "echo extension_loaded('openssl') ? '1' : '0';"
if ($openssl -ne "1") { Gagal "Ekstensi openssl PHP belum aktif. Aktifkan di php.ini lalu coba lagi." }
Info "PHP siap."

Langkah "Menentukan alamat jaringan"
if (-not $Ip) { $Ip = CariIpLan }
if (-not $Ip) {
    Peringatan "IP LAN tidak terdeteksi. Hanya localhost yang akan tersedia; ponsel belum bisa terhubung."
}

$namaKomputer = $env:COMPUTERNAME
$hosts = New-Object System.Collections.Generic.List[string]
foreach ($h in @("localhost", "127.0.0.1", $Ip, $namaKomputer)) {
    if ($h -and $h -match '^[A-Za-z0-9][A-Za-z0-9.-]*$' -and -not $hosts.Contains($h)) { $hosts.Add($h) }
}

if (PortTerpakai $AppPort) {
    $awal = $AppPort
    while (PortTerpakai $AppPort -and $AppPort -lt ($awal + 20)) { $AppPort++ }
    if (PortTerpakai $AppPort) { Gagal "Port $awal-$AppPort semuanya terpakai. Tutup aplikasi lain atau jalankan dengan -AppPort 8010." }
    Peringatan "Port $awal sudah dipakai. Laravel akan memakai port $AppPort."
}
if (PortTerpakai $Port) {
    $awal = $Port
    while (PortTerpakai $Port -and $Port -lt ($awal + 20)) { $Port++ }
    if (PortTerpakai $Port) { Gagal "Port $awal-$Port semuanya terpakai. Tutup aplikasi lain atau jalankan dengan -Port 9443." }
    Peringatan "Port $awal sudah dipakai. HTTPS akan memakai port $Port."
}

if ($Ip) { Info "IP LAN       : $Ip" }
if ($namaKomputer) { Info "Nama komputer: $namaKomputer" }
Info "HTTPS        : port $Port"
Info "Laravel      : http://127.0.0.1:$AppPort"

Langkah "Menyiapkan Caddy"
$caddy = Get-Command caddy -ErrorAction SilentlyContinue
if ($caddy) {
    $caddyExe = $caddy.Source
    Info "Memakai Caddy dari PATH: $caddyExe"
} else {
    $caddyExe = UnduhCaddy
    Info "Caddy siap: $caddyExe"
}
& $caddyExe version | Out-Null
if ($LASTEXITCODE -ne 0) { Gagal "Caddy tidak bisa dijalankan. Coba pasang ulang dari https://caddyserver.com/download." }

$alamat = ($hosts | ForEach-Object { "https://${_}:$Port" }) -join ", "
$storage = (Join-Path $SslDir ".caddy").Replace('\', '/')
$caddyfile = Join-Path $SslDir "Caddyfile"
$caddyText = @"
{
    admin off
    skip_install_trust
    auto_https disable_redirects
    storage file_system "$storage"
}

$alamat {
    tls internal
    reverse_proxy 127.0.0.1:$AppPort {
        header_up X-Forwarded-Proto https
    }
}
"@
[System.IO.File]::WriteAllText($caddyfile, $caddyText, (New-Object System.Text.UTF8Encoding($false)))
$eap = $ErrorActionPreference
$ErrorActionPreference = "Continue"
& $caddyExe validate --config $caddyfile --adapter caddyfile 2>&1 | Out-Null
$kodeCaddy = $LASTEXITCODE
$ErrorActionPreference = $eap
if ($kodeCaddy -ne 0) { Gagal "Konfigurasi Caddy tidak valid. Lihat ssl\Caddyfile." }
Info "Konfigurasi Caddy: ssl\Caddyfile"

Langkah "Menjalankan Laravel dan HTTPS"
$env:APP_URL = "https://$(if ($Ip) { $Ip } else { 'localhost' }):$Port"
$env:FORCE_HTTPS = "true"
$env:TRUSTED_PROXIES = "127.0.0.1,::1"
$env:SESSION_SECURE_COOKIE = "true"
$env:SECURITY_HSTS = "false"
$env:PHP_CLI_SERVER_WORKERS = "4"

# Pastikan konfigurasi yang di-cache tidak menimpa pengaturan HTTPS di atas.
& $php artisan optimize:clear 2>&1 | Out-Null

$phpProc = Start-Process -FilePath $php -ArgumentList @("artisan", "serve", "--host=127.0.0.1", "--port=$AppPort") -WorkingDirectory $Root -NoNewWindow -PassThru
$caddyOut = Join-Path $Root "storage\logs\https-caddy.log"
$caddyErr = Join-Path $Root "storage\logs\https-caddy-error.log"
New-Item -ItemType Directory -Force -Path (Split-Path -Parent $caddyOut) | Out-Null

try {
    $siap = $false
    for ($i = 0; $i -lt 40; $i++) {
        Start-Sleep -Milliseconds 500
        if ($phpProc.HasExited) { break }
        if (PortTerpakai $AppPort) { $siap = $true; break }
    }
    if (-not $siap) {
        if ($phpProc -and -not $phpProc.HasExited) { & taskkill /PID $phpProc.Id /T /F 2>&1 | Out-Null }
        Gagal "Laravel tidak mau berjalan di port $AppPort. Coba jalankan 'php artisan serve' manual untuk melihat pesannya."
    }

    $caddyProc = Start-Process -FilePath $caddyExe -ArgumentList @("run", "--config", $caddyfile, "--adapter", "caddyfile") -WorkingDirectory $Root -PassThru -WindowStyle Hidden -RedirectStandardOutput $caddyOut -RedirectStandardError $caddyErr

    $siap = $false
    for ($i = 0; $i -lt 40; $i++) {
        Start-Sleep -Milliseconds 500
        if ($caddyProc.HasExited) { break }
        if (PortTerpakai $Port) { $siap = $true; break }
    }
    if (-not $siap) {
        Write-Host "`n--- log Caddy ---" -ForegroundColor DarkGray
        Get-Content $caddyErr -Tail 20 -ErrorAction SilentlyContinue
        if ($caddyProc -and -not $caddyProc.HasExited) { Stop-Process -Id $caddyProc.Id -Force -ErrorAction SilentlyContinue }
        if ($phpProc -and -not $phpProc.HasExited) { & taskkill /PID $phpProc.Id /T /F 2>&1 | Out-Null }
        Gagal "Caddy tidak bisa membuka port $Port. Pastikan port tidak dipakai aplikasi lain."
    }

    Start-Sleep -Seconds 2
    $caSumber = Join-Path $SslDir ".caddy\pki\authorities\local\root.crt"
    $caTujuan = Join-Path $SslDir "caddy-root-ca.crt"
    if (Test-Path $caSumber) {
        Copy-Item -LiteralPath $caSumber -Destination $caTujuan -Force
        Write-Host "`nSertifikat root lokal disalin ke: ssl\caddy-root-ca.crt" -ForegroundColor Green
        $certutil = Join-Path $env:SystemRoot "System32\certutil.exe"
        if (Test-Path $certutil) {
            $pasang = Start-Process -FilePath $certutil -ArgumentList @("-user", "-addstore", "Root", $caTujuan) -Wait -PassThru -WindowStyle Hidden
            if ($pasang.ExitCode -eq 0) {
                Write-Host "Sertifikat root dipasang untuk pengguna Windows ini." -ForegroundColor Green
            } else {
                Peringatan "Sertifikat root belum terpasang otomatis. Klik dua kali ssl\caddy-root-ca.crt dan pilih Trusted Root Certification Authorities."
            }
        }
    } else {
        Peringatan "File root CA Caddy belum ditemukan. Jika browser memberi peringatan, impor ssl\caddy-root-ca.crt secara manual."
    }

    Write-Host "`nHTTPS AKTIF" -ForegroundColor Green
    Write-Host "  Komputer ini : https://localhost:$Port"
    if ($Ip) { Write-Host "  Ponsel/tablet: https://${Ip}:$Port" -ForegroundColor Yellow }
    if ($namaKomputer) { Write-Host "  Nama komputer: https://${namaKomputer}:$Port" }
    Write-Host ""
    Write-Host "Jika ponsel menampilkan peringatan sertifikat:" -ForegroundColor Yellow
    Write-Host "  1. Kirim file ssl\caddy-root-ca.crt ke ponsel."
    Write-Host "  2. Android: Settings > Security > Encryption & credentials > Install a certificate > CA certificate."
    Write-Host "     iPhone/iPad: buka file, Settings > General > VPN & Device Management, lalu aktifkan full trust."
    Write-Host "  3. Jika Windows Firewall bertanya, izinkan Caddy untuk jaringan privat."
    Write-Host ""
    Write-Host "Tekan Ctrl+C untuk menghentikan HTTPS." -ForegroundColor DarkGray

    if (-not $TanpaBrowser) { Start-Process "https://localhost:$Port" | Out-Null }

    Wait-Process -Id $caddyProc.Id
} finally {
    if ($caddyProc -and -not $caddyProc.HasExited) { Stop-Process -Id $caddyProc.Id -Force -ErrorAction SilentlyContinue }
    if ($phpProc -and -not $phpProc.HasExited) {
        & taskkill /PID $phpProc.Id /T /F 2>&1 | Out-Null
    }
}

