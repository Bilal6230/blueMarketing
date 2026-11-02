import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiException implements Exception {
  final int status;
  final String message;
  const ApiException(this.status, this.message);
  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({required this.baseUrl, required this.tokenReader, http.Client? client})
      : _client = client ?? http.Client();

  final String baseUrl;
  final Future<String?> Function() tokenReader;
  final http.Client _client;

  Future<Map<String, dynamic>> request(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
  }) async {
    final token = await tokenReader();
    final uri = Uri.parse('${baseUrl.replaceAll(RegExp(r'/+$'), '')}/api/v1/mobile/${path.replaceFirst(RegExp(r'^/+'), '')}')
        .replace(queryParameters: query);
    final req = http.Request(method, uri)
      ..headers['Accept'] = 'application/json'
      ..headers['Content-Type'] = 'application/json';
    if (token != null && token.isNotEmpty) {
      req.headers['Authorization'] = 'Bearer $token';
    }
    if (body != null) req.body = jsonEncode(body);
    final response = await http.Response.fromStream(await _client.send(req));
    final decoded = jsonDecode(response.body);
    if (decoded is! Map<String, dynamic>) {
      throw ApiException(response.statusCode, 'Invalid server response.');
    }
    if (response.statusCode >= 400 || decoded['status'] == false) {
      throw ApiException(response.statusCode, decoded['message']?.toString() ?? 'Request failed.');
    }
    return decoded;
  }

  void close() => _client.close();
}
