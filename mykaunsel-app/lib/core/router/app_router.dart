import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/forgot_password_screen.dart';
import '../../features/auth/presentation/pending_auth.dart';
import '../../features/auth/presentation/sign_in_screen.dart';
import '../../features/auth/presentation/sign_up_screen.dart';
import '../../features/auth/presentation/two_factor_screen.dart';
import '../../features/auth/presentation/verify_email_screen.dart';
import '../../features/account/presentation/account_screen.dart';
import '../../features/account/presentation/change_password_screen.dart';
import '../../features/account/presentation/devices_screen.dart';
import '../../features/account/presentation/profile_screen.dart';
import '../../features/booking/domain/booking_preview.dart';
import '../../features/booking/presentation/booking_args.dart';
import '../../features/booking/presentation/booking_confirmed_placeholder.dart';
import '../../features/booking/presentation/confirm_booking_screen.dart';
import '../../features/booking/presentation/pick_slot_screen.dart';
import '../../features/counselors/presentation/counselor_detail_screen.dart';
import '../../features/counselors/presentation/counselors_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/startup/presentation/maintenance_screen.dart';
import '../../features/startup/presentation/splash_screen.dart';
import '../../features/startup/presentation/startup_error_screen.dart';
import '../../features/startup/presentation/update_required_screen.dart';
import '../../shared/widgets/app_shell.dart';
import '../session/session_controller.dart';
import '../session/session_state.dart';

abstract final class AppRoutes {
  static const splash = '/';
  static const maintenance = '/maintenance';
  static const updateRequired = '/update-required';
  static const startupError = '/startup-error';
  static const signIn = '/sign-in';
  static const twoFactor = '/two-factor';
  static const verifyEmail = '/verify-email';
  static const signUp = '/sign-up';
  static const forgotPassword = '/forgot-password';
  static const home = '/home';
  static const counselors = '/counselors';
  static const account = '/account';
  static const accountProfile = '/account/profile';
  static const accountPassword = '/account/password';
  static const accountDevices = '/account/devices';

  static String counselorPath(String id) => '/counselors/${Uri.encodeComponent(id)}';

  static String pickSlotPath(String id) => '/book/${Uri.encodeComponent(id)}';

  /// The slot id is in the address so the screen can restore itself after a sign-in. It is a random public id, nothing private.
  static String confirmBookingPath(String id, String slotId) => '/book/${Uri.encodeComponent(id)}/confirm?slot=${Uri.encodeQueryComponent(slotId)}';

  static const bookingConfirmed = '/booking-confirmed';

  /// Pages a signed-out person may be on.
  static const signedOutPages = {signIn, twoFactor, verifyEmail, signUp, forgotPassword};

  /// Pages that only exist for a situation (starting, maintenance…); a signed-in person is never left on one.
  static const gatePages = {splash, maintenance, updateRequired, startupError};
}

/// Where the person has to be for this session state, or null when they are already in the right place. [returnTo] (already checked by
/// [isSafeReturnPath]) is where a person goes after signing in again, instead of Home.
/// A plain function, so every rule is unit-tested without any screen.
String? sessionRedirect(SessionState session, String location, {String? returnTo}) {
  final target = switch (session) {
    SessionStarting() => AppRoutes.splash,
    SessionMaintenance() => AppRoutes.maintenance,
    SessionUpdateRequired() => AppRoutes.updateRequired,
    SessionStartupFailed() => AppRoutes.startupError,
    SessionSignedOut() => AppRoutes.signedOutPages.contains(location) ? location : AppRoutes.signIn,
    SessionSignedIn() =>
      AppRoutes.gatePages.contains(location) || AppRoutes.signedOutPages.contains(location) ? (returnTo ?? AppRoutes.home) : location,
  };

  return target == location ? null : target;
}

/// The code page and the "check your email" page only make sense right after the step before them. Opened any other
/// way (an old link, the back button after finishing), they send the person to sign-in.
String? pendingRedirect(String location, {required bool hasChallenge, required bool hasEmail}) {
  if (location == AppRoutes.twoFactor && !hasChallenge) {
    return AppRoutes.signIn;
  }

  if (location == AppRoutes.verifyEmail && !hasEmail) {
    return AppRoutes.signIn;
  }

  return null;
}

/// Tells go_router to re-check where the person should be, but ONLY when the KIND of session changes (starting →
/// signed out → signed in…). Changing a name inside a signed-in session must not make the router rebuild its pages (it
/// would undo a "go back" that is in progress).
class SessionRefresh extends ChangeNotifier {
  SessionRefresh(this.state);

  SessionState state;

  void update(SessionState next) {
    final kindChanged = next.runtimeType != state.runtimeType;
    state = next;

    if (kindChanged) {
      notifyListeners();
    }
  }
}

final appRouterProvider = Provider<GoRouter>((ref) {
  final session = SessionRefresh(ref.read(sessionControllerProvider));
  ref.listen<SessionState>(sessionControllerProvider, (_, next) => session.update(next));
  ref.onDispose(session.dispose);

  final router = GoRouter(
    initialLocation: AppRoutes.splash,
    refreshListenable: session,
    redirect: (context, state) {
      final location = state.matchedLocation;
      final current = session.state;

      // The session ended on a booking screen: remember (in memory) where to come back to, once the person has signed in again.
      if (current is SessionSignedOut && current.reason == SignedOutReason.sessionExpired && isSafeReturnPath(state.uri.toString())) {
        final place = state.uri.toString();
        Future.microtask(() => ref.read(returnPathProvider.notifier).set(place));
      }

      final saved = ref.read(returnPathProvider);
      final returnTo = isSafeReturnPath(saved) ? saved : null;
      final target = sessionRedirect(current, location, returnTo: returnTo);

      if (target != null && target == returnTo && current is SessionSignedIn) {
        Future.microtask(() => ref.read(returnPathProvider.notifier).set(null)); // used once
      }

      return target ??
          pendingRedirect(
            location,
            hasChallenge: ref.read(pendingTwoFactorProvider) != null,
            hasEmail: ref.read(pendingVerificationEmailProvider) != null,
          );
    },
    routes: [
      GoRoute(path: AppRoutes.splash, builder: (_, _) => const SplashScreen()),
      GoRoute(path: AppRoutes.maintenance, builder: (_, _) => const MaintenanceScreen()),
      GoRoute(path: AppRoutes.updateRequired, builder: (_, _) => const UpdateRequiredScreen()),
      GoRoute(path: AppRoutes.startupError, builder: (_, _) => const StartupErrorScreen()),
      GoRoute(path: AppRoutes.signIn, builder: (_, _) => const SignInScreen()),
      GoRoute(path: AppRoutes.twoFactor, builder: (_, _) => const TwoFactorScreen()),
      GoRoute(path: AppRoutes.verifyEmail, builder: (_, _) => const VerifyEmailScreen()),
      GoRoute(path: AppRoutes.signUp, builder: (_, _) => const SignUpScreen()),
      GoRoute(path: AppRoutes.forgotPassword, builder: (_, _) => const ForgotPasswordScreen()),
      GoRoute(
        path: AppRoutes.bookingConfirmed,
        redirect: (_, state) => state.extra is CreatedBooking ? null : AppRoutes.home,
        builder: (_, state) => BookingConfirmedPlaceholderScreen(booking: state.extra! as CreatedBooking),
      ),
      // Booking sits on top of the tabs (no bottom bar), so it is a short, focused flow. Counselor details come in `extra`.
      GoRoute(
        path: '/book/:id',
        builder: (_, state) {
          final id = state.pathParameters['id']!;
          final extra = state.extra;

          return PickSlotScreen(counselor: extra is BookingCounselorSummary ? extra : BookingCounselorSummary(id: id, name: ''));
        },
        routes: [
          GoRoute(
            path: 'confirm',
            // Needs the slot's public id in the address (so it can restore itself after a sign-in); without one there is nothing to confirm.
            redirect: (_, state) => RegExp(r'^[A-Za-z0-9_-]{1,64}$').hasMatch(state.uri.queryParameters['slot'] ?? '') ? null : AppRoutes.pickSlotPath(state.pathParameters['id']!),
            builder: (_, state) => ConfirmBookingScreen(
              counselorId: state.pathParameters['id']!,
              slotId: state.uri.queryParameters['slot']!,
              snapshot: state.extra is ConfirmBookingArgs ? state.extra! as ConfirmBookingArgs : null,
            ),
          ),
        ],
      ),
      // The signed-in app: three tabs, each keeping its own place.
      StatefulShellRoute.indexedStack(
        builder: (_, _, navigationShell) => AppShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: AppRoutes.home, builder: (_, _) => const HomeScreen())]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: AppRoutes.counselors,
              builder: (_, _) => const CounselorsScreen(),
              routes: [GoRoute(path: ':id', builder: (_, state) => CounselorDetailScreen(id: state.pathParameters['id']!))],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: AppRoutes.account,
              builder: (_, _) => const AccountScreen(),
              routes: [
                GoRoute(path: 'profile', builder: (_, _) => const ProfileScreen()),
                GoRoute(path: 'password', builder: (_, _) => const ChangePasswordScreen()),
                GoRoute(path: 'devices', builder: (_, _) => const DevicesScreen()),
              ],
            ),
          ]),
        ],
      ),
    ],
  );

  ref.onDispose(router.dispose);

  return router;
});
