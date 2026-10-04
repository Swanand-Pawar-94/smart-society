# Flutter Production Build Reference

## API URL Configuration

The Flutter app uses a centralized `ApiConfig` located in [mobile/lib/core/api_config.dart](file:///d:/SmartSociety/mobile/lib/core/api_config.dart).

By default, during development, it uses the development LAN IP (`http://192.168.29.18:8000/api`).

For production deployment, pass your Hostinger API URL at compile time using `--dart-define=API_BASE_URL=...`.

### Release Build Command

```bash
cd mobile
flutter clean
flutter pub get
flutter build apk --release --dart-define=API_BASE_URL=https://api.YOURDOMAIN.com/api
```

### Build App Bundle (for Google Play Store)

```bash
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.YOURDOMAIN.com/api
```

### Production Security Checks

1. `android:usesCleartextTraffic`: In production builds targeting HTTPS, all requests are encrypted over SSL/TLS.
2. `android.permission.INTERNET`: Enabled in `mobile/android/app/src/main/AndroidManifest.xml`.
3. Token storage: Access tokens are saved encrypted in `flutter_secure_storage`.
4. No sensitive passwords or API keys are stored in client-side code.
