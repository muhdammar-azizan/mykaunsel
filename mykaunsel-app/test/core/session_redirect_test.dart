import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/core/session/session_state.dart';

import '../support/fakes.dart';

void main() {
  test('starting always shows the splash', () {
    expect(sessionRedirect(const SessionStarting(), AppRoutes.home), AppRoutes.splash);
    expect(sessionRedirect(const SessionStarting(), AppRoutes.splash), isNull);
  });

  test('maintenance, update and startup problems lock the person onto their screen, wherever they were', () {
    for (final location in [AppRoutes.home, AppRoutes.signIn, AppRoutes.splash]) {
      expect(sessionRedirect(const SessionMaintenance('x'), location), AppRoutes.maintenance);
      expect(sessionRedirect(const SessionUpdateRequired(), location), AppRoutes.updateRequired);
      expect(sessionRedirect(const SessionStartupFailed(NetworkFailure()), location), AppRoutes.startupError);
    }

    expect(sessionRedirect(const SessionMaintenance('x'), AppRoutes.maintenance), isNull);
  });

  test('signed out → sign-in, and the signed-out pages are allowed', () {
    expect(sessionRedirect(const SessionSignedOut(), AppRoutes.splash), AppRoutes.signIn);
    expect(sessionRedirect(const SessionSignedOut(), AppRoutes.home), AppRoutes.signIn);
    expect(sessionRedirect(const SessionSignedOut(), AppRoutes.maintenance), AppRoutes.signIn);
    expect(sessionRedirect(const SessionSignedOut(), AppRoutes.signIn), isNull);
    expect(sessionRedirect(const SessionSignedOut(), AppRoutes.twoFactor), isNull);
    expect(sessionRedirect(const SessionSignedOut(), AppRoutes.verifyEmail), isNull);
    expect(sessionRedirect(const SessionSignedOut(), AppRoutes.signUp), isNull);
    expect(sessionRedirect(const SessionSignedOut(), AppRoutes.forgotPassword), isNull);
  });

  test('signed in → home, and a signed-in person can never sit on a gate or sign-in page', () {
    final signedIn = const SessionSignedIn(testUser);

    expect(sessionRedirect(signedIn, AppRoutes.splash), AppRoutes.home);
    expect(sessionRedirect(signedIn, AppRoutes.signIn), AppRoutes.home);
    expect(sessionRedirect(signedIn, AppRoutes.maintenance), AppRoutes.home);
    expect(sessionRedirect(signedIn, AppRoutes.startupError), AppRoutes.home);
    expect(sessionRedirect(signedIn, AppRoutes.signUp), AppRoutes.home);
    expect(sessionRedirect(signedIn, AppRoutes.forgotPassword), AppRoutes.home);
    expect(sessionRedirect(signedIn, AppRoutes.twoFactor), AppRoutes.home);
    expect(sessionRedirect(signedIn, AppRoutes.home), isNull);
  });

  test('other pages inside the app are left alone for a signed-in person', () {
    expect(sessionRedirect(const SessionSignedIn(testUser), '/bookings/12'), isNull);
  });

  test('the code page and the check-your-email page need the step before them', () {
    expect(pendingRedirect(AppRoutes.twoFactor, hasChallenge: false, hasEmail: true), AppRoutes.signIn);
    expect(pendingRedirect(AppRoutes.twoFactor, hasChallenge: true, hasEmail: false), isNull);
    expect(pendingRedirect(AppRoutes.verifyEmail, hasChallenge: true, hasEmail: false), AppRoutes.signIn);
    expect(pendingRedirect(AppRoutes.verifyEmail, hasChallenge: false, hasEmail: true), isNull);
    expect(pendingRedirect(AppRoutes.signIn, hasChallenge: false, hasEmail: false), isNull);
  });
}
