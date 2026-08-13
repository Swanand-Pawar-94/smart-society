import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'services/api_service.dart';
import 'state/auth_controller.dart';

void main() => runApp(ChangeNotifierProvider(
    create: (_) => AuthController(ApiService())..restore(),
    child: const SmartSocietyApp()));

class SmartSocietyApp extends StatelessWidget {
  const SmartSocietyApp({super.key});
  @override
  Widget build(BuildContext context) => MaterialApp(
      title: 'Smart Society',
      theme: ThemeData(colorSchemeSeed: Colors.indigo, useMaterial3: true),
      home: const SessionGate());
}

class SessionGate extends StatelessWidget {
  const SessionGate({super.key});
  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    if (auth.loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return auth.session == null
        ? const LoginScreen()
        : auth.session!.user.role == 'RESIDENT'
            ? const ResidentShell()
            : RoleHome(role: auth.session!.user.role);
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _loginInput = TextEditingController();
  final _password = TextEditingController();
  bool _submitting = false;
  bool _obscurePassword = true;
  bool _rememberMe = true;
  String _role = 'RESIDENT';

  static const _roles = {
    'RESIDENT': 'Resident',
    'ADMIN': 'Administrator',
    'STAFF': 'Society staff',
    'SECURITY': 'Security team',
  };

  @override
  void dispose() {
    _loginInput.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!_form.currentState!.validate()) return;
    setState(() => _submitting = true);
    await context.read<AuthController>().login(
          _loginInput.text.trim(),
          _password.text,
          requestedRole: _role,
          rememberMe: _rememberMe,
        );
    if (mounted) setState(() => _submitting = false);
  }

  Future<void> _forgotPassword() async {
    final email = TextEditingController(text: _loginInput.text.trim());
    final form = GlobalKey<FormState>();
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Reset your password'),
        content: Form(
          key: form,
          child: TextFormField(
            controller: email,
            keyboardType: TextInputType.emailAddress,
            decoration: const InputDecoration(
              labelText: 'Account email',
              hintText: 'name@example.com',
            ),
            validator: (value) => value == null || !value.contains('@')
                ? 'Enter the email linked to your account'
                : null,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () async {
              if (!form.currentState!.validate()) return;
              final auth = context.read<AuthController>();
              final sent = await auth.requestPasswordReset(email.text.trim());
              if (!dialogContext.mounted || !mounted) return;
              if (sent) Navigator.pop(dialogContext);
              ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                content: Text(auth.message ??
                    auth.error ??
                    'Unable to request a password reset.'),
              ));
            },
            child: const Text('Send reset link'),
          ),
        ],
      ),
    );
    email.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    return Scaffold(
        body: SafeArea(
            child: Center(
                child: SingleChildScrollView(
                    padding: const EdgeInsets.all(24),
                    child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 420),
                        child: Form(
                            key: _form,
                            child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.apartment,
                                      size: 70,
                                      color: Theme.of(context)
                                          .colorScheme
                                          .primary),
                                  const SizedBox(height: 16),
                                  Text('Smart Society',
                                      style: Theme.of(context)
                                          .textTheme
                                          .headlineMedium),
                                  const SizedBox(height: 24),
                                  TextFormField(
                                      controller: _loginInput,
                                      keyboardType: TextInputType.emailAddress,
                                      decoration: const InputDecoration(
                                          labelText: 'Email or mobile number',
                                          hintText: 'name@example.com',
                                          border: OutlineInputBorder()),
                                      validator: (value) => value == null ||
                                              value.trim().isEmpty
                                          ? 'Enter your email or mobile number'
                                          : null),
                                  const SizedBox(height: 16),
                                  DropdownButtonFormField<String>(
                                    initialValue: _role,
                                    decoration: const InputDecoration(
                                      labelText: 'Sign in as',
                                      border: OutlineInputBorder(),
                                    ),
                                    items: _roles.entries
                                        .map((entry) => DropdownMenuItem(
                                              value: entry.key,
                                              child: Text(entry.value),
                                            ))
                                        .toList(),
                                    onChanged: (value) =>
                                        setState(() => _role = value ?? _role),
                                  ),
                                  const SizedBox(height: 16),
                                  TextFormField(
                                      controller: _password,
                                      obscureText: _obscurePassword,
                                      decoration: InputDecoration(
                                        labelText: 'Password',
                                        border: const OutlineInputBorder(),
                                        suffixIcon: IconButton(
                                          tooltip: _obscurePassword
                                              ? 'Show password'
                                              : 'Hide password',
                                          icon: Icon(_obscurePassword
                                              ? Icons.visibility_outlined
                                              : Icons.visibility_off_outlined),
                                          onPressed: () => setState(() =>
                                              _obscurePassword =
                                                  !_obscurePassword),
                                        ),
                                      ),
                                      validator: (value) => value == null ||
                                              value.length < 8
                                          ? 'Password must be at least 8 characters'
                                          : null),
                                  Row(children: [
                                    Expanded(
                                      child: CheckboxListTile(
                                        value: _rememberMe,
                                        contentPadding: EdgeInsets.zero,
                                        controlAffinity:
                                            ListTileControlAffinity.leading,
                                        title: const Text('Remember me'),
                                        onChanged: (value) => setState(
                                            () => _rememberMe = value ?? true),
                                      ),
                                    ),
                                    TextButton(
                                      onPressed: _forgotPassword,
                                      child: const Text('Forgot password?'),
                                    ),
                                  ]),
                                  if (auth.error != null)
                                    Padding(
                                        padding: const EdgeInsets.only(top: 12),
                                        child: Text(auth.error!,
                                            style: TextStyle(
                                                color: Theme.of(context)
                                                    .colorScheme
                                                    .error))),
                                  const SizedBox(height: 24),
                                  FilledButton(
                                      onPressed: _submitting ? null : _login,
                                      child: _submitting
                                          ? const CircularProgressIndicator()
                                          : const Text('Sign in')),
                                  const SizedBox(height: 8),
                                  TextButton(
                                    onPressed: _submitting
                                        ? null
                                        : () => Navigator.of(context).push(
                                              MaterialPageRoute(
                                                builder: (_) =>
                                                    const SignUpScreen(),
                                              ),
                                            ),
                                    child: const Text('Create an account'),
                                  ),
                                  Text(
                                    'Administrator accounts are created only by the society.',
                                    textAlign: TextAlign.center,
                                    style:
                                        Theme.of(context).textTheme.bodySmall,
                                  ),
                                ])))))));
  }
}

class SignUpScreen extends StatefulWidget {
  const SignUpScreen({super.key});

  @override
  State<SignUpScreen> createState() => _SignUpScreenState();
}

class _SignUpScreenState extends State<SignUpScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirmPassword = TextEditingController();
  final _flatNumber = TextEditingController();
  final _building = TextEditingController();
  final _emergencyContact = TextEditingController();
  final _designation = TextEditingController();
  final _employeeId = TextEditingController();
  String _role = 'RESIDENT';
  String _relation = 'TENANT';
  String _gender = 'PREFER_NOT_TO_SAY';
  String _shift = 'ROTATING';
  DateTime? _dateOfBirth;
  DateTime? _joiningDate;
  bool _submitting = false;

  static const _roles = {
    'RESIDENT': 'Resident',
    'STAFF': 'Society staff',
    'SECURITY': 'Security team'
  };

  @override
  void dispose() {
    for (final controller in [
      _name,
      _email,
      _phone,
      _password,
      _confirmPassword,
      _flatNumber,
      _building,
      _emergencyContact,
      _designation,
      _employeeId,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  String _isoDate(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

  String _displayDate(DateTime? value) => value == null
      ? 'Choose date'
      : '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year}';

  Future<void> _pickDate({required bool joining}) async {
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: joining
          ? (_joiningDate ?? now)
          : (_dateOfBirth ?? DateTime(now.year - 25)),
      firstDate: joining ? DateTime(2000) : DateTime(1900),
      lastDate: now,
      helpText: joining ? 'Select joining date' : 'Select date of birth',
    );
    if (selected != null && mounted) {
      setState(() {
        if (joining) {
          _joiningDate = selected;
        } else {
          _dateOfBirth = selected;
        }
      });
    }
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    if ((_role == 'STAFF' || _role == 'SECURITY') && _joiningDate == null) {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Choose your joining date.')));
      return;
    }
    setState(() => _submitting = true);
    final payload = <String, dynamic>{
      'role': _role,
      'name': _name.text.trim(),
      'email': _email.text.trim(),
      'phone': _phone.text.trim(),
      'password': _password.text,
      'password_confirmation': _confirmPassword.text,
      'emergency_contact': _emergencyContact.text.trim().isEmpty
          ? null
          : _emergencyContact.text.trim(),
      if (_dateOfBirth != null) 'date_of_birth': _isoDate(_dateOfBirth!),
      if (_role == 'RESIDENT') ...{
        'flat_number': _flatNumber.text.trim(),
        'building': _building.text.trim(),
        'relation_to_owner': _relation,
        'gender': _gender,
      },
      if (_role == 'STAFF') ...{
        'designation': _designation.text.trim(),
        'joining_date': _isoDate(_joiningDate!),
      },
      if (_role == 'SECURITY') ...{
        'employee_id': _employeeId.text.trim(),
        'joining_date': _isoDate(_joiningDate!),
        'shift': _shift,
      },
    };
    final success = await context.read<AuthController>().register(payload);
    if (!mounted) return;
    setState(() => _submitting = false);
    final auth = context.read<AuthController>();
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(auth.message ?? auth.error ?? 'Unable to register.'),
    ));
    if (success) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final isResident = _role == 'RESIDENT';
    final isStaff = _role == 'STAFF';
    final isSecurity = _role == 'SECURITY';
    return Scaffold(
      appBar: AppBar(title: const Text('Create your account')),
      body: SafeArea(
        child: Form(
          key: _form,
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              const Text(
                'Choose your account type and provide the information needed by your society.',
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _role,
                decoration: const InputDecoration(
                    labelText: 'Account type', border: OutlineInputBorder()),
                items: _roles.entries
                    .map((entry) => DropdownMenuItem(
                        value: entry.key, child: Text(entry.value)))
                    .toList(),
                onChanged: (value) => setState(() => _role = value ?? _role),
              ),
              const SizedBox(height: 16),
              _requiredField(_name, 'Full name'),
              const SizedBox(height: 12),
              _requiredField(_email, 'Email address',
                  keyboardType: TextInputType.emailAddress,
                  validator: (value) => value == null || !value.contains('@')
                      ? 'Enter a valid email'
                      : null),
              const SizedBox(height: 12),
              _requiredField(_phone, 'Mobile number',
                  keyboardType: TextInputType.phone,
                  validator: (value) => value == null ||
                          !RegExp(r'^(?:\\+91|91)?[6-9][0-9]{9}$')
                              .hasMatch(value.trim())
                      ? 'Enter a valid 10-digit Indian mobile number'
                      : null),
              const SizedBox(height: 12),
              _requiredField(_password, 'Password',
                  obscure: true,
                  validator: (value) => value == null || value.length < 8
                      ? 'Use at least 8 characters'
                      : null),
              const SizedBox(height: 12),
              _requiredField(_confirmPassword, 'Confirm password',
                  obscure: true,
                  validator: (value) => value != _password.text
                      ? 'Passwords do not match'
                      : null),
              const SizedBox(height: 12),
              _optionalField(_emergencyContact, 'Emergency contact',
                  keyboardType: TextInputType.phone),
              if (isResident) ...[
                const Divider(height: 32),
                Text('Residence details',
                    style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 12),
                _requiredField(_flatNumber, 'Flat number'),
                const SizedBox(height: 12),
                _requiredField(_building, 'Building / block'),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _relation,
                  decoration: const InputDecoration(
                      labelText: 'Relationship to flat',
                      border: OutlineInputBorder()),
                  items: const [
                    DropdownMenuItem(value: 'OWNER', child: Text('Owner')),
                    DropdownMenuItem(value: 'TENANT', child: Text('Tenant')),
                    DropdownMenuItem(
                        value: 'FAMILY_MEMBER', child: Text('Family member')),
                  ],
                  onChanged: (value) =>
                      setState(() => _relation = value ?? _relation),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _gender,
                  decoration: const InputDecoration(
                      labelText: 'Gender (optional)',
                      border: OutlineInputBorder()),
                  items: const [
                    DropdownMenuItem(value: 'MALE', child: Text('Male')),
                    DropdownMenuItem(value: 'FEMALE', child: Text('Female')),
                    DropdownMenuItem(
                        value: 'NON_BINARY', child: Text('Non-binary')),
                    DropdownMenuItem(
                        value: 'PREFER_NOT_TO_SAY',
                        child: Text('Prefer not to say')),
                  ],
                  onChanged: (value) =>
                      setState(() => _gender = value ?? _gender),
                ),
                const SizedBox(height: 12),
                _dateButton(
                    'Date of birth (optional)',
                    _displayDate(_dateOfBirth),
                    () => _pickDate(joining: false)),
              ],
              if (isStaff || isSecurity) ...[
                const Divider(height: 32),
                Text('Work details',
                    style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 12),
                if (isStaff) _requiredField(_designation, 'Designation'),
                if (isSecurity) ...[
                  _requiredField(_employeeId, 'Employee ID'),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _shift,
                    decoration: const InputDecoration(
                        labelText: 'Shift', border: OutlineInputBorder()),
                    items: const [
                      DropdownMenuItem(
                          value: 'MORNING', child: Text('Morning')),
                      DropdownMenuItem(
                          value: 'EVENING', child: Text('Evening')),
                      DropdownMenuItem(value: 'NIGHT', child: Text('Night')),
                      DropdownMenuItem(
                          value: 'ROTATING', child: Text('Rotating')),
                    ],
                    onChanged: (value) =>
                        setState(() => _shift = value ?? _shift),
                  ),
                ],
                const SizedBox(height: 12),
                _dateButton('Joining date', _displayDate(_joiningDate),
                    () => _pickDate(joining: true)),
              ],
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _submitting ? null : _submit,
                child: _submitting
                    ? const CircularProgressIndicator()
                    : const Text('Submit registration'),
              ),
              if (isStaff || isSecurity)
                const Padding(
                  padding: EdgeInsets.only(top: 12),
                  child: Text(
                    'Staff and security accounts require administrator activation before sign-in.',
                    textAlign: TextAlign.center,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _requiredField(
    TextEditingController controller,
    String label, {
    TextInputType? keyboardType,
    bool obscure = false,
    String? Function(String?)? validator,
  }) =>
      TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        obscureText: obscure,
        decoration: InputDecoration(
            labelText: label, border: const OutlineInputBorder()),
        validator: validator ??
            (value) => value == null || value.trim().isEmpty
                ? '$label is required'
                : null,
      );

  Widget _optionalField(
    TextEditingController controller,
    String label, {
    TextInputType? keyboardType,
  }) =>
      TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        decoration: InputDecoration(
            labelText: label, border: const OutlineInputBorder()),
      );

  Widget _dateButton(String label, String value, VoidCallback onTap) =>
      OutlinedButton.icon(
        onPressed: onTap,
        icon: const Icon(Icons.calendar_today_outlined),
        label: Align(
          alignment: Alignment.centerLeft,
          child: Text('$label: $value'),
        ),
      );
}

class RoleHome extends StatelessWidget {
  const RoleHome({super.key, required this.role});
  final String role;
  @override
  Widget build(BuildContext context) {
    final pages = role == 'SECURITY'
        ? const [
            'Dashboard',
            'Profile',
            'Visitors',
            'Parcels',
            'Notices',
            'Messages',
            'Notifications'
          ]
        : role == 'STAFF'
            ? const [
                'Dashboard',
                'Profile',
                'Complaints',
                'Parcels',
                'Notices',
                'Messages',
                'Notifications'
              ]
            : role == 'ADMIN'
                ? const [
                    'Dashboard',
                    'Profile',
                    'Residents',
                    'Flats',
                    'Visitors',
                    'Maintenance',
                    'Payments',
                    'Reports',
                    'Complaints',
                    'Notices',
                    'Staff',
                    'Parking',
                    'Parcels',
                    'Amenities',
                    'Bookings',
                    'Messages',
                    'Notifications'
                  ]
                : const [
                    'Profile',
                    'Visitors',
                    'Maintenance',
                    'Payments',
                    'Complaints',
                    'Notices',
                    'Parking',
                    'Amenities',
                    'Bookings',
                    'Messages',
                    'Notifications'
                  ];
    return Scaffold(
        appBar: AppBar(
            title: Text(role == 'ADMIN'
                ? 'Administration'
                : role == 'SECURITY'
                    ? 'Gate Management'
                    : role == 'STAFF'
                        ? 'Staff workspace'
                        : 'Resident Portal'),
            actions: [
              NotificationBell(role: role),
              IconButton(
                  icon: const Icon(Icons.logout),
                  onPressed: () => context.read<AuthController>().logout())
            ]),
        body: ListView(padding: const EdgeInsets.all(16), children: [
          Text('Welcome, ${context.read<AuthController>().session!.user.name}',
              style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          for (final page in pages)
            Card(
                child: ListTile(
                    leading: const Icon(Icons.chevron_right),
                    title: Text(page),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => page == 'Profile'
                            ? const AccountProfileScreen()
                            : role == 'ADMIN' && page == 'Reports'
                                ? const AdminReportsScreen()
                                : page == 'Dashboard' &&
                                        const {'ADMIN', 'STAFF', 'SECURITY'}
                                            .contains(role)
                                    ? RoleDashboardScreen(role: role)
                                    : role == 'ADMIN' && page == 'Payments'
                                        ? const AdminPaymentsScreen()
                                        : ModuleScreen(
                                            title: page, role: role)))))
        ]));
  }
}

class RoleDashboardScreen extends StatefulWidget {
  const RoleDashboardScreen({super.key, required this.role});
  final String role;

  @override
  State<RoleDashboardScreen> createState() => _RoleDashboardScreenState();
}

class AdminReportsScreen extends StatefulWidget {
  const AdminReportsScreen({super.key});

  @override
  State<AdminReportsScreen> createState() => _AdminReportsScreenState();
}

class _AdminReportsScreenState extends State<AdminReportsScreen> {
  static const _ranges = {
    'TODAY': 'Today',
    'THIS_WEEK': 'This week',
    'THIS_MONTH': 'This month',
    'LAST_MONTH': 'Last month',
    'THIS_YEAR': 'This year',
    'CUSTOM': 'Custom date range',
  };

  String _range = 'THIS_MONTH';
  DateTimeRange? _customRange;
  Map<String, dynamic>? _report;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  String _date(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

  Future<void> _load() async {
    if (_range == 'CUSTOM' && _customRange == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    final query = <String, String>{'range': _range};
    if (_customRange != null && _range == 'CUSTOM') {
      query['date_from'] = _date(_customRange!.start);
      query['date_to'] = _date(_customRange!.end);
    }
    try {
      final path =
          'admin/reports?${query.entries.map((entry) => '${Uri.encodeQueryComponent(entry.key)}=${Uri.encodeQueryComponent(entry.value)}').join('&')}';
      final result = await context.read<AuthController>().api.get(path);
      _report = Map<String, dynamic>.from(result['data'] as Map);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _selectCustomRange() async {
    final now = DateTime.now();
    final selected = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime(now.year + 1),
      initialDateRange: _customRange ??
          DateTimeRange(
              start: now.subtract(const Duration(days: 30)), end: now),
      helpText: 'Select report dates',
    );
    if (selected != null && mounted) {
      setState(() => _customRange = selected);
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _report == null) {
      return Scaffold(
          appBar: AppBar(title: const Text('Society reports')),
          body: const Center(child: CircularProgressIndicator()));
    }
    if (_error != null && _report == null) {
      return Scaffold(
          appBar: AppBar(title: const Text('Society reports')),
          body: _ErrorState(message: _error!, onRetry: _load));
    }
    final report = _report!;
    final maintenance = Map<String, dynamic>.from(report['maintenance'] as Map);
    final complaints = Map<String, dynamic>.from(report['complaints'] as Map);
    final visitors = Map<String, dynamic>.from(report['visitors'] as Map);
    final occupancy = Map<String, dynamic>.from(report['occupancy'] as Map);
    final parking = Map<String, dynamic>.from(report['parking'] as Map);
    final staff = Map<String, dynamic>.from(report['staff'] as Map);
    final collection = report['collection_by_day'] is List
        ? List<dynamic>.from(report['collection_by_day'] as List)
        : const <dynamic>[];
    final rangeData = Map<String, dynamic>.from(report['range'] as Map);
    return Scaffold(
      appBar: AppBar(title: const Text('Society reports')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
          children: [
            DropdownButtonFormField<String>(
              initialValue: _range,
              decoration: const InputDecoration(
                  labelText: 'Report period', border: OutlineInputBorder()),
              items: _ranges.entries
                  .map((entry) => DropdownMenuItem(
                      value: entry.key, child: Text(entry.value)))
                  .toList(),
              onChanged: (value) {
                setState(() => _range = value ?? _range);
                if (_range == 'CUSTOM') {
                  _selectCustomRange();
                } else {
                  _load();
                }
              },
            ),
            if (_range == 'CUSTOM')
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: OutlinedButton.icon(
                  onPressed: _selectCustomRange,
                  icon: const Icon(Icons.date_range_outlined),
                  label: Text(_customRange == null
                      ? 'Choose custom range'
                      : '${_date(_customRange!.start)} to ${_date(_customRange!.end)}'),
                ),
              ),
            const SizedBox(height: 12),
            Text(rangeData['label']?.toString() ?? '',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 16),
            _ReportSection(
              title: 'Maintenance collection',
              metrics: [
                ('Collected', _money(maintenance['collection']), Colors.green),
                (
                  'Payments',
                  maintenance['payments_received']?.toString() ?? '0',
                  Colors.blue
                ),
                (
                  'Pending due',
                  _money(maintenance['pending_amount']),
                  Colors.orange
                ),
                (
                  'Bills generated',
                  maintenance['bills_generated']?.toString() ?? '0',
                  Colors.indigo
                ),
              ],
            ),
            _ReportSection(
              title: 'Operations',
              metrics: [
                (
                  'Open complaints',
                  complaints['open']?.toString() ?? '0',
                  Colors.red
                ),
                (
                  'Resolved',
                  complaints['resolved']?.toString() ?? '0',
                  Colors.green
                ),
                (
                  'Visitors',
                  visitors['registered']?.toString() ?? '0',
                  Colors.purple
                ),
                (
                  'Checked in',
                  visitors['entered']?.toString() ?? '0',
                  Colors.teal
                ),
              ],
            ),
            _ReportSection(
              title: 'Society capacity',
              metrics: [
                (
                  'Occupied flats',
                  occupancy['occupied_flats']?.toString() ?? '0',
                  Colors.indigo
                ),
                (
                  'Vacant flats',
                  occupancy['vacant_flats']?.toString() ?? '0',
                  Colors.orange
                ),
                (
                  'Parking available',
                  parking['available_slots']?.toString() ?? '0',
                  Colors.green
                ),
                (
                  'Active staff',
                  staff['active']?.toString() ?? '0',
                  Colors.cyan
                ),
              ],
            ),
            const _SectionHeader(title: 'Collection by day'),
            if (collection.isEmpty)
              const _EmptyCard(
                  message: 'No verified payment collection in this period.')
            else
              ...collection.map((raw) {
                final item = Map<String, dynamic>.from(raw as Map);
                return _InfoCard(
                  icon: Icons.show_chart_outlined,
                  title: _money(item['amount']),
                  subtitle: item['date']?.toString() ?? '',
                );
              }),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(_error!,
                    style:
                        TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
          ],
        ),
      ),
    );
  }
}

class _ReportSection extends StatelessWidget {
  const _ReportSection({required this.title, required this.metrics});
  final String title;
  final List<(String, String, Color)> metrics;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionHeader(title: title),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            childAspectRatio: 1.8,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            children: metrics
                .map((metric) => _DashboardMetricTile(
                    metric: _DashboardMetric(metric.$1, metric.$2,
                        Icons.bar_chart_outlined, metric.$3)))
                .toList(),
          ),
          const SizedBox(height: 22),
        ],
      );
}

class _RoleDashboardScreenState extends State<RoleDashboardScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = true;

  String get _endpoint => switch (widget.role) {
        'ADMIN' => 'admin/dashboard',
        'STAFF' => 'staff/dashboard',
        'SECURITY' => 'security/dashboard',
        _ => 'resident/dashboard',
      };

  String get _title => switch (widget.role) {
        'ADMIN' => 'Admin dashboard',
        'STAFF' => 'Staff dashboard',
        'SECURITY' => 'Security dashboard',
        _ => 'Dashboard',
      };

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await context.read<AuthController>().api.get(_endpoint);
      _data = Map<String, dynamic>.from(result['data'] as Map);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  void _open(String page) {
    Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => page == 'Payments' && widget.role == 'ADMIN'
            ? const AdminPaymentsScreen()
            : ModuleScreen(title: page, role: widget.role)));
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
          appBar: AppBar(title: Text(_title)),
          body: const Center(child: CircularProgressIndicator()));
    }
    if (_error != null) {
      return Scaffold(
          appBar: AppBar(title: Text(_title)),
          body: _ErrorState(message: _error!, onRetry: _load));
    }
    final data = _data!;
    final name = context.read<AuthController>().session!.user.name;
    final metrics = switch (widget.role) {
      'ADMIN' => [
          _DashboardMetric('Residents', data['total_residents'],
              Icons.people_outline, Colors.indigo),
          _DashboardMetric('Flats', data['total_flats'],
              Icons.apartment_outlined, Colors.blue),
          _DashboardMetric('Occupied', data['occupied_flats'],
              Icons.home_work_outlined, Colors.teal),
          _DashboardMetric('Vacant', data['vacant_flats'],
              Icons.meeting_room_outlined, Colors.orange),
          _DashboardMetric('Maintenance due', data['pending_maintenance_bills'],
              Icons.receipt_long_outlined, Colors.deepOrange),
          _DashboardMetric(
              'Collected',
              _money(data['total_collected_maintenance']),
              Icons.account_balance_wallet_outlined,
              Colors.green),
          _DashboardMetric('Open complaints', data['open_complaints'],
              Icons.support_agent_outlined, Colors.red),
          _DashboardMetric('Visitors today', data['today_visitors'],
              Icons.badge_outlined, Colors.purple),
          _DashboardMetric('Active staff', data['active_staff'],
              Icons.groups_outlined, Colors.cyan),
          _DashboardMetric('Parcels waiting', data['parcels_pending_pickup'],
              Icons.inventory_2_outlined, Colors.brown),
        ],
      'STAFF' => [
          _DashboardMetric('Assigned tasks', data['assigned_tasks'],
              Icons.assignment_outlined, Colors.indigo),
          _DashboardMetric('Pending', data['pending_tasks'],
              Icons.pending_actions_outlined, Colors.orange),
          _DashboardMetric('Completed', data['completed_tasks'],
              Icons.task_alt_outlined, Colors.green),
        ],
      _ => [
          _DashboardMetric('Expected visitors', data['expected_visitors'],
              Icons.event_available_outlined, Colors.indigo),
          _DashboardMetric('Waiting approval', data['waiting_for_approval'],
              Icons.hourglass_top_outlined, Colors.orange),
          _DashboardMetric('Checked in', data['checked_in_today'],
              Icons.login_outlined, Colors.green),
          _DashboardMetric('Checked out', data['checked_out_today'],
              Icons.logout_outlined, Colors.blue),
          _DashboardMetric(
              'Parcels to collect',
              data['parcels_awaiting_pickup'],
              Icons.inventory_2_outlined,
              Colors.brown),
        ],
    };
    final quickActions = switch (widget.role) {
      'ADMIN' => <(String, IconData, String)>[
          ('Add resident', Icons.person_add_alt_1, 'Residents'),
          ('Add flat', Icons.add_home_work_outlined, 'Flats'),
          ('Generate maintenance', Icons.receipt_long_outlined, 'Maintenance'),
          ('Record payment', Icons.payments_outlined, 'Payments'),
          ('Add notice', Icons.campaign_outlined, 'Notices'),
          ('View complaints', Icons.support_agent_outlined, 'Complaints'),
          ('Add staff', Icons.badge_outlined, 'Staff'),
          ('View visitors', Icons.groups_outlined, 'Visitors'),
        ],
      'STAFF' => <(String, IconData, String)>[
          ('My complaints', Icons.support_agent_outlined, 'Complaints'),
          ('Receive parcel', Icons.inventory_2_outlined, 'Parcels'),
          ('Messages', Icons.chat_bubble_outline, 'Messages'),
        ],
      _ => <(String, IconData, String)>[
          ('Check in visitor', Icons.login_outlined, 'Visitors'),
          ('Check out visitor', Icons.logout_outlined, 'Visitors'),
          ('Delivery entry', Icons.inventory_2_outlined, 'Parcels'),
          ('Vehicle entry', Icons.directions_car_outlined, 'Visitors'),
          ('Emergency contacts', Icons.emergency_outlined, 'Notifications'),
        ],
    };
    final activities = data['recent_activity'] is List
        ? List<dynamic>.from(data['recent_activity'] as List)
        : const <dynamic>[];
    final staffMember = data['staff_member'] is Map
        ? Map<String, dynamic>.from(data['staff_member'] as Map)
        : null;
    final complaints = data['recent_complaints'] is List
        ? List<dynamic>.from(data['recent_complaints'] as List)
        : const <dynamic>[];
    return Scaffold(
      appBar: AppBar(title: Text(_title)),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
          children: [
            Text('Welcome, $name',
                style: Theme.of(context)
                    .textTheme
                    .headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w700)),
            if (staffMember != null)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                    '${staffMember['designation'] ?? 'Staff'}${staffMember['shift'] == null ? '' : ' · ${staffMember['shift']} shift'}'),
              ),
            const SizedBox(height: 18),
            GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              childAspectRatio: 1.42,
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
              children: metrics
                  .map((metric) => _DashboardMetricTile(metric: metric))
                  .toList(),
            ),
            const SizedBox(height: 24),
            const _SectionHeader(title: 'Quick actions'),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: quickActions
                  .map((action) => _QuickAction(
                        icon: action.$2,
                        label: action.$1,
                        onTap: () => _open(action.$3),
                      ))
                  .toList(),
            ),
            if (activities.isNotEmpty) ...[
              const SizedBox(height: 24),
              const _SectionHeader(title: 'Recent activity'),
              ...activities.map((raw) {
                final activity = Map<String, dynamic>.from(raw as Map);
                return _InfoCard(
                  icon: Icons.history_outlined,
                  title: activity['title']?.toString() ?? 'Society activity',
                  subtitle: activity['detail']?.toString() ?? '',
                );
              }),
            ],
            if (complaints.isNotEmpty) ...[
              const SizedBox(height: 24),
              const _SectionHeader(title: 'Assigned complaints'),
              ...complaints.map((raw) {
                final complaint = Map<String, dynamic>.from(raw as Map);
                return _InfoCard(
                  icon: Icons.assignment_outlined,
                  title: complaint['title']?.toString() ?? 'Complaint',
                  subtitle:
                      '${complaint['flat'] ?? 'Flat'} · ${complaint['status'] ?? ''}',
                  onTap: () => _open('Complaints'),
                );
              }),
            ],
          ],
        ),
      ),
    );
  }
}

class _DashboardMetric {
  const _DashboardMetric(this.label, this.value, this.icon, this.color);
  final String label;
  final dynamic value;
  final IconData icon;
  final Color color;
}

class _DashboardMetricTile extends StatelessWidget {
  const _DashboardMetricTile({required this.metric});
  final _DashboardMetric metric;

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Icon(metric.icon, color: metric.color),
              Text(metric.value?.toString() ?? '0',
                  style: Theme.of(context)
                      .textTheme
                      .titleLarge
                      ?.copyWith(fontWeight: FontWeight.w700)),
              Text(metric.label, maxLines: 1, overflow: TextOverflow.ellipsis),
            ],
          ),
        ),
      );
}

class NotificationBell extends StatefulWidget {
  const NotificationBell({super.key, required this.role});
  final String role;

  @override
  State<NotificationBell> createState() => _NotificationBellState();
}

class _NotificationBellState extends State<NotificationBell> {
  int _unread = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final response =
          await context.read<AuthController>().api.get('notifications');
      final items = _pageItems(response);
      if (mounted) {
        setState(() => _unread = items
            .where((item) => item is Map && item['read_at'] == null)
            .length);
      }
    } on ApiException {
      // Notification failure should not prevent the role dashboard from opening.
    }
  }

  Future<void> _open() async {
    await Navigator.of(context).push(MaterialPageRoute(
        builder: (_) =>
            ModuleScreen(title: 'Notifications', role: widget.role)));
    if (mounted) _load();
  }

  @override
  Widget build(BuildContext context) => Badge(
        isLabelVisible: _unread > 0,
        label: Text(_unread > 99 ? '99+' : '$_unread'),
        child: IconButton(
          tooltip: 'Notifications',
          icon: const Icon(Icons.notifications_none_rounded),
          onPressed: _open,
        ),
      );
}

class AccountProfileScreen extends StatefulWidget {
  const AccountProfileScreen({super.key});

  @override
  State<AccountProfileScreen> createState() => _AccountProfileScreenState();
}

class _AccountProfileScreenState extends State<AccountProfileScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _emergencyContact = TextEditingController();
  final _password = TextEditingController();
  final _confirmPassword = TextEditingController();
  String _gender = 'PREFER_NOT_TO_SAY';
  DateTime? _dateOfBirth;
  bool _loading = true;
  bool _saving = false;
  String? _error;
  Map<String, dynamic>? _user;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _email,
      _phone,
      _emergencyContact,
      _password,
      _confirmPassword,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await context.read<AuthController>().api.get('auth/me');
      final user = Map<String, dynamic>.from(result['data'] as Map);
      final profile = user['profile'] is Map
          ? Map<String, dynamic>.from(user['profile'] as Map)
          : <String, dynamic>{};
      _user = user;
      _name.text = user['name']?.toString() ?? '';
      _email.text = user['email']?.toString() ?? '';
      _phone.text = user['phone']?.toString() ?? '';
      _emergencyContact.text = profile['emergency_contact']?.toString() ?? '';
      _dateOfBirth =
          DateTime.tryParse(profile['date_of_birth']?.toString() ?? '');
      if (const {'MALE', 'FEMALE', 'NON_BINARY', 'PREFER_NOT_TO_SAY'}
          .contains(profile['gender'])) {
        _gender = profile['gender'].toString();
      }
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _pickDateOfBirth() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _dateOfBirth ?? DateTime(now.year - 25),
      firstDate: DateTime(1900),
      lastDate: now,
    );
    if (picked != null && mounted) setState(() => _dateOfBirth = picked);
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await context.read<AuthController>().api.patch('auth/profile', {
        'name': _name.text.trim(),
        'email': _email.text.trim(),
        'phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        'emergency_contact': _emergencyContact.text.trim().isEmpty
            ? null
            : _emergencyContact.text.trim(),
        'gender': _gender,
        if (_dateOfBirth != null)
          'date_of_birth':
              '${_dateOfBirth!.year.toString().padLeft(4, '0')}-${_dateOfBirth!.month.toString().padLeft(2, '0')}-${_dateOfBirth!.day.toString().padLeft(2, '0')}',
        if (_password.text.isNotEmpty) ...{
          'password': _password.text,
          'password_confirmation': _confirmPassword.text,
        },
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Profile updated successfully.')));
      await _load();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
          appBar: AppBar(title: const Text('My profile')),
          body: const Center(child: CircularProgressIndicator()));
    }
    if (_error != null && _user == null) {
      return Scaffold(
          appBar: AppBar(title: const Text('My profile')),
          body: _ErrorState(message: _error!, onRetry: _load));
    }
    final resident = _user?['resident'] is Map
        ? Map<String, dynamic>.from(_user!['resident'] as Map)
        : null;
    final staff = _user?['staff_member'] is Map
        ? Map<String, dynamic>.from(_user!['staff_member'] as Map)
        : null;
    return Scaffold(
      appBar: AppBar(title: const Text('My profile')),
      body: Form(
        key: _form,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: ListTile(
                leading: CircleAvatar(child: Text(_initials(_name.text))),
                title: Text(_user?['role']?.toString() ?? 'Member'),
                subtitle: Text(resident == null
                    ? (staff?['designation']?.toString() ?? '')
                    : 'Flat ${resident['flat_number'] ?? '—'} · ${resident['building'] ?? '—'}'),
              ),
            ),
            const SizedBox(height: 16),
            _TextField(controller: _name, label: 'Full name'),
            _TextField(
                controller: _email,
                label: 'Email',
                keyboardType: TextInputType.emailAddress),
            _TextField(
                controller: _phone,
                label: 'Mobile number',
                required: false,
                keyboardType: TextInputType.phone),
            _TextField(
                controller: _emergencyContact,
                label: 'Emergency contact',
                required: false,
                keyboardType: TextInputType.phone),
            DropdownButtonFormField<String>(
              initialValue: _gender,
              decoration: const InputDecoration(
                  labelText: 'Gender', border: OutlineInputBorder()),
              items: const [
                DropdownMenuItem(value: 'MALE', child: Text('Male')),
                DropdownMenuItem(value: 'FEMALE', child: Text('Female')),
                DropdownMenuItem(
                    value: 'NON_BINARY', child: Text('Non-binary')),
                DropdownMenuItem(
                    value: 'PREFER_NOT_TO_SAY',
                    child: Text('Prefer not to say')),
              ],
              onChanged:
                  _saving ? null : (value) => setState(() => _gender = value!),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _saving ? null : _pickDateOfBirth,
              icon: const Icon(Icons.cake_outlined),
              label: Align(
                alignment: Alignment.centerLeft,
                child: Text(_dateOfBirth == null
                    ? 'Date of birth: not set'
                    : 'Date of birth: ${_dateOfBirth!.day.toString().padLeft(2, '0')}/${_dateOfBirth!.month.toString().padLeft(2, '0')}/${_dateOfBirth!.year}'),
              ),
            ),
            const Divider(height: 32),
            Text('Change password',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            _TextField(
                controller: _password,
                label: 'New password (optional)',
                required: false,
                obscure: true),
            _TextField(
                controller: _confirmPassword,
                label: 'Confirm new password',
                required: false,
                obscure: true,
                validator: (value) =>
                    _password.text.isEmpty || value == _password.text
                        ? null
                        : 'Passwords do not match'),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(_error!,
                    style:
                        TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const CircularProgressIndicator()
                  : const Text('Save profile'),
            ),
          ],
        ),
      ),
    );
  }
}

class ResidentShell extends StatefulWidget {
  const ResidentShell({super.key});

  @override
  State<ResidentShell> createState() => _ResidentShellState();
}

class _ResidentShellState extends State<ResidentShell> {
  var _index = 0;

  static const _titles = ['Home', 'My Unit', 'Gate', 'More'];

  @override
  Widget build(BuildContext context) {
    final pages = [
      ResidentDashboardScreen(
          onOpenTab: (index) => setState(() => _index = index)),
      const MyUnitScreen(),
      const ResidentVisitorsScreen(),
      const ResidentMoreScreen(),
    ];
    return Scaffold(
      appBar: AppBar(
        title: Text(_titles[_index]),
        actions: const [
          NotificationBell(role: 'RESIDENT'),
        ],
      ),
      body: pages[_index],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (index) => setState(() => _index = index),
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home),
              label: 'Home'),
          NavigationDestination(
              icon: Icon(Icons.apartment_outlined),
              selectedIcon: Icon(Icons.apartment),
              label: 'My Unit'),
          NavigationDestination(
              icon: Icon(Icons.shield_outlined),
              selectedIcon: Icon(Icons.shield),
              label: 'Gate'),
          NavigationDestination(
              icon: Icon(Icons.grid_view_outlined),
              selectedIcon: Icon(Icons.grid_view),
              label: 'More'),
        ],
      ),
    );
  }
}

class ResidentDashboardScreen extends StatefulWidget {
  const ResidentDashboardScreen({super.key, required this.onOpenTab});
  final ValueChanged<int> onOpenTab;

  @override
  State<ResidentDashboardScreen> createState() =>
      _ResidentDashboardScreenState();
}

class _ResidentDashboardScreenState extends State<ResidentDashboardScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response =
          await context.read<AuthController>().api.get('resident/dashboard');
      _data = Map<String, dynamic>.from(response['data'] as Map);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return _ErrorState(message: _error!, onRetry: _load);
    }
    final dashboard = _data!;
    final resident = Map<String, dynamic>.from(dashboard['resident'] as Map);
    final dues = Map<String, dynamic>.from(dashboard['dues'] as Map);
    final visitors = Map<String, dynamic>.from(dashboard['visitors'] as Map);
    final complaints =
        Map<String, dynamic>.from(dashboard['complaints'] as Map);
    final upcomingBooking = dashboard['upcoming_booking'] as Map?;
    final latestNotice = dashboard['latest_notice'] as Map?;
    final outstanding = _money(dues['outstanding_amount']);
    final overdue = _money(dues['overdue_amount']);
    final greeting = switch (DateTime.now().hour) {
      < 12 => 'Good morning',
      < 17 => 'Good afternoon',
      _ => 'Good evening',
    };
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 28),
        children: [
          Text(greeting, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 4),
          Text(resident['name']?.toString() ?? 'Resident',
              style: Theme.of(context)
                  .textTheme
                  .headlineSmall
                  ?.copyWith(fontWeight: FontWeight.w700)),
          Text(
              '${resident['building'] ?? ''} · Flat ${resident['flat_number'] ?? ''}',
              style: Theme.of(context).textTheme.bodyMedium),
          const SizedBox(height: 20),
          if (_asDouble(dues['overdue_amount']) > 0)
            _UrgentCard(
              icon: Icons.warning_amber_rounded,
              title: 'Maintenance payment overdue',
              message:
                  '$overdue is overdue. Review your due and create a secure payment request.',
              action: 'Pay now',
              onTap: () => Navigator.of(context)
                  .push(MaterialPageRoute(builder: (_) => const DuesScreen())),
            ),
          _SectionHeader(
              title: 'At a glance', action: 'Refresh', onAction: _load),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: 1.42,
            children: [
              _SummaryTile(
                  icon: Icons.account_balance_wallet_outlined,
                  label: 'My dues',
                  value: outstanding == '₹0.00' ? 'No dues' : outstanding,
                  color: _asDouble(dues['outstanding_amount']) > 0
                      ? Colors.orange
                      : Colors.green,
                  onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const DuesScreen()))),
              _SummaryTile(
                  icon: Icons.person_pin_circle_outlined,
                  label: 'At the gate',
                  value: '${visitors['at_gate'] ?? 0} waiting',
                  color: Colors.blue,
                  onTap: () => widget.onOpenTab(2)),
              _SummaryTile(
                  icon: Icons.support_agent_outlined,
                  label: 'Helpdesk',
                  value: '${complaints['open'] ?? 0} active',
                  color: Colors.deepPurple,
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => const ModuleScreen(
                          title: 'Complaints', role: 'RESIDENT')))),
              _SummaryTile(
                  icon: Icons.event_available_outlined,
                  label: 'Visitors today',
                  value: '${visitors['expected_today'] ?? 0} expected',
                  color: Colors.teal,
                  onTap: () => widget.onOpenTab(2)),
            ],
          ),
          const SizedBox(height: 24),
          const _SectionHeader(title: 'Quick actions'),
          Wrap(spacing: 10, runSpacing: 10, children: [
            _QuickAction(
                icon: Icons.person_add_alt_1,
                label: 'Add visitor',
                onTap: () => widget.onOpenTab(2)),
            _QuickAction(
                icon: Icons.payments_outlined,
                label: 'Pay dues',
                onTap: () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const DuesScreen()))),
            _QuickAction(
                icon: Icons.calendar_month_outlined,
                label: 'Book amenity',
                onTap: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => const ModuleScreen(
                        title: 'Amenities', role: 'RESIDENT')))),
            _QuickAction(
                icon: Icons.add_comment_outlined,
                label: 'Helpdesk',
                onTap: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => const ModuleScreen(
                        title: 'Complaints', role: 'RESIDENT')))),
          ]),
          const SizedBox(height: 24),
          if (upcomingBooking != null) ...[
            const _SectionHeader(title: 'Upcoming booking'),
            _InfoCard(
              icon: Icons.event,
              title: upcomingBooking['amenity_name']?.toString() ??
                  'Amenity booking',
              subtitle:
                  '${upcomingBooking['booking_date'] ?? ''} at ${upcomingBooking['start_time'] ?? ''}',
              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) =>
                      const ModuleScreen(title: 'Bookings', role: 'RESIDENT'))),
            ),
          ],
          _SectionHeader(
              title: 'Announcements',
              action: 'View all',
              onAction: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) =>
                      const ModuleScreen(title: 'Notices', role: 'RESIDENT')))),
          if (latestNotice == null)
            const _EmptyCard(message: 'No announcements right now.')
          else
            _InfoCard(
              icon: Icons.campaign_outlined,
              title: latestNotice['title']?.toString() ?? 'Society update',
              subtitle: latestNotice['content']?.toString() ?? '',
              onTap: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) =>
                      const ModuleScreen(title: 'Notices', role: 'RESIDENT'))),
            ),
        ],
      ),
    );
  }
}

class MyUnitScreen extends StatefulWidget {
  const MyUnitScreen({super.key});

  @override
  State<MyUnitScreen> createState() => _MyUnitScreenState();
}

class _MyUnitScreenState extends State<MyUnitScreen> {
  Map<String, dynamic>? _profile;
  List<dynamic> _parking = [];
  String? _error;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<AuthController>().api;
      final result = await Future.wait([
        api.get('resident/profile'),
        api.get('resident/parking-slots'),
      ]);
      _profile = Map<String, dynamic>.from(result[0]['data'] as Map);
      _parking = _pageItems(result[1]);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return _ErrorState(message: _error!, onRetry: _load);
    final profile = _profile!;
    final user = profile['user'] is Map
        ? Map<String, dynamic>.from(profile['user'] as Map)
        : <String, dynamic>{};
    final flat = profile['flat'] is Map
        ? Map<String, dynamic>.from(profile['flat'] as Map)
        : <String, dynamic>{};
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(padding: const EdgeInsets.all(16), children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Row(children: [
              CircleAvatar(
                  radius: 30, child: Text(_initials(user['name']?.toString()))),
              const SizedBox(width: 14),
              Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                    Text(user['name']?.toString() ?? 'Resident',
                        style: Theme.of(context).textTheme.titleLarge),
                    Text(user['email']?.toString() ?? ''),
                    Text(
                        'Flat ${flat['flat_number'] ?? '—'} · ${flat['building'] ?? '—'}'),
                  ])),
              IconButton(
                tooltip: 'Edit profile',
                icon: const Icon(Icons.edit_outlined),
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) => const ModuleScreen(
                        title: 'Profile', role: 'RESIDENT'))),
              ),
            ]),
          ),
        ),
        const SizedBox(height: 20),
        const _SectionHeader(title: 'Your parking'),
        if (_parking.isEmpty)
          const _EmptyCard(message: 'No parking slot is assigned to your unit.')
        else
          ..._parking.map((item) {
            final slot = Map<String, dynamic>.from(item as Map);
            return _InfoCard(
              icon: Icons.local_parking_outlined,
              title: slot['slot_number']?.toString() ?? 'Parking slot',
              subtitle: [slot['parking_type'], slot['vehicle_number']]
                  .whereType<String>()
                  .join(' · '),
            );
          }),
        const SizedBox(height: 20),
        const _SectionHeader(title: 'Unit services'),
        _InfoCard(
            icon: Icons.payment_outlined,
            title: 'Maintenance dues',
            subtitle: 'View bills, payment requests and history',
            onTap: () => Navigator.of(context)
                .push(MaterialPageRoute(builder: (_) => const DuesScreen()))),
        _InfoCard(
            icon: Icons.people_outline,
            title: 'Resident profile',
            subtitle: 'Keep your contact details updated',
            onTap: () => Navigator.of(context).push(MaterialPageRoute(
                builder: (_) =>
                    const ModuleScreen(title: 'Profile', role: 'RESIDENT')))),
      ]),
    );
  }
}

class DuesScreen extends StatefulWidget {
  const DuesScreen({super.key});

  @override
  State<DuesScreen> createState() => _DuesScreenState();
}

class _DuesScreenState extends State<DuesScreen> {
  List<dynamic> _bills = [];
  String? _error;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _bills = _pageItems(await context
          .read<AuthController>()
          .api
          .get('resident/maintenance-bills'));
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  double get _totalOutstanding => _bills.fold<double>(
      0,
      (total, item) =>
          total +
          _asDouble((item as Map)['outstanding_amount'] ?? item['amount']));

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        appBar: AppBar(title: const Text('Maintenance & payments'), actions: [
          IconButton(
            tooltip: 'Payment activity',
            icon: const Icon(Icons.receipt_long_outlined),
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => const PaymentActivityScreen())),
          ),
        ]),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? _ErrorState(message: _error!, onRetry: _load)
                : RefreshIndicator(
                    onRefresh: _load,
                    child: _bills.isEmpty
                        ? ListView(children: const [
                            SizedBox(height: 180),
                            Center(child: Text('No maintenance bills yet.'))
                          ])
                        : ListView.builder(
                            padding: const EdgeInsets.all(16),
                            itemCount: _bills.length,
                            itemBuilder: (_, index) {
                              final bill = Map<String, dynamic>.from(
                                  _bills[index] as Map);
                              final outstanding = _asDouble(
                                  bill['outstanding_amount'] ?? bill['amount']);
                              final status =
                                  bill['status']?.toString() ?? 'UNPAID';
                              return Card(
                                child: Padding(
                                  padding: const EdgeInsets.all(16),
                                  child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(children: [
                                          Expanded(
                                              child: Text(
                                                  'Maintenance · ${bill['billing_month'] ?? ''}',
                                                  style: Theme.of(context)
                                                      .textTheme
                                                      .titleMedium)),
                                          _StatusBadge(status: status),
                                        ]),
                                        const SizedBox(height: 10),
                                        Text('Due ${bill['due_date'] ?? '—'}'),
                                        const SizedBox(height: 4),
                                        Text(_money(outstanding),
                                            style: Theme.of(context)
                                                .textTheme
                                                .headlineSmall
                                                ?.copyWith(
                                                    fontWeight:
                                                        FontWeight.w700)),
                                        if ((bill['notes']
                                                ?.toString()
                                                .isNotEmpty ??
                                            false))
                                          Padding(
                                              padding:
                                                  const EdgeInsets.only(top: 8),
                                              child: Text(
                                                  bill['notes'].toString())),
                                        const SizedBox(height: 12),
                                        Align(
                                          alignment: Alignment.centerRight,
                                          child: outstanding <= 0
                                              ? const Text('Paid',
                                                  style: TextStyle(
                                                      color: Colors.green,
                                                      fontWeight:
                                                          FontWeight.w600))
                                              : FilledButton.icon(
                                                  icon: const Icon(
                                                      Icons.lock_outline),
                                                  label: const Text(
                                                      'Pay full amount'),
                                                  onPressed: () async {
                                                    final changed = await Navigator
                                                            .of(context)
                                                        .push<bool>(
                                                            MaterialPageRoute(
                                                                builder: (_) =>
                                                                    const FullPaymentCheckoutScreen()));
                                                    if (changed == true) {
                                                      _load();
                                                    }
                                                  },
                                                ),
                                        ),
                                      ]),
                                ),
                              );
                            },
                          ),
                  ),
        floatingActionButton: _loading || _totalOutstanding <= 0
            ? null
            : FloatingActionButton.extended(
                icon: const Icon(Icons.account_balance_wallet_outlined),
                label: Text('Pay all ${_money(_totalOutstanding)}'),
                onPressed: () async {
                  final changed = await Navigator.of(context).push<bool>(
                      MaterialPageRoute(
                          builder: (_) => const FullPaymentCheckoutScreen()));
                  if (changed == true) _load();
                },
              ));
  }
}

class PaymentCheckoutScreen extends StatefulWidget {
  const PaymentCheckoutScreen({super.key, required this.bill});
  final Map<String, dynamic> bill;

  @override
  State<PaymentCheckoutScreen> createState() => _PaymentCheckoutScreenState();
}

class _PaymentCheckoutScreenState extends State<PaymentCheckoutScreen> {
  var _method = 'UPI';
  var _submitting = false;
  String? _error;

  Future<void> _submit() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await context
          .read<AuthController>()
          .api
          .post('resident/maintenance-payments', {
        'maintenance_bill_id': widget.bill['id'],
        'payment_method': _method,
      });
      final payment = Map<String, dynamic>.from(result['data'] as Map);
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Payment request created'),
          content: Text(
              'Reference ${payment['reference_id']}\n\nStatus: ${payment['status']}. No funds have been confirmed by this app. Complete payment through the configured provider or society office.'),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('OK'))
          ],
        ),
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final amount =
        _asDouble(widget.bill['outstanding_amount'] ?? widget.bill['amount']);
    return Scaffold(
      appBar: AppBar(title: const Text('Review payment')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        Card(
            child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Maintenance fee',
                          style: Theme.of(context).textTheme.titleLarge),
                      const SizedBox(height: 8),
                      Text('Due date: ${widget.bill['due_date'] ?? '—'}'),
                      const Divider(height: 28),
                      _PaymentLine(
                          label: 'Outstanding due', value: _money(amount)),
                      const _PaymentLine(label: 'Late fee', value: '₹0.00'),
                      const Divider(),
                      _PaymentLine(
                          label: 'Total payable',
                          value: _money(amount),
                          bold: true),
                    ]))),
        const SizedBox(height: 20),
        Text('Payment method', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        RadioGroup<String>(
          groupValue: _method,
          onChanged: (value) {
            if (!_submitting) {
              setState(() => _method = value!);
            }
          },
          child: Column(
            children: [
              for (final method in const [
                'UPI',
                'CARD',
                'BANK_TRANSFER',
                'CASH'
              ])
                RadioListTile<String>(
                  value: method,
                  title: Text(_paymentMethodLabel(method)),
                ),
            ],
          ),
        ),
        if (_error != null)
          Text(_error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error)),
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: _submitting ? null : _submit,
          icon: _submitting
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2))
              : const Icon(Icons.lock_outline),
          label: Text(
              _submitting ? 'Creating request…' : 'Create payment request'),
        ),
        const SizedBox(height: 12),
        const Text(
            'A real payment provider is not connected yet. This creates a pending request only; it does not charge your account.',
            textAlign: TextAlign.center),
      ]),
    );
  }
}

class FullPaymentCheckoutScreen extends StatefulWidget {
  const FullPaymentCheckoutScreen({super.key});

  @override
  State<FullPaymentCheckoutScreen> createState() =>
      _FullPaymentCheckoutScreenState();
}

class _FullPaymentCheckoutScreenState extends State<FullPaymentCheckoutScreen> {
  var _method = 'UPI';
  var _submitting = false;
  var _loadingSummary = true;
  Map<String, dynamic>? _summary;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadSummary();
  }

  Future<void> _loadSummary() async {
    setState(() {
      _loadingSummary = true;
      _error = null;
    });
    try {
      final response = await context
          .read<AuthController>()
          .api
          .get('resident/payment-summary');
      _summary = Map<String, dynamic>.from(response['data'] as Map);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loadingSummary = false);
  }

  Future<void> _submit() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final api = context.read<AuthController>().api;

      // Step 1: Create the full-payment order (server calculates the amount).
      final orderResponse =
          await api.post('resident/payment-orders/full', {'payment_method': _method});
      final order = Map<String, dynamic>.from(orderResponse['data'] as Map);
      final orderId = order['id'];

      // Step 2: Confirm the order via the internal demo endpoint.
      // The backend validates ownership, generates a demo reference, records
      // MaintenancePayment rows, updates bill statuses, and issues a receipt.
      final confirmResponse = await api
          .post('resident/payment-orders/$orderId/confirm-demo', {});
      final confirmed = Map<String, dynamic>.from(confirmResponse['data'] as Map);
      final receipt = confirmed['receipt'] as Map?;

      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Payment successful'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Reference: ${confirmed['provider_reference'] ?? ''}'),
              Text('Total: ${_money(confirmed['amount'])}'),
              if (receipt != null) ...[
                const SizedBox(height: 8),
                Text('Receipt: ${receipt['receipt_number'] ?? ''}'),
                Text('Demo reference: ${receipt['payment_reference'] ?? ''}'),
              ],
              const SizedBox(height: 12),
              const Text(
                  'Your outstanding dues have been updated. This is a demo payment — no real money was transferred.'),
            ],
          ),
          actions: [
            FilledButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Done'))
          ],
        ),
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loadingSummary) {
      return Scaffold(
          appBar: AppBar(title: const Text('Pay all outstanding dues')),
          body: const Center(child: CircularProgressIndicator()));
    }
    if (_summary == null) {
      return Scaffold(
          appBar: AppBar(title: const Text('Pay all outstanding dues')),
          body: _ErrorState(message: _error!, onRetry: _loadSummary));
    }
    return Scaffold(
      appBar: AppBar(title: const Text('Pay all outstanding dues')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Amount due',
                    style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                Text(_money(_summary!['total_due']),
                    style: Theme.of(context)
                        .textTheme
                        .headlineMedium
                        ?.copyWith(fontWeight: FontWeight.w700)),
                const Divider(height: 28),
                _PaymentLine(
                    label: 'Maintenance',
                    value: _money(_summary!['maintenance'])),
                _PaymentLine(
                    label: 'Water charge',
                    value: _money(_summary!['water_charge'])),
                _PaymentLine(
                    label: 'Common electricity',
                    value: _money(_summary!['electricity_common_area_charge'])),
                _PaymentLine(
                    label: 'Parking charge',
                    value: _money(_summary!['parking_charge'])),
                _PaymentLine(
                    label: 'Other charges',
                    value: _money(_summary!['other_charges'])),
                _PaymentLine(
                    label: 'Late fee', value: _money(_summary!['late_fee'])),
                if (_asDouble(_summary!['discount']) > 0)
                  _PaymentLine(
                      label: 'Discount',
                      value: '-${_money(_summary!['discount'])}'),
                _PaymentLine(
                    label: 'Previous dues',
                    value: _money(_summary!['previous_dues'])),
                const Divider(),
                _PaymentLine(
                    label: 'Total due',
                    value: _money(_summary!['total_due']),
                    bold: true),
                const SizedBox(height: 12),
                const Text(
                    'The backend calculates this amount from your outstanding bills. The app never sends the payable amount.'),
              ],
            ),
          ),
        ),
        const SizedBox(height: 20),
        Text('Payment method', style: Theme.of(context).textTheme.titleMedium),
        RadioGroup<String>(
          groupValue: _method,
          onChanged: (value) {
            if (!_submitting) setState(() => _method = value!);
          },
          child: Column(children: [
            for (final method in const [
              'UPI',
              'CREDIT_CARD',
              'DEBIT_CARD',
              'NET_BANKING',
              'OTHER',
            ])
              RadioListTile<String>(
                  value: method, title: Text(_paymentMethodLabel(method))),
          ]),
        ),
        if (_error != null)
          Text(_error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error)),
        const SizedBox(height: 16),
        FilledButton.icon(
            onPressed: _submitting ? null : _submit,
            icon: _submitting
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.lock_outline),
            label: Text(_submitting ? 'Processing payment…' : 'Pay full amount')),
        const SizedBox(height: 12),
        const Text(
            '⚠ Demo mode: payments are confirmed internally. No real money is transferred.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 12)),
      ]),
    );
  }
}

class PaymentActivityScreen extends StatefulWidget {
  const PaymentActivityScreen({super.key});

  @override
  State<PaymentActivityScreen> createState() => _PaymentActivityScreenState();
}

class _PaymentActivityScreenState extends State<PaymentActivityScreen> {
  List<dynamic> _payments = [];
  List<dynamic> _orders = [];
  String? _error;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<AuthController>().api;
      final results = await Future.wait([
        api.get('resident/maintenance-payments'),
        api.get('resident/payment-orders'),
      ]);
      _payments = _pageItems(results[0]);
      _orders = _pageItems(results[1]);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Payment activity')),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? _ErrorState(message: _error!, onRetry: _load)
                : RefreshIndicator(
                    onRefresh: _load,
                    child:
                        ListView(padding: const EdgeInsets.all(16), children: [
                      if (_orders.isNotEmpty) ...[
                        Text('Full-payment orders',
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 8),
                        for (final raw in _orders)
                          _PaymentActivityCard(
                            item: Map<String, dynamic>.from(raw as Map),
                            isOrder: true,
                            onTap: () async {
                              final changed = await Navigator.of(context)
                                  .push<bool>(MaterialPageRoute(
                                      builder: (_) => PaymentOrderDetailScreen(
                                          order:
                                              Map<String, dynamic>.from(raw))));
                              if (changed == true) _load();
                            },
                          ),
                        const SizedBox(height: 20),
                      ],
                      Text('Individual payment requests',
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      if (_payments.isEmpty)
                        const Card(
                            child: Padding(
                                padding: EdgeInsets.all(16),
                                child: Text(
                                    'No individual payment requests yet.')))
                      else
                        for (final raw in _payments)
                          _PaymentActivityCard(
                              item: Map<String, dynamic>.from(raw as Map)),
                    ]),
                  ),
      );
}

class _PaymentActivityCard extends StatelessWidget {
  const _PaymentActivityCard(
      {required this.item, this.isOrder = false, this.onTap});
  final Map<String, dynamic> item;
  final bool isOrder;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final reference = isOrder
        ? item['provider_reference']?.toString() ?? 'Payment order'
        : item['reference_id']?.toString() ?? 'Payment request';
    final status = item['status']?.toString() ?? 'PENDING';
    return Card(
      child: ListTile(
        title: Text(isOrder ? 'Full payment order' : 'Payment request'),
        subtitle: Text(
            '$reference\n${_paymentMethodLabel(item['payment_method']?.toString() ?? '')}'),
        isThreeLine: true,
        trailing:
            Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Text(_money(item['amount']),
              style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 4),
          _StatusBadge(status: status),
        ]),
        onTap: onTap,
      ),
    );
  }
}

class AdminPaymentsScreen extends StatefulWidget {
  const AdminPaymentsScreen({super.key});

  @override
  State<AdminPaymentsScreen> createState() => _AdminPaymentsScreenState();
}

class _AdminPaymentsScreenState extends State<AdminPaymentsScreen> {
  List<dynamic> _payments = [];
  List<dynamic> _orders = [];
  String? _error;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<AuthController>().api;
      final results = await Future.wait([
        api.get('admin/payment-orders'),
        api.get('admin/maintenance-payments'),
      ]);
      _orders = _pageItems(results[0]);
      _payments = _pageItems(results[1]);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Payments')),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? _ErrorState(message: _error!, onRetry: _load)
                : RefreshIndicator(
                    onRefresh: _load,
                    child:
                        ListView(padding: const EdgeInsets.all(16), children: [
                      Text('Full-payment orders',
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      if (_orders.isEmpty)
                        const Card(
                            child: Padding(
                                padding: EdgeInsets.all(16),
                                child: Text('No full-payment orders pending.')))
                      else
                        for (final raw in _orders)
                          _PaymentActivityCard(
                            item: Map<String, dynamic>.from(raw as Map),
                            isOrder: true,
                            onTap: () async {
                              final changed = await Navigator.of(context)
                                  .push<bool>(MaterialPageRoute(
                                      builder: (_) => PaymentOrderDetailScreen(
                                          order: Map<String, dynamic>.from(raw),
                                          admin: true)));
                              if (changed == true) _load();
                            },
                          ),
                      const SizedBox(height: 20),
                      Text('Individual payment requests',
                          style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      if (_payments.isEmpty)
                        const Card(
                            child: Padding(
                                padding: EdgeInsets.all(16),
                                child: Text('No individual payment requests.')))
                      else
                        for (final raw in _payments)
                          _PaymentActivityCard(
                            item: Map<String, dynamic>.from(raw as Map),
                            onTap: () async {
                              final changed = await Navigator.of(context)
                                  .push<bool>(MaterialPageRoute(
                                      builder: (_) =>
                                          AdminMaintenancePaymentDetailScreen(
                                              payment:
                                                  Map<String, dynamic>.from(
                                                      raw))));
                              if (changed == true) _load();
                            },
                          ),
                    ]),
                  ),
      );
}

class AdminMaintenancePaymentDetailScreen extends StatefulWidget {
  const AdminMaintenancePaymentDetailScreen({super.key, required this.payment});
  final Map<String, dynamic> payment;

  @override
  State<AdminMaintenancePaymentDetailScreen> createState() =>
      _AdminMaintenancePaymentDetailScreenState();
}

class _AdminMaintenancePaymentDetailScreenState
    extends State<AdminMaintenancePaymentDetailScreen> {
  Map<String, dynamic>? _payment;
  String? _error;
  var _loading = true;
  var _saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await context
          .read<AuthController>()
          .api
          .get('admin/maintenance-payments/${widget.payment['id']}');
      _payment = Map<String, dynamic>.from(response['data'] as Map);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _review(String status) async {
    final receipt = TextEditingController(
        text: _payment?['receipt_reference']?.toString() ?? '');
    final saved = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
              title: Text(status == 'COMPLETED'
                  ? 'Confirm payment'
                  : 'Mark payment as failed'),
              content: status == 'COMPLETED'
                  ? TextField(
                      controller: receipt,
                      decoration: const InputDecoration(
                          labelText: 'Receipt / transaction reference',
                          helperText: 'Required for an approved payment'),
                    )
                  : const Text(
                      'This request will be marked failed and will not change the bill balance.'),
              actions: [
                TextButton(
                    onPressed: () => Navigator.pop(context, false),
                    child: const Text('Cancel')),
                FilledButton(
                    onPressed: () {
                      if (status == 'COMPLETED' &&
                          receipt.text.trim().isEmpty) {
                        return;
                      }
                      Navigator.pop(context, true);
                    },
                    child: const Text('Save')),
              ],
            ));
    if (saved != true) {
      receipt.dispose();
      return;
    }
    if (!mounted) {
      receipt.dispose();
      return;
    }
    setState(() => _saving = true);
    try {
      await context
          .read<AuthController>()
          .api
          .patch('admin/maintenance-payments/${widget.payment['id']}/status', {
        'status': status,
        if (receipt.text.trim().isNotEmpty)
          'receipt_reference': receipt.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
      receipt.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
          appBar: AppBar(title: const Text('Payment request')),
          body: const Center(child: CircularProgressIndicator()));
    }
    if (_error != null) {
      return Scaffold(
          appBar: AppBar(title: const Text('Payment request')),
          body: _ErrorState(message: _error!, onRetry: _load));
    }
    final payment = _payment!;
    final status = payment['status']?.toString() ?? 'PENDING';
    final bill = payment['bill'] as Map?;
    return Scaffold(
      appBar: AppBar(title: const Text('Payment request')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(
                    child: Text('Reference ${payment['reference_id'] ?? ''}',
                        style: Theme.of(context).textTheme.titleMedium)),
                _StatusBadge(status: status),
              ]),
              const SizedBox(height: 12),
              _PaymentLine(
                  label: 'Amount',
                  value: _money(payment['amount']),
                  bold: true),
              _PaymentLine(
                  label: 'Method',
                  value: _paymentMethodLabel(
                      payment['payment_method']?.toString() ?? '')),
              if (bill != null)
                _PaymentLine(
                    label: 'Maintenance bill',
                    value: bill['billing_month']?.toString() ?? '-'),
              if (payment['receipt_reference'] != null)
                _PaymentLine(
                    label: 'Receipt / transaction reference',
                    value: payment['receipt_reference'].toString()),
            ]),
          ),
        ),
        const SizedBox(height: 16),
        if (status == 'PENDING') ...[
          FilledButton.icon(
            onPressed: _saving ? null : () => _review('COMPLETED'),
            icon: const Icon(Icons.verified_outlined),
            label: const Text('Confirm payment'),
          ),
          TextButton(
            onPressed: _saving ? null : () => _review('FAILED'),
            child: const Text('Mark as failed'),
          ),
        ],
      ]),
    );
  }
}

class PaymentOrderDetailScreen extends StatefulWidget {
  const PaymentOrderDetailScreen(
      {super.key, required this.order, this.admin = false});
  final Map<String, dynamic> order;
  final bool admin;

  @override
  State<PaymentOrderDetailScreen> createState() =>
      _PaymentOrderDetailScreenState();
}

class _PaymentOrderDetailScreenState extends State<PaymentOrderDetailScreen> {
  Map<String, dynamic>? _order;
  String? _error;
  var _loading = true;
  var _saving = false;

  String get _endpoint =>
      widget.admin ? 'admin/payment-orders' : 'resident/payment-orders';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await context
          .read<AuthController>()
          .api
          .get('$_endpoint/${widget.order['id']}');
      _order = Map<String, dynamic>.from(response['data'] as Map);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _cancel() async {
    setState(() => _saving = true);
    try {
      await context
          .read<AuthController>()
          .api
          .patch('$_endpoint/${widget.order['id']}/cancel', {});
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _updateStatus(String status) async {
    final reference = TextEditingController(
        text: _order?['gateway_payment_id']?.toString() ?? '');
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
              title: Text(status == 'SUCCESSFUL'
                  ? 'Confirm payment'
                  : 'Update payment order'),
              content: Column(mainAxisSize: MainAxisSize.min, children: [
                if (status == 'SUCCESSFUL')
                  TextField(
                    controller: reference,
                    decoration: const InputDecoration(
                        labelText: 'Receipt / transaction reference',
                        helperText: 'Required for an approved payment'),
                  ),
                TextField(
                  controller: note,
                  maxLines: 3,
                  decoration: const InputDecoration(
                      labelText: 'Verification note (optional)'),
                ),
              ]),
              actions: [
                TextButton(
                    onPressed: () => Navigator.pop(context, false),
                    child: const Text('Cancel')),
                FilledButton(
                    onPressed: () {
                      if (status == 'SUCCESSFUL' &&
                          reference.text.trim().isEmpty) {
                        return;
                      }
                      Navigator.pop(context, true);
                    },
                    child: const Text('Save')),
              ],
            ));
    if (confirmed != true) {
      reference.dispose();
      note.dispose();
      return;
    }
    if (!mounted) {
      reference.dispose();
      note.dispose();
      return;
    }
    setState(() => _saving = true);
    try {
      await context
          .read<AuthController>()
          .api
          .patch('$_endpoint/${widget.order['id']}/status', {
        'status': status,
        if (reference.text.trim().isNotEmpty)
          'gateway_payment_id': reference.text.trim(),
        if (note.text.trim().isNotEmpty) 'verification_note': note.text.trim(),
      });
      await _load();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
      reference.dispose();
      note.dispose();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
          appBar: AppBar(title: const Text('Payment order')),
          body: const Center(child: CircularProgressIndicator()));
    }
    if (_error != null) {
      return Scaffold(
          appBar: AppBar(title: const Text('Payment order')),
          body: _ErrorState(message: _error!, onRetry: _load));
    }
    final order = _order!;
    final status = order['status']?.toString() ?? 'PENDING';
    final items = order['items'] is List ? order['items'] as List : const [];
    final canReview =
        widget.admin && const ['PENDING', 'PROCESSING'].contains(status);
    return Scaffold(
      appBar: AppBar(title: const Text('Payment order')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(
                    child: Text('Order ${order['provider_reference'] ?? ''}',
                        style: Theme.of(context).textTheme.titleMedium)),
                _StatusBadge(status: status),
              ]),
              const SizedBox(height: 12),
              _PaymentLine(
                  label: 'Total', value: _money(order['amount']), bold: true),
              _PaymentLine(
                  label: 'Method',
                  value: _paymentMethodLabel(
                      order['payment_method']?.toString() ?? '')),
              if (order['gateway_payment_id'] != null)
                _PaymentLine(
                    label: 'Receipt / transaction reference',
                    value: order['gateway_payment_id'].toString()),
              if (order['verification_note']?.toString().isNotEmpty ?? false)
                Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(order['verification_note'].toString())),
              if (order['receipt'] is Map)
                Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.receipt_long_outlined),
                    label: const Text('View receipt'),
                    onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute(
                            builder: (_) => PaymentReceiptScreen(
                                orderId: order['id'], admin: widget.admin))),
                  ),
                ),
            ]),
          ),
        ),
        const SizedBox(height: 16),
        Text('Bills in this order',
            style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        for (final raw in items)
          Card(
              child: ListTile(
                  title: Text(
                      'Maintenance ${((raw as Map)['bill'] as Map?)?['billing_month'] ?? ''}'),
                  subtitle: Text(
                      'Due ${((raw['bill'] as Map?)?['due_date'] ?? '-')}'),
                  trailing: Text(_money(raw['amount']),
                      style: const TextStyle(fontWeight: FontWeight.w700)))),
        const SizedBox(height: 16),
        if (!widget.admin && status == 'PENDING')
          OutlinedButton.icon(
            onPressed: _saving ? null : _cancel,
            icon: const Icon(Icons.cancel_outlined),
            label: const Text('Cancel payment order'),
          ),
        if (canReview) ...[
          if (status == 'PENDING')
            OutlinedButton.icon(
              onPressed: _saving ? null : () => _updateStatus('PROCESSING'),
              icon: const Icon(Icons.manage_search_outlined),
              label: const Text('Mark as under review'),
            ),
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: _saving ? null : () => _updateStatus('SUCCESSFUL'),
            icon: const Icon(Icons.verified_outlined),
            label: const Text('Confirm payment'),
          ),
          TextButton(
            onPressed: _saving ? null : () => _updateStatus('FAILED'),
            child: const Text('Mark as failed'),
          ),
        ],
        if (!widget.admin && status == 'PENDING')
          const Padding(
            padding: EdgeInsets.only(top: 12),
            child: Text(
                'This request is awaiting society verification. Your dues are updated only after the payment is confirmed.'),
          ),
      ]),
    );
  }
}

class PaymentReceiptScreen extends StatefulWidget {
  const PaymentReceiptScreen(
      {super.key, required this.orderId, this.admin = false});
  final dynamic orderId;
  final bool admin;

  @override
  State<PaymentReceiptScreen> createState() => _PaymentReceiptScreenState();
}

class _PaymentReceiptScreenState extends State<PaymentReceiptScreen> {
  Map<String, dynamic>? _receipt;
  String? _error;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final endpoint =
          widget.admin ? 'admin/payment-orders' : 'resident/payment-orders';
      final response = await context
          .read<AuthController>()
          .api
          .get('$endpoint/${widget.orderId}/receipt');
      _receipt = Map<String, dynamic>.from(response['data'] as Map);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Payment receipt')),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? _ErrorState(message: _error!, onRetry: _load)
                : ListView(padding: const EdgeInsets.all(20), children: [
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(children: [
                                const Icon(Icons.verified_rounded,
                                    color: Colors.green),
                                const SizedBox(width: 8),
                                Text('Payment successful',
                                    style:
                                        Theme.of(context).textTheme.titleLarge),
                              ]),
                              const Divider(height: 28),
                              _PaymentLine(
                                  label: 'Receipt number',
                                  value:
                                      _receipt!['receipt_number']?.toString() ??
                                          '-'),
                              _PaymentLine(
                                  label: 'Amount',
                                  value: _money(_receipt!['amount']),
                                  bold: true),
                              _PaymentLine(
                                  label: 'Method',
                                  value: _paymentMethodLabel(
                                      _receipt!['payment_method']?.toString() ??
                                          '')),
                              _PaymentLine(
                                  label: 'Payment reference',
                                  value: _receipt!['payment_reference']
                                          ?.toString() ??
                                      '-'),
                              _PaymentLine(
                                  label: 'Issued at',
                                  value: _receipt!['issued_at']?.toString() ??
                                      '-'),
                            ]),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                        'Keep this receipt number for your records. The confirmed amount has been applied to your maintenance dues.'),
                  ]),
      );
}

class ResidentVisitorsScreen extends StatefulWidget {
  const ResidentVisitorsScreen({super.key});

  @override
  State<ResidentVisitorsScreen> createState() => _ResidentVisitorsScreenState();
}

class _ResidentVisitorsScreenState extends State<ResidentVisitorsScreen> {
  List<dynamic> _visitors = [];
  String? _error;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _visitors = _pageItems(
          await context.read<AuthController>().api.get('resident/visitors'));
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _action(Map<String, dynamic> visitor, String action) async {
    try {
      await context
          .read<AuthController>()
          .api
          .patch('resident/visitors/${visitor['id']}/$action', {});
      await _load();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _ErrorState(message: _error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _visitors.isEmpty
                      ? ListView(children: const [
                          SizedBox(height: 180),
                          Center(
                              child: Text(
                                  'No upcoming visitors. Add one when you are expecting someone.'))
                        ])
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _visitors.length,
                          itemBuilder: (_, index) {
                            final visitor = Map<String, dynamic>.from(
                                _visitors[index] as Map);
                            final pending =
                                visitor['approval_status'] == 'PENDING' &&
                                    !visitor['is_pre_approved'];
                            return Card(
                                child: Padding(
                                    padding: const EdgeInsets.all(14),
                                    child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Row(children: [
                                            Expanded(
                                                child: Text(
                                                    visitor['visitor_name']
                                                            ?.toString() ??
                                                        'Visitor',
                                                    style: Theme.of(context)
                                                        .textTheme
                                                        .titleMedium)),
                                            _StatusBadge(
                                                status: visitor['entry_status']
                                                        ?.toString() ??
                                                    'EXPECTED')
                                          ]),
                                          const SizedBox(height: 6),
                                          Text(
                                              '${visitor['visitor_type'] ?? 'Guest'} · ${visitor['purpose'] ?? ''}'),
                                          if (visitor['expected_at'] != null)
                                            Text(
                                                'Expected: ${visitor['expected_at']}'),
                                          if (pending)
                                            Padding(
                                                padding: const EdgeInsets.only(
                                                    top: 10),
                                                child:
                                                    Wrap(spacing: 8, children: [
                                                  FilledButton(
                                                      onPressed: () => _action(
                                                          visitor, 'approve'),
                                                      child: const Text(
                                                          'Approve')),
                                                  OutlinedButton(
                                                      onPressed: () => _action(
                                                          visitor, 'reject'),
                                                      child:
                                                          const Text('Reject')),
                                                ])),
                                        ])));
                          },
                        ),
                ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final changed = await Navigator.of(context).push<bool>(
              MaterialPageRoute(
                  builder: (_) => const VisitorPreApprovalScreen()));
          if (changed == true) _load();
        },
        icon: const Icon(Icons.person_add_alt_1),
        label: const Text('Add visitor'),
      ));
}

class VisitorPreApprovalScreen extends StatefulWidget {
  const VisitorPreApprovalScreen({super.key});

  @override
  State<VisitorPreApprovalScreen> createState() =>
      _VisitorPreApprovalScreenState();
}

class _VisitorPreApprovalScreenState extends State<VisitorPreApprovalScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _mobile = TextEditingController();
  final _purpose = TextEditingController();
  final _vehicle = TextEditingController();
  DateTime _expectedAt = DateTime.now().add(const Duration(hours: 1));
  var _type = 'GUEST';
  var _saving = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _mobile.dispose();
    _purpose.dispose();
    _vehicle.dispose();
    super.dispose();
  }

  Future<void> _selectDateTime() async {
    final date = await showDatePicker(
        context: context,
        firstDate: DateTime.now(),
        lastDate: DateTime.now().add(const Duration(days: 365)),
        initialDate: _expectedAt);
    if (date == null || !mounted) return;
    final time = await showTimePicker(
        context: context, initialTime: TimeOfDay.fromDateTime(_expectedAt));
    if (time != null) {
      setState(() => _expectedAt =
          DateTime(date.year, date.month, date.day, time.hour, time.minute));
    }
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await context
          .read<AuthController>()
          .api
          .post('resident/visitors/pre-approvals', {
        'visitor_name': _name.text.trim(),
        'mobile_number': _mobile.text.trim(),
        'purpose': _purpose.text.trim(),
        'visitor_type': _type,
        'vehicle_number':
            _vehicle.text.trim().isEmpty ? null : _vehicle.text.trim(),
        'expected_at': _expectedAt.toIso8601String(),
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Pre-approve visitor')),
        body: Form(
            key: _form,
            child: ListView(padding: const EdgeInsets.all(16), children: [
              _TextField(controller: _name, label: 'Visitor name'),
              _TextField(
                  controller: _mobile,
                  label: 'Mobile number',
                  keyboardType: TextInputType.phone),
              _TextField(controller: _purpose, label: 'Purpose'),
              _TextField(
                  controller: _vehicle,
                  label: 'Vehicle number (optional)',
                  required: false),
              DropdownButtonFormField<String>(
                  initialValue: _type,
                  decoration: const InputDecoration(
                      labelText: 'Visitor type', border: OutlineInputBorder()),
                  items: const [
                    DropdownMenuItem(value: 'GUEST', child: Text('Guest')),
                    DropdownMenuItem(
                        value: 'DELIVERY', child: Text('Delivery')),
                    DropdownMenuItem(value: 'CAB', child: Text('Cab / taxi')),
                    DropdownMenuItem(
                        value: 'SERVICE_PROVIDER',
                        child: Text('Service provider')),
                    DropdownMenuItem(value: 'OTHER', child: Text('Other')),
                  ],
                  onChanged: _saving
                      ? null
                      : (value) => setState(() => _type = value!)),
              const SizedBox(height: 14),
              OutlinedButton.icon(
                  onPressed: _saving ? null : _selectDateTime,
                  icon: const Icon(Icons.schedule),
                  label: Text('Expected: ${_expectedAt.toLocal()}')),
              if (_error != null)
                Padding(
                    padding: const EdgeInsets.only(top: 12),
                    child: Text(_error!,
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.error))),
              const SizedBox(height: 20),
              FilledButton(
                  onPressed: _saving ? null : _save,
                  child: Text(_saving ? 'Saving…' : 'Pre-approve visitor')),
            ])),
      );
}

class ResidentMoreScreen extends StatelessWidget {
  const ResidentMoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    const modules = [
      ('Amenities', Icons.pool_outlined),
      ('Bookings', Icons.event_note_outlined),
      ('Complaints', Icons.support_agent_outlined),
      ('Notices', Icons.campaign_outlined),
      ('Payments', Icons.receipt_long_outlined),
      ('Parking', Icons.local_parking_outlined),
      ('Parcels', Icons.inventory_2_outlined),
      ('Messages', Icons.forum_outlined),
      ('Notifications', Icons.notifications_outlined),
      ('Profile', Icons.person_outline),
    ];
    return ListView(padding: const EdgeInsets.all(12), children: [
      for (final module in modules)
        Card(
            child: ListTile(
          leading: Icon(module.$2),
          title: Text(module.$1),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.of(context).push(MaterialPageRoute(
              builder: (_) => module.$1 == 'Payments'
                  ? const DuesScreen()
                  : module.$1 == 'Profile'
                      ? const AccountProfileScreen()
                      : ModuleScreen(title: module.$1, role: 'RESIDENT'))),
        )),
      const SizedBox(height: 8),
      Card(
          child: ListTile(
        leading: const Icon(Icons.logout),
        title: const Text('Log out'),
        onTap: () => context.read<AuthController>().logout(),
      )),
    ]);
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, this.action, this.onAction});
  final String title;
  final String? action;
  final VoidCallback? onAction;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Row(children: [
          Expanded(
              child: Text(title,
                  style: Theme.of(context)
                      .textTheme
                      .titleLarge
                      ?.copyWith(fontWeight: FontWeight.w700))),
          if (action != null)
            TextButton(onPressed: onAction, child: Text(action!))
        ]),
      );
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile(
      {required this.icon,
      required this.label,
      required this.value,
      required this.color,
      required this.onTap});
  final IconData icon;
  final String label;
  final String value;
  final Color color;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          decoration: BoxDecoration(
              color: color.withValues(alpha: .09),
              borderRadius: BorderRadius.circular(16)),
          padding: const EdgeInsets.all(14),
          child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(icon, color: color),
                const SizedBox(height: 8),
                Text(label),
                const SizedBox(height: 2),
                Text(value,
                    style: Theme.of(context)
                        .textTheme
                        .titleMedium
                        ?.copyWith(fontWeight: FontWeight.w700)),
              ]),
        ),
      );
}

class _QuickAction extends StatelessWidget {
  const _QuickAction(
      {required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
          width: 100,
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          decoration: BoxDecoration(
              border: Border.all(
                  color: Theme.of(context).colorScheme.outlineVariant),
              borderRadius: BorderRadius.circular(14)),
          child: Column(children: [
            Icon(icon),
            const SizedBox(height: 6),
            Text(label,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis)
          ])));
}

class _UrgentCard extends StatelessWidget {
  const _UrgentCard(
      {required this.icon,
      required this.title,
      required this.message,
      required this.action,
      required this.onTap});
  final IconData icon;
  final String title;
  final String message;
  final String action;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => Card(
      color: Theme.of(context).colorScheme.errorContainer,
      child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(icon, color: Theme.of(context).colorScheme.error),
            const SizedBox(width: 12),
            Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                  Text(title,
                      style: Theme.of(context)
                          .textTheme
                          .titleMedium
                          ?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 4),
                  Text(message),
                  TextButton(onPressed: onTap, child: Text(action))
                ]))
          ])));
}

class _InfoCard extends StatelessWidget {
  const _InfoCard(
      {required this.icon,
      required this.title,
      required this.subtitle,
      this.onTap});
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => Card(
      child: ListTile(
          leading: Icon(icon),
          title: Text(title),
          subtitle: subtitle.isEmpty
              ? null
              : Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis),
          trailing: onTap == null ? null : const Icon(Icons.chevron_right),
          onTap: onTap));
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.message});
  final String message;
  @override
  Widget build(BuildContext context) => Card(
      child: Padding(
          padding: const EdgeInsets.all(20),
          child: Center(child: Text(message, textAlign: TextAlign.center))));
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;
  @override
  Widget build(BuildContext context) => Center(
      child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_outlined, size: 40),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Try again'))
          ])));
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;
  @override
  Widget build(BuildContext context) {
    final normalized = status.replaceAll('_', ' ');
    final color = status.contains('PAID') ||
            status.contains('APPROVED') ||
            status.contains('CONFIRMED') ||
            status.contains('ENTERED')
        ? Colors.green
        : status.contains('OVERDUE') || status.contains('REJECTED')
            ? Colors.red
            : Colors.orange;
    return Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
            color: color.withValues(alpha: .12),
            borderRadius: BorderRadius.circular(99)),
        child: Text(normalized,
            style: TextStyle(
                fontSize: 11, color: color, fontWeight: FontWeight.w700)));
  }
}

class _PaymentLine extends StatelessWidget {
  const _PaymentLine(
      {required this.label, required this.value, this.bold = false});
  final String label;
  final String value;
  final bool bold;
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(label,
            style: bold ? const TextStyle(fontWeight: FontWeight.w700) : null),
        Text(value,
            style: bold ? const TextStyle(fontWeight: FontWeight.w700) : null)
      ]));
}

class _TextField extends StatelessWidget {
  const _TextField(
      {required this.controller,
      required this.label,
      this.keyboardType,
      this.required = true,
      this.obscure = false,
      this.validator});
  final TextEditingController controller;
  final String label;
  final TextInputType? keyboardType;
  final bool required;
  final bool obscure;
  final String? Function(String?)? validator;
  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextFormField(
          controller: controller,
          keyboardType: keyboardType,
          obscureText: obscure,
          decoration: InputDecoration(
              labelText: label, border: const OutlineInputBorder()),
          validator: validator ??
              (required
                  ? (value) => value == null || value.trim().isEmpty
                      ? '$label is required'
                      : null
                  : null)));
}

List<dynamic> _pageItems(Map<String, dynamic> response) {
  final data = response['data'];
  if (data is List) return data;
  if (data is Map && data['data'] is List) {
    return List<dynamic>.from(data['data'] as List);
  }
  return [];
}

double _asDouble(dynamic value) =>
    double.tryParse(value?.toString() ?? '') ?? 0;
String _money(dynamic value) => '₹${_asDouble(value).toStringAsFixed(2)}';
String _initials(String? name) => (name ?? 'R')
    .trim()
    .split(RegExp(r'\s+'))
    .where((word) => word.isNotEmpty)
    .take(2)
    .map((word) => word[0].toUpperCase())
    .join();
String _paymentMethodLabel(String method) => switch (method) {
      'UPI' => 'UPI',
      'CARD' => 'Credit / debit card',
      'CREDIT_CARD' => 'Credit card',
      'DEBIT_CARD' => 'Debit card',
      'NET_BANKING' => 'Net banking',
      'BANK_TRANSFER' => 'Net banking / transfer',
      'CASH' => 'Pay at society office',
      'OTHER' => 'Other supported method',
      _ => method
    };
String _emptyMessage(String title) => switch (title) {
      'Complaints' =>
        'No complaints yet. Report an issue to society management.',
      'Bookings' => 'No bookings yet. Book an amenity when you need it.',
      'Parcels' => 'No parcels are currently available.',
      'Notifications' => 'No new notifications.',
      'Notices' => 'No notices are available.',
      'Visitors' => 'No visitors found.',
      _ => 'No records found.',
    };

class ModuleScreen extends StatefulWidget {
  const ModuleScreen({super.key, required this.title, required this.role});
  final String title;
  final String role;
  @override
  State<ModuleScreen> createState() => _ModuleScreenState();
}

class _ModuleScreenState extends State<ModuleScreen> {
  List<dynamic> _items = [];
  String? _error;
  bool _loading = true;
  String get _endpoint {
    const resident = {
      'Profile': 'resident/profile',
      'Visitors': 'resident/visitors',
      'Maintenance': 'resident/maintenance-bills',
      'Payments': 'resident/maintenance-payments',
      'Complaints': 'resident/complaints',
      'Notices': 'resident/notices',
      'Parking': 'resident/parking-slots',
      'Amenities': 'amenities',
      'Bookings': 'resident/amenity-bookings',
      'Messages': 'conversations',
      'Notifications': 'notifications'
    };
    const security = {
      'Visitors': 'security/visitors',
      'Parcels': 'security/parcels',
      'Notices': 'security/notices',
      'Messages': 'conversations',
      'Notifications': 'notifications'
    };
    const staff = {
      'Complaints': 'staff/complaints',
      'Parcels': 'staff/parcels',
      'Notices': 'staff/notices',
      'Messages': 'conversations',
      'Notifications': 'notifications',
    };
    const admin = {
      'Dashboard': 'admin/dashboard',
      'Residents': 'admin/residents',
      'Flats': 'admin/flats',
      'Visitors': 'admin/visitors',
      'Maintenance': 'admin/maintenance-bills',
      'Payments': 'admin/payment-orders',
      'Reports': 'admin/reports',
      'Complaints': 'admin/complaints',
      'Notices': 'admin/notices',
      'Staff': 'admin/staff-members',
      'Parking': 'admin/parking-slots',
      'Parcels': 'admin/parcels',
      'Amenities': 'admin/amenities',
      'Bookings': 'admin/amenity-bookings',
      'Messages': 'conversations',
      'Notifications': 'notifications'
    };
    return (widget.role == 'ADMIN'
        ? admin
        : widget.role == 'SECURITY'
            ? security
            : widget.role == 'STAFF'
                ? staff
                : resident)[widget.title]!;
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await context.read<AuthController>().api.get(_endpoint);
      final data = response['data'];
      _items = data is List
          ? data
          : data is Map && data['data'] is List
              ? data['data'] as List<dynamic>
              : data is Map
                  ? data.entries
                      .map((entry) =>
                          {'name': entry.key, 'status': '${entry.value}'})
                      .toList()
                  : [];
    } on ApiException catch (exception) {
      _error = exception.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  FormConfig? get _form => FormConfig.forRoute(_endpoint);
  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
          appBar: AppBar(title: Text(widget.title)),
          body: const Center(child: CircularProgressIndicator()));
    }
    if (_error != null) {
      return Scaffold(
          appBar: AppBar(title: Text(widget.title)),
          body: Center(
              child: FilledButton(
                  onPressed: _load, child: Text('Retry: $_error'))));
    }
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      floatingActionButton: _form == null || !_form!.showCreate
          ? null
          : FloatingActionButton(
              onPressed: () async {
                final result = await Navigator.of(context).push<bool>(
                  MaterialPageRoute(
                      builder: (_) => ModuleFormScreen(config: _form!)),
                );
                if (result == true) _load();
              },
              child: const Icon(Icons.add),
            ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _items.isEmpty
            ? ListView(children: [
                const SizedBox(height: 180),
                Center(
                    child: Text(_emptyMessage(widget.title),
                        textAlign: TextAlign.center)),
                if (_form != null && _form!.showCreate)
                  Padding(
                    padding: const EdgeInsets.all(20),
                    child: Center(
                        child: FilledButton.icon(
                      icon: const Icon(Icons.add),
                      label: Text(_form!.title),
                      onPressed: () async {
                        final result = await Navigator.of(context).push<bool>(
                            MaterialPageRoute(
                                builder: (_) =>
                                    ModuleFormScreen(config: _form!)));
                        if (result == true) _load();
                      },
                    )),
                  ),
              ])
            : ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: _items.length,
                itemBuilder: (_, index) {
                  final item = Map<String, dynamic>.from(_items[index] as Map);
                  final headline = item['name'] ??
                      item['title'] ??
                      item['provider_reference'] ??
                      item['reference_id'] ??
                      item['flat_number'] ??
                      item['visitor_name'] ??
                      item['slot_number'] ??
                      'Record #${item['id']}';
                  final subtitle = [
                    item['status'],
                    item['email'],
                    item['due_date'],
                    item['payment_method']
                  ].whereType<String>().join(' • ');
                  return Card(
                    child: ListTile(
                      title: Text('$headline'),
                      subtitle: subtitle.isEmpty ? null : Text(subtitle),
                      trailing: _endpoint == 'admin/staff-members' && item['status'] != null
                          ? Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: item['status'] == 'ACTIVE'
                                    ? Colors.green.shade100
                                    : Colors.orange.shade100,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Text(
                                '${item['status']}',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold,
                                  color: item['status'] == 'ACTIVE'
                                      ? Colors.green.shade800
                                      : Colors.orange.shade900,
                                ),
                              ),
                            )
                          : null,
                      onTap: () async {
                        final changed = await Navigator.of(context).push<bool>(
                          MaterialPageRoute(
                            builder: (_) => _endpoint == 'conversations'
                                ? ConversationDetailScreen(conversation: item)
                                : _endpoint == 'admin/payment-orders'
                                    ? PaymentOrderDetailScreen(
                                        order: item, admin: true)
                                    : RecordDetailScreen(
                                        title: widget.title,
                                        endpoint: _endpoint,
                                        item: item,
                                        config: _form,
                                      ),
                          ),
                        );
                        if (changed == true) _load();
                      },
                    ),
                  );
                },
              ),
      ),
    );
  }
}

class ConversationDetailScreen extends StatefulWidget {
  const ConversationDetailScreen({super.key, required this.conversation});
  final Map<String, dynamic> conversation;

  @override
  State<ConversationDetailScreen> createState() =>
      _ConversationDetailScreenState();
}

class _ConversationDetailScreenState extends State<ConversationDetailScreen> {
  final _message = TextEditingController();
  Map<String, dynamic>? _conversation;
  String? _error;
  bool _loading = true;
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final api = context.read<AuthController>().api;
      final result =
          await api.get('conversations/${widget.conversation['id']}');
      await api.patch('conversations/${widget.conversation['id']}/read', {});
      _conversation = Map<String, dynamic>.from(result['data'] as Map);
    } on ApiException catch (error) {
      _error = error.message;
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _send() async {
    final body = _message.text.trim();
    if (body.isEmpty) return;
    setState(() => _sending = true);
    try {
      await context.read<AuthController>().api.post(
          'conversations/${widget.conversation['id']}/messages',
          {'body': body});
      _message.clear();
      await _load();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final messages = _conversation?['messages'] as List<dynamic>? ?? [];
    return Scaffold(
      appBar: AppBar(
          title: Text(_conversation?['subject']?.toString() ?? 'Conversation')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: FilledButton(
                      onPressed: _load, child: Text('Retry: $_error')))
              : Column(children: [
                  Expanded(
                    child: RefreshIndicator(
                      onRefresh: _load,
                      child: messages.isEmpty
                          ? ListView(children: const [
                              SizedBox(height: 180),
                              Center(child: Text('No messages yet.')),
                            ])
                          : ListView.builder(
                              padding: const EdgeInsets.all(12),
                              itemCount: messages.length,
                              itemBuilder: (_, index) {
                                final message = Map<String, dynamic>.from(
                                    messages[index] as Map);
                                final sender = message['sender'] is Map
                                    ? (message['sender'] as Map)['name']
                                    : 'Member';
                                return Card(
                                    child: ListTile(
                                  title:
                                      Text(message['body']?.toString() ?? ''),
                                  subtitle:
                                      Text(sender?.toString() ?? 'Member'),
                                ));
                              },
                            ),
                    ),
                  ),
                  SafeArea(
                    top: false,
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(children: [
                        Expanded(
                          child: TextField(
                            controller: _message,
                            minLines: 1,
                            maxLines: 4,
                            decoration: const InputDecoration(
                                labelText: 'Message',
                                border: OutlineInputBorder()),
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton(
                          onPressed: _sending ? null : _send,
                          icon: _sending
                              ? const CircularProgressIndicator()
                              : const Icon(Icons.send),
                        ),
                      ]),
                    ),
                  ),
                ]),
    );
  }
}

class RecordDetailScreen extends StatefulWidget {
  const RecordDetailScreen(
      {super.key,
      required this.title,
      required this.endpoint,
      required this.item,
      this.config});
  final String title;
  final String endpoint;
  final Map<String, dynamic> item;
  final FormConfig? config;
  @override
  State<RecordDetailScreen> createState() => _RecordDetailScreenState();
}

class _RecordDetailScreenState extends State<RecordDetailScreen> {
  late Map<String, dynamic> _item;
  bool _updatingStatus = false;
  bool _hasChanged = false;

  @override
  void initState() {
    super.initState();
    _item = Map<String, dynamic>.from(widget.item);
  }

  bool get _canDelete => const {
        'admin/residents',
        'admin/flats',
        'admin/maintenance-bills'
      }.contains(widget.endpoint);

  Future<void> _delete() async {
    final api = context.read<AuthController>().api;
    final accepted = await showDialog<bool>(
        context: context,
        builder: (_) => AlertDialog(
                title: const Text('Delete record?'),
                content: const Text('This action cannot be undone.'),
                actions: [
                  TextButton(
                      onPressed: () => Navigator.pop(context, false),
                      child: const Text('Cancel')),
                  FilledButton(
                      onPressed: () => Navigator.pop(context, true),
                      child: const Text('Delete'))
                ]));
    if (accepted != true) return;
    try {
      await api.delete('${widget.endpoint}/${_item['id']}');
      if (mounted) {
        Navigator.pop(context, true);
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _patch(String suffix, [Map<String, dynamic>? data]) async {
    try {
      await context
          .read<AuthController>()
          .api
          .patch('${widget.endpoint}/${_item['id']}/$suffix', data ?? {});
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _updateStaffStatus(String newStatus) async {
    final isActivating = newStatus == 'ACTIVE';
    final actionWord = isActivating ? 'Activate' : 'Deactivate';
    final confirmTitle = '$actionWord staff account?';
    final confirmBody = isActivating
        ? 'Activate this staff account?'
        : 'Deactivate this staff account?';

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(confirmTitle),
        content: Text(confirmBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(actionWord),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _updatingStatus = true);
    final api = context.read<AuthController>().api;

    try {
      final response = await api.patch(
        'admin/staff-members/${_item['id']}/status',
        {'status': newStatus},
      );

      if (!mounted) return;

      final updated = response['data'] is Map
          ? Map<String, dynamic>.from(response['data'] as Map)
          : null;

      setState(() {
        if (updated != null) {
          _item = updated;
        } else {
          _item['status'] = newStatus;
        }
        _hasChanged = true;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isActivating
                ? 'Staff account activated successfully.'
                : 'Staff account deactivated successfully.',
          ),
        ),
      );
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _updatingStatus = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, result) {
          if (!didPop) Navigator.pop(context, _hasChanged);
        },
        child: Scaffold(
            appBar: AppBar(
                title: Text(widget.title),
                leading: IconButton(
                  icon: const Icon(Icons.arrow_back),
                  onPressed: () => Navigator.pop(context, _hasChanged),
                ),
                actions: [
                  if (widget.config != null)
                    IconButton(
                        icon: const Icon(Icons.edit),
                        onPressed: () async {
                          final changed = await Navigator.push<bool>(
                              context,
                              MaterialPageRoute(
                                  builder: (_) => ModuleFormScreen(
                                      config: widget.config!,
                                      initial: widget.endpoint == 'resident/profile' &&
                                              _item['user'] is Map
                                          ? {
                                              ..._item,
                                              ...Map<String, dynamic>.from(
                                                  _item['user'] as Map),
                                            }
                                          : _item,
                                      submitPath: widget.endpoint == 'resident/profile'
                                          ? widget.endpoint
                                          : '${widget.endpoint}/${_item['id']}',
                                      isEdit: true)));
                          if (changed == true && context.mounted) {
                            setState(() => _hasChanged = true);
                            Navigator.pop(context, true);
                          }
                        }),
                  if (_canDelete)
                    IconButton(icon: const Icon(Icons.delete), onPressed: _delete),
                  if (widget.endpoint == 'resident/visitors') ...[
                    IconButton(
                        tooltip: 'Approve',
                        icon: const Icon(Icons.check_circle),
                        onPressed: () => _patch('approve')),
                    IconButton(
                        tooltip: 'Reject',
                        icon: const Icon(Icons.cancel),
                        onPressed: () => _patch('reject')),
                  ],
                  if (widget.endpoint == 'security/visitors') ...[
                    IconButton(
                        tooltip: 'Record entry',
                        icon: const Icon(Icons.login),
                        onPressed: () => _patch('entry')),
                    IconButton(
                        tooltip: 'Record exit',
                        icon: const Icon(Icons.logout),
                        onPressed: () => _patch('exit')),
                  ],
                  if (widget.endpoint == 'resident/amenity-bookings' ||
                      widget.endpoint == 'admin/amenity-bookings')
                    IconButton(
                        tooltip: 'Cancel booking',
                        icon: const Icon(Icons.cancel_schedule_send),
                        onPressed: () => _patch('cancel')),
                  if (widget.endpoint == 'notifications')
                    IconButton(
                        tooltip: 'Mark read',
                        icon: const Icon(Icons.mark_email_read),
                        onPressed: () async {
                          final api = context.read<AuthController>().api;
                          try {
                            await api
                                .patch('notifications/${_item['id']}/read', {});
                            if (!context.mounted) return;
                            Navigator.pop(context, true);
                          } on ApiException catch (error) {
                            if (!context.mounted) return;
                            ScaffoldMessenger.of(context)
                                .showSnackBar(SnackBar(content: Text(error.message)));
                          }
                        }),
                  if (widget.endpoint == 'admin/parcels' ||
                      widget.endpoint == 'staff/parcels')
                    IconButton(
                        tooltip: 'Mark collected',
                        icon: const Icon(Icons.inventory_2),
                        onPressed: () => _patch('collect'))
                ]),
            body: ListView(padding: const EdgeInsets.all(16), children: [
              if (widget.endpoint == 'admin/staff-members') ...[
                Card(
                  elevation: 2,
                  color: _item['status'] == 'ACTIVE'
                      ? Colors.green.shade50
                      : Colors.orange.shade50,
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              _item['status'] == 'ACTIVE'
                                  ? Icons.check_circle
                                  : Icons.pause_circle_filled,
                              color: _item['status'] == 'ACTIVE'
                                  ? Colors.green.shade700
                                  : Colors.orange.shade800,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              'Status: ${_item['status'] ?? 'INACTIVE'}',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                                color: _item['status'] == 'ACTIVE'
                                    ? Colors.green.shade800
                                    : Colors.orange.shade900,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        if (_item['status'] == 'ACTIVE')
                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton.icon(
                              onPressed: _updatingStatus
                                  ? null
                                  : () => _updateStaffStatus('INACTIVE'),
                              icon: _updatingStatus
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Icon(Icons.block),
                              label: Text(_updatingStatus ? 'Updating...' : 'Deactivate Account'),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: Colors.red.shade700,
                                side: BorderSide(color: Colors.red.shade300),
                              ),
                            ),
                          )
                        else
                          SizedBox(
                            width: double.infinity,
                            child: FilledButton.icon(
                              onPressed: _updatingStatus
                                  ? null
                                  : () => _updateStaffStatus('ACTIVE'),
                              icon: _updatingStatus
                                  ? const SizedBox(
                                      width: 18,
                                      height: 18,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                  : const Icon(Icons.verified_user),
                              label: Text(_updatingStatus ? 'Updating...' : 'Activate Account'),
                              style: FilledButton.styleFrom(
                                backgroundColor: Colors.green.shade700,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
              ],
              for (final entry
                  in _item.entries.where((entry) => entry.value != null))
                if (entry.value is Map)
                  for (final subEntry
                      in (entry.value as Map).entries.where((e) => e.value != null))
                    ListTile(
                      title: Text('${entry.key} ${subEntry.key}'.replaceAll('_', ' ')),
                      subtitle: Text('${subEntry.value}'),
                    )
                else
                  ListTile(
                      title: Text(entry.key.replaceAll('_', ' ')),
                      subtitle: Text('${entry.value}'))
            ])),
      );
}

class FormConfig {
  const FormConfig(this.title, this.path, this.fields,
      {this.patchOnEdit = false,
      this.optionalFields = const [],
      this.showCreate = true,
      this.optionsEndpoint,
      this.optionKeys = const {},
      this.staticOptions = const {}});
  final String title;
  final String path;
  final List<String> fields;
  final bool patchOnEdit;
  final List<String> optionalFields;
  final bool showCreate;
  final String? optionsEndpoint;
  final Map<String, String> optionKeys;
  final Map<String, List<FormOption>> staticOptions;
  static FormConfig? forRoute(String route) => {
        'resident/profile': const FormConfig('Edit profile', 'resident/profile',
            ['name', 'email', 'phone', 'password'],
            patchOnEdit: true, optionalFields: ['password']),
        'resident/complaints':
            const FormConfig('New complaint', 'resident/complaints', [
          'category',
          'title',
          'description',
          'priority'
        ], optionalFields: [
          'published_at',
          'expires_at'
        ], staticOptions: {
          'category': [
            FormOption('PLUMBING', 'Plumbing'),
            FormOption('ELECTRICITY', 'Electricity'),
            FormOption('SECURITY', 'Security'),
            FormOption('CLEANING', 'Cleaning'),
            FormOption('LIFT', 'Lift'),
            FormOption('PARKING', 'Parking'),
            FormOption('WATER', 'Water'),
            FormOption('NOISE', 'Noise'),
            FormOption('MAINTENANCE', 'Maintenance'),
            FormOption('OTHER', 'Other'),
          ],
          'priority': [
            FormOption('LOW', 'Low'),
            FormOption('MEDIUM', 'Medium'),
            FormOption('HIGH', 'High'),
            FormOption('EMERGENCY', 'Emergency'),
          ],
        }),
        'resident/maintenance-payments': const FormConfig(
            'Submit payment', 'resident/maintenance-payments', [
          'maintenance_bill_id',
          'amount',
          'payment_method',
          'reference_id',
          'receipt_reference',
        ],
            optionalFields: [
              'reference_id',
              'receipt_reference'
            ]),
        'resident/visitors': const FormConfig(
            'Pre-approve visitor',
            'resident/visitors/pre-approvals',
            [
              'visitor_name',
              'visitor_type',
              'expected_at',
            ],
            staticOptions: {
              'visitor_type': [
                FormOption('GUEST', 'Guest'),
                FormOption('DELIVERY', 'Delivery'),
                FormOption('CAB', 'Cab / taxi'),
                FormOption('SERVICE_PROVIDER', 'Service provider'),
                FormOption('OTHER', 'Other'),
              ],
            }),
        'security/visitors': const FormConfig(
            'Gate visitor',
            'security/visitors',
            [
              'visitor_name',
              'flat_id',
              'resident_id',
              'visitor_type',
            ],
            optionsEndpoint: 'security/visitors/options',
            optionKeys: {'flat_id': 'flats', 'resident_id': 'residents'},
            staticOptions: {
              'visitor_type': [
                FormOption('GUEST', 'Guest'),
                FormOption('DELIVERY', 'Delivery'),
                FormOption('CAB', 'Cab / taxi'),
                FormOption('SERVICE_PROVIDER', 'Service provider'),
                FormOption('OTHER', 'Other'),
              ],
            }),
        'resident/amenity-bookings': const FormConfig(
            'Book amenity',
            'resident/amenity-bookings',
            ['amenity_id', 'booking_date', 'start_time', 'end_time', 'notes'],
            optionsEndpoint: 'amenities',
            optionKeys: {'amenity_id': 'data'}),
        'admin/staff-members':
            const FormConfig('Add staff member', 'admin/staff-members', [
          'name',
          'employee_id',
          'mobile',
          'email',
          'designation',
          'joining_date',
          'shift',
          'status',
          'emergency_contact'
        ], optionalFields: [
          'employee_id',
          'email',
          'shift',
          'emergency_contact'
        ], staticOptions: {
          'shift': [
            FormOption('MORNING', 'Morning'),
            FormOption('EVENING', 'Evening'),
            FormOption('NIGHT', 'Night'),
            FormOption('ROTATING', 'Rotating'),
          ],
          'status': [
            FormOption('ACTIVE', 'Active'),
            FormOption('INACTIVE', 'Inactive'),
            FormOption('ON_LEAVE', 'On leave'),
          ],
        }),
        'admin/parking-slots': const FormConfig(
            'Add parking slot',
            'admin/parking-slots',
            ['slot_number', 'parking_type', 'flat_id', 'vehicle_number'],
            optionalFields: ['flat_id', 'vehicle_number'],
            optionsEndpoint: 'admin/options',
            optionKeys: {'flat_id': 'flats'},
            staticOptions: {
              'parking_type': [
                FormOption('CAR', 'Car'),
                FormOption('BIKE', 'Bike'),
                FormOption('VISITOR', 'Visitor'),
                FormOption('OTHER', 'Other'),
              ],
            }),
        'admin/amenities': const FormConfig('Add amenity', 'admin/amenities', [
          'name',
          'description',
          'max_booking_hours',
          'opening_time',
          'closing_time',
          'is_active'
        ], optionalFields: [
          'description',
          'opening_time',
          'closing_time'
        ], staticOptions: {
          'is_active': [FormOption('1', 'Active'), FormOption('0', 'Inactive')],
        }),
        'admin/notices': const FormConfig('Create notice', 'admin/notices', [
          'title',
          'content',
          'category',
          'priority',
          'audience',
          'is_published',
          'published_at',
          'expires_at'
        ], optionalFields: [
          'published_at',
          'expires_at'
        ], staticOptions: {
          'category': [
            FormOption('GENERAL', 'General'),
            FormOption('MAINTENANCE', 'Maintenance'),
            FormOption('EMERGENCY', 'Emergency'),
            FormOption('EVENT', 'Event'),
            FormOption('SECURITY', 'Security'),
            FormOption('WATER', 'Water'),
            FormOption('ELECTRICITY', 'Electricity'),
          ],
          'priority': [
            FormOption('LOW', 'Low'),
            FormOption('NORMAL', 'Normal'),
            FormOption('HIGH', 'High'),
            FormOption('EMERGENCY', 'Emergency'),
          ],
          'audience': [
            FormOption('ALL', 'Society-wide'),
            FormOption('RESIDENTS', 'All residents'),
            FormOption('OWNERS', 'All owners'),
            FormOption('STAFF', 'All staff'),
          ],
          'is_published': [
            FormOption('1', 'Publish now'),
            FormOption('0', 'Save as draft'),
          ],
        }),
        'admin/flats': const FormConfig(
            'Flat',
            'admin/flats',
            [
              'flat_number',
              'building',
              'floor',
              'owner_user_id',
              'occupancy_status'
            ],
            optionsEndpoint: 'admin/options',
            optionKeys: {'owner_user_id': 'owners'},
            optionalFields: ['owner_user_id'],
            staticOptions: {
              'occupancy_status': [
                FormOption('VACANT', 'Vacant'),
                FormOption('OCCUPIED', 'Occupied'),
                FormOption('UNDER_MAINTENANCE', 'Under maintenance')
              ],
            }),
        'admin/maintenance-bills': const FormConfig(
            'Maintenance bill',
            'admin/maintenance-bills',
            [
              'flat_id',
              'billing_month',
              'billing_period_start',
              'billing_period_end',
              'due_date',
              'base_maintenance',
              'water_charge',
              'electricity_common_area_charge',
              'parking_charge',
              'other_charges',
              'late_fee',
              'discount',
              'notes'
            ],
            optionsEndpoint: 'admin/options',
            optionKeys: {'flat_id': 'flats'},
            optionalFields: [
              'billing_period_start',
              'billing_period_end',
              'water_charge',
              'electricity_common_area_charge',
              'parking_charge',
              'other_charges',
              'late_fee',
              'discount',
              'notes',
            ]),
        'admin/residents': const FormConfig(
            'Resident',
            'admin/residents',
            [
              'name',
              'email',
              'phone',
              'password',
              'flat_id',
              'relation_to_owner'
            ],
            optionsEndpoint: 'admin/options',
            optionKeys: {'flat_id': 'flats'},
            staticOptions: {
              'relation_to_owner': [
                FormOption('OWNER', 'Owner'),
                FormOption('TENANT', 'Tenant'),
                FormOption('FAMILY_MEMBER', 'Family member')
              ],
            }),
        'admin/complaints': const FormConfig('Update complaint',
            'admin/complaints', ['status', 'assigned_staff_id', 'note'],
            patchOnEdit: true,
            showCreate: false,
            optionalFields: ['assigned_staff_id', 'note'],
            optionsEndpoint: 'admin/options',
            optionKeys: {'assigned_staff_id': 'staff_members'},
            staticOptions: {
              'status': [
                FormOption('OPEN', 'Open'),
                FormOption('ASSIGNED', 'Assigned'),
                FormOption('IN_PROGRESS', 'In progress'),
                FormOption('RESOLVED', 'Resolved'),
                FormOption('CLOSED', 'Closed'),
                FormOption('REOPENED', 'Reopened'),
              ],
            }),
        'staff/complaints': const FormConfig(
            'Update complaint', 'staff/complaints', ['status', 'note'],
            patchOnEdit: true,
            showCreate: false,
            optionalFields: ['note'],
            staticOptions: {
              'status': [
                FormOption('OPEN', 'Open'),
                FormOption('ASSIGNED', 'Assigned'),
                FormOption('IN_PROGRESS', 'In progress'),
                FormOption('RESOLVED', 'Resolved'),
                FormOption('CLOSED', 'Closed'),
                FormOption('REOPENED', 'Reopened'),
              ],
            }),
        'admin/parcels': const FormConfig(
            'Receive parcel',
            'admin/parcels',
            [
              'flat_id',
              'resident_id',
              'courier_name',
              'tracking_number',
              'parcel_type',
              'notes'
            ],
            optionsEndpoint: 'admin/options',
            optionKeys: {'flat_id': 'flats', 'resident_id': 'residents'},
            optionalFields: ['tracking_number', 'notes'],
            staticOptions: {
              'parcel_type': [
                FormOption('DOCUMENT', 'Document'),
                FormOption('BOX', 'Box'),
                FormOption('FOOD', 'Food'),
                FormOption('OTHER', 'Other')
              ],
            }),
        'staff/parcels': const FormConfig(
            'Receive parcel',
            'staff/parcels',
            [
              'flat_id',
              'resident_id',
              'courier_name',
              'tracking_number',
              'parcel_type',
              'notes'
            ],
            optionsEndpoint: 'staff/options',
            optionKeys: {'flat_id': 'flats', 'resident_id': 'residents'},
            optionalFields: ['tracking_number', 'notes'],
            staticOptions: {
              'parcel_type': [
                FormOption('DOCUMENT', 'Document'),
                FormOption('BOX', 'Box'),
                FormOption('FOOD', 'Food'),
                FormOption('OTHER', 'Other')
              ],
            }),
        'security/parcels': const FormConfig(
            'Receive parcel',
            'security/parcels',
            [
              'flat_id',
              'resident_id',
              'courier_name',
              'tracking_number',
              'parcel_type',
              'notes'
            ],
            optionsEndpoint: 'security/visitors/options',
            optionKeys: {'flat_id': 'flats', 'resident_id': 'residents'},
            optionalFields: ['tracking_number', 'notes'],
            staticOptions: {
              'parcel_type': [
                FormOption('DOCUMENT', 'Document'),
                FormOption('BOX', 'Box'),
                FormOption('FOOD', 'Food'),
                FormOption('OTHER', 'Other')
              ],
            })
      }[route];
}

class FormOption {
  const FormOption(this.value, this.label);
  final String value;
  final String label;
}

class ModuleFormScreen extends StatefulWidget {
  const ModuleFormScreen(
      {super.key,
      required this.config,
      this.initial,
      this.submitPath,
      this.isEdit = false});
  final FormConfig config;
  final Map<String, dynamic>? initial;
  final String? submitPath;
  final bool isEdit;
  @override
  State<ModuleFormScreen> createState() => _ModuleFormScreenState();
}

class _ModuleFormScreenState extends State<ModuleFormScreen> {
  final _form = GlobalKey<FormState>();
  final Map<String, TextEditingController> _controllers = {};
  final Map<String, List<FormOption>> _options = {};
  String? _error;
  bool _saving = false;
  bool _loadingOptions = false;

  static const _dateFields = {
    'billing_month',
    'billing_period_start',
    'billing_period_end',
    'due_date',
    'joining_date',
    'booking_date',
    'date_of_birth',
  };

  bool _isDateField(String field) => _dateFields.contains(field);
  bool _isMonthField(String field) => field == 'billing_month';

  String _displayDate(String value, {bool monthOnly = false}) {
    final date = DateTime.tryParse(value);
    if (date == null) return monthOnly ? 'Select billing month' : 'Select date';
    return monthOnly
        ? '${_monthName(date.month)} ${date.year}'
        : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
  }

  Future<void> _pickDate(String field) async {
    final current = DateTime.tryParse(_controllers[field]!.text);
    final now = DateTime.now();
    final selected = await showDatePicker(
      context: context,
      initialDate: current ?? now,
      firstDate: field == 'date_of_birth' ? DateTime(1900) : DateTime(2000),
      lastDate: field == 'date_of_birth' ? now : DateTime(now.year + 10),
      initialDatePickerMode:
          _isMonthField(field) ? DatePickerMode.year : DatePickerMode.day,
      helpText: _isMonthField(field)
          ? 'Select billing month and year'
          : 'Select ${_fieldLabel(field).toLowerCase()}',
    );
    if (selected == null || !mounted) return;
    final value = _isMonthField(field)
        ? DateTime(selected.year, selected.month, 1)
        : selected;
    setState(() => _controllers[field]!.text =
        '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}');
  }

  @override
  void initState() {
    super.initState();
    for (final field in widget.config.fields) {
      final raw = widget.initial?[field];
      _controllers[field] = TextEditingController(
          text: field == 'is_active' && raw is bool
              ? (raw ? '1' : '0')
              : raw?.toString() ?? '');
    }
    _options.addAll(widget.config.staticOptions);
    _loadOptions();
  }

  Future<void> _loadOptions() async {
    final endpoint = widget.config.optionsEndpoint;
    if (endpoint == null) return;
    setState(() => _loadingOptions = true);
    try {
      final response = await context.read<AuthController>().api.get(endpoint);
      final data = response['data'];
      for (final entry in widget.config.optionKeys.entries) {
        dynamic raw;
        if (endpoint == 'amenities') {
          raw = data is Map ? data['data'] : data;
        } else if (data is Map) {
          raw = data[entry.value];
        }
        if (raw is List) {
          _options[entry.key] = raw
              .whereType<Map>()
              .map((item) => FormOption(
                  item['id'].toString(),
                  item['label']?.toString() ??
                      item['name']?.toString() ??
                      'Option'))
              .toList();
        }
      }
    } on ApiException catch (error) {
      _error = 'Could not load form selections: ${error.message}';
    } finally {
      if (mounted) setState(() => _loadingOptions = false);
    }
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    final data = <String, dynamic>{};
    for (final entry in _controllers.entries) {
      final value = entry.value.text.trim();
      if (value.isNotEmpty) {
        data[entry.key] =
            entry.key.endsWith('_id') || entry.key == 'max_booking_hours'
                ? int.tryParse(value) ?? value
                : value;
      }
    }
    try {
      final api = context.read<AuthController>().api;
      final path = widget.submitPath ?? widget.config.path;
      if (widget.isEdit) {
        if (widget.config.patchOnEdit) {
          await api.patch(path, data);
        } else {
          await api.put(path, data);
        }
      } else {
        await api.post(path, data);
      }
      if (mounted) {
        Navigator.pop(context, true);
      }
    } on ApiException catch (exception) {
      if (mounted) setState(() => _error = exception.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
      appBar: AppBar(title: Text(widget.config.title)),
      body: Form(
          key: _form,
          child: ListView(padding: const EdgeInsets.all(16), children: [
            if (_loadingOptions)
              const Padding(
                  padding: EdgeInsets.only(bottom: 16),
                  child: LinearProgressIndicator()),
            for (final field in widget.config.fields)
              Padding(
                  padding: const EdgeInsets.only(bottom: 14),
                  child: _isDateField(field)
                      ? _DateSelectionField(
                          label: _fieldLabel(field),
                          value: _displayDate(_controllers[field]!.text,
                              monthOnly: _isMonthField(field)),
                          required:
                              !widget.config.optionalFields.contains(field),
                          onTap: _saving ? null : () => _pickDate(field),
                        )
                      : !_options.containsKey(field) &&
                              !widget.config.optionKeys.containsKey(field)
                          ? TextFormField(
                              controller: _controllers[field]!,
                              keyboardType: field.endsWith('_id') ||
                                      field == 'max_booking_hours' ||
                                      const {
                                        'base_maintenance',
                                        'water_charge',
                                        'electricity_common_area_charge',
                                        'parking_charge',
                                        'other_charges',
                                        'late_fee',
                                        'discount',
                                      }.contains(field)
                                  ? TextInputType.number
                                  : TextInputType.text,
                              maxLines:
                                  field == 'description' || field == 'content'
                                      ? 4
                                      : 1,
                              decoration: InputDecoration(
                                  labelText: _fieldLabel(field),
                                  border: const OutlineInputBorder()),
                              validator: (value) => value == null ||
                                      (value.trim().isEmpty &&
                                          !widget.config.optionalFields
                                              .contains(field) &&
                                          field != 'notes' &&
                                          !(widget.isEdit &&
                                              field == 'password'))
                                  ? '${_fieldLabel(field)} is required'
                                  : null)
                          : _SelectionField(
                              label: _fieldLabel(field),
                              options: _options[field] ?? const [],
                              controller: _controllers[field]!,
                              required:
                                  !widget.config.optionalFields.contains(field),
                              enabled: !_saving && !_loadingOptions,
                            )),
            if (_error != null)
              Text(_error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error)),
            FilledButton(
                onPressed: _saving ? null : _save,
                child: _saving
                    ? const CircularProgressIndicator()
                    : const Text('Save'))
          ])));
}

class _DateSelectionField extends StatelessWidget {
  const _DateSelectionField({
    required this.label,
    required this.value,
    required this.required,
    required this.onTap,
  });

  final String label;
  final String value;
  final bool required;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => FormField<String>(
        key: ValueKey(value),
        initialValue: value.startsWith('Select') ? '' : value,
        validator: (fieldValue) => required && fieldValue!.isEmpty
            ? 'Please select ${label.toLowerCase()}.'
            : null,
        builder: (state) => OutlinedButton.icon(
          onPressed: onTap,
          icon: const Icon(Icons.calendar_month_outlined),
          label: Align(
            alignment: Alignment.centerLeft,
            child: Text('$label: $value'),
          ),
        ),
      );
}

String _monthName(int month) => const [
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December',
    ][month - 1];

class _SelectionField extends StatefulWidget {
  const _SelectionField(
      {required this.label,
      required this.options,
      required this.controller,
      required this.required,
      required this.enabled});
  final String label;
  final List<FormOption> options;
  final TextEditingController controller;
  final bool required;
  final bool enabled;

  @override
  State<_SelectionField> createState() => _SelectionFieldState();
}

class _SelectionFieldState extends State<_SelectionField> {
  @override
  Widget build(BuildContext context) {
    final current =
        widget.options.any((option) => option.value == widget.controller.text)
            ? widget.controller.text
            : null;
    return DropdownButtonFormField<String>(
      initialValue: current,
      isExpanded: true,
      decoration: InputDecoration(
          labelText: widget.label, border: const OutlineInputBorder()),
      items: widget.options
          .map((option) => DropdownMenuItem(
              value: option.value,
              child: Text(option.label, overflow: TextOverflow.ellipsis)))
          .toList(),
      onChanged: widget.enabled
          ? (value) => setState(() => widget.controller.text = value ?? '')
          : null,
      validator: (value) => widget.required && (value == null || value.isEmpty)
          ? 'Please select ${widget.label.toLowerCase()}.'
          : null,
    );
  }
}

String _fieldLabel(String field) =>
    {
      'owner_user_id': 'Owner',
      'flat_id': 'Flat',
      'resident_id': 'Resident',
      'amenity_id': 'Amenity',
      'occupancy_status': 'Occupancy status',
      'parking_type': 'Parking type',
      'visitor_type': 'Visitor type',
    }[field] ??
    field.replaceAll('_', ' ');
