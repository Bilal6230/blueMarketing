import 'package:flutter_test/flutter_test.dart';
import 'package:bluemarketing_employee/core/module_access.dart';

void main() {
  test('basic employees see shared modules only', () {
    expect(ModuleAccess.visible([], []), ['Attendance', 'Projects', 'Profile']);
  });
  test('CRM permission reveals CRM', () {
    expect(ModuleAccess.allowed('CRM', ['read lead'], []), isTrue);
    expect(ModuleAccess.allowed('Stock', ['read lead'], []), isFalse);
  });
  test('superadmin sees all available modules', () {
    expect(ModuleAccess.visible([], ['superadmin']).length, 9);
  });
}
