@echo off
echo =====================================================
echo  Smart Society - Laravel Development Server
echo =====================================================
echo.
echo Checking current Wi-Fi IP...
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /i "IPv4" ^| findstr "192.168"') do set WIFI_IP=%%a
set WIFI_IP=%WIFI_IP: =%
echo PC Wi-Fi IP: %WIFI_IP%
echo.
echo Killing any existing PHP on port 8000...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8000 "') do taskkill /PID %%a /F 2>nul
timeout /t 1 /nobreak >nul
echo.
echo Starting Laravel on 0.0.0.0:8000 (all interfaces)...
echo Phone browser test URL: http://%WIFI_IP%:8000/api/ping
echo.
cd /d %~dp0
php artisan serve --host=0.0.0.0 --port=8000