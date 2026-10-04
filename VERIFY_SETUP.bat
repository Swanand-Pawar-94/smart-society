@echo off
setlocal enabledelayedexpansion
color 0A
echo.
echo ========================================
echo   Smart Society Setup Verification
echo ========================================
echo.

REM Check 1: Find PC IP
echo [1/6] Finding PC IP Address...
for /f "tokens=14" %%a in ('ipconfig ^| findstr /C:"IPv4 Address"') do (
    set PCIP=%%a
    goto :ipfound
)
:ipfound
if defined PCIP (
    echo     ✓ PC IP: %PCIP%
) else (
    echo     ✗ Could not determine IP address
)
echo.

REM Check 2: Verify backend is running
echo [2/6] Checking if backend is running on port 8000...
netstat -an | findstr ":8000.*LISTENING" >nul 2>&1
if %errorlevel% equ 0 (
    echo     ✓ Backend is LISTENING on port 8000
) else (
    echo     ✗ Backend NOT running
    echo     Run: backend\START_SERVER.bat
)
echo.

REM Check 3: Test health endpoint
echo [3/6] Testing backend health endpoint...
curl -s http://localhost:8000/api/health >nul 2>&1
if %errorlevel% equ 0 (
    echo     ✓ Health endpoint responding
    curl -s http://localhost:8000/api/health | findstr "status"
) else (
    echo     ✗ Health endpoint not responding
    echo     Ensure Laravel backend is running
)
echo.

REM Check 4: Verify Flutter config
echo [4/6] Checking Flutter API configuration...
if exist "mobile\lib\core\api_config.dart" (
    echo     ✓ API config file exists
    findstr "_developmentBaseUrl" mobile\lib\core\api_config.dart
) else (
    echo     ✗ API config file not found
)
echo.

REM Check 5: Check Android manifest
echo [5/6] Verifying Android cleartext configuration...
if exist "mobile\android\app\src\main\AndroidManifest.xml" (
    findstr "usesCleartextTraffic" mobile\android\app\src\main\AndroidManifest.xml >nul 2>&1
    if !errorlevel! equ 0 (
        echo     ✓ Cleartext traffic enabled
    ) else (
        echo     ✗ Cleartext traffic NOT enabled
    )
) else (
    echo     ✗ AndroidManifest.xml not found
)
echo.

REM Check 6: Flutter installation
echo [6/6] Checking Flutter installation...
where flutter >nul 2>&1
if %errorlevel% equ 0 (
    echo     ✓ Flutter is installed
) else (
    echo     ✗ Flutter not found in PATH
)
echo.

echo ========================================
echo   Verification Complete
echo ========================================
echo.
echo Next Steps:
echo   1. Update api_config.dart with your IP: %PCIP%
echo   2. Connect phone to same Wi-Fi network
echo   3. Run: cd mobile
echo   4. Run: flutter clean
echo   5. Run: flutter pub get
echo   6. Run: flutter run
echo.
echo Test from phone browser:
echo   http://%PCIP%:8000/api/health
echo.
pause
