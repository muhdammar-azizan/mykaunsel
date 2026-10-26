import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/session/session_controller.dart';
import 'package:mykaunsel/core/session/session_state.dart';

import '../support/fakes.dart';

void main() {
  late FakeTokenStorage storage;
  late FakePlatformStatusApi statusApi;
  late FakeAuthApi authApi;

  ProviderContainer build() {
    final container = ProviderContainer(overrides: testOverrides(statusApi: statusApi, authApi: authApi, storage: storage));
    addTearDown(container.dispose);

    return container;
  }

  /// Builds the controller and waits until start() has finished.
  Future<ProviderContainer> started() async {
    final container = build();
    container.read(sessionControllerProvider);
    await pump();

    return container;
  }

  setUp(() {
    storage = FakeTokenStorage();
    statusApi = FakePlatformStatusApi(() async => status());
    authApi = FakeAuthApi();
  });

  group('starting up', () {
    test('begins in the starting state', () {
      final container = build();

      expect(container.read(sessionControllerProvider), isA<SessionStarting>());
    });

    test('no saved token → signed out, and nobody is asked who they are', () async {
      var meCalls = 0;
      authApi.meHandler = () async {
        meCalls++;

        return testUser;
      };

      final container = await started();

      expect(container.read(sessionControllerProvider), isA<SessionSignedOut>());
      expect((container.read(sessionControllerProvider) as SessionSignedOut).reason, SignedOutReason.none);
      expect(meCalls, 0);
    });

    test('a saved token that still works → signed in', () async {
      storage.token = '5|mkp_abc';

      final container = await started();

      final state = container.read(sessionControllerProvider);
      expect(state, isA<SessionSignedIn>());
      expect((state as SessionSignedIn).user.name, 'Siti Aminah');
    });

    test('a saved token the server no longer accepts → token removed, signed out with "session expired"', () async {
      storage.token = 'old';
      authApi.meHandler = () async => throw const UnauthorizedFailure();

      final container = await started();

      expect((container.read(sessionControllerProvider) as SessionSignedOut).reason, SignedOutReason.sessionExpired);
      expect(storage.token, isNull);
    });

    test('maintenance on → maintenance, and the token is not even looked at', () async {
      storage.token = 'keep-me';
      statusApi.handler = () async => status(maintenance: true, maintenanceMessage: 'Back at 6 PM.');

      final container = await started();

      expect((container.read(sessionControllerProvider) as SessionMaintenance).message, 'Back at 6 PM.');
      expect(storage.token, 'keep-me');
    });

    test('app too old → update required, with the store link', () async {
      statusApi.handler = () async => status(updateRequired: true, storeUrl: 'https://play.example/app');

      final container = await started();

      final state = container.read(sessionControllerProvider) as SessionUpdateRequired;
      expect(state.storeUrl, 'https://play.example/app');
      expect(state.message, 'Please update.');
    });

    test('maintenance answered as 503 instead of a status → still maintenance', () async {
      statusApi.handler = () async => throw const MaintenanceFailure('Down.');

      final container = await started();

      expect((container.read(sessionControllerProvider) as SessionMaintenance).message, 'Down.');
    });

    test('426 on the status call → update required', () async {
      statusApi.handler = () async => throw const UpdateRequiredFailure('Old.', storeUrl: 'https://x.example');

      final container = await started();

      expect(container.read(sessionControllerProvider), isA<SessionUpdateRequired>());
    });

    test('no connection → startup failed, and the saved token is kept', () async {
      storage.token = '5|mkp_abc';
      statusApi.handler = () async => throw const NetworkFailure();

      final container = await started();

      expect((container.read(sessionControllerProvider) as SessionStartupFailed).failure, isA<NetworkFailure>());
      expect(storage.token, '5|mkp_abc');
    });

    test('the server failing while checking the token keeps the token too', () async {
      storage.token = '5|mkp_abc';
      authApi.meHandler = () async => throw const ServerFailure(statusCode: 500);

      final container = await started();

      expect(container.read(sessionControllerProvider), isA<SessionStartupFailed>());
      expect(storage.token, '5|mkp_abc');
    });

    test('"try again" starts over and can succeed', () async {
      statusApi.handler = () async => throw const NetworkFailure();
      final container = await started();
      expect(container.read(sessionControllerProvider), isA<SessionStartupFailed>());

      statusApi.handler = () async => status();
      await container.read(sessionControllerProvider.notifier).start();

      expect(container.read(sessionControllerProvider), isA<SessionSignedOut>());
    });
  });

  group('while the app is open', () {
    test('signing in saves the token and signs in', () async {
      final container = await started();

      await container.read(sessionControllerProvider.notifier).signIn(token: '9|mkp_new', user: testUser);

      expect(storage.token, '9|mkp_new');
      expect(container.read(sessionControllerProvider), isA<SessionSignedIn>());
    });

    test('signing out removes the token and tells the server', () async {
      storage.token = 't';
      final container = await started();

      await container.read(sessionControllerProvider.notifier).signOut();

      expect(storage.token, isNull);
      expect(authApi.logoutCalls, 1);
      expect((container.read(sessionControllerProvider) as SessionSignedOut).reason, SignedOutReason.signedOutByUser);
    });

    test('signing out works without a connection', () async {
      storage.token = 't';
      authApi.logoutHandler = () async => throw const NetworkFailure();
      final container = await started();

      await container.read(sessionControllerProvider.notifier).signOut();

      expect(storage.token, isNull);
      expect(container.read(sessionControllerProvider), isA<SessionSignedOut>());
    });

    test('a 401 on a signed-in person ends the session (once, even if several calls fail together)', () async {
      storage.token = 't';
      final container = await started();
      final controller = container.read(sessionControllerProvider.notifier);

      controller.handleFailure(const UnauthorizedFailure(), hadToken: true);
      controller.handleFailure(const UnauthorizedFailure(), hadToken: true);
      await pump();

      expect((container.read(sessionControllerProvider) as SessionSignedOut).reason, SignedOutReason.sessionExpired);
      expect(storage.token, isNull);
      expect(storage.clears, 1);
    });

    test('a 401 that did not carry a token (a wrong password) changes nothing', () async {
      final container = await started();

      container.read(sessionControllerProvider.notifier).handleFailure(const UnauthorizedFailure(), hadToken: false);

      expect((container.read(sessionControllerProvider) as SessionSignedOut).reason, SignedOutReason.none);
    });

    test('maintenance or "update needed" arriving mid-session switches to those screens', () async {
      storage.token = 't';
      final container = await started();
      final controller = container.read(sessionControllerProvider.notifier);

      controller.handleFailure(const MaintenanceFailure('Down.'), hadToken: true);
      expect(container.read(sessionControllerProvider), isA<SessionMaintenance>());

      controller.handleFailure(const UpdateRequiredFailure('Old.'), hadToken: true);
      expect(container.read(sessionControllerProvider), isA<SessionUpdateRequired>());
    });

    test('other failures (validation, rate limit, offline) are left to the screen that asked', () async {
      storage.token = 't';
      final container = await started();
      final controller = container.read(sessionControllerProvider.notifier);

      controller.handleFailure(const ValidationFailure('Bad.'), hadToken: true);
      controller.handleFailure(const RateLimitedFailure(), hadToken: true);
      controller.handleFailure(const NetworkFailure(), hadToken: true);

      expect(container.read(sessionControllerProvider), isA<SessionSignedIn>());
    });

    test('failures during start-up are handled by start() itself, not twice', () async {
      final container = build();
      container.read(sessionControllerProvider.notifier).handleFailure(const MaintenanceFailure('Down.'), hadToken: false);

      expect(container.read(sessionControllerProvider), isA<SessionStarting>());
    });
  });
}

/// Lets the controller's start() (several awaits) run to the end.
Future<void> pump() async {
  for (var i = 0; i < 20; i++) {
    await Future<void>.delayed(Duration.zero);
  }
}
