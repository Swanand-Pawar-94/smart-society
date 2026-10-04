# Smart Society Backend Startup Script
# This script starts the Laravel development server with correct configuration

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "   Smart Society Backend Server" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Get the local IP address
$localIP = (Get-WmiObject -Class Win32_NetworkAdapterConfiguration | Where-Object { $_.IPEnabled -eq $true -and $_.IPAddress -match '^192\.168\.|^10\.|^172\.(1[6-9]|2[0-9]|3[01])\.' } | Select-Object -First 1).IPAddress | Where-Object { $_ -match '^\d+\.\d+\.\d+\.\d+$' } | Select-Object -First 1

if ($localIP) {
    Write-Host "Local IP Address: $localIP" -ForegroundColor Green
    Write-Host ""
    Write-Host "Backend will be accessible at:" -ForegroundColor Yellow
    Write-Host "  - http://localhost:8000/api" -ForegroundColor White
    Write-Host "  - http://127.0.0.1:8000/api" -ForegroundColor White
    Write-Host "  - http://${localIP}:8000/api" -ForegroundColor White
    Write-Host ""
    Write-Host "For physical Android devices, use:" -ForegroundColor Yellow
    Write-Host "  http://${localIP}:8000/api" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "For Android emulator, use:" -ForegroundColor Yellow
    Write-Host "  http://10.0.2.2:8000/api" -ForegroundColor Cyan
} else {
    Write-Host "Warning: Could not detect LAN IP address" -ForegroundColor Yellow
    Write-Host "Server will still start on 0.0.0.0:8000" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Starting Laravel server on 0.0.0.0:8000..." -ForegroundColor Green
Write-Host ""
Write-Host "Press Ctrl+C to stop the server" -ForegroundColor Gray
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Start the server
php artisan serve --host=0.0.0.0 --port=8000
