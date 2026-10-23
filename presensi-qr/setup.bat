@echo off
REM Pembungkus agar setup.ps1 bisa dijalankan dengan klik ganda.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup.ps1" %*
pause
