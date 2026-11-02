class ModuleAccess {
  static const Map<String, Set<String>> requirements = {
    'CRM': {'read lead'},
    'Stock': {'read stock'},
    'Inventory Reports': {'view inventory'},
    'Project Reports': {'read project'},
    'Approvals': {'can approve'},
    'Labour': {'read labour'},
  };

  static bool allowed(String module, Iterable<String> permissions, Iterable<String> roles) {
    if (module == 'Attendance' || module == 'Profile' || module == 'Projects') return true;
    final grants = permissions.toSet();
    if (roles.contains('superadmin')) return true;
    final required = requirements[module];
    return required != null && required.any(grants.contains);
  }

  static List<String> visible(Iterable<String> permissions, Iterable<String> roles) {
    return ['Attendance', 'Projects', 'CRM', 'Labour', 'Stock',
      'Inventory Reports', 'Project Reports', 'Approvals', 'Profile']
        .where((module) => allowed(module, permissions, roles)).toList();
  }
}
