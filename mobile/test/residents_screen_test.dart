import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:smart_society_app/main.dart';
import 'package:smart_society_app/services/api_service.dart';
import 'package:smart_society_app/state/auth_controller.dart';

void main() {
  testWidgets('Residents screen renders populated resident cards and handles empty state',
      (WidgetTester tester) async {
    final mockClient = MockClient((request) async {
      if (request.url.path.endsWith('admin/residents')) {
        return http.Response(
          jsonEncode({
            'data': [
              {
                'id': 1,
                'relation_to_owner': 'OWNER',
                'is_primary_contact': true,
                'user': {
                  'id': 4,
                  'name': 'Test Resident',
                  'email': 'resident@smartsociety.local',
                  'phone': '+91999999901',
                  'role': 'RESIDENT',
                },
                'flat': {
                  'id': 1,
                  'flat_number': '101',
                  'building': 'Tower A',
                  'floor': '1',
                  'occupancy_status': 'OCCUPIED',
                },
              },
              {
                'id': 2,
                'relation_to_owner': 'TENANT',
                'is_primary_contact': false,
                'user': {
                  'id': 5,
                  'name': 'Aarav Sharma',
                  'email': 'aarav.sharma@example.com',
                  'phone': '+919811122233',
                  'role': 'RESIDENT',
                },
                'flat': {
                  'id': 2,
                  'flat_number': '102',
                  'building': 'Tower B',
                  'floor': '1',
                  'occupancy_status': 'OCCUPIED',
                },
              },
            ]
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }
      return http.Response('{"data":[]}', 200,
          headers: {'content-type': 'application/json'});
    });

    final apiService = ApiService(client: mockClient);
    final authController = AuthController(apiService);

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: authController,
        child: const MaterialApp(
          home: ModuleScreen(
            title: 'Residents',
            role: 'ADMIN',
            showAppBar: true,
          ),
        ),
      ),
    );

    // Initial loading indicator
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    // Settle async HTTP response
    await tester.pumpAndSettle();

    // Verify first resident
    expect(find.text('Test Resident'), findsOneWidget);
    expect(find.text('Flat 101 • Tower A'), findsOneWidget);
    expect(find.text('Owner'), findsOneWidget);
    expect(find.text('Primary'), findsOneWidget);
    expect(find.text('resident@smartsociety.local · +91999999901'), findsOneWidget);

    // Verify second resident
    expect(find.text('Aarav Sharma'), findsOneWidget);
    expect(find.text('Flat 102 • Tower B'), findsOneWidget);
    expect(find.text('Tenant'), findsOneWidget);
    expect(find.text('aarav.sharma@example.com · +919811122233'), findsOneWidget);

    // Floating action button exists
    expect(find.byType(FloatingActionButton), findsOneWidget);
  });

  testWidgets('Residents screen displays "No residents found" when list is empty',
      (WidgetTester tester) async {
    final mockClient = MockClient((request) async {
      return http.Response(
        jsonEncode({'data': []}),
        200,
        headers: {'content-type': 'application/json'},
      );
    });

    final apiService = ApiService(client: mockClient);
    final authController = AuthController(apiService);

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: authController,
        child: const MaterialApp(
          home: ModuleScreen(
            title: 'Residents',
            role: 'ADMIN',
            showAppBar: true,
          ),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('No residents found'), findsOneWidget);
  });
}
