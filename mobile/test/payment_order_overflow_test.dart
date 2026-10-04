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
  testWidgets(
      'PaymentOrderDetailScreen does not overflow with long references even on small screens',
      (WidgetTester tester) async {
    // Set screen size to a narrow 320x640 mobile screen
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final mockOrder = {
      'id': 2,
      'resident_id': 3,
      'flat_id': 2,
      'amount': '4000.00',
      'payment_method': 'UPI',
      'status': 'SUCCESSFUL',
      'provider': 'DEMO',
      'provider_reference': 'ORDER-XNCVBLKIOYVO',
      'gateway_payment_id': 'DEMO-20260801-000002',
      'verification_note':
          'Confirmed via internal demo payment (PAYMENT_MODE=demo).',
      'verified_at': '2026-08-31T18:44:05.000000Z',
      'items': [
        {
          'id': 2,
          'payment_order_id': 2,
          'maintenance_bill_id': 2,
          'amount': '2000.00',
          'bill': {
            'id': 2,
            'billing_month': '2026-08-01',
            'due_date': '2026-08-16',
          }
        },
        {
          'id': 3,
          'payment_order_id': 2,
          'maintenance_bill_id': 4,
          'amount': '2000.00',
          'bill': {
            'id': 4,
            'billing_month': '2026-09-01',
            'due_date': '2026-09-16',
          }
        }
      ]
    };

    final api = ApiService(
      client: MockClient((request) async {
        if (request.url.path.contains('payment-orders/2')) {
          return _json({'data': mockOrder});
        }
        return _json({'message': 'Not found'}, statusCode: 404);
      }),
    );

    final auth = AuthController(api)
      ..loading = false
      ..session = const Session(
        token: 'test-token',
        user: AppUser(
          id: 7,
          name: 'Swanand',
          email: 'pawarswanand6@gmail.com',
          role: 'RESIDENT',
        ),
      );

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: auth,
        child: MaterialApp(
          home: PaymentOrderDetailScreen(order: mockOrder),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Verify all contents are present
    expect(find.text('Payment order'), findsOneWidget);
    expect(find.text('Receipt / transaction reference'), findsOneWidget);
    expect(find.text('DEMO-20260801-000002'), findsOneWidget);
    expect(find.text('Order ORDER-XNCVBLKIOYVO'), findsOneWidget);
    expect(find.text('₹4000.00'), findsOneWidget);

    // Verify zero RenderFlex overflow errors occurred
    expect(tester.takeException(), isNull);
  });
}
