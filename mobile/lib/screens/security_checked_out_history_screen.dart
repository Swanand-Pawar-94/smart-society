import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_service.dart';
import '../state/auth_controller.dart';

class SecurityCheckedOutHistoryScreen extends StatefulWidget {
  const SecurityCheckedOutHistoryScreen({super.key});

  @override
  State<SecurityCheckedOutHistoryScreen> createState() =>
      _SecurityCheckedOutHistoryScreenState();
}

class _SecurityCheckedOutHistoryScreenState
    extends State<SecurityCheckedOutHistoryScreen> {
  List<dynamic> _visitors = [];
  bool _loading = true;
  String? _error;
  String _searchQuery = '';
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadHistory();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadHistory() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final api = context.read<AuthController>().api;
      final response = await api.get('security/visitors?entry_status=EXITED');
      final data = response['data'];
      final rawList = data is List
          ? data
          : data is Map && data['data'] is List
              ? data['data'] as List<dynamic>
              : [];

      // Defensive filtering for exited visitors
      final exited = rawList.where((item) {
        if (item is! Map) return false;
        final entryStatus = item['entry_status']?.toString().toUpperCase();
        final approvalStatus =
            item['approval_status']?.toString().toUpperCase();
        return entryStatus == 'EXITED' || approvalStatus == 'COMPLETED';
      }).toList();

      if (mounted) {
        setState(() {
          _visitors = exited;
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
          _error = 'Unable to load checked out visitors. Please try again.';
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
      final flatNum =
          (flat?['flat_number'] ?? item['flat_number'] ?? '').toString().toLowerCase();
      final building =
          (flat?['building'] ?? item['building'] ?? '').toString().toLowerCase();
      final vehicle = item['vehicle_number']?.toString().toLowerCase() ?? '';

      return name.contains(query) ||
          type.contains(query) ||
          flatNum.contains(query) ||
          building.contains(query) ||
          vehicle.contains(query);
    }).toList();
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

    return Scaffold(
      appBar: AppBar(
        title: const Text('Checked Out Visitors'),
      ),
      body: RefreshIndicator(
        onRefresh: _loadHistory,
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
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
                    'Checked Out History',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF1E293B),
                    ),
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      '${filtered.length} records',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF475569),
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
                                Icon(Icons.error_outline,
                                    size: 48, color: Colors.red.shade400),
                                const SizedBox(height: 12),
                                Text(
                                  _error!,
                                  textAlign: TextAlign.center,
                                  style: const TextStyle(
                                      fontSize: 14, color: Color(0xFF64748B)),
                                ),
                                const SizedBox(height: 16),
                                FilledButton(
                                  onPressed: _loadHistory,
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
                                        Icons.history,
                                        size: 56,
                                        color: Colors.grey.shade400,
                                      ),
                                      const SizedBox(height: 16),
                                      const Text(
                                        'No checked out visitors found.',
                                        textAlign: TextAlign.center,
                                        style: TextStyle(
                                          fontSize: 15,
                                          fontWeight: FontWeight.w600,
                                          color: Color(0xFF475569),
                                        ),
                                      ),
                                      const SizedBox(height: 8),
                                      const Text(
                                        'Visitors who have completed exit will appear in this history.',
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
                                        onPressed: _loadHistory,
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
                                final item = Map<String, dynamic>.from(
                                    filtered[index] as Map);
                                final name = item['visitor_name'] ?? 'Visitor';
                                final type = item['visitor_type']
                                        ?.toString()
                                        .replaceAll('_', ' ') ??
                                    'Guest';
                                final flat = item['flat'] is Map
                                    ? item['flat'] as Map
                                    : null;
                                final flatNumber =
                                    flat?['flat_number']?.toString() ??
                                        item['flat_number']?.toString() ??
                                        '-';
                                final vehicle = item['vehicle_number']?.toString();
                                final enteredAt = item['entered_at'];
                                final exitedAt = item['exited_at'];

                                return Card(
                                  margin:
                                      const EdgeInsets.symmetric(vertical: 6),
                                  elevation: 0,
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(16),
                                    side: const BorderSide(
                                        color: Color(0xFFE2E8F0)),
                                  ),
                                  child: Padding(
                                    padding: const EdgeInsets.all(14),
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            const CircleAvatar(
                                              radius: 20,
                                              backgroundColor:
                                                  Color(0xFFF1F5F9),
                                              child: Icon(
                                                Icons.logout,
                                                color: Color(0xFF64748B),
                                                size: 18,
                                              ),
                                            ),
                                            const SizedBox(width: 12),
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment:
                                                    CrossAxisAlignment.start,
                                                children: [
                                                  Text(
                                                    name.toString(),
                                                    style: const TextStyle(
                                                      fontSize: 15,
                                                      fontWeight:
                                                          FontWeight.w700,
                                                      color: Color(0xFF1E293B),
                                                    ),
                                                  ),
                                                  const SizedBox(height: 2),
                                                  Text(
                                                    '$type • Flat $flatNumber${vehicle != null && vehicle.isNotEmpty ? " • $vehicle" : ""}',
                                                    style: const TextStyle(
                                                      fontSize: 12,
                                                      color: Color(0xFF64748B),
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ),
                                            Container(
                                              padding:
                                                  const EdgeInsets.symmetric(
                                                      horizontal: 8,
                                                      vertical: 3),
                                              decoration: BoxDecoration(
                                                color: Colors.grey.shade100,
                                                borderRadius:
                                                    BorderRadius.circular(8),
                                                border: Border.all(
                                                    color:
                                                        Colors.grey.shade300),
                                              ),
                                              child: Text(
                                                'Checked Out',
                                                style: TextStyle(
                                                  fontSize: 10,
                                                  fontWeight: FontWeight.w700,
                                                  color: Colors.grey.shade700,
                                                ),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 10),
                                        Row(
                                          children: [
                                            if (enteredAt != null) ...[
                                              Icon(Icons.login,
                                                  size: 12,
                                                  color: Colors.grey.shade500),
                                              const SizedBox(width: 4),
                                              Text(
                                                'In: ${_formatDateTime(enteredAt)}',
                                                style: TextStyle(
                                                  fontSize: 11,
                                                  color: Colors.grey.shade600,
                                                ),
                                              ),
                                              const SizedBox(width: 12),
                                            ],
                                            if (exitedAt != null) ...[
                                              Icon(Icons.logout,
                                                  size: 12,
                                                  color: Colors.grey.shade500),
                                              const SizedBox(width: 4),
                                              Text(
                                                'Out: ${_formatDateTime(exitedAt)}',
                                                style: TextStyle(
                                                  fontSize: 11,
                                                  fontWeight: FontWeight.w600,
                                                  color: Colors.grey.shade700,
                                                ),
                                              ),
                                            ],
                                          ],
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
            ),
          ],
        ),
      ),
    );
  }
}
