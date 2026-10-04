import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:smart_society_app/main.dart';
import 'package:smart_society_app/services/api_service.dart';
import 'package:smart_society_app/state/auth_controller.dart';

void main() {
  testWidgets('login screen provides Resident, Administrator, and Security team roles',
      (WidgetTester tester) async {
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => AuthController(ApiService()),
        child: const MaterialApp(home: LoginScreen()),
      ),
    );

    // Initial role is Resident
    expect(find.text('Resident'), findsOneWidget);

    // Tap dropdown to verify all role items
    await tester.tap(find.text('Resident'));
    await tester.pumpAndSettle();

    expect(find.text('Resident').hitTestable(), findsWidgets);
    expect(find.text('Administrator').hitTestable(), findsWidgets);
    expect(find.text('Security team').hitTestable(), findsWidgets);
  });
}
