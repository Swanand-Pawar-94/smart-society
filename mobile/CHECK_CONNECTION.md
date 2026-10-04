# Backend Connection Troubleshooting Guide

## Quick Connection Test

### 1. Check Your PC's IP Address

**Windows:**
```cmd
ipconfig
```

Look for `IPv4 Address` under your active network adapter (Wi-Fi or Ethernet).

**Example output:**
```
IPv4 Address. . . . . . . . . . . : 192.168.29.18
```

### 2. Update API Configuration

If your IP address is different from `192.168.29.18`, update the file:

**File:** `lib/core/api_config.dart`

```dart
static const String _developmentBaseUrl = 'http://YOUR_IP_HERE:8000/api';
```

Replace `YOUR_IP_HERE` with your actual IP address.

### 3. Start Laravel Backend

**IMPORTANT:** Start the backend with `--host=0.0.0.0` to allow external connections:

```bash
cd D:\SmartSociety\backend
php artisan serve --host=0.0.0.0 --port=8000
```

**You should see:**
```
Laravel development server started: http://0.0.0.0:8000
```

### 4. Verify Backend is Accessible

From your PC, open a browser and go to:
```
http://192.168.29.18:8000/api
```

Replace `192.168.29.18` with your actual IP.

**Expected:** You should see a JSON response or Laravel welcome page.

### 5. Test from Phone's Browser (Before Running App)

1. Connect your phone to the same Wi-Fi network as your PC
2. Open Chrome/Safari on your phone
3. Go to: `http://192.168.29.18:8000/api` (use your actual IP)

**If this works:** Your network is configured correctly, proceed to step 6.

**If this fails:** Network issue, see troubleshooting below.

### 6. Run Flutter App

```bash
cd D:\SmartSociety\mobile
flutter clean
flutter pub get
flutter run
```

---

## Common Issues and Solutions

### Issue 1: "Connection timed out"

**Causes:**
- Backend not running
- Wrong IP address
- Phone and PC on different networks
- Firewall blocking connection

**Solutions:**

1. **Verify backend is running:**
   ```bash
   # Make sure you see this running:
   php artisan serve --host=0.0.0.0 --port=8000
   ```

2. **Check IP address is correct:**
   ```cmd
   ipconfig
   ```
   Update `api_config.dart` if IP changed.

3. **Ensure same network:**
   - PC and phone must be on the SAME Wi-Fi network
   - Check Wi-Fi name on both devices

4. **Check Windows Firewall:**
   ```cmd
   # Run as Administrator
   netsh advfirewall firewall add rule name="Laravel Dev Server" dir=in action=allow protocol=TCP localport=8000
   ```

5. **Test with curl from phone (if Termux installed):**
   ```bash
   curl http://192.168.29.18:8000/api
   ```

### Issue 2: "Cannot connect to backend server"

**Causes:**
- Backend stopped running
- Port 8000 is blocked
- PC's IP address changed

**Solutions:**

1. **Restart backend with correct host:**
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```

2. **Check if port 8000 is in use:**
   ```cmd
   netstat -ano | findstr :8000
   ```

3. **Get fresh IP address:**
   ```cmd
   ipconfig
   ```
   Update if changed.

### Issue 3: "Network error" or "SocketException"

**Causes:**
- No internet connection
- Wi-Fi disabled
- Airplane mode on

**Solutions:**

1. **Check phone's Wi-Fi:**
   - Ensure Wi-Fi is enabled
   - Connected to same network as PC

2. **Check PC's network:**
   - Ensure PC is connected to Wi-Fi/Ethernet
   - Try pinging PC from another device

3. **Restart network:**
   - Turn Wi-Fi off and on (on phone)
   - Disconnect and reconnect to network

### Issue 4: Backend returns 404 for API routes

**Causes:**
- API routes not registered
- Wrong API path

**Solutions:**

1. **Check Laravel routes:**
   ```bash
   cd backend
   php artisan route:list | findstr api
   ```

2. **Verify API routes exist:**
   - Should see routes like `/api/auth/login`, `/api/resident/dashboard`, etc.

3. **Check `.htaccess` or nginx config if deployed**

---

## Network Configuration Checklist

### ✅ PC Setup
- [ ] Laravel backend running: `php artisan serve --host=0.0.0.0 --port=8000`
- [ ] Firewall allows port 8000
- [ ] PC is on Wi-Fi/Ethernet (not airplane mode)
- [ ] PC's IP address is known: Run `ipconfig`

### ✅ Phone Setup
- [ ] Connected to same Wi-Fi network as PC
- [ ] Wi-Fi enabled (not mobile data only)
- [ ] Can browse internet (general connectivity works)
- [ ] Airplane mode OFF

### ✅ App Configuration
- [ ] `api_config.dart` has correct IP address
- [ ] `AndroidManifest.xml` has `usesCleartextTraffic="true"` ✅ (already configured)
- [ ] App rebuilt after IP change: `flutter clean && flutter pub get && flutter run`

---

## Testing Steps

### Step 1: Network Connectivity Test

**On PC:**
```bash
# Start backend
cd D:\SmartSociety\backend
php artisan serve --host=0.0.0.0 --port=8000
```

**On PC's browser:**
```
http://192.168.29.18:8000/api
```
Should show JSON or Laravel page.

**On phone's browser:**
```
http://192.168.29.18:8000/api
```
Should show same result.

**Result:**
- ✅ Both work → Network is good, proceed to Step 2
- ❌ PC works, phone fails → Network/firewall issue, see troubleshooting
- ❌ Both fail → Backend not running or wrong IP

### Step 2: API Endpoint Test

**Test login endpoint from PC:**
```bash
curl -X POST http://192.168.29.18:8000/api/auth/login ^
  -H "Content-Type: application/json" ^
  -d "{\"login\":\"test@example.com\",\"password\":\"password\",\"device_name\":\"test\"}"
```

**Expected:** JSON response (success or error message, not HTML).

### Step 3: Flutter App Test

1. Ensure `api_config.dart` has correct IP
2. Run:
   ```bash
   flutter clean
   flutter pub get
   flutter run
   ```
3. Try to login in the app
4. Check Flutter console for detailed error messages

---

## Advanced Troubleshooting

### Enable Detailed Logging

The app already has detailed logging in `api_service.dart`:

```dart
print('[ApiService] Executing $method request to: $uri');
```

Check Flutter console when making API calls to see:
- Exact URL being called
- Request method
- Detailed error messages

### Check Flutter Console Output

When a request fails, look for:
```
[ApiService] Executing POST request to: http://192.168.29.18:8000/api/auth/login
```

This shows the exact URL. Verify:
1. IP address is correct
2. Port is 8000
3. Path includes `/api`

### Verify Backend Logs

In Laravel backend terminal, you should see incoming requests:
```
[timestamp] POST /api/auth/login
```

If you don't see this, the request isn't reaching the backend.

### Check Android Logcat

```bash
flutter logs
```

Look for network-related errors or exceptions.

---

## Production Deployment Notes

For production:

1. **Update API URL:**
   ```dart
   static const String _productionBaseUrl = 'https://api.smartsociety.com/api';
   ```

2. **Remove cleartext traffic:**
   In `AndroidManifest.xml`, you can use network security config:
   ```xml
   <application
       android:networkSecurityConfig="@xml/network_security_config"
       android:usesCleartextTraffic="false">
   ```

3. **Use environment variables:**
   ```bash
   flutter run --dart-define=API_BASE_URL=https://api.smartsociety.com/api
   ```

4. **Enable HTTPS:**
   - Use proper SSL certificates
   - Never use HTTP in production

---

## Quick Reference

### Current Configuration

**API Base URL:** `http://192.168.29.18:8000/api`

**Configuration File:** `lib/core/api_config.dart`

**Android Permissions:** Already configured in `AndroidManifest.xml`

### Key Commands

**Check PC IP:**
```cmd
ipconfig
```

**Start Backend:**
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

**Rebuild Flutter App:**
```bash
flutter clean
flutter pub get
flutter run
```

**Allow Firewall:**
```cmd
netsh advfirewall firewall add rule name="Laravel Dev Server" dir=in action=allow protocol=TCP localport=8000
```

---

## Still Having Issues?

If none of the above solutions work:

1. **Verify network setup:**
   - Ping PC from phone (use network tools app)
   - Check router settings (AP isolation disabled)

2. **Try different port:**
   ```bash
   php artisan serve --host=0.0.0.0 --port=8080
   ```
   Update `api_config.dart` to use port 8080.

3. **Check VPN/Proxy:**
   - Disable VPN on PC or phone
   - Disable proxy settings

4. **Test with emulator first:**
   - Android Emulator: Use `http://10.0.2.2:8000/api`
   - iOS Simulator: Use `http://127.0.0.1:8000/api`

5. **Check antivirus:**
   - Temporarily disable antivirus
   - Add exception for port 8000

---

## Success Checklist

When everything works, you should see:

- ✅ Backend running on `http://0.0.0.0:8000`
- ✅ PC browser can access `http://192.168.29.18:8000/api`
- ✅ Phone browser can access `http://192.168.29.18:8000/api`
- ✅ Flutter app connects successfully
- ✅ Login works in Flutter app
- ✅ No connection timeout errors

---

**Last Updated:** August 19, 2026  
**Current IP:** 192.168.29.18  
**Backend Port:** 8000  
**Status:** Ready for Physical Device Testing
