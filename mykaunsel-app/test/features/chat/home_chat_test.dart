import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';

import '../../support/fakes.dart';

Future<void> openHome(WidgetTester tester, {FakeChatApi? chat, Size size = const Size(400, 900), double textScale = 1}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: testOverrides(chatApi: chat, storage: FakeTokenStorage('5|mkp_abc')),
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  group('Home', () {
    testWidgets('offers the way to a counselor, the chat, and phone lines', (tester) async {
      await openHome(tester);

      expect(find.text('Find a counselor'), findsOneWidget);
      expect(find.text('Talk it through'), findsOneWidget);
      expect(find.text('Need help right now?'), findsOneWidget);
    });

    testWidgets('the chat card opens the Chat tab', (tester) async {
      await openHome(tester);

      await tester.tap(find.text('Talk it through'));
      await tester.pumpAndSettle();

      expect(find.text('Before you chat'), findsWidgets, reason: 'this person has not agreed yet, so the chat asks first');
      expect(find.byTooltip('Chat history'), findsOneWidget);
    });

    testWidgets('the help card opens the phone lines, with the built-in ones if the server does not answer', (tester) async {
      final chat = FakeChatApi()..helplinesHandler = (() async => throw Exception('down'));
      await openHome(tester, chat: chat);

      await tester.tap(find.text('Need help right now?'));
      await tester.pumpAndSettle();

      expect(find.text('Call HEAL 15555'), findsOneWidget);
      expect(find.text('Call Talian Kasih 15999'), findsOneWidget);
    });

    testWidgets('the cards do not overflow at 320×640, text ×2.0', (tester) async {
      await openHome(tester, size: const Size(320, 640), textScale: 2);

      expect(tester.takeException(), isNull);
      await tester.scrollUntilVisible(find.text('Need help right now?'), 200, scrollable: find.byType(Scrollable).first);
      expect(tester.takeException(), isNull);
    });
  });
}
