import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_service.dart';
import '../state/auth_controller.dart';

class SecurityCheckInScreen extends StatefulWidget {
  const SecurityCheckInScreen({super.key});

  @override
  State<SecurityCheckInScreen> createState() => _SecurityCheckInScreenState();
}

class _SecurityCheckInScreenState extends State<SecurityCheckInScreen> {
  List<dynamic> _visitors = [];
  bool _loading = true;
  String? _error;
  String _searchQuery = '';
  final TextEditingController _searchController = TextEditingController();
  bool _hasChanged = false;
  int? _actioningId;

  @override
  void initState() {
    super.initState();
    _loadEligibleVisitors();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadEligibleVisitors() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final api = context.read<AuthController>().api;
      final response = await api.get('security/visitors?eligible_for_check_in=true');
      final data = response['data'];
      final rawList = data is List
          ? data
          : data is Map && data['data'] is List
              ? data['data'] as List<dynamic>
              : [];

      // Defensive filtering for check-in eligibility
      final eligible = rawList.where((item) {
        if (item is! Map) return false;
        final approvalStatus = item['approval_status']?.toString().toUpperCase();
        final entryStatus = item['entry_status']?.toString().toUpperCase();
        final enteredAt = item['entered_at'];

        return approvalStatus == 'APPROVED' &&
            enteredAt == null &&
            entryStatus != 'ENTERED' &&
            entryStatus != 'EXITED';
      }).toList();

      if (mounted) {
        setState(() {
          _visitors = eligible;
          _loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _error = e.message;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'Unable to load eligible visitors. Please try again.';
          _loading = false;
        });
      }
    }
  }

  List<dynamic> get _filteredVisitors {
    if (_searchQuery.trim().isEmpty) return _visitors;
    final query = _searchQuery.trim().toLowerCase();

    return _visitors.where((item) {
      if (item is! Map) return false;
      final name = item['visitor_name']?.toString().toLowerCase() ?? '';
      final type = item['visitor_type']?.toString().toLowerCase() ?? '';
      final flat = item['flat'] is Map ? item['flat'] as Map : null;
      final flatNum = (flat?['flat_number'] ?? item['flat_number'] ?? '').toString().toLowerCase();
      final building = (flat?['building'] ?? item['building'] ?? '').toString().toLowerCase();
      final vehicle = item['vehicle_number']?.toString().toLowerCase() ?? '';

      return name.contains(query) ||
          type.contains(query) ||
          flatNum.contains(query) ||
          building.contains(query) ||
          vehicle.contains(query);
    }).toList();
  }

  Future<void> _confirmAndCheckIn(Map<String, dynamic> visitor) async {
    final visitorId = visitor['id'];
    if (visitorId == null) return;

    final name = visitor['visitor_name'] ?? 'Visitor';
    final type = visitor['visitor_type']?.toString().replaceAll('_', ' ') ?? 'Guest';
    final flat = visitor['flat'] is Map ? visitor['flat'] as Map : null;
    final flatNumber = flat?['flat_number']?.toString() ?? visitor['flat_number']?.toString() ?? '-';
    final building = flat?['building']?.toString() ?? visitor['building']?.toString();
    final phone = visitor['mobile_number']?.toString() ?? '-';
    final purpose = visitor['purpose']?.toString() ?? '-';
    final vehicle = visitor['vehicle_number']?.toString();
    final expectedAt = visitor['expected_at'];

    final confirmed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        padding: EdgeInsets.fromLTRB(
          20,
          16,
          20,
          MediaQuery.of(ctx).viewInsets.bottom + 24,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCFCE7),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.login, color: Color(0xFF059669), size: 24),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Check In Confirmation',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF1E293B),
                        ),
                      ),
                      Text(
                        'Verify visitor details before granting entry',
                        style: TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),
            _infoRow('Visitor Name', '$name ($type)'),
            _infoRow('Destination', 'Flat $flatNumber${building != null ? " ($building)" : ""}'),
            _infoRow('Phone', phone),
            _infoRow('Purpose', purpose),
            if (vehicle != null && vehicle.isNotEmpty) _infoRow('Vehicle', vehicle),
            if (expectedAt != null) _infoRow('Expected Time', _formatDateTime(expectedAt)),
            const SizedBox(height: 24),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: () => Navigator.pop(ctx, false),
                    child: const Text('Cancel'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    style: FilledButton.styleFrom(
                      backgroundColor: const Color(0xFF059669),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    icon: const Icon(Icons.check_circle_outline, size: 18),
                    label: const Text('Check In'),
                    onPressed: () => Navigator.pop(ctx, true),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _actioningId = visitorId as int);
    final api = context.read<AuthController>().api;

    try {
      await api.patch('security/visitors/$visitorId/entry', {});

      if (!mounted) return;
      setState(() {
        _hasChanged = true;
        _visitors.removeWhere((v) => v is Map && v['id'] == visitorId);
        _actioningId = null;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Visitor Checked In\n$name has been successfully checked in.'),
          backgroundColor: const Color(0xFF059669),
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 4),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _actioningId = null);
      _loadEligibleVisitors();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: Colors.red.shade700,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _actioningId = null);
      _loadEligibleVisitors();

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Failed to check in visitor. Please try again.'),
          backgroundColor: Colors.red,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                color: Color(0xFF64748B),
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 13,
                color: Color(0xFF1E293B),
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _formatDateTime(dynamic value) {
    final parsed = DateTime.tryParse(value?.toString() ?? '');
    if (parsed == null) return value?.toString() ?? '-';
    final local = parsed.toLocal();
    final hour = local.hour == 0 ? 12 : (local.hour > 12 ? local.hour - 12 : local.hour);
    final minute = local.minute.toString().padLeft(2, '0');
    final ampm = local.hour >= 12 ? 'PM' : 'AM';
    return '${local.day.toString().padLeft(2, '0')}/${local.month.toString().padLeft(2, '0')}/${local.year} $hour:$minute $ampm';
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _filteredVisitors;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) Navigator.pop(context, _hasChanged);
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Check In Visitor'),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back),
            onPressed: () => Navigator.pop(context, _hasChanged),
          ),
        ),
        body: RefreshIndicator(
          onRefresh: _loadEligibleVisitors,
          child: Column(
            children: [
              // Search Bar
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: TextField(
                  controller: _searchController,
                  onChanged: (val) => setState(() => _searchQuery = val),
                  decoration: InputDecoration(
                    hintText: 'Search visitor...',
                    prefixIcon: const Icon(Icons.search, size: 20),
                    suffixIcon: _searchQuery.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, size: 18),
                            onPressed: () {
                              _searchController.clear();
                              setState(() => _searchQuery = '');
                            },
                          )
                        : null,
                    filled: true,
                    fillColor: Colors.grey.shade100,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide.none,
                    ),
                  ),
                ),
              ),

              // Header Bar
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Eligible Visitors',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF1E293B),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFDCFCE7),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        '${filtered.length} eligible',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF059669),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Body content
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator())
                    : _error != null
                        ? Center(
                            child: Padding(
                              padding: const EdgeInsets.all(24),
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.error_outline, size: 48, color: Colors.red.shade400),
                                  const SizedBox(height: 12),
                                  Text(
                                    _error!,
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(fontSize: 14, color: Color(0xFF64748B)),
                                  ),
                                  const SizedBox(height: 16),
                                  FilledButton(
                                    onPressed: _loadEligibleVisitors,
                                    child: const Text('Retry'),
                                  ),
                                ],
                              ),
                            ),
                          )
                        : filtered.isEmpty
                            ? ListView(
                                children: [
                                  const SizedBox(height: 100),
                                  Center(
                                    child: Column(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(
                                          Icons.how_to_reg_outlined,
                                          size: 56,
                                          color: Colors.grey.shade400,
                                        ),
                                        const SizedBox(height: 16),
                                        const Text(
                                          'No visitors are currently eligible for check-in.',
                                          textAlign: TextAlign.center,
                                          style: TextStyle(
                                            fontSize: 15,
                                            fontWeight: FontWeight.w600,
                                            color: Color(0xFF475569),
                                          ),
                                        ),
                                        const SizedBox(height: 8),
                                        const Text(
                                          'Only approved visitors awaiting entry appear here.',
                                          textAlign: TextAlign.center,
                                          style: TextStyle(
                                            fontSize: 12,
                                            color: Color(0xFF94A3B8),
                                          ),
                                        ),
                                        const SizedBox(height: 20),
                                        OutlinedButton.icon(
                                          icon: const Icon(Icons.refresh, size: 16),
                                          label: const Text('Refresh'),
                                          onPressed: _loadEligibleVisitors,
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              )
                            : ListView.builder(
                                padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                                itemCount: filtered.length,
                                itemBuilder: (context, index) {
                                  final item = Map<String, dynamic>.from(filtered[index] as Map);
                                  final name = item['visitor_name'] ?? 'Visitor';
                                  final type = item['visitor_type']?.toString().replaceAll('_', ' ') ?? 'Guest';
                                  final flat = item['flat'] is Map ? item['flat'] as Map : null;
                                  final flatNumber = flat?['flat_number']?.toString() ?? item['flat_number']?.toString() ?? '-';
                                  final id = item['id'];
                                  final isActioning = _actioningId == id;

                                  return Card(
                                    margin: const EdgeInsets.symmetric(vertical: 6),
                                    elevation: 0,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(16),
                                      side: const BorderSide(color: Color(0xFFE2E8F0)),
                                    ),
                                    child: InkWell(
                                      borderRadius: BorderRadius.circular(16),
                                      onTap: isActioning ? null : () => _confirmAndCheckIn(item),
                                      child: Padding(
                                        padding: const EdgeInsets.all(14),
                                        child: Row(
                                          children: [
                                            const CircleAvatar(
                                              radius: 22,
                                              backgroundColor: Color(0xFFDCFCE7),
                                              child: Icon(
                                                Icons.login,
                                                color: Color(0xFF059669),
                                                size: 20,
                                              ),
                                            ),
                                            const SizedBox(width: 14),
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  Text(
                                                    name.toString(),
                                                    style: const TextStyle(
                                                      fontSize: 15,
                                                      fontWeight: FontWeight.w700,
                                                      color: Color(0xFF1E293B),
                                                    ),
                                                  ),
                                                  const SizedBox(height: 3),
                                                  Text(
                                                    '$type • Flat $flatNumber',
                                                    style: const TextStyle(
                                                      fontSize: 12,
                                                      color: Color(0xFF64748B),
                                                    ),
                                                  ),
                                                  const SizedBox(height: 6),
                                                  Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                                    decoration: BoxDecoration(
                                                      color: Colors.green.shade50,
                                                      borderRadius: BorderRadius.circular(8),
                                                      border: Border.all(color: Colors.green.shade200),
                                                    ),
                                                    child: Text(
                                                      'Approved',
                                                      style: TextStyle(
                                                        fontSize: 10,
                                                        fontWeight: FontWeight.w700,
                                                        color: Colors.green.shade800,
                                                      ),
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ),
                                            const SizedBox(width: 8),
                                            FilledButton(
                                              style: FilledButton.styleFrom(
                                                backgroundColor: const Color(0xFF059669),
                                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                                                shape: RoundedRectangleBorder(
                                                  borderRadius: BorderRadius.circular(10),
                                                ),
                                              ),
                                              onPressed: isActioning ? null : () => _confirmAndCheckIn(item),
                                              child: isActioning
                                                  ? const SizedBox(
                                                      width: 16,
                                                      height: 16,
                                                      child: CircularProgressIndicator(
                                                        strokeWidth: 2,
                                                        color: Colors.white,
                                                      ),
                                                    )
                                                  : const Text(
                                                      'Check In',
                                                      style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                                                    ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  );
                                },
                              ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
