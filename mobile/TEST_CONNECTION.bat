@echo off
echo ========================================
echo Testing Backend Connection
echo ========================================
echo.

REM Get PC IP address
echo Finding PC IP address...
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address"') do (
    set IP=%%a
    set IP=!IP:~1!
    goto :found
)
:found
echo PC IP Address: %IP%
echo.

REM Read current config
echo Checking Flutter API configuration...
findstr "_developmentBaseUrl" lib\core\api_config.dart
echo.

REM Test backend health endpoint
echo Testing backend health endpoint...
echo URL: http://localhost:8000/api/health
echo.
curl -s http://localhost:8000/api/health
echo.
echo.

echo ========================================
echo Connection Test Complete
echo ========================================
echo.
echo Next Steps:
echo 1. Ensure backend shows "status": "ok" above
echo 2. Update api_config.dart with your IP: %IP%
echo 3. Run: flutter clean
echo 4. Run: flutter pub get  
echo 5. Run: flutter run
echo.
pause
