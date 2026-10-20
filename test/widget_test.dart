import 'package:bilihub/main.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('BiliHub app loads the login page', (tester) async {
    await tester.pumpWidget(const BiliHubApp());

    expect(find.text('Welcome Back'), findsOneWidget);
  });
}
