import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'core/api_client.dart';
import 'core/module_access.dart';
import 'core/session.dart';

const apiBaseUrl = String.fromEnvironment('API_BASE_URL');

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const BlueMarketingApp());
}

class BlueMarketingApp extends StatefulWidget {
  const BlueMarketingApp({super.key});
  @override
  State<BlueMarketingApp> createState() => _BlueMarketingAppState();
}

class _BlueMarketingAppState extends State<BlueMarketingApp> {
  late final ApiClient api = ApiClient(
    baseUrl: apiBaseUrl,
    tokenReader: () => EmployeeSession.storage.read(key: EmployeeSession.tokenKey),
  );
  late final EmployeeSession session = EmployeeSession(api);
  Map<String, dynamic>? user;
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    _restore();
  }

  Future<void> _restore() async {
    try {
      final token = await const FlutterSecureStorage().read(key: EmployeeSession.tokenKey);
      if (token != null) user = await session.me();
    } catch (_) {
      await EmployeeSession.storage.delete(key: EmployeeSession.tokenKey);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _login(String email, String password) async {
    setState(() { loading = true; error = null; });
    try {
      await session.login(email, password);
      user = await session.me();
    } catch (e) {
      error = e.toString();
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _logout() async {
    setState(() => loading = true);
    try { await session.logout(); } catch (_) {}
    if (mounted) setState(() { user = null; loading = false; });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'BlueMarketing Employee',
      theme: ThemeData(useMaterial3: true, colorSchemeSeed: Colors.blue),
      home: apiBaseUrl.isEmpty
          ? const Scaffold(body: Center(child: Text('Set API_BASE_URL via --dart-define.')))
          : loading
              ? const Scaffold(body: Center(child: CircularProgressIndicator()))
              : user == null
                  ? LoginScreen(onLogin: _login, error: error)
                  : EmployeeHome(data: user!, onLogout: _logout),
    );
  }

  @override
  void dispose() {
    api.close();
    super.dispose();
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.onLogin, this.error});
  final Future<void> Function(String, String) onLogin;
  final String? error;
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}
class _LoginScreenState extends State<LoginScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Employee Login')),
    body: Center(child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 400),
      child: Padding(padding: const EdgeInsets.all(24), child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(controller: email, keyboardType: TextInputType.emailAddress,
            decoration: const InputDecoration(labelText: 'Email')),
          TextField(controller: password, obscureText: true,
            decoration: const InputDecoration(labelText: 'Password')),
          if (widget.error != null) Text(widget.error!, style: const TextStyle(color: Colors.red)),
          const SizedBox(height: 24),
          FilledButton(onPressed: () => widget.onLogin(email.text.trim(), password.text),
            child: const Text('Log in')),
        ],
      )),
    )),
  );
  @override
  void dispose() { email.dispose(); password.dispose(); super.dispose(); }
}

class EmployeeHome extends StatelessWidget {
  const EmployeeHome({super.key, required this.data, required this.onLogout});
  final Map<String, dynamic> data;
  final VoidCallback onLogout;

  @override
  Widget build(BuildContext context) {
    final permissions = (data['permissions'] as List? ?? []).map((e) => e.toString());
    final roles = (data['role_names'] as List? ?? []).map((e) => e.toString());
    final modules = ModuleAccess.visible(permissions, roles);
    final profile = data['user'] as Map? ?? {};
    return Scaffold(
      appBar: AppBar(title: Text('Welcome, ${profile['name'] ?? 'Employee'}'),
        actions: [IconButton(onPressed: onLogout, icon: const Icon(Icons.logout))]),
      body: ListView(children: [
        for (final module in modules)
          ListTile(title: Text(module), trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => Scaffold(appBar: AppBar(title: Text(module)),
                body: const Center(child: Text('Module screen pending API integration.')))))),
      ]),
    );
  }
}
