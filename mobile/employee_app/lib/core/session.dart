import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_client.dart';

class EmployeeSession {
  EmployeeSession(this.client);
  final ApiClient client;
  static const storage = FlutterSecureStorage();
  static const tokenKey = 'employee_access_token';

  Future<Map<String, dynamic>> login(String email, String password, {int? projectId}) async {
    final response = await client.request('POST', 'auth/login', body: {
      'email': email,
      'password': password,
      if (projectId != null) 'project_id': projectId,
    });
    final data = Map<String, dynamic>.from(response['data'] as Map);
    await storage.write(key: tokenKey, value: data['token'] as String);
    return data;
  }

  Future<Map<String, dynamic>> me() async {
    final response = await client.request('GET', 'auth/me');
    return Map<String, dynamic>.from(response['data'] as Map);
  }

  Future<void> logout() async {
    try {
      await client.request('POST', 'auth/logout');
    } finally {
      await storage.delete(key: tokenKey);
    }
  }
}
