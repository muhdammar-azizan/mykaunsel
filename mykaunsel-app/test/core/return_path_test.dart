import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/router/app_router.dart';
import 'package:mykaunsel/core/session/session_state.dart';
import 'package:mykaunsel/features/auth/presentation/pending_auth.dart';

import '../support/fakes.dart';

void main() {
  group('isSafeReturnPath: only a booking route with plain ids', () {
    test('accepts the picker and the confirm screen', () {
      expect(isSafeReturnPath('/book/01hxabc'), isTrue);
      expect(isSafeReturnPath('/book/01hxabc/confirm?slot=01hxdef'), isTrue);
      expect(isSafeReturnPath(AppRoutes.confirmBookingPath('c-1', 's_2')), isTrue);
    });

    test('rejects anything else: nothing, other routes, external addresses, tricks', () {
      for (final bad in [
        null,
        '',
        '/',
        '/home',
        '/account/password',
        'https://evil.example/book/x',
        '//evil.example/book/x',
        'http://evil.example',
        'javascript:alert(1)',
        '/book/',
        '/book/../account',
        '/book/x/../../account',
        r'/book/x\confirm',
        '/book/x/confirm',
        '/book/x/confirm?slot=',
        '/book/x/confirm?slot=a&next=https://evil.example',
        '/book/x/confirm?slot=a b',
        '/book/x/confirm?slot=a#frag',
        '/book/x/confirm?slot=a%2e%2e',
        '/book/x?next=/home',
        '/book/${'a' * 65}',
        '/book/x/confirm?slot=${'a' * 201}',
      ]) {
        expect(isSafeReturnPath(bad), isFalse, reason: '$bad');
      }
    });
  });

  group('after signing in again', () {
    final signedIn = SessionSignedIn(testUser);

    test('a safe destination is used instead of Home', () {
      expect(sessionRedirect(signedIn, AppRoutes.signIn, returnTo: '/book/c1/confirm?slot=s1'), '/book/c1/confirm?slot=s1');
      expect(sessionRedirect(signedIn, AppRoutes.twoFactor, returnTo: '/book/c1'), '/book/c1');
    });

    test('without one the person goes Home, as before', () {
      expect(sessionRedirect(signedIn, AppRoutes.signIn), AppRoutes.home);
    });

    test('it only matters on the sign-in pages: a signed-in person elsewhere stays where they are', () {
      expect(sessionRedirect(signedIn, AppRoutes.counselors, returnTo: '/book/c1'), isNull);
    });
  });
}
