@echo off
echo ========================================
echo Smart Society Backend Server
echo ========================================
echo.

REM Verify database connection first
call php artisan society:check-db
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Could not connect to MySQL database!
    echo Please make sure XAMPP / MariaDB is running on port 3306.
    echo.
    pause
    exit /b 1
)

echo.
echo SERVER STARTED ON PORT 8000
echo Server is accessible on ALL network interfaces (0.0.0.0:8000)
echo.
echo To find your PC's IP address:
echo   1. Open Command Prompt
echo   2. Type: ipconfig
echo   3. Look for "IPv4 Address" under your active network adapter
echo.
echo Flutter app API config:
echo   File: mobile\lib\core\api_config.dart
echo   Default: http://192.168.1.9:8000/api
echo.
echo If connecting from physical Android phone:
echo   Make sure Windows Firewall allows TCP port 8000
echo   (Run ADD_FIREWALL_RULE.bat as Administrator once)
echo ========================================
echo.

php artisan serve --host=0.0.0.0 --port=8000
