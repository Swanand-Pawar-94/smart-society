@echo off
setlocal enabledelayedexpansion

:: ============================================================
:: Smart Society - Android Emulator Launcher
:: ============================================================
:: The standard Android emulator uses 10.0.2.2 to access the
:: development host machine's loopback (127.0.0.1).
:: Launches Flutter with: --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
:: ============================================================

echo ============================================================
echo   Smart Society - Mobile Launcher [Android Emulator Mode]
echo ============================================================
echo.

:: -- STEP 1: Verify Laravel on Localhost:8000 -----------------
echo [STEP 1] Checking Laravel API on localhost:8000...
powershell -NoProfile -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/health' -UseBasicParsing -TimeoutSec 3; Write-Host '[OK] Laravel responded: HTTP' $r.StatusCode } catch { Write-Host '[WARNING] Laravel is not responding on localhost:8000'; Write-Host '         Starting Laravel on 0.0.0.0:8000...'; Start-Process cmd -ArgumentList '/c cd /d D:\SmartSociety\backend && php artisan serve --host=0.0.0.0 --port=8000' -WindowStyle Minimized; Start-Sleep -Seconds 3 }"
echo.

:: -- STEP 2: List Connected Devices ---------------------------
echo [STEP 2] Checking for running emulator...
flutter devices
echo.

:: -- STEP 3: Launch Flutter in Emulator Mode ------------------
echo ============================================================
echo  Starting Flutter in Emulator Mode
echo  API Base URL: http://10.0.2.2:8000/api
echo ============================================================
echo.

flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api --dart-define=API_ENV=emulator

endlocal