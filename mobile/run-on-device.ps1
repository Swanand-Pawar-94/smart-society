# Smart Society Flutter App - Run on Physical Device
# This script runs the Flutter app with the correct API URL for physical devices

param(
    [string]$BackendIP = ""
)

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "   Smart Society Mobile App - Physical Device" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Try to detect backend IP if not provided
if ([string]::IsNullOrEmpty($BackendIP)) {
    $BackendIP = (Get-WmiObject -Class Win32_NetworkAdapterConfiguration | Where-Object { $_.IPEnabled -eq $true -and $_.IPAddress -match '^192\.168\.|^10\.|^172\.(1[6-9]|2[0-9]|3[01])\.' } | Select-Object -First 1).IPAddress | Where-Object { $_ -match '^\d+\.\d+\.\d+\.\d+$' } | Select-Object -First 1
    
    if ([string]::IsNullOrEmpty($BackendIP)) {
        Write-Host "Error: Could not detect backend IP address" -ForegroundColor Red
        Write-Host "Please provide the backend IP manually:" -ForegroundColor Yellow
        Write-Host "  .\run-on-device.ps1 -BackendIP 192.168.1.X" -ForegroundColor White
        Write-Host ""
        exit 1
    }
}

$ApiUrl = "http://${BackendIP}:8000/api"

Write-Host "Backend API URL: $ApiUrl" -ForegroundColor Green
Write-Host ""
Write-Host "Make sure:" -ForegroundColor Yellow
Write-Host "  1. Backend is running (see backend\start-server.ps1)" -ForegroundColor White
Write-Host "  2. Physical device is connected via USB with debugging enabled" -ForegroundColor White
Write-Host "  3. Device is on the same WiFi network as your PC" -ForegroundColor White
Write-Host ""
Write-Host "Testing backend connectivity..." -ForegroundColor Yellow

# Test if backend is reachable
try {
    $response = Invoke-WebRequest -Uri "http://${BackendIP}:8000/api/health" -TimeoutSec 5 -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Host "✓ Backend is reachable!" -ForegroundColor Green
    }
} catch {
    Write-Host "✗ Warning: Cannot reach backend at http://${BackendIP}:8000" -ForegroundColor Yellow
    Write-Host "  The app will likely fail to connect." -ForegroundColor Yellow
    Write-Host "  Please ensure the backend server is running." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Starting Flutter app..." -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Run Flutter with custom API URL
flutter run --dart-define=API_BASE_URL=$ApiUrl
