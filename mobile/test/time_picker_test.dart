import 'dart:convert';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:smart_society_app/core/time_picker_helper.dart';
import 'package:smart_society_app/main.dart';
import 'package:smart_society_app/models/session.dart';
import 'package:smart_society_app/services/api_service.dart';
import 'package:smart_society_app/state/auth_controller.dart';

http.Response _json(Map<String, dynamic> body, {int statusCode = 200}) =>
    http.Response(
      jsonEncode(body),
      statusCode,
      headers: {'content-type': 'application/json; charset=utf-8'},
    );

void main() {
  group('Time Picker Utility Tests', () {
    test('formatTimeOfDay24 formats to HH:mm 24-hour string', () {
      expect(
          formatTimeOfDay24(const TimeOfDay(hour: 9, minute: 5)), equals('09:05'));
      expect(
          formatTimeOfDay24(const TimeOfDay(hour: 14, minute: 30)), equals('14:30'));
      expect(
          formatTimeOfDay24(const TimeOfDay(hour: 0, minute: 0)), equals('00:00'));
      expect(
          formatTimeOfDay24(const TimeOfDay(hour: 23, minute: 59)), equals('23:59'));
    });

    test('formatTimeOfDay12 formats to hh:mm a 12-hour string', () {
      expect(
          formatTimeOfDay12(const TimeOfDay(hour: 9, minute: 5)), equals('09:05 AM'));
      expect(
          formatTimeOfDay12(const TimeOfDay(hour: 14, minute: 30)), equals('02:30 PM'));
      expect(
          formatTimeOfDay12(const TimeOfDay(hour: 0, minute: 0)), equals('12:00 AM'));
      expect(
          formatTimeOfDay12(const TimeOfDay(hour: 12, minute: 15)), equals('12:15 PM'));
    });

    test('parseTimeOfDay parses various time formats correctly', () {
      expect(parseTimeOfDay(null), isNull);
      expect(parseTimeOfDay(''), isNull);
      expect(parseTimeOfDay('  '), isNull);

      // 24-hour formats
      expect(parseTimeOfDay('09:30'), equals(const TimeOfDay(hour: 9, minute: 30)));
      expect(parseTimeOfDay('14:45'), equals(const TimeOfDay(hour: 14, minute: 45)));
      expect(parseTimeOfDay('00:00'), equals(const TimeOfDay(hour: 0, minute: 0)));
      expect(parseTimeOfDay('23:59:00'), equals(const TimeOfDay(hour: 23, minute: 59)));

      // 12-hour formats
      expect(parseTimeOfDay('09:30 AM'), equals(const TimeOfDay(hour: 9, minute: 30)));
      expect(parseTimeOfDay('02:45 PM'), equals(const TimeOfDay(hour: 14, minute: 45)));
      expect(parseTimeOfDay('12:00 AM'), equals(const TimeOfDay(hour: 0, minute: 0)));
      expect(parseTimeOfDay('12:00 PM'), equals(const TimeOfDay(hour: 12, minute: 0)));
    });

    test('formatTimeString12 converts string time to 12-hour display', () {
      expect(formatTimeString12('14:30'), equals('02:30 PM'));
      expect(formatTimeString12('09:05:00'), equals('09:05 AM'));
      expect(formatTimeString12('', placeholder: 'Select time'), equals('Select time'));
    });
  });

  group('Scroll-wheel Time Picker Widget Tests', () {
    testWidgets('showScrollWheelTimePicker displays CupertinoDatePicker with Cancel & Done',
        (WidgetTester tester) async {
      TimeOfDay? selectedResult;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () async {
                  selectedResult = await showScrollWheelTimePicker(
                    context,
                    initialTime: const TimeOfDay(hour: 10, minute: 30),
                    title: 'Select Booking Time',
                  );
                },
                child: const Text('Open Time Picker'),
              ),
            ),
          ),
        ),
      );

      // Tap to open bottom sheet
      await tester.tap(find.text('Open Time Picker'));
      await tester.pumpAndSettle();

      // Verify bottom sheet UI
      expect(find.text('Select Booking Time'), findsOneWidget);
      expect(find.text('Cancel'), findsOneWidget);
      expect(find.text('Done'), findsOneWidget);
      expect(find.byType(CupertinoDatePicker), findsOneWidget);

      // Tap Done
      await tester.tap(find.text('Done'));
      await tester.pumpAndSettle();

      // Verify returned time
      expect(selectedResult, equals(const TimeOfDay(hour: 10, minute: 30)));
    });

    testWidgets('showScrollWheelTimePicker Cancel returns null',
        (WidgetTester tester) async {
      TimeOfDay? selectedResult = const TimeOfDay(hour: 1, minute: 0);

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () async {
                  selectedResult = await showScrollWheelTimePicker(
                    context,
                    initialTime: const TimeOfDay(hour: 14, minute: 0),
                  );
                },
                child: const Text('Open Time Picker'),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Open Time Picker'));
      await tester.pumpAndSettle();

      // Tap Cancel
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();

      expect(selectedResult, isNull);
    });

    testWidgets('ScrollWheelTimePickerField renders read-only button and shows validation',
        (WidgetTester tester) async {
      final formKey = GlobalKey<FormState>();
      String timeValue = '';

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Form(
              key: formKey,
              child: StatefulBuilder(
                builder: (context, setState) => ScrollWheelTimePickerField(
                  label: 'Start time',
                  value: timeValue,
                  required: true,
                  onTap: () {
                    setState(() {
                      timeValue = '14:30';
                    });
                  },
                ),
              ),
            ),
          ),
        ),
      );

      // Verify label and initial display
      expect(find.text('Start time: Select start time'), findsOneWidget);

      // Trigger validation
      formKey.currentState!.validate();
      await tester.pump();

      // Verify required validation error message appears
      expect(find.text('Please select start time.'), findsOneWidget);

      // Tap the field to simulate selection
      await tester.tap(find.byType(OutlinedButton));
      await tester.pump();

      // Verify formatted time is displayed
      expect(find.text('Start time: 02:30 PM'), findsOneWidget);
    });

    testWidgets('ModuleFormScreen validates start and end times for amenity booking',
        (WidgetTester tester) async {
      final api = ApiService(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/amenities')) {
            return _json({
              'data': [
                {'id': 1, 'name': 'Clubhouse'}
              ]
            });
          }
          return _json({'message': 'Not found'}, statusCode: 404);
        }),
      );

      final auth = AuthController(api)
        ..loading = false
        ..session = const Session(
          token: 'test-token',
          user: AppUser(
            id: 1,
            name: 'Resident John',
            email: 'john@example.com',
            role: 'RESIDENT',
          ),
        );

      final config = FormConfig.forRoute('resident/amenity-bookings')!;

      await tester.pumpWidget(
        ChangeNotifierProvider<AuthController>.value(
          value: auth,
          child: MaterialApp(
            home: ModuleFormScreen(
              config: config,
              initial: const {
                'amenity_id': '1',
                'booking_date': '2026-08-20',
                'start_time': '14:00',
                'end_time': '10:00',
              },
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Verify Start time and End time buttons exist with formatted display
      expect(find.text('Start time: 02:00 PM'), findsOneWidget);
      expect(find.text('End time: 10:00 AM'), findsOneWidget);

      // Tap Save to trigger validation
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      // End time (10:00) is before Start time (14:00), so validation error should trigger!
      expect(find.text('End time must be after start time.'), findsOneWidget);
    });

    testWidgets('ModuleFormScreen validates opening and closing times for admin amenities',
        (WidgetTester tester) async {
      final api = ApiService(
        client: MockClient((request) async {
          return _json({'data': {}});
        }),
      );

      final auth = AuthController(api)
        ..loading = false
        ..session = const Session(
          token: 'admin-token',
          user: AppUser(
            id: 1,
            name: 'Admin User',
            email: 'admin@example.com',
            role: 'ADMIN',
          ),
        );

      final config = FormConfig.forRoute('admin/amenities')!;

      await tester.pumpWidget(
        ChangeNotifierProvider<AuthController>.value(
          value: auth,
          child: MaterialApp(
            home: ModuleFormScreen(
              config: config,
              initial: const {
                'name': 'Swimming Pool',
                'max_booking_hours': '2',
                'opening_time': '18:00',
                'closing_time': '09:00',
              },
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Verify Opening and Closing time buttons exist
      expect(find.text('Opening time: 06:00 PM'), findsOneWidget);
      expect(find.text('Closing time: 09:00 AM'), findsOneWidget);

      // Tap Save to trigger validation
      await tester.tap(find.text('Save'));
      await tester.pumpAndSettle();

      // Closing time is earlier than opening time
      expect(find.text('Closing time must be after opening time.'), findsOneWidget);
    });

    testWidgets('VisitorPreApprovalScreen opens scroll-wheel time picker',
        (WidgetTester tester) async {
      final api = ApiService(
        client: MockClient((request) async {
          return _json({'data': {}});
        }),
      );

      final auth = AuthController(api)
        ..loading = false
        ..session = const Session(
          token: 'token',
          user: AppUser(
            id: 1,
            name: 'Resident John',
            email: 'john@example.com',
            role: 'RESIDENT',
          ),
        );

      await tester.pumpWidget(
        ChangeNotifierProvider<AuthController>.value(
          value: auth,
          child: const MaterialApp(
            home: VisitorPreApprovalScreen(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      // Verify Expected date/time button exists
      expect(find.byIcon(Icons.schedule), findsOneWidget);
    });
  });
}
