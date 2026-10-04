import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:smart_society_app/main.dart';
import 'package:smart_society_app/models/session.dart';
import 'package:smart_society_app/screens/security_check_in_screen.dart';
import 'package:smart_society_app/screens/security_check_out_screen.dart';
import 'package:smart_society_app/screens/security_checked_out_history_screen.dart';
import 'package:smart_society_app/services/api_service.dart';
import 'package:smart_society_app/state/auth_controller.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  AuthController createSecurityAuth(http.Client client) {
    final api = ApiService(client: client);
    return AuthController(api)
      ..loading = false
      ..session = const Session(
        token: 'security-token',
        user: AppUser(
          id: 10,
          name: 'Security Guard',
          email: 'security@smartsociety.local',
          role: 'SECURITY',
        ),
      );
  }

  group('Security Dashboard Navigation and Metric Cards', () {
    testWidgets('Quick Actions and Metric Cards navigate to dedicated workflows and Gate Status is non-clickable',
        (tester) async {
      int dashboardCalls = 0;

      final mockClient = MockClient((request) async {
        final path = request.url.path;

        if (path.endsWith('security/dashboard')) {
          dashboardCalls++;
          return http.Response(
            jsonEncode({
              'data': {
                'expected_visitors': 5,
                'waiting_for_approval': 1,
                'checked_in_today': 2,
                'checked_out_today': 3,
                'parcels_awaiting_pickup': 4,
              }
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        if (path.endsWith('security/visitors')) {
          return http.Response(
            jsonEncode({'data': []}),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        return http.Response('{"data":[]}', 200,
            headers: {'content-type': 'application/json'});
      });

      final auth = createSecurityAuth(mockClient);

      await tester.pumpWidget(
        ChangeNotifierProvider<AuthController>.value(
          value: auth,
          child: const MaterialApp(
            home: SecurityShell(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Verify dashboard metrics are displayed
      expect(find.text('Checked In'), findsWidgets);
      expect(find.text('Checked Out'), findsWidgets);
      expect(find.text('Gate Status'), findsOneWidget);
      expect(find.text('Secure'), findsOneWidget);

      // Verify Gate Status is non-clickable: tapping it shows no SnackBar
      await tester.scrollUntilVisible(find.text('Gate Status'), 100);
      await tester.tap(find.text('Gate Status'), warnIfMissed: false);
      await tester.pumpAndSettle();
      expect(find.byType(SnackBar), findsNothing);

      // 1. Quick Action: "Check in visitor" opens SecurityCheckInScreen
      await tester.scrollUntilVisible(find.text('Check in visitor'), 100);
      expect(find.text('Check in visitor'), findsOneWidget);
      await tester.tap(find.text('Check in visitor'));
      await tester.pumpAndSettle();
      expect(find.byType(SecurityCheckInScreen), findsOneWidget);
      expect(find.text('Eligible Visitors'), findsOneWidget);

      // Return to Dashboard
      await tester.tap(find.byIcon(Icons.arrow_back));
      await tester.pumpAndSettle();
      expect(find.byType(SecurityDashboardScreen), findsOneWidget);

      // 2. Quick Action: "Check out visitor" opens SecurityCheckOutScreen
      expect(find.text('Check out visitor'), findsOneWidget);
      await tester.tap(find.text('Check out visitor'));
      await tester.pumpAndSettle();
      expect(find.byType(SecurityCheckOutScreen), findsOneWidget);
      expect(find.text('Currently Inside'), findsWidgets);

      // Return to Dashboard
      await tester.tap(find.byIcon(Icons.arrow_back));
      await tester.pumpAndSettle();
      expect(find.byType(SecurityDashboardScreen), findsOneWidget);

      // 3. Metric Card: "Checked In" opens SecurityCheckOutScreen (Currently Inside)
      await tester.tap(find.text('Checked In').first);
      await tester.pumpAndSettle();
      expect(find.byType(SecurityCheckOutScreen), findsOneWidget);

      // Return to Dashboard
      await tester.tap(find.byIcon(Icons.arrow_back));
      await tester.pumpAndSettle();
      expect(find.byType(SecurityDashboardScreen), findsOneWidget);

      // 4. Metric Card: "Checked Out" opens SecurityCheckedOutHistoryScreen
      await tester.tap(find.text('Checked Out').first);
      await tester.pumpAndSettle();
      expect(find.byType(SecurityCheckedOutHistoryScreen), findsOneWidget);
      expect(find.text('Checked Out History'), findsOneWidget);

      // Return to Dashboard
      await tester.tap(find.byTooltip('Back'));
      await tester.pumpAndSettle();
      expect(find.byType(SecurityDashboardScreen), findsOneWidget);

      // 5. Verify generic Visitors tab in bottom navigation bar remains intact
      await tester.tap(find.byIcon(Icons.badge_outlined));
      await tester.pumpAndSettle();
      // Tab 1 is the generic ModuleScreen for Visitors
      expect(find.text('Visitors'), findsWidgets);
      expect(dashboardCalls, greaterThanOrEqualTo(1));
    });
  });

  group('SecurityCheckInScreen Workflow', () {
    testWidgets('shows eligible visitors only, searches, confirms check-in, and calls entry API',
        (tester) async {
      bool entryApiCalled = false;

      final mockClient = MockClient((request) async {
        final path = request.url.path;

        if (request.method == 'GET' && path.endsWith('security/visitors')) {
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': 101,
                  'visitor_name': 'Ramesh Kumar',
                  'visitor_type': 'GUEST',
                  'approval_status': 'APPROVED',
                  'entry_status': 'EXPECTED',
                  'entered_at': null,
                  'purpose': 'Family visit',
                  'mobile_number': '9876543210',
                  'vehicle_number': 'MH12AB1234',
                  'flat': {'id': 1, 'flat_number': '101', 'building': 'Tower A'},
                },
                {
                  'id': 102,
                  'visitor_name': 'Krish Delivery',
                  'visitor_type': 'DELIVERY',
                  'approval_status': 'APPROVED',
                  'entry_status': 'WAITING',
                  'entered_at': null,
                  'purpose': 'Amazon delivery',
                  'mobile_number': '9876543211',
                  'flat': {'id': 2, 'flat_number': '402', 'building': 'Tower B'},
                },
                {
                  'id': 103,
                  'visitor_name': 'Already Entered',
                  'visitor_type': 'GUEST',
                  'approval_status': 'APPROVED',
                  'entry_status': 'ENTERED',
                  'entered_at': '2026-10-03T10:00:00Z',
                  'flat': {'id': 1, 'flat_number': '101'},
                },
              ]
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        if (request.method == 'PATCH' && path.endsWith('security/visitors/101/entry')) {
          entryApiCalled = true;
          return http.Response(
            jsonEncode({
              'data': {
                'id': 101,
                'visitor_name': 'Ramesh Kumar',
                'entry_status': 'ENTERED',
                'entered_at': '2026-10-03T18:00:00Z',
              }
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        return http.Response('{"data":[]}', 200,
            headers: {'content-type': 'application/json'});
      });

      final auth = createSecurityAuth(mockClient);

      await tester.pumpWidget(
        ChangeNotifierProvider<AuthController>.value(
          value: auth,
          child: const MaterialApp(
            home: SecurityCheckInScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Verify only eligible visitors appear (Already Entered must NOT appear)
      expect(find.text('Ramesh Kumar'), findsOneWidget);
      expect(find.text('Krish Delivery'), findsOneWidget);
      expect(find.text('Already Entered'), findsNothing);
      expect(find.text('2 eligible'), findsOneWidget);

      // Test Search
      await tester.enterText(find.byType(TextField), 'Krish');
      await tester.pumpAndSettle();
      expect(find.text('Ramesh Kumar'), findsNothing);
      expect(find.text('Krish Delivery'), findsOneWidget);

      // Clear search
      await tester.tap(find.byIcon(Icons.clear));
      await tester.pumpAndSettle();
      expect(find.text('Ramesh Kumar'), findsOneWidget);

      // Tap Check In button on Ramesh Kumar
      await tester.tap(find.widgetWithText(FilledButton, 'Check In').first);
      await tester.pumpAndSettle();

      // Verify confirmation bottom sheet details
      expect(find.text('Check In Confirmation'), findsOneWidget);
      expect(find.text('Verify visitor details before granting entry'), findsOneWidget);
      expect(find.text('Family visit'), findsOneWidget);
      expect(find.text('MH12AB1234'), findsOneWidget);

      // Confirm check-in
      await tester.tap(find.widgetWithText(FilledButton, 'Check In').last);
      await tester.pumpAndSettle();

      // Verify API was called
      expect(entryApiCalled, isTrue);

      // Verify success feedback
      expect(find.textContaining('Ramesh Kumar has been successfully checked in'), findsOneWidget);

      // Verify Ramesh is removed from eligible list
      expect(find.text('Ramesh Kumar'), findsNothing);
      expect(find.text('Krish Delivery'), findsOneWidget);
    });

    testWidgets('shows empty state when no visitors are eligible', (tester) async {
      final mockClient = MockClient((request) async {
        return http.Response(
          jsonEncode({'data': []}),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final auth = createSecurityAuth(mockClient);

      await tester.pumpWidget(
        ChangeNotifierProvider<AuthController>.value(
          value: auth,
          child: const MaterialApp(
            home: SecurityCheckInScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('No visitors are currently eligible for check-in.'), findsOneWidget);
      expect(find.byIcon(Icons.how_to_reg_outlined), findsOneWidget);
      expect(find.widgetWithText(OutlinedButton, 'Refresh'), findsOneWidget);
    });
  });

  group('SecurityCheckOutScreen Workflow', () {
    testWidgets('shows currently inside visitors only, displays check-in time, confirms check-out, and calls exit API',
        (tester) async {
      bool exitApiCalled = false;

      final mockClient = MockClient((request) async {
        final path = request.url.path;

        if (request.method == 'GET' && path.endsWith('security/visitors')) {
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': 201,
                  'visitor_name': 'Inside Visitor Ramesh',
                  'visitor_type': 'GUEST',
                  'entry_status': 'ENTERED',
                  'approval_status': 'APPROVED',
                  'entered_at': '2026-10-03T17:42:00Z',
                  'exited_at': null,
                  'purpose': 'Consultation',
                  'mobile_number': '9123456789',
                  'flat': {'id': 1, 'flat_number': '101'},
                },
                {
                  'id': 202,
                  'visitor_name': 'Exited Visitor Krish',
                  'visitor_type': 'DELIVERY',
                  'entry_status': 'EXITED',
                  'approval_status': 'COMPLETED',
                  'entered_at': '2026-10-03T16:00:00Z',
                  'exited_at': '2026-10-03T17:00:00Z',
                  'flat': {'id': 2, 'flat_number': '402'},
                },
              ]
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        if (request.method == 'PATCH' && path.endsWith('security/visitors/201/exit')) {
          exitApiCalled = true;
          return http.Response(
            jsonEncode({
              'data': {
                'id': 201,
                'visitor_name': 'Inside Visitor Ramesh',
                'entry_status': 'EXITED',
                'approval_status': 'COMPLETED',
                'exited_at': '2026-10-03T18:30:00Z',
              }
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }

        return http.Response('{"data":[]}', 200,
            headers: {'content-type': 'application/json'});
      });

      final auth = createSecurityAuth(mockClient);

      await tester.pumpWidget(
        ChangeNotifierProvider<AuthController>.value(
          value: auth,
          child: const MaterialApp(
            home: SecurityCheckOutScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Verify currently inside visitor is displayed
      expect(find.text('Inside Visitor Ramesh'), findsOneWidget);
      expect(find.text('Exited Visitor Krish'), findsNothing);
      expect(find.text('1 inside'), findsOneWidget);

      // Verify checked in time is displayed
      expect(find.textContaining('Checked in:'), findsOneWidget);

      // Tap Check Out button
      await tester.tap(find.widgetWithText(FilledButton, 'Check Out').first);
      await tester.pumpAndSettle();

      // Verify confirmation sheet
      expect(find.text('Check Out Confirmation'), findsOneWidget);
      expect(find.text('Record visitor exit from society'), findsOneWidget);
      expect(find.text('Consultation'), findsOneWidget);

      // Confirm checkout
      await tester.tap(find.widgetWithText(FilledButton, 'Check Out').last);
      await tester.pumpAndSettle();

      // Verify API was called
      expect(exitApiCalled, isTrue);

      // Verify success feedback
      expect(find.textContaining('Inside Visitor Ramesh has been successfully checked out'), findsOneWidget);

      // Verify removed from currently inside list
      expect(find.text('Inside Visitor Ramesh'), findsNothing);
    });

    testWidgets('shows empty state when no visitors are currently checked in', (tester) async {
      final mockClient = MockClient((request) async {
        return http.Response(
          jsonEncode({'data': []}),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

      final auth = createSecurityAuth(mockClient);

      await tester.pumpWidget(
        ChangeNotifierProvider<AuthController>.value(
          value: auth,
          child: const MaterialApp(
            home: SecurityCheckOutScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('No visitors are currently checked in.'), findsOneWidget);
      expect(find.byIcon(Icons.door_sliding_outlined), findsOneWidget);
      expect(find.widgetWithText(OutlinedButton, 'Refresh'), findsOneWidget);
    });
  });
}
