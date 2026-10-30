import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/providers.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/chat/domain/chat.dart';

import '../../support/fakes.dart';

final _agreed = testUser.copyWith(aiConsent: true);

/// Opens the app signed in and goes to the Chat tab. [consent] is whether the person has agreed to AI processing.
Future<ProviderContainer> openChat(
  WidgetTester tester,
  FakeChatApi chat, {
  bool consent = true,
  FakeAccountApi? account,
  List<Uri>? opened,
  bool opens = true,
  double textScale = 1,
  Size size = const Size(400, 900),
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...testOverrides(chatApi: chat, accountApi: account, authApi: FakeAuthApi(me: () async => consent ? _agreed : testUser), storage: FakeTokenStorage('5|mkp_abc')),
        urlOpenerProvider.overrideWithValue((uri) async {
          opened?.add(uri);

          return opens;
        }),
      ],
      child: MediaQuery(data: MediaQueryData(size: size, textScaler: TextScaler.linear(textScale)), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(AppRoutes.chat);
  await tester.pumpAndSettle();

  return container;
}

Finder input() => find.byType(TextField);

Future<void> say(WidgetTester tester, String text) async {
  await tester.enterText(input(), text);
  await tester.pump();
  await tester.tap(find.byTooltip('Send'));
  await tester.pumpAndSettle();
}

void main() {
  group('before the person has agreed', () {
    testWidgets('the chat asks first, in four plain lines, and sends nothing', (tester) async {
      final chat = FakeChatApi();
      await openChat(tester, chat, consent: false);

      expect(find.text('Before you chat'), findsWidgets);
      expect(find.textContaining("It is an AI, not a counselor or a doctor"), findsOneWidget);
      expect(find.textContaining("We don't send your name, email or account"), findsOneWidget);
      expect(find.textContaining('kept for 12 months'), findsOneWidget);
      expect(find.textContaining('turn this off at any time in Account'), findsOneWidget);
      expect(input(), findsNothing);
      expect(chat.sends, isEmpty);
    });

    testWidgets('"I agree" asks the server once, then the conversation opens', (tester) async {
      final account = FakeAccountApi();
      await openChat(tester, FakeChatApi(), consent: false, account: account);

      await tester.tap(find.text('I agree'));
      await tester.pumpAndSettle();

      expect(account.consentCalls, [true]);
      expect(find.text('Hi Siti. How are you feeling today?'), findsOneWidget);
      expect(input(), findsOneWidget);
    });

    testWidgets('"Not now" goes home and asks nothing', (tester) async {
      final account = FakeAccountApi();
      await openChat(tester, FakeChatApi(), consent: false, account: account);

      await tester.tap(find.text('Not now'));
      await tester.pumpAndSettle();

      expect(account.consentCalls, isEmpty);
      expect(find.text('Before you chat'), findsNothing);
    });

    testWidgets('if the choice can not be saved it says so and stays', (tester) async {
      final account = FakeAccountApi()..aiConsentHandler = ((consent) async => throw const NetworkFailure());
      await openChat(tester, FakeChatApi(), consent: false, account: account);

      await tester.tap(find.text('I agree'));
      await tester.pumpAndSettle();

      expect(find.text("We couldn't save your choice. Please try again."), findsOneWidget);
      expect(input(), findsNothing);
      expect(tester.widget<FilledButton>(find.widgetWithText(FilledButton, 'I agree')).onPressed, isNotNull);
    });
  });

  group('talking', () {
    testWidgets('a greeting, the box, what this is, and a way to ask for help that is always there', (tester) async {
      await openChat(tester, FakeChatApi());

      expect(find.text('Hi Siti. How are you feeling today?'), findsOneWidget);
      expect(find.textContaining("I'm an AI companion, not a counselor"), findsOneWidget);
      expect(find.text('AI companion. Not a counselor or a doctor.'), findsOneWidget);
      expect(find.text('Write a message'), findsOneWidget);
      expect(find.byTooltip('Need help now?'), findsOneWidget);
      expect(find.byTooltip('Chat history'), findsOneWidget);
      expect(find.byTooltip('New conversation'), findsNothing, reason: 'nothing to start over yet');
    });

    testWidgets('a message shows, the answer shows, the box empties, and the next message joins the same conversation', (tester) async {
      final chat = FakeChatApi();
      await openChat(tester, chat);

      await say(tester, '  I feel low today.  ');

      expect(chat.sends.single.text, 'I feel low today.');
      expect(chat.sends.single.session, isNull);
      expect(find.text('I feel low today.'), findsOneWidget);
      expect(find.text('I hear you. That sounds hard.'), findsOneWidget);
      expect(tester.widget<TextField>(input()).controller!.text, isEmpty);
      expect(find.text('Hi Siti. How are you feeling today?'), findsNothing);

      await say(tester, 'It is my exams.');

      expect(chat.sends.length, 2);
      expect(chat.sends.last.session, 's1');
      expect(find.text('It is my exams.'), findsOneWidget);
    });

    testWidgets('each message carries its own client id', (tester) async {
      final chat = FakeChatApi();
      await openChat(tester, chat);

      await say(tester, 'one');
      await say(tester, 'two');

      expect(chat.sends.map((s) => s.clientId).toSet().length, 2);
      expect(chat.sends.first.clientId.length, 36);
    });

    testWidgets('while it waits: a sign of thinking, no second send, then the answer', (tester) async {
      final pending = Completer<ChatSendResult>();
      final chat = FakeChatApi()..sendHandler = ((text, session, id) => pending.future);
      await openChat(tester, chat);

      await tester.enterText(input(), 'hello');
      await tester.tap(find.byTooltip('Send'));
      await tester.pump();

      expect(find.text('Thinking…'), findsWidgets);
      expect(tester.widget<IconButton>(find.widgetWithIcon(IconButton, Icons.send)).onPressed, isNull);
      expect(chat.sends.length, 1);

      pending.complete(aChatResult(text: 'hello'));
      await tester.pumpAndSettle();

      expect(find.text('Thinking…'), findsNothing);
      expect(find.text('I hear you. That sounds hard.'), findsOneWidget);
    });

    testWidgets('an empty message is not sent', (tester) async {
      final chat = FakeChatApi();
      await openChat(tester, chat);

      await tester.enterText(input(), '   ');
      await tester.tap(find.byTooltip('Send'));
      await tester.pumpAndSettle();

      expect(chat.sends, isEmpty);
    });

    testWidgets('a screen reader hears who said what', (tester) async {
      final handle = tester.ensureSemantics();
      await openChat(tester, FakeChatApi());
      await say(tester, 'I feel low');

      expect(find.bySemanticsLabel('You said: I feel low'), findsOneWidget);
      expect(find.bySemanticsLabel('Companion said: I hear you. That sounds hard.'), findsOneWidget);
      handle.dispose();
    });

    testWidgets('leaving the tab and coming back keeps the conversation', (tester) async {
      final container = await openChat(tester, FakeChatApi());
      await say(tester, 'I feel low');

      container.read(appRouterProvider).go(AppRoutes.home);
      await tester.pumpAndSettle();
      container.read(appRouterProvider).go(AppRoutes.chat);
      await tester.pumpAndSettle();

      expect(find.text('I feel low'), findsOneWidget);
      expect(find.text('I hear you. That sounds hard.'), findsOneWidget);
    });
  });

  group('when the companion can not answer', () {
    testWidgets('the message stays, a kind line says so, and "Try again" asks for the answer to the conversation', (tester) async {
      var down = true;
      final chat = FakeChatApi()
        ..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, unavailable: down))
        ..replyHandler = ((session) async => aChatResult(text: 'I feel low', session: session, reply: 'Thank you for waiting.'));
      await openChat(tester, chat);

      await say(tester, 'I feel low');

      expect(find.text('I feel low'), findsOneWidget);
      expect(find.text("I can't reply right now. Your message is saved. Please try again in a moment."), findsOneWidget);

      down = false;
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(chat.replies, ['s1']);
      expect(chat.sends.length, 1, reason: 'the message was not sent again');
      expect(find.text("I can't reply right now. Your message is saved. Please try again in a moment."), findsNothing);
      expect(find.text('Thank you for waiting.'), findsOneWidget);
      expect(find.text('I feel low'), findsOneWidget);
    });

    testWidgets('if asking again fails too, the line stays with its button', (tester) async {
      final chat = FakeChatApi()
        ..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, unavailable: true))
        ..replyHandler = ((session) async => throw const NetworkFailure());
      await openChat(tester, chat);
      await say(tester, 'hello');

      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text("I can't reply right now. Your message is saved. Please try again in a moment."), findsOneWidget);
      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);
      expect(find.text('Try again'), findsOneWidget);
    });
  });

  group('when a message does not go', () {
    testWidgets('no connection: the words, and "Send again" sends the SAME message with the SAME client id', (tester) async {
      var tries = 0;
      final chat = FakeChatApi()
        ..sendHandler = ((text, session, id) async {
          if (++tries == 1) throw const NetworkFailure(timedOut: true);

          return aChatResult(text: text, session: session);
        });
      await openChat(tester, chat);
      await say(tester, 'hello');

      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);
      expect(find.text('hello'), findsOneWidget);

      await tester.tap(find.text('Send again'));
      await tester.pumpAndSettle();

      expect(chat.sends.length, 2);
      expect(chat.sends[0].clientId, chat.sends[1].clientId);
      expect(chat.sends[0].text, chat.sends[1].text);
      expect(find.text('hello'), findsOneWidget, reason: 'one bubble, not two');
      expect(find.text('Send again'), findsNothing);
      expect(find.text('I hear you. That sounds hard.'), findsOneWidget);
    });

    testWidgets('a server error says it may or may not have been sent, and will not be sent twice', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => throw const ServerFailure(statusCode: 500));
      await openChat(tester, chat);
      await say(tester, 'hello');

      expect(find.textContaining('may or may not have been sent'), findsOneWidget);
      expect(find.text('Send again'), findsOneWidget);
    });

    testWidgets('too many messages says how long to wait', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => throw const RateLimitedFailure(retryAfterSeconds: 30));
      await openChat(tester, chat);
      await say(tester, 'hello');

      expect(find.text('Too many messages. Please wait 30 seconds.'), findsOneWidget);
    });

    testWidgets('the daily limit says come back tomorrow, with no "Send again"', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => throw const RateLimitedFailure(retryAfterSeconds: 40000));
      await openChat(tester, chat);
      await say(tester, 'hello');

      expect(find.text("You've reached today's limit of messages. Please come back tomorrow."), findsOneWidget);
      expect(find.text('Send again'), findsNothing);
    });

    testWidgets('a full conversation says to start a new one', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => throw const ConflictFailure(code: 'session_full'));
      await openChat(tester, chat);
      await say(tester, 'hello');

      expect(find.text('This conversation is full. Start a new one.'), findsOneWidget);
      expect(find.text('Send again'), findsNothing);
    });

    testWidgets('a conversation the server ended meanwhile: this message starts a new one, said plainly', (tester) async {
      final chat = FakeChatApi()
        ..sendHandler = ((text, session, id) async {
          if (text == 'second' && session != null) throw const ConflictFailure(code: 'session_ended');

          return aChatResult(text: text, session: session == null && text == 'second' ? 's2' : session);
        });
      await openChat(tester, chat);
      await say(tester, 'first');
      await say(tester, 'second');

      expect(chat.sends.map((s) => s.session), [null, 's1', null]);
      expect(chat.sends[1].clientId, chat.sends[2].clientId);
      expect(find.text('This is a new conversation, because the last one had ended.'), findsOneWidget);
      expect(find.text('second'), findsOneWidget);
    });

    testWidgets('consent withdrawn elsewhere: the message goes and the chat asks again', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => throw const ForbiddenFailure('x', code: 'ai_consent_required'));
      await openChat(tester, chat);
      await say(tester, 'hello');

      expect(find.text('Before you chat'), findsWidgets);
      expect(input(), findsNothing);
    });
  });

  group('worrying words', () {
    testWidgets('a calm card with the phone lines appears, the chat goes on, and a call is a tap', (tester) async {
      final opened = <Uri>[];
      final chat = FakeChatApi()
        ..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, crisis: true, helplines: fallbackHelplines));
      await openChat(tester, chat, opened: opened);

      await say(tester, 'I do not want to live anymore');

      expect(find.text('You are not alone'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'Call HEAL 15555'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'Call Talian Kasih 15999'), findsOneWidget);
      expect(find.textContaining('nearest hospital emergency department'), findsOneWidget);
      expect(find.text('I hear you. That sounds hard.'), findsOneWidget, reason: 'the companion still answers');
      expect(tester.widget<IconButton>(find.widgetWithIcon(IconButton, Icons.send)).onPressed, isNotNull, reason: 'it never blocks the chat');

      await tester.tap(find.text('Call HEAL 15555'));
      await tester.pumpAndSettle();

      expect(opened.single.toString(), 'tel:15555');
    });

    testWidgets('if the phone can not call, it says which number to dial', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, crisis: true, helplines: fallbackHelplines));
      await openChat(tester, chat, opens: false);
      await say(tester, 'help');

      await tester.tap(find.text('Call Talian Kasih 15999'));
      await tester.pumpAndSettle();

      expect(find.text("We couldn't start the call. Please dial 15999."), findsOneWidget);
    });

    testWidgets('"Continue chatting" puts the card away and the conversation carries on', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, crisis: text == 'bad', helplines: fallbackHelplines));
      await openChat(tester, chat);
      await say(tester, 'bad');

      await tester.tap(find.text('Continue chatting'));
      await tester.pumpAndSettle();
      expect(find.text('You are not alone'), findsNothing);

      await say(tester, 'thank you');
      expect(find.text('thank you'), findsOneWidget);
      expect(find.text('You are not alone'), findsNothing);
    });

    testWidgets('the card shows even when the companion could not answer', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, unavailable: true, crisis: true, helplines: fallbackHelplines));
      await openChat(tester, chat);
      await say(tester, 'help');

      expect(find.text('You are not alone'), findsOneWidget);
      expect(find.text('Call HEAL 15555'), findsOneWidget);
    });

    testWidgets('a card that arrives with no lines still has the built-in ones', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, crisis: true));
      await openChat(tester, chat);
      await say(tester, 'help');

      expect(find.text('Call HEAL 15555'), findsOneWidget);
    });

    testWidgets('"Find a counselor" goes to the counselors', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, crisis: true, helplines: fallbackHelplines));
      await openChat(tester, chat);
      await say(tester, 'help');

      await tester.ensureVisible(find.widgetWithText(OutlinedButton, 'Find a counselor'));
      await tester.tap(find.widgetWithText(OutlinedButton, 'Find a counselor'));
      await tester.pumpAndSettle();

      expect(find.text('Find a counselor'), findsWidgets);
      expect(find.byType(TextField), findsOneWidget);
    });

    testWidgets('"Need help now?" is always one tap away, with the lines from the server', (tester) async {
      final chat = FakeChatApi()..helplinesHandler = (() async => const [Helpline(key: 'x', name: 'Test Line', number: '123')]);
      await openChat(tester, chat);

      await tester.tap(find.byTooltip('Need help now?'));
      await tester.pumpAndSettle();

      expect(find.text("You don't have to face this alone. These lines are there to listen."), findsOneWidget);
      expect(find.text('Call Test Line 123'), findsOneWidget);
    });

    testWidgets('and with no connection it still has the built-in lines', (tester) async {
      final chat = FakeChatApi()..helplinesHandler = (() async => throw const NetworkFailure());
      await openChat(tester, chat);

      await tester.tap(find.byTooltip('Need help now?'));
      await tester.pumpAndSettle();

      expect(find.text('Call HEAL 15555'), findsOneWidget);
      expect(find.text('Call Talian Kasih 15999'), findsOneWidget);
    });

    testWidgets('counselors the server suggests are shown as cards, and one opens their page', (tester) async {
      final chat = FakeChatApi()..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, counselors: [aCounselor()]));
      await openChat(tester, chat);
      await say(tester, 'I want to see a counselor');

      expect(find.text('Counselors who may be able to help'), findsOneWidget);
      expect(find.text('Kaunselor Aina Syazwani'), findsOneWidget);

      await tester.ensureVisible(find.text('Kaunselor Aina Syazwani'));
      await tester.tap(find.text('Kaunselor Aina Syazwani'));
      await tester.pumpAndSettle();

      expect(find.text('See available times'), findsOneWidget);
    });
  });

  group('a new conversation', () {
    testWidgets('clears the screen and ends the old one on the server, once', (tester) async {
      final chat = FakeChatApi();
      await openChat(tester, chat);
      await say(tester, 'I feel low');

      await tester.tap(find.byTooltip('New conversation'));
      await tester.pumpAndSettle();

      expect(chat.ends, ['s1']);
      expect(find.text('I feel low'), findsNothing);
      expect(find.text('Hi Siti. How are you feeling today?'), findsOneWidget);

      await say(tester, 'a new start');
      expect(chat.sends.last.session, isNull);
    });

    testWidgets('if ending it fails nothing is shown: the server ends it by itself', (tester) async {
      final chat = FakeChatApi()..endHandler = ((session) async => throw const NetworkFailure());
      await openChat(tester, chat);
      await say(tester, 'hello');

      await tester.tap(find.byTooltip('New conversation'));
      await tester.pumpAndSettle();

      expect(find.text('Hi Siti. How are you feeling today?'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });

  group('history', () {
    Future<void> openHistory(WidgetTester tester, FakeChatApi chat) async {
      await openChat(tester, chat);
      await tester.tap(find.byTooltip('Chat history'));
      await tester.pumpAndSettle();
    }

    testWidgets('groups Today, This week and Earlier, with a preview and the feeling', (tester) async {
      final chat = FakeChatApi()
        ..historyHandler = ((page) async => ChatHistoryPage(
              items: [
                aChatSession(id: 'a', ended: true, preview: 'Exams worry me', emotion: 'anxiety'),
                aChatSession(id: 'b', ended: true, preview: 'A rough week', days: -3),
                aChatSession(id: 'c', ended: true, preview: 'Long ago', days: -20, emotion: 'hope'),
              ],
              page: 1,
              lastPage: 1,
            ));
      await openHistory(tester, chat);

      expect(find.text('Chat history'), findsWidgets);
      expect(find.text('Today'), findsOneWidget);
      expect(find.text('This week'), findsOneWidget);
      expect(find.text('Earlier'), findsOneWidget);
      expect(find.text('Exams worry me'), findsOneWidget);
      expect(find.text('Mostly felt: anxiety'), findsOneWidget);
      expect(find.text('Mostly felt: hope'), findsOneWidget);
    });

    testWidgets('empty: a plain message', (tester) async {
      await openHistory(tester, FakeChatApi());

      expect(find.text('No conversations yet'), findsOneWidget);
    });

    testWidgets('a conversation opens read only, with no box to type in', (tester) async {
      final chat = FakeChatApi()
        ..historyHandler = ((page) async => ChatHistoryPage(items: [aChatSession(id: 'a', ended: true, preview: 'Exams worry me', emotion: 'stress')], page: 1, lastPage: 1))
        ..detailHandler = ((id) async => ChatSessionDetail(
              session: aChatSession(id: id, ended: true, emotion: 'stress'),
              messages: [aChatMessage('1', ChatSender.user, 'Exams worry me'), aChatMessage('2', ChatSender.assistant, 'That sounds stressful.')],
            ));
      await openHistory(tester, chat);
      await tester.tap(find.text('Exams worry me'));
      await tester.pumpAndSettle();

      expect(chat.details, ['a']);
      expect(find.text('This conversation is finished. You can read it here.'), findsOneWidget);
      expect(find.text('Mostly felt: stress'), findsOneWidget);
      expect(find.text('Exams worry me'), findsOneWidget);
      expect(find.text('That sounds stressful.'), findsOneWidget);
      expect(find.byType(TextField), findsNothing);
      expect(find.byTooltip('Send'), findsNothing);
    });

    testWidgets('a failure shows the words and "Try again"', (tester) async {
      var tries = 0;
      final chat = FakeChatApi()
        ..historyHandler = ((page) async {
          if (++tries == 1) throw const ServerFailure(statusCode: 500);

          return ChatHistoryPage(items: [aChatSession(id: 'a', preview: 'Back again')], page: 1, lastPage: 1);
        });
      await openHistory(tester, chat);

      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('Back again'), findsOneWidget);
    });

    testWidgets('a conversation that is not there says so', (tester) async {
      final chat = FakeChatApi()
        ..historyHandler = ((page) async => ChatHistoryPage(items: [aChatSession(id: 'a', preview: 'Gone')], page: 1, lastPage: 1))
        ..detailHandler = ((id) async => throw const NotFoundFailure());
      await openHistory(tester, chat);
      await tester.tap(find.text('Gone'));
      await tester.pumpAndSettle();

      expect(find.text("We couldn't find this conversation."), findsOneWidget);
    });
  });

  group('in Account', () {
    Future<void> openAccount(WidgetTester tester, FakeAccountApi account, {bool consent = true}) async {
      final container = await openChat(tester, FakeChatApi(), account: account, consent: consent);
      container.read(appRouterProvider).go(AppRoutes.account);
      await tester.pumpAndSettle();
    }

    testWidgets('the switch shows whether the chat is on', (tester) async {
      await openAccount(tester, FakeAccountApi());

      expect(find.text('AI chat'), findsOneWidget);
      expect(find.text('On: you can talk with the companion.'), findsOneWidget);
      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isTrue);
    });

    testWidgets('turning it off asks first; "Cancel" changes nothing', (tester) async {
      final account = FakeAccountApi();
      await openAccount(tester, account);

      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();
      expect(find.text('Turn off AI chat?'), findsOneWidget);

      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();

      expect(account.consentCalls, isEmpty);
      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isTrue);
    });

    testWidgets('confirming turns it off, the switch follows the server, and the chat asks again', (tester) async {
      final account = FakeAccountApi();
      final container = await openChat(tester, FakeChatApi(), account: account);
      container.read(appRouterProvider).go(AppRoutes.account);
      await tester.pumpAndSettle();

      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Turn off'));
      await tester.pumpAndSettle();

      expect(account.consentCalls, [false]);
      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isFalse);
      expect(find.text('Off: the chat is turned off.'), findsOneWidget);

      container.read(appRouterProvider).go(AppRoutes.chat);
      await tester.pumpAndSettle();
      expect(find.text('Before you chat'), findsWidgets);
    });

    testWidgets('turning it on asks with the same words as the chat, and "I agree" saves', (tester) async {
      final account = FakeAccountApi();
      await openAccount(tester, account, consent: false);

      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();
      expect(find.textContaining("It is an AI, not a counselor or a doctor"), findsOneWidget);

      await tester.tap(find.text('I agree'));
      await tester.pumpAndSettle();

      expect(account.consentCalls, [true]);
      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isTrue);
    });

    testWidgets('if the server does not answer, the switch stays as it was', (tester) async {
      final account = FakeAccountApi()..aiConsentHandler = ((consent) async => throw const NetworkFailure());
      await openAccount(tester, account);

      await tester.tap(find.byType(SwitchListTile));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Turn off'));
      await tester.pumpAndSettle();

      expect(tester.widget<SwitchListTile>(find.byType(SwitchListTile)).value, isTrue);
    });
  });

  group('the bottom bar', () {
    testWidgets('has five tabs, Chat among them', (tester) async {
      await openChat(tester, FakeChatApi());

      final labels = tester.widgetList<NavigationDestination>(find.byType(NavigationDestination)).map((d) => d.label).toList();

      expect(labels, ['Home', 'Find', 'Chat', 'Bookings', 'Account']);
    });
  });

  group('large text and small screens', () {
    for (final scale in [1.0, 2.0]) {
      testWidgets('the gate, the chat with a crisis card, and the history do not overflow at 320×640, text ×$scale', (tester) async {
        final chat = FakeChatApi()
          ..sendHandler = ((text, session, id) async => aChatResult(text: text, session: session, crisis: true, helplines: fallbackHelplines, counselors: [aCounselor()]))
          ..historyHandler = ((page) async => ChatHistoryPage(items: [aChatSession(id: 'a', ended: true, preview: 'A long first message that has to wrap on a small screen with big text', emotion: 'anxiety')], page: 1, lastPage: 1));
        final container = await openChat(tester, chat, size: const Size(320, 640), textScale: scale);
        await say(tester, 'I want to die, a long sentence so that the bubble has to wrap across several lines on this small screen');
        expect(tester.takeException(), isNull);

        await tester.tap(find.byTooltip('Need help now?'));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await tester.tapAt(const Offset(10, 10));
        await tester.pumpAndSettle();

        container.read(appRouterProvider).go(AppRoutes.chatHistory);
        await tester.pumpAndSettle();
        expect(find.text('Chat history'), findsWidgets);
        expect(tester.takeException(), isNull);
      });
    }

    testWidgets('the consent gate does not overflow at 320×640, text ×2.0', (tester) async {
      await openChat(tester, FakeChatApi(), consent: false, size: const Size(320, 640), textScale: 2);
      await tester.scrollUntilVisible(find.text('I agree'), 200, scrollable: find.byType(Scrollable).first);

      expect(find.text('I agree'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}
