# Flutter mobile client

The mobile client is located in `D:\SmartSociety\mobile`. Supply the API base URL using `API_BASE_URL` when running it.

Start Laravel:

```powershell
cd D:\SmartSociety\backend
php artisan serve --host=0.0.0.0 --port=8000
```

Then run the mobile client from `D:\SmartSociety\mobile`:

```powershell
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

`10.0.2.2` targets the development PC from an Android emulator. On a physical device use the PC's LAN IP, such as `http://192.168.1.10:8000/api`; both devices must be on the same network and the firewall must permit port 8000. Do not use `localhost` on a physical device.
