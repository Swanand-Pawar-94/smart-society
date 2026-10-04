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

void main() {
  testWidgets('both dues invoice actions load the exact selected invoice',
      (WidgetTester tester) async {
    final requestedInvoicePaths = <String>[];
    final api = ApiService(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/resident/maintenance-bills')) {
          return _json({
            'data': [_summaryBill(41), _summaryBill(42)],
          });
        }

        final billId = int.tryParse(request.url.pathSegments.last);
        if (billId != null) {
          requestedInvoicePaths.add(request.url.path);
          return _json({'data': _invoice(billId)});
        }

        return _json({'message': 'Not found'}, statusCode: 404);
      }),
    );
    final auth = AuthController(api)
      ..loading = false
      ..session = const Session(
        token: 'test-token',
        user: AppUser(
          id: 8,
          name: 'Resident One',
          email: 'resident@example.test',
          role: 'RESIDENT',
        ),
      );

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: auth,
        child: const MaterialApp(home: DuesScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Open Invoices'), findsOneWidget);
    expect(find.text('View Invoice'), findsNWidgets(2));

    await tester.tap(find.text('View Invoice').first);
    await tester.pumpAndSettle();
    expect(find.text('INVOICE'), findsOneWidget);
    expect(find.text('Kasliwal Marvel (West)'), findsOneWidget);
    expect(
      find.text(
        'Kasliwal Marvel (West),\nBeed Bypass,\nChhatrapati Sambhajinagar,\nMaharashtra',
      ),
      findsOneWidget,
    );
    expect(find.text('INV-41'), findsOneWidget);
    expect(requestedInvoicePaths.last, '/api/resident/maintenance-bills/41');

    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.text('History'));
    await tester.pumpAndSettle();
    expect(find.text('Invoice Posted on'), findsNWidgets(2));

    await tester.tap(find.text('Invoice Posted on').last);
    await tester.pumpAndSettle();
    expect(find.text('INVOICE'), findsOneWidget);
    expect(find.text('INV-42'), findsOneWidget);
    expect(requestedInvoicePaths.last, '/api/resident/maintenance-bills/42');
  });

  testWidgets('clicking Pay full amount on a specific invoice opens single-invoice checkout with exact amount',
      (WidgetTester tester) async {
    int? paidBillId;
    final api = ApiService(
      client: MockClient((request) async {
        if (request.url.path.endsWith('/resident/maintenance-bills')) {
          return _json({
            'data': [_summaryBill(41), _summaryBill(42)],
          });
        }
        if (request.url.path.endsWith('/resident/payments/create-order') ||
            request.url.path.endsWith('/resident/maintenance-payments')) {
          final body = jsonDecode(request.body) as Map<String, dynamic>;
          paidBillId = (body['invoiceId'] ?? body['maintenance_bill_id']) as int?;
          return _json({
            'data': {
              'order_id': 'order_mock_12345',
              'amount': 100000,
              'amount_formatted': 1000.0,
              'currency': 'INR',
              'key_id': 'rzp_test_mock',
              'name': 'Kasliwal Marvel (West)',
              'description': 'Maintenance · August 2026',
              'payment_order_id': 99,
              'maintenance_bill_id': paidBillId,
              'is_single_invoice': true,
            }
          });
        }
        if (request.url.path.endsWith('/resident/payments/verify')) {
          return _json({
            'message': 'Payment verified successfully.',
            'data': {
              'success': true,
              'payment_id': 'pay_mock_123',
              'order': {'id': 99, 'status': 'SUCCESSFUL'},
            }
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
          id: 8,
          name: 'Resident One',
          email: 'resident@example.test',
          role: 'RESIDENT',
        ),
      );

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: auth,
        child: const MaterialApp(home: DuesScreen()),
      ),
    );
    await tester.pumpAndSettle();

    // Click "Pay full amount" on the first invoice (ID 41)
    await tester.tap(find.text('Pay full amount').first);
    await tester.pumpAndSettle();

    // Checkout screen should review single invoice
    expect(find.text('Review payment'), findsOneWidget);
    expect(find.text('Amount due'), findsOneWidget);
    expect(find.text('Previous dues'), findsOneWidget);
    expect(find.text('₹0.00'), findsWidgets); // Previous dues is 0 for single invoice

    // Submit payment button exists and is clickable
    await tester.drag(find.byType(ListView), const Offset(0, -300));
    await tester.pumpAndSettle();
    final payButton = find.text('Pay full amount');
    expect(payButton, findsOneWidget);
    await tester.tap(payButton);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 200));

    expect(paidBillId, 41);
  });
}

http.Response _json(Map<String, dynamic> body, {int statusCode = 200}) =>
    http.Response(jsonEncode(body), statusCode,
        headers: const {'content-type': 'application/json'});

Map<String, dynamic> _summaryBill(int id) => {
      'id': id,
      'billing_month': '2026-08-01',
      'due_date': '2026-08-16',
      'amount': '1000.00',
      'outstanding_amount': '1000.00',
      'status': 'UNPAID',
      'notes': 'Automated monthly maintenance invoice for August 2026',
      'created_at': '2026-08-01T00:00:00.000000Z',
    };

Map<String, dynamic> _invoice(int id) => {
      ..._summaryBill(id),
      'flat': {'id': 1, 'building': 'A', 'flat_number': '101'},
      'paid_amount': '0.00',
      'invoice': {
        'invoice_number': 'INV-$id',
        'invoice_date': '2026-08-01',
        'due_date': '2026-08-16',
        'invoice_period': 'August 2026',
        'society': {
          'name': 'Kasliwal Marvel (West)',
          'address':
              'Kasliwal Marvel (West),\nBeed Bypass,\nChhatrapati Sambhajinagar,\nMaharashtra',
        },
        'unit': {'building': 'A', 'flat_number': '101'},
        'resident': {'name': 'Resident One'},
        'line_items': [
          {
            'account': 'Maintenance Fee',
            'rate': 1000,
            'comment': 'Monthly maintenance fee',
            'amount': 1000,
          },
        ],
        'current_total': 1000,
        'paid_amount': 0,
        'previous_dues': 0,
        'previous_dues_as_of': '2026-07-31',
        'net_payable': 1000,
        'amount_in_words': 'One thousand rupees only',
      },
    };
