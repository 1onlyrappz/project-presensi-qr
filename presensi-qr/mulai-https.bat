@echo off
setlocal
chcp 65001 >nul
title Presensi QR - HTTPS Lokal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0ssl\mulai-https.ps1" %*
set "KODE=%ERRORLEVEL%"
if not "%KODE%"=="0" pause
endlocal
