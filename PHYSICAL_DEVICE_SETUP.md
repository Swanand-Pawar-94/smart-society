# Physical Android Device Setup Guide

## Current Configuration Status

✅ **API Configuration**: Centralized in `mobile/lib/core/api_config.dart`  
✅ **Current IP**: `192.168.29.18:8000`  
✅ **Android Cleartext**: Enabled in AndroidManifest.xml  
✅ **Network Permissions**: Configured  
✅ **Error Handling**: User-friendly messages implemented  
✅ **No Hardcoded URLs**: All API calls use ApiConfig.baseUrl  

## Quick Start

### Step 1: Find Your PC's IP Address

**Windows:**
```cmd
ipconfig
```

Look for **IPv4 Address** under your active network adapter (e.g., Wi-Fi or Ethernet).

Example output:
```
Wireless LAN adapter Wi-Fi:
   IPv4 Address. . . . . . . . . . . : 192.168.29.18
```

### Step 2: Update Flutter API Configuration

**File**: `mobile/lib/core/api_config.dart`

Update line 17:
```dart
static const String _developmentBaseUrl = 'http://YOUR_IP:8000/api';
```

Replace `YOUR_IP` with your PC's actual IP address:
```dart
static const String _developmentBaseUrl = 'http://192.168.29.18:8000/api';
```

### Step 3: Start Laravel Backend

**Option A: Use the startup script**
```cmd
cd D:\SmartSociety\backend
START_SERVER.bat
```

**Option B: Manual command**
```cmd
cd D:\SmartSociety\backend
php artisan serve --host=0.0.0.0 --port=8000
```

**IMPORTANT**: Must use `--host=0.0.0.0` (not `127.0.0.1`) for physical device access!

### Step 4: Verify Backend is Running

Open browser and visit:
```
http://localhost:8000/api/health
```

Should return:
```json
{
  "status": "ok",
  "service": "Smart Society API",
  "timestamp": "2026-08-19T...",
  "database": "smartsociety"
}
```

### Step 5: Connect Physical Device

1. **Connect phone to same Wi-Fi network as PC**
2. **Rebuild Flutter app**:
   ```cmd
   cd D:\SmartSociety\mobile
   flutter clean
   flutter pub get
   flutter run --dart-define=API_BASE_URL=http://192.168.29.18:8000/api
   ```

## Configuration Details

### API Configuration Architecture

```
mobile/lib/core/api_config.dart
├── _developmentBaseUrl (local testing)
├── _productionBaseUrl (live deployment)
└── baseUrl (active URL, can be overridden)
```

### Configuration Methods

#### Method 1: Edit api_config.dart (Permanent)
Update `_developmentBaseUrl` in `mobile/lib/core/api_config.dart`:
```dart
static const String _developmentBaseUrl = 'http://192.168.29.18:8000/api';
```

#### Method 2: Command Line Override (Temporary)
```cmd
flutter run --dart-define=API_BASE_URL=http://192.168.29.18:8000/api
```

#### Method 3: Build-time Configuration
```cmd
flutter build apk --dart-define=API_BASE_URL=http://your-production-url.com/api
```

### Android Network Configuration

**File**: `android/app/src/main/AndroidManifest.xml`

✅ Already configured:
```xml
<uses-permission android:name="android.permission.INTERNET"/>
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE"/>
<application
    android:usesCleartextTraffic="true">
```

**Note**: `usesCleartextTraffic="true"` allows HTTP (not HTTPS) connections for development. In production, this should be restricted using a network security config.

## Troubleshooting

### Error: "Connection timed out"

**Possible Causes:**
1. ❌ Backend not running
2. ❌ Wrong IP address in api_config.dart
3. ❌ Phone and PC on different networks
4. ❌ Windows Firewall blocking port 8000

**Solutions:**

#### 1. Verify Backend is Running
```cmd
netstat -an | findstr :8000
```
Should show: `TCP    0.0.0.0:8000    ...    LISTENING`

#### 2. Test from Phone Browser
Open phone browser and visit:
```
http://192.168.29.18:8000/api/health
```

If this works → API config issue
If this fails → Network/firewall issue

#### 3. Check Firewall
```cmd
netsh advfirewall firewall add rule name="Laravel Dev Server" dir=in action=allow protocol=TCP localport=8000
```

#### 4. Verify Same Network
**PC IP**: Run `ipconfig` → Note first 3 octets (e.g., `192.168.29.x`)  
**Phone IP**: Settings → Wi-Fi → Current network → IP address

First 3 octets MUST match!

### Error: "Cannot connect to backend server"

**Check:**
1. Laravel server running: `php artisan serve --host=0.0.0.0 --port=8000`
2. Not using `127.0.0.1` or `localhost` in api_config.dart
3. Using actual LAN IP (e.g., `192.168.x.x`)

### Error: "The backend returned an invalid response"

**Check:**
1. Backend returning valid JSON
2. No PHP errors in Laravel console
3. Database connection working
4. `.env` file properly configured

### Connection Works from Browser but Not from App

**Check:**
1. Flutter app rebuilt after changing api_config.dart
2. Correct URL format: `http://IP:8000/api` (with `/api` suffix)
3. No typos in IP address

## Network Security for Production

For production deployment, restrict cleartext traffic:

**File**: `android/app/src/main/res/xml/network_security_config.xml`
```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <!-- Allow cleartext for development only -->
    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="true">192.168.0.0/16</domain>
        <domain includeSubdomains="true">10.0.0.0/8</domain>
    </domain-config>
    
    <!-- Production (HTTPS only) -->
    <domain-config cleartextTrafficPermitted="false">
        <domain includeSubdomains="true">api.smartsociety.com</domain>
    </domain-config>
</network-security-config>
```

Then update AndroidManifest.xml:
```xml
<application
    android:networkSecurityConfig="@xml/network_security_config">
```

## Testing Checklist

- [ ] Backend running on `0.0.0.0:8000`
- [ ] PC IP address updated in `api_config.dart`
- [ ] Phone and PC on same Wi-Fi network
- [ ] Flutter app rebuilt (`flutter clean && flutter pub get`)
- [ ] Health endpoint accessible from phone browser
- [ ] Windows Firewall allows port 8000
- [ ] Login works from Flutter app

## API Endpoints Available

All endpoints are relative to base URL (`http://192.168.29.18:8000/api`)

### Public Endpoints
- `GET /health` - Server health check
- `POST /auth/login` - User login
- `POST /auth/register` - User registration
- `POST /auth/forgot-password` - Password reset request
- `POST /auth/reset-password` - Password reset

### Protected Endpoints (require authentication)
- `GET /resident/dashboard` - Resident dashboard data
- `GET /security/dashboard` - Security dashboard data
- `GET /admin/dashboard` - Admin dashboard data
- `GET /security/visitors` - Visitor management
- `GET /security/parcels` - Parcel management
- And many more...

## Development Workflow

### When Your IP Address Changes

1. Find new IP: `ipconfig`
2. Update `mobile/lib/core/api_config.dart`
3. Rebuild app: `flutter run`

**That's it!** Single configuration point ensures consistency.

### Multiple Developers

Each developer updates their own `api_config.dart` with their PC's IP.

**Option**: Add to `.gitignore`:
```
# Personal API configuration
lib/core/api_config.dart
```

Then create `api_config.dart.example` for reference.

## Command Reference

### Find PC IP
```cmd
ipconfig
```

### Start Backend
```cmd
cd D:\SmartSociety\backend
php artisan serve --host=0.0.0.0 --port=8000
```

### Check Port
```cmd
netstat -an | findstr :8000
```

### Allow Firewall
```cmd
netsh advfirewall firewall add rule name="Laravel Dev Server" dir=in action=allow protocol=TCP localport=8000
```

### Rebuild Flutter
```cmd
cd D:\SmartSociety\mobile
flutter clean
flutter pub get
flutter run
```

### Override URL
```cmd
flutter run --dart-define=API_BASE_URL=http://192.168.29.18:8000/api
```

## Support

If issues persist:

1. Check Laravel logs: `backend/storage/logs/laravel.log`
2. Check Flutter console for detailed error messages
3. Verify phone can ping PC: Use network utility app
4. Temporarily disable Windows Firewall to test

---

**Status**: Configuration is production-ready  
**Current IP**: 192.168.29.18  
**Last Updated**: August 19, 2026
