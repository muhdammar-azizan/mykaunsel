import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/app.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/providers.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/features/feed/domain/feed.dart';

import '../../support/fakes.dart';

/// Opens the app signed in as Siti Aminah and goes to the Feed tab.
Future<ProviderContainer> openFeed(
  WidgetTester tester,
  FakeFeedApi feed, {
  Size size = const Size(400, 900),
  List<Uri>? opened,
  String path = AppRoutes.feed,
}) async {
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        ...testOverrides(feedApi: feed, storage: FakeTokenStorage('5|mkp_abc')),
        urlOpenerProvider.overrideWithValue((uri) async {
          opened?.add(uri);

          return true;
        }),
      ],
      child: MediaQuery(data: MediaQueryData(size: size), child: const MyKaunselApp()),
    ),
  );
  await tester.pumpAndSettle();

  final container = ProviderScope.containerOf(tester.element(find.byType(MyKaunselApp)));
  container.read(appRouterProvider).go(path);
  await tester.pumpAndSettle();

  return container;
}

Future<void> openPostMenuOn(WidgetTester tester, String text) async {
  final card = find.ancestor(of: find.text(text), matching: find.byType(InkWell)).first;
  await tester.tap(find.descendant(of: find.ancestor(of: card, matching: find.byType(Material)).first, matching: find.byTooltip('Post options')));
  await tester.pumpAndSettle();
}

void main() {
  group('the feed', () {
    testWidgets('shows posts newest first under their authors names, with a badge for counselors, likes and comment counts', (tester) async {
      final feed = FakeFeedApi(posts: [
        aFeedPost('p1', text: 'Feeling calmer today', name: 'Aiman', likes: 3, comments: 2),
        aFeedPost('p2', text: 'A tip for exams', name: 'Dr Hana', counselor: true, minutesAgo: 180),
      ]);
      await openFeed(tester, feed);

      expect(find.text('Feeling calmer today'), findsOneWidget);
      expect(find.text('Aiman'), findsOneWidget);
      expect(find.text('3 likes'), findsOneWidget);
      expect(find.text('2 comments'), findsOneWidget);
      expect(find.text('5 min ago'), findsOneWidget);
      expect(find.text('3 h ago'), findsOneWidget);
      expect(find.text('Counselor'), findsOneWidget);
      expect(tester.getTopLeft(find.text('Feeling calmer today')).dy, lessThan(tester.getTopLeft(find.text('A tip for exams')).dy));
    });

    testWidgets('says so when there are no posts, and offers to write the first', (tester) async {
      await openFeed(tester, FakeFeedApi());

      expect(find.text('No posts yet'), findsOneWidget);
      expect(find.widgetWithText(FilledButton, 'New post'), findsOneWidget);
    });

    testWidgets('a failure can be tried again', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'Back again')])..feedHandler = (_) async => throw const NetworkFailure();
      await openFeed(tester, feed);

      expect(find.text('Something went wrong'), findsOneWidget);

      feed.feedHandler = null;
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('Back again'), findsOneWidget);
    });

    testWidgets('asks for the next page when the end is reached, without repeating a post', (tester) async {
      final feed = FakeFeedApi()
        ..feedHandler = (cursor) async => cursor == null
            ? FeedPage(items: [for (var i = 0; i < 6; i++) aFeedPost('p$i', text: 'Post number $i')], nextCursor: 'p5')
            : FeedPage(items: [aFeedPost('p5', text: 'Post number 5'), aFeedPost('p6', text: 'Last post')]);
      await openFeed(tester, feed, size: const Size(400, 500));

      await tester.scrollUntilVisible(find.text('Last post'), 300, scrollable: find.byType(Scrollable).first);
      await tester.pumpAndSettle();

      expect(find.text('Last post'), findsOneWidget);
      expect(find.text('Post number 5'), findsOneWidget);
      expect(feed.calls.where((c) => c.startsWith('feed:')), ['feed:', 'feed:p5']);
    });

    testWidgets('a like shows at once and is the servers count; if it fails the heart goes back and the person is told', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'Like me', likes: 1)]);
      await openFeed(tester, feed);

      await tester.tap(find.text('1 like'));
      await tester.pumpAndSettle();

      expect(find.text('2 likes'), findsOneWidget);
      expect(find.byIcon(Icons.favorite), findsOneWidget);
      expect(feed.calls, contains('like:p1'));

      feed.reactHandler = (_, _) async => throw const NetworkFailure();
      await tester.tap(find.text('2 likes'));
      await tester.pumpAndSettle();

      expect(find.text('2 likes'), findsOneWidget);
      expect(find.byIcon(Icons.favorite), findsOneWidget);
      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);
    });

    testWidgets('a like is read aloud as a switch that is on or off, with the count', (tester) async {
      final handle = tester.ensureSemantics();
      await openFeed(tester, FakeFeedApi(posts: [aFeedPost('p1', likes: 4, liked: true)]));

      expect(find.bySemanticsLabel('Remove like, 4 likes'), findsOneWidget);
      handle.dispose();
    });
  });

  group('writing a post', () {
    testWidgets('shows the name it will go under, counts as you type, and posts to the top of the feed', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'Older post')]);
      await openFeed(tester, feed);

      await tester.tap(find.text('New post').last);
      await tester.pumpAndSettle();

      expect(find.textContaining('Posting as Siti Aminah'), findsOneWidget);
      expect(find.text('0/500'), findsOneWidget);
      expect(tester.widget<TextButton>(find.widgetWithText(TextButton, 'Post')).onPressed, isNull);

      await tester.enterText(find.byType(TextField), 'Hello all');
      await tester.pump();

      expect(find.text('9/500'), findsOneWidget);

      await tester.tap(find.widgetWithText(TextButton, 'Post'));
      await tester.pumpAndSettle();

      expect(feed.creates.single.text, 'Hello all');
      expect(find.text('Hello all'), findsOneWidget);
      expect(tester.getTopLeft(find.text('Hello all')).dy, lessThan(tester.getTopLeft(find.text('Older post')).dy));
    });

    testWidgets('stops at 500 characters', (tester) async {
      await openFeed(tester, FakeFeedApi());
      await tester.tap(find.widgetWithText(FilledButton, 'New post'));
      await tester.pumpAndSettle();

      await tester.enterText(find.byType(TextField), 'a' * 520);
      await tester.pump();

      expect(find.text('500/500'), findsOneWidget);
    });

    testWidgets('a post that did not go stays on screen and goes again with the same client id, which the server stores once', (tester) async {
      final feed = FakeFeedApi();
      var first = true;
      feed.createHandler = (text, key) async {
        if (first) {
          first = false;
          throw const NetworkFailure();
        }

        return FeedWrite(item: aFeedPost('n1', text: text, mine: true));
      };
      await openFeed(tester, feed);
      await tester.tap(find.widgetWithText(FilledButton, 'New post'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'Try me');
      await tester.pump();

      await tester.tap(find.widgetWithText(TextButton, 'Post'));
      await tester.pumpAndSettle();

      expect(find.text('Try me'), findsOneWidget);
      expect(find.text('We could not reach MyKaunsel. Check your internet connection and try again.'), findsOneWidget);

      await tester.tap(find.widgetWithText(TextButton, 'Post'));
      await tester.pumpAndSettle();

      expect(feed.creates.length, 2);
      expect(feed.creates[0].clientId, feed.creates[1].clientId);
      expect(find.text('Try me'), findsOneWidget); // now in the feed
    });

    testWidgets('the daily limit is said in plain words', (tester) async {
      final feed = FakeFeedApi()..createHandler = (_, _) async => throw const RateLimitedFailure(retryAfterSeconds: 40000);
      await openFeed(tester, feed);
      await tester.tap(find.widgetWithText(FilledButton, 'New post'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'One more');
      await tester.pump();
      await tester.tap(find.widgetWithText(TextButton, 'Post'));
      await tester.pumpAndSettle();

      expect(find.text("You have reached today's limit of posts. Please come back tomorrow."), findsOneWidget);
    });

    testWidgets('words that may mean danger are still shared, and a calm card shows where to get help', (tester) async {
      final opened = <Uri>[];
      final feed = FakeFeedApi()..flagNext = true;
      await openFeed(tester, feed, opened: opened);
      await tester.tap(find.widgetWithText(FilledButton, 'New post'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'I cannot go on');
      await tester.pump();
      await tester.tap(find.widgetWithText(TextButton, 'Post'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 600)); // (the spinner behind the card keeps turning, so nothing "settles")

      expect(find.text('You are not alone'), findsOneWidget);
      expect(feed.posts.single.text, 'I cannot go on');

      await tester.tap(find.text('Call HEAL 15555'));
      await tester.pump();
      expect(opened.single.toString(), 'tel:15555');

      await tester.tap(find.text('OK, continue'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pumpAndSettle();

      expect(find.text('I cannot go on'), findsOneWidget);
    });
  });

  group('a post and its comments', () {
    testWidgets('shows the whole post, comments oldest first, and a badge on a counselors reply', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'Hard week', comments: 2)]);
      feed.commentsByPost['p1'] = [
        aFeedComment('c1', text: 'Hang in there', name: 'Aiman', minutesAgo: 4),
        aFeedComment('c2', text: 'You are not alone', name: 'Dr Hana', counselor: true, minutesAgo: 1),
      ];
      await openFeed(tester, feed);

      await tester.tap(find.text('Hard week'));
      await tester.pumpAndSettle();

      expect(find.text('Comments'), findsOneWidget);
      expect(tester.getTopLeft(find.text('Hang in there')).dy, lessThan(tester.getTopLeft(find.text('You are not alone')).dy));
      expect(find.text('Counselor'), findsOneWidget);
      expect(find.byType(TextField), findsOneWidget); // the box stays at the bottom
    });

    testWidgets('says when there are no comments yet', (tester) async {
      await openFeed(tester, FakeFeedApi(posts: [aFeedPost('p1', text: 'Quiet')]), path: AppRoutes.feedPostPath('p1'));

      expect(find.text('No comments yet. Be the first to reply.'), findsOneWidget);
    });

    testWidgets('a comment is added at the bottom, empties the box and raises the count in the feed', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'Hello')]);
      await openFeed(tester, feed, path: AppRoutes.feedPostPath('p1'));

      expect(tester.widget<IconButton>(find.widgetWithIcon(IconButton, Icons.send)).onPressed, isNull);

      await tester.enterText(find.byType(TextField), 'Welcome');
      await tester.pump();
      await tester.tap(find.byTooltip('Send comment'));
      await tester.pumpAndSettle();

      expect(find.text('Welcome'), findsOneWidget);
      expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, '');
      expect(find.text('1 comment'), findsOneWidget);
    });

    testWidgets('a comment that did not go keeps its words and goes again with the same client id', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1')]);
      var first = true;
      feed.commentHandler = (post, text, key) async {
        if (first) {
          first = false;
          throw const ServerFailure();
        }

        return FeedWrite(item: aFeedComment('c9', text: text, mine: true));
      };
      await openFeed(tester, feed, path: AppRoutes.feedPostPath('p1'));

      await tester.enterText(find.byType(TextField), 'Try');
      await tester.pump();
      await tester.tap(find.byTooltip('Send comment'));
      await tester.pumpAndSettle();

      expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, 'Try');

      await tester.tap(find.byTooltip('Send comment'));
      await tester.pumpAndSettle();

      expect(feed.commentCreates.length, 2);
      expect(feed.commentCreates[0].clientId, feed.commentCreates[1].clientId);
      expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, '');
    });

    testWidgets('a flagged comment is shared and the help card is shown', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1')])..flagNext = true;
      await openFeed(tester, feed, path: AppRoutes.feedPostPath('p1'));

      await tester.enterText(find.byType(TextField), 'I am done');
      await tester.pump();
      await tester.tap(find.byTooltip('Send comment'));
      await tester.pumpAndSettle();

      expect(find.text('You are not alone'), findsOneWidget);
      expect(feed.commentsByPost['p1']!.single.text, 'I am done');
    });

    testWidgets('a person deletes their own comment after confirming', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', comments: 1)]);
      feed.commentsByPost['p1'] = [aFeedComment('c1', text: 'Oops', name: 'Siti Aminah', mine: true)];
      await openFeed(tester, feed, path: AppRoutes.feedPostPath('p1'));

      await tester.tap(find.byTooltip('Comment options'));
      await tester.pumpAndSettle();

      expect(find.text('Report comment'), findsNothing);

      await tester.tap(find.text('Delete comment'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Delete'));
      await tester.pumpAndSettle();

      expect(find.text('Oops'), findsNothing);
      expect(feed.calls, contains('deleteComment:c1'));
    });

    testWidgets('a post that is gone says so', (tester) async {
      await openFeed(tester, FakeFeedApi(), path: AppRoutes.feedPostPath('nope'));

      expect(find.text('This post is no longer available'), findsOneWidget);
    });

    testWidgets('liking inside the post is shown in the feed too', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'Share')]);
      final container = await openFeed(tester, feed, path: AppRoutes.feedPostPath('p1'));

      await tester.tap(find.text('No likes'));
      await tester.pumpAndSettle();

      container.read(appRouterProvider).pop();
      await tester.pumpAndSettle();

      expect(find.text('1 like'), findsOneWidget);
    });
  });

  group('the menu', () {
    testWidgets('on my own post: edit and delete, and no report or mute', (tester) async {
      await openFeed(tester, FakeFeedApi(posts: [aFeedPost('p1', text: 'Mine', name: 'Siti Aminah', mine: true)]));
      await openPostMenuOn(tester, 'Mine');

      expect(find.text('Edit post'), findsOneWidget);
      expect(find.text('Delete post'), findsOneWidget);
      expect(find.text('Report post'), findsNothing);
      expect(find.textContaining('Mute'), findsNothing);
    });

    testWidgets('on someone elses: report and mute by name, no edit or delete', (tester) async {
      await openFeed(tester, FakeFeedApi(posts: [aFeedPost('p1', text: 'Theirs', name: 'Aiman')]));
      await openPostMenuOn(tester, 'Theirs');

      expect(find.text('Report post'), findsOneWidget);
      expect(find.text('Mute Aiman'), findsOneWidget);
      expect(find.text('Edit post'), findsNothing);
      expect(find.text('Delete post'), findsNothing);
    });

    testWidgets('editing changes the words and marks the post edited', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'First try', mine: true)]);
      await openFeed(tester, feed);
      await openPostMenuOn(tester, 'First try');

      await tester.tap(find.text('Edit post'));
      await tester.pumpAndSettle();

      expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, 'First try');

      await tester.enterText(find.byType(TextField), 'Better words');
      await tester.pump();
      await tester.tap(find.widgetWithText(TextButton, 'Save'));
      await tester.pumpAndSettle();

      expect(find.text('Better words'), findsOneWidget);
      expect(find.textContaining('edited'), findsOneWidget);
      expect(feed.calls, contains('edit:p1'));
    });

    testWidgets('deleting asks first, then takes the post out of the feed', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'Remove me', mine: true), aFeedPost('p2', text: 'Stay')]);
      await openFeed(tester, feed);
      await openPostMenuOn(tester, 'Remove me');

      await tester.tap(find.text('Delete post'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();

      expect(find.text('Remove me'), findsOneWidget);
      expect(feed.calls.where((c) => c.startsWith('delete')), isEmpty);

      await openPostMenuOn(tester, 'Remove me');
      await tester.tap(find.text('Delete post'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Delete'));
      await tester.pumpAndSettle();

      expect(find.text('Remove me'), findsNothing);
      expect(find.text('Stay'), findsOneWidget);
    });

    testWidgets('reporting offers the five reasons and sends the one chosen', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'Rude post')]);
      await openFeed(tester, feed);
      await openPostMenuOn(tester, 'Rude post');

      await tester.tap(find.text('Report post'));
      await tester.pumpAndSettle();

      for (final reason in ['Spam', 'Harassment or bullying', 'Self-harm or someone in danger', 'Inappropriate content', 'Something else']) {
        expect(find.text(reason), findsOneWidget);
      }

      await tester.tap(find.text('Harassment or bullying'));
      await tester.pumpAndSettle();

      expect(feed.reports.single, (id: 'p1', isComment: false, reason: 'harassment'));
      expect(find.text('Thank you. Our team will look at it.'), findsOneWidget);
    });

    testWidgets('reporting a comment sends it as a comment', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', comments: 1)]);
      feed.commentsByPost['p1'] = [aFeedComment('c1', text: 'Mean words')];
      await openFeed(tester, feed, path: AppRoutes.feedPostPath('p1'));

      await tester.tap(find.byTooltip('Comment options'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Report comment'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Spam'));
      await tester.pumpAndSettle();

      expect(feed.reports.single, (id: 'c1', isComment: true, reason: 'spam'));
    });

    testWidgets('muting asks first, then their posts leave the feed', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p1', text: 'Noisy', name: 'Aiman'), aFeedPost('p2', text: 'Calm', name: 'Hana')]);
      await openFeed(tester, feed);
      await openPostMenuOn(tester, 'Noisy');

      await tester.tap(find.text('Mute Aiman'));
      await tester.pumpAndSettle();

      expect(find.text('Mute Aiman?'), findsOneWidget);

      await tester.tap(find.widgetWithText(FilledButton, 'Mute'));
      await tester.pumpAndSettle();

      expect(find.text('Noisy'), findsNothing);
      expect(find.text('Calm'), findsOneWidget);
      expect(find.text('Aiman is muted.'), findsOneWidget);
    });
  });

  group('muted people', () {
    testWidgets('lists them and unmuting brings their posts back', (tester) async {
      final feed = FakeFeedApi(posts: [aFeedPost('p2', text: 'Calm', name: 'Hana')]);
      feed.mutedPeople.add(const FeedMuted(id: 'm1', name: 'Aiman'));
      await openFeed(tester, feed);

      await tester.tap(find.byTooltip('Muted people'));
      await tester.pumpAndSettle();

      expect(find.text('Aiman'), findsOneWidget);

      feed.posts.insert(0, aFeedPost('p1', text: 'Noisy', name: 'Aiman'));
      await tester.tap(find.text('Unmute'));
      await tester.pumpAndSettle();

      expect(find.text('Aiman'), findsNothing);
      expect(find.text('No one is muted'), findsOneWidget);
      expect(feed.calls, contains('unmute:m1'));
    });

    testWidgets('says so when no one is muted', (tester) async {
      await openFeed(tester, FakeFeedApi(), path: AppRoutes.feedMuted);

      expect(find.text('No one is muted'), findsOneWidget);
    });

    testWidgets('a failure can be tried again', (tester) async {
      final feed = FakeFeedApi()..mutesHandler = () async => throw const NetworkFailure();
      await openFeed(tester, feed, path: AppRoutes.feedMuted);

      expect(find.text('Try again'), findsOneWidget);

      feed.mutesHandler = null;
      await tester.tap(find.text('Try again'));
      await tester.pumpAndSettle();

      expect(find.text('No one is muted'), findsOneWidget);
    });
  });

  testWidgets('the bottom bar has Feed and no Find tab, and Find still opens from Home', (tester) async {
    await openFeed(tester, FakeFeedApi(), path: AppRoutes.home);

    final bar = find.byType(NavigationBar);
    expect(find.descendant(of: bar, matching: find.text('Feed')), findsOneWidget);
    expect(find.descendant(of: bar, matching: find.text('Find')), findsNothing);

    await tester.tap(find.text('Find a counselor'));
    await tester.pumpAndSettle();

    expect(find.byType(TextField), findsWidgets);
    expect(tester.widget<NavigationBar>(bar).selectedIndex, 0);
  });
}
