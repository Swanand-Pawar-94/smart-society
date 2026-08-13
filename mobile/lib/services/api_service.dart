import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../core/api_config.dart';

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode});
  final String message;
  final int? statusCode;
}

class ApiService {
  ApiService({http.Client? client}) : _client = client ?? http.Client();
  final http.Client _client;
  String? token;
  Future<void> Function()? onUnauthorized;

  Future<Map<String, dynamic>> get(String path) => _request('GET', path);
  Future<Map<String, dynamic>> post(String path, Map<String, dynamic> body) =>
      _request('POST', path, body);
  Future<Map<String, dynamic>> patch(String path, Map<String, dynamic> body) =>
      _request('PATCH', path, body);
  Future<Map<String, dynamic>> put(String path, Map<String, dynamic> body) =>
      _request('PUT', path, body);
  Future<Map<String, dynamic>> delete(String path) => _request('DELETE', path);

  Future<Map<String, dynamic>> _request(String method, String path,
      [Map<String, dynamic>? body]) async {
    final request = http.Request(
        method, Uri.parse('${ApiConfig.baseUrl}/$path'))
      ..headers.addAll(
          {'Accept': 'application/json', 'Content-Type': 'application/json'})
      ..body = body == null ? '' : jsonEncode(body);
    if (token != null) request.headers['Authorization'] = 'Bearer $token';
    try {
      final streamed =
          await _client.send(request).timeout(const Duration(seconds: 15));
      final response = await http.Response.fromStream(streamed);
      final decoded = response.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode >= 200 && response.statusCode < 300) {
        return decoded;
      }
      if (response.statusCode == 401) {
        await onUnauthorized?.call();
      }
      final errors = decoded['errors'];
      final detail = errors is Map && errors.isNotEmpty
          ? (errors.values.first as List).first.toString()
          : decoded['message']?.toString();
      throw ApiException(detail ?? 'Request failed.',
          statusCode: response.statusCode);
    } on TimeoutException {
      throw const ApiException('The server took too long to respond.');
    } on http.ClientException {
      throw const ApiException(
          'Cannot reach the backend. Check the server address and connection.');
    } on FormatException {
      throw const ApiException('The backend returned an invalid response.');
    }
  }
}
