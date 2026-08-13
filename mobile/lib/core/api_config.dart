class ApiConfig {
  const ApiConfig._();

  /// Override at launch: --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://192.168.29.18:8000/api',
  );
}
