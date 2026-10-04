# Smart Society Flutter App - Run on Android Emulator
# This script runs the Flutter app with the correct API URL for Android emulator

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "   Smart Society Mobile App - Android Emulator" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# For Android emulator, use 10.0.2.2 to access host machine's localhost
$ApiUrl = "http://10.0.2.2:8000/api"

Write-Host "Backend API URL: $ApiUrl" -ForegroundColor Green
Write-Host "(10.0.2.2 is the special alias for localhost on Android emulator)" -ForegroundColor Gray
Write-Host ""
Write-Host "Make sure:" -ForegroundColor Yellow
Write-Host "  1. Backend is running on localhost:8000 (see backend\start-server.ps1)" -ForegroundColor White
Write-Host "  2. Android emulator is started" -ForegroundColor White
Write-Host ""
Write-Host "Testing backend connectivity..." -ForegroundColor Yellow

# Test if backend is reachable from host
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8000/api/health" -TimeoutSec 5 -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Host "✓ Backend is reachable from host!" -ForegroundColor Green
    }
} catch {
    Write-Host "✗ Warning: Cannot reach backend at http://localhost:8000" -ForegroundColor Yellow
    Write-Host "  The app will likely fail to connect." -ForegroundColor Yellow
    Write-Host "  Please ensure the backend server is running." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Starting Flutter app..." -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Run Flutter with emulator API URL
flutter run --dart-define=API_BASE_URL=$ApiUrl
