@echo off
setlocal enabledelayedexpansion

:: ============================================================
:: Smart Society - Physical Android Device via Wi-Fi (LAN Mode)
:: ============================================================
:: Connects physical phone to PC over local Wi-Fi network.
:: Dynamically detects PC's active LAN IPv4 address.
:: Launches Flutter with: --dart-define=API_BASE_URL=http://<PC_IP>:8000/api
:: ============================================================

:: Optional manual override: set MANUAL_IP=192.168.1.9 to force a specific IP
set MANUAL_IP=

echo ============================================================
echo   Smart Society - Mobile Launcher [LAN / Wi-Fi Mode]
echo ============================================================
echo.

:: -- STEP 1: Detect Active PC LAN IPv4 Address ----------------
if defined MANUAL_IP (
    set PC_IP=%MANUAL_IP%
    echo [INFO] Using manually configured IP: %PC_IP%
) else (
    echo [STEP 1] Detecting active PC LAN IPv4 address...
    for /f "usebackq tokens=*" %%i in (`powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias 'Wi-Fi*','Ethernet*' -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -notlike '169.254*' -and $_.IPAddress -notlike '127.*' } | Select-Object -ExpandProperty IPAddress -First 1)"`) do set PC_IP=%%i
)

if not defined PC_IP (
    echo [ERROR] Could not automatically detect active LAN IPv4 address!
    echo.
    echo Please make sure your PC is connected to Wi-Fi or Ethernet.
    echo Alternatively, open this file and set MANUAL_IP at the top.
    echo.
    pause
    exit /b 1
)

echo [OK] Active PC LAN IP: %PC_IP%
set API_URL=http://%PC_IP%:8000/api
echo      API Base URL    : %API_URL%
echo.

:: -- STEP 2: Verify Laravel Backend on Port 8000 --------------
echo [STEP 2] Verifying Laravel API reachable at %API_URL%/health...
powershell -NoProfile -Command "try { $r = Invoke-WebRequest -Uri '%API_URL%/health' -UseBasicParsing -TimeoutSec 3; Write-Host '[OK] Laravel responded: HTTP' $r.StatusCode } catch { Write-Host '[WARNING] Laravel is not responding on %PC_IP%:8000'; Write-Host '         Starting Laravel server on 0.0.0.0:8000...'; Start-Process cmd -ArgumentList '/c cd /d D:\SmartSociety\backend && php artisan serve --host=0.0.0.0 --port=8000' -WindowStyle Minimized; Start-Sleep -Seconds 3 }"
echo.

:: -- STEP 3: Check Connected Android Device -------------------
echo [STEP 3] Checking for connected devices...
flutter devices
echo.

:: -- STEP 4: Launch Flutter App in LAN Mode -------------------
echo ============================================================
echo  Starting Flutter in LAN Mode
echo  Target URL: %API_URL%
echo  Ensure phone is connected to the SAME Wi-Fi network!
echo ============================================================
echo.

flutter run --dart-define=API_BASE_URL=%API_URL% --dart-define=API_ENV=lan

endlocal