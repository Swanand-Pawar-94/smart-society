@echo off
setlocal enabledelayedexpansion

:: ============================================================
:: Smart Society - Physical Android Device via USB (ADB Reverse)
:: ============================================================
:: Uses ADB reverse to tunnel phone:8000 -> PC:8000 over USB cable.
:: The phone reaches Laravel at 127.0.0.1:8000/api via the tunnel.
:: No Wi-Fi router or LAN configuration needed.
:: ============================================================

set ADB_CUSTOM=
set FLUTTER=flutter

echo ============================================================
echo   Smart Society - Mobile Launcher [USB / ADB Reverse Mode]
echo ============================================================
echo.

:: -- STEP 1: Locate adb.exe -----------------------------------
set ADB=
if defined ADB_CUSTOM if exist "%ADB_CUSTOM%" set ADB=%ADB_CUSTOM%
if not defined ADB where adb.exe >nul 2>&1 && set ADB=adb.exe
if not defined ADB if exist "%LOCALAPPDATA%\Android\Sdk\platform-tools\adb.exe" set ADB=%LOCALAPPDATA%\Android\Sdk\platform-tools\adb.exe
if not defined ADB if exist "%USERPROFILE%\AppData\Local\Android\Sdk\platform-tools\adb.exe" set ADB=%USERPROFILE%\AppData\Local\Android\Sdk\platform-tools\adb.exe
if not defined ADB if exist "C:\Android\platform-tools\adb.exe" set ADB=C:\Android\platform-tools\adb.exe

if not defined ADB (
    echo [ERROR] adb.exe could not be found automatically!
    echo.
    echo Please ensure Android SDK Platform-Tools is installed.
    echo Default location:
    echo   %%LOCALAPPDATA%%\Android\Sdk\platform-tools\adb.exe
    echo.
    echo You can also open this BAT file and set ADB_CUSTOM=... at the top.
    echo.
    pause
    exit /b 1
)

echo [OK] Using ADB: "%ADB%"
echo.

:: -- STEP 2: Detect Connected Android Device ------------------
echo [STEP 2] Checking for connected Android device...
"%ADB%" devices
echo.

set DEVICE_ID=
for /f "skip=1 tokens=1,2" %%a in ('"%ADB%" devices') do (
    if "%%b"=="device" (
        set DEVICE_ID=%%a
    )
)

if not defined DEVICE_ID (
    echo [ERROR] No Android device detected!
    echo.
    echo Checklist:
    echo   1. Connect your phone using a USB cable.
    echo   2. Enable USB Debugging in phone Developer Options.
    echo   3. Accept the "Allow USB debugging?" prompt on the phone screen.
    echo   4. If prompted, select "File Transfer / Android Auto" mode.
    echo.
    pause
    exit /b 1
)

echo [OK] Detected Device: %DEVICE_ID%
echo.

:: -- STEP 3: Setup ADB Reverse Tunnel -------------------------
echo [STEP 3] Setting up port forward (phone:8000 -> PC:8000)...
"%ADB%" -s %DEVICE_ID% reverse tcp:8000 tcp:8000
if %errorlevel% neq 0 (
    echo [ERROR] Failed to run adb reverse.
    echo         Please reconnect the USB cable and try again.
    pause
    exit /b 1
)
echo [OK] ADB reverse active.
"%ADB%" reverse --list
echo.

:: -- STEP 4: Verify Laravel on Localhost:8000 -----------------
echo [STEP 4] Checking Laravel API on localhost:8000...
powershell -NoProfile -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/health' -UseBasicParsing -TimeoutSec 3; Write-Host '[OK] Laravel responded: HTTP' $r.StatusCode } catch { Write-Host '[WARNING] Laravel is not responding on localhost:8000'; Write-Host '         Starting Laravel on 0.0.0.0:8000...'; Start-Process cmd -ArgumentList '/c cd /d D:\SmartSociety\backend && php artisan serve --host=0.0.0.0 --port=8000' -WindowStyle Minimized; Start-Sleep -Seconds 3 }"
echo.

:: -- STEP 5: Launch Flutter in USB Mode -----------------------
echo ============================================================
echo  Starting Flutter in USB / ADB Reverse Mode
echo  API URL  : http://127.0.0.1:8000/api
echo  Device   : %DEVICE_ID%
echo ============================================================
echo.

flutter run -d %DEVICE_ID% --dart-define=API_BASE_URL=http://127.0.0.1:8000/api --dart-define=API_ENV=usb

endlocal