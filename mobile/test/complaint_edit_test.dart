import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
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
  testWidgets('Resident can open complaint, edit details, and save via PUT request',
      (WidgetTester tester) async {
    Map<String, dynamic> currentComplaint = {
      'id': 1,
      'category': 'PLUMBING',
      'title': 'Leaky faucet',
      'description': 'Kitchen sink faucet is dripping continuously.',
      'priority': 'LOW',
      'status': 'OPEN',
      'created_at': '2026-08-16',
      'history': [],
    };

    String? lastUpdateMethod;
    String? lastUpdatePath;
    Map<String, dynamic>? lastRequestBody;

    final api = ApiService(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/resident/complaints/1')) {
          if (request.method == 'GET') {
            return _json({'data': currentComplaint});
          }
          if (request.method == 'PUT' || request.method == 'PATCH') {
            lastUpdateMethod = request.method;
            lastUpdatePath = request.url.path;
            lastRequestBody = jsonDecode(request.body) as Map<String, dynamic>;
            currentComplaint = {
              ...currentComplaint,
              ...lastRequestBody!,
            };
            return _json({'data': currentComplaint});
          }
        }
        return _json({'message': 'Not found'}, statusCode: 404);
      }),
    );

    final auth = AuthController(api)
      ..loading = false
      ..session = const Session(
        token: 'test-token',
        user: AppUser(
          id: 2,
          name: 'Swanand',
          email: 'swanand@example.com',
          role: 'RESIDENT',
        ),
      );

    final config = FormConfig.forRoute('resident/complaints');

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: auth,
        child: MaterialApp(
          home: RecordDetailScreen(
            title: 'Complaint',
            endpoint: 'resident/complaints',
            item: currentComplaint,
            config: config,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Verify initial complaint details
    expect(find.text('Leaky faucet'), findsOneWidget);
    expect(find.text('Kitchen sink faucet is dripping continuously.'), findsOneWidget);

    // Tap Edit button
    final editButton = find.byIcon(Icons.edit);
    expect(editButton, findsOneWidget);
    await tester.tap(editButton);
    await tester.pumpAndSettle();

    // Verify Edit form is displayed with existing values
    expect(find.text('Edit complaint'), findsOneWidget);
    expect(find.text('Leaky faucet'), findsOneWidget);

    // Enter new title and description
    final titleField = find.widgetWithText(TextFormField, 'Leaky faucet');
    await tester.enterText(titleField, 'Heavy pipe leak');

    final descField = find.widgetWithText(TextFormField, 'Kitchen sink faucet is dripping continuously.');
    await tester.enterText(descField, 'Main pipe under sink burst and flooding kitchen.');

    // Save
    final saveButton = find.widgetWithText(FilledButton, 'Save');
    expect(saveButton, findsOneWidget);
    await tester.tap(saveButton);
    await tester.pumpAndSettle();

    // Verify correct PUT method, path, and payload were sent
    expect(lastUpdateMethod, 'PUT');
    expect(lastUpdatePath, '/api/resident/complaints/1');
    expect(lastRequestBody?['title'], 'Heavy pipe leak');
    expect(lastRequestBody?['description'], 'Main pipe under sink burst and flooding kitchen.');

    // Verify updated details appear immediately on ItemDetailScreen
    expect(find.text('Heavy pipe leak'), findsOneWidget);
    expect(find.text('Main pipe under sink burst and flooding kitchen.'), findsOneWidget);
  });
}
