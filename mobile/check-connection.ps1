# Smart Society - Backend Connection Checker
# Run this script to verify your PC is ready for physical device testing

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Smart Society Backend Connection Checker" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# 1. Get PC's IP Address
Write-Host "[1/5] Checking PC's IP Address..." -ForegroundColor Yellow
$ipAddress = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object {
    $_.IPAddress -notlike "127.0.0.1" -and 
    $_.IPAddress -notlike "169.254.*" -and
    $_.InterfaceAlias -like "*Wi-Fi*" -or $_.InterfaceAlias -like "*Ethernet*"
} | Select-Object -First 1).IPAddress

if ($ipAddress) {
    Write-Host "   ✓ PC IP Address: $ipAddress" -ForegroundColor Green
} else {
    Write-Host "   ✗ Could not detect IP address" -ForegroundColor Red
    Write-Host "   Run 'ipconfig' manually to find your IPv4 Address" -ForegroundColor Yellow
    $ipAddress = "UNKNOWN"
}

# 2. Check if backend is running
Write-Host "`n[2/5] Checking if Laravel backend is running on port 8000..." -ForegroundColor Yellow
$backendRunning = Get-NetTCPConnection -LocalPort 8000 -ErrorAction SilentlyContinue

if ($backendRunning) {
    Write-Host "   ✓ Backend is running on port 8000" -ForegroundColor Green
} else {
    Write-Host "   ✗ Backend is NOT running on port 8000" -ForegroundColor Red
    Write-Host "   Start it with: php artisan serve --host=0.0.0.0 --port=8000" -ForegroundColor Yellow
}

# 3. Check firewall rule for port 8000
Write-Host "`n[3/5] Checking Windows Firewall for port 8000..." -ForegroundColor Yellow
$firewallRule = Get-NetFirewallRule -DisplayName "Laravel Dev Server" -ErrorAction SilentlyContinue

if ($firewallRule) {
    Write-Host "   ✓ Firewall rule exists for Laravel Dev Server" -ForegroundColor Green
} else {
    Write-Host "   ⚠ Firewall rule not found" -ForegroundColor Yellow
    Write-Host "   To add it, run as Administrator:" -ForegroundColor Yellow
    Write-Host "   netsh advfirewall firewall add rule name=`"Laravel Dev Server`" dir=in action=allow protocol=TCP localport=8000" -ForegroundColor Gray
}

# 4. Check API config file
Write-Host "`n[4/5] Checking Flutter API configuration..." -ForegroundColor Yellow
$apiConfigPath = "lib\core\api_config.dart"

if (Test-Path $apiConfigPath) {
    $apiConfigContent = Get-Content $apiConfigPath -Raw
    
    if ($apiConfigContent -match "static const String _developmentBaseUrl = 'http://([0-9\.]+):8000/api'") {
        $configuredIp = $matches[1]
        
        if ($configuredIp -eq $ipAddress) {
            Write-Host "   ✓ API config has correct IP: $configuredIp" -ForegroundColor Green
        } else {
            Write-Host "   ✗ API config has WRONG IP: $configuredIp (should be $ipAddress)" -ForegroundColor Red
            Write-Host "   Update lib/core/api_config.dart with your current IP" -ForegroundColor Yellow
        }
    } else {
        Write-Host "   ⚠ Could not parse IP from API config" -ForegroundColor Yellow
    }
} else {
    Write-Host "   ✗ API config file not found: $apiConfigPath" -ForegroundColor Red
}

# 5. Test backend accessibility
Write-Host "`n[5/5] Testing backend accessibility..." -ForegroundColor Yellow

if ($ipAddress -ne "UNKNOWN" -and $backendRunning) {
    try {
        $testUrl = "http://$($ipAddress):8000/api"
        $response = Invoke-WebRequest -Uri $testUrl -TimeoutSec 5 -ErrorAction Stop
        Write-Host "   ✓ Backend is accessible at $testUrl" -ForegroundColor Green
        Write-Host "   Response: $($response.StatusCode) $($response.StatusDescription)" -ForegroundColor Gray
    } catch {
        Write-Host "   ✗ Backend is NOT accessible at http://$($ipAddress):8000/api" -ForegroundColor Red
        Write-Host "   Error: $($_.Exception.Message)" -ForegroundColor Gray
    }
} else {
    Write-Host "   ⊗ Skipped (backend not running or IP unknown)" -ForegroundColor Gray
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Summary" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Write-Host "Configuration:" -ForegroundColor White
Write-Host "  PC IP Address:     $ipAddress" -ForegroundColor Gray
Write-Host "  Backend Port:      8000" -ForegroundColor Gray
Write-Host "  API Base URL:      http://$($ipAddress):8000/api" -ForegroundColor Gray

Write-Host "`nNext Steps:" -ForegroundColor White

if (-not $backendRunning) {
    Write-Host "  1. Start Laravel backend:" -ForegroundColor Yellow
    Write-Host "     cd D:\SmartSociety\backend" -ForegroundColor Gray
    Write-Host "     php artisan serve --host=0.0.0.0 --port=8000" -ForegroundColor Gray
}

if ($apiConfigContent -notmatch $ipAddress) {
    Write-Host "  2. Update API config in lib/core/api_config.dart with IP: $ipAddress" -ForegroundColor Yellow
}

if (-not $firewallRule) {
    Write-Host "  3. Add firewall rule (run as Administrator):" -ForegroundColor Yellow
    Write-Host "     netsh advfirewall firewall add rule name=`"Laravel Dev Server`" dir=in action=allow protocol=TCP localport=8000" -ForegroundColor Gray
}

Write-Host "  4. Rebuild Flutter app:" -ForegroundColor Yellow
Write-Host "     flutter clean" -ForegroundColor Gray
Write-Host "     flutter pub get" -ForegroundColor Gray
Write-Host "     flutter run" -ForegroundColor Gray

Write-Host "  5. Test connection from phone browser:" -ForegroundColor Yellow
Write-Host "     Open: http://$($ipAddress):8000/api" -ForegroundColor Gray

Write-Host "`n========================================`n" -ForegroundColor Cyan

# Quick test option
Write-Host "Press any key to test backend with curl (or Ctrl+C to exit)..." -ForegroundColor Cyan
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")

if ($ipAddress -ne "UNKNOWN") {
    Write-Host "`nTesting API endpoint..." -ForegroundColor Yellow
    $testUrl = "http://$($ipAddress):8000/api"
    
    try {
        $result = Invoke-RestMethod -Uri $testUrl -Method Get -TimeoutSec 10
        Write-Host "✓ API Response received!" -ForegroundColor Green
        Write-Host ($result | ConvertTo-Json) -ForegroundColor Gray
    } catch {
        Write-Host "✗ API Test Failed" -ForegroundColor Red
        Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Gray
    }
}

Write-Host "`nDone!`n" -ForegroundColor Cyan
