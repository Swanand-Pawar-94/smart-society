@echo off
echo ========================================
echo Adding Windows Firewall Rule
echo ========================================
echo.
echo This will allow incoming connections on port 8000
echo for the Laravel development server.
echo.
echo Note: This requires Administrator privileges
echo Right-click this file and select "Run as administrator"
echo.
pause

netsh advfirewall firewall add rule name="Laravel Dev Server - Port 8000" dir=in action=allow protocol=TCP localport=8000

echo.
echo ========================================
echo Firewall rule added successfully!
echo ========================================
echo.
echo Port 8000 is now accessible from other devices on your network.
echo.
echo Next steps:
echo 1. Start the backend server: START_SERVER.bat
echo 2. Update Flutter API config with your PC's IP
echo 3. Run Flutter app on physical device
echo.
pause
