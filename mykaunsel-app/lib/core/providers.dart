import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/account/data/account_api.dart';
import '../features/auth/data/auth_api.dart';
import '../features/booking/data/availability_api.dart';
import '../features/booking/data/booking_api.dart';
import '../features/counselors/data/counselor_api.dart';
import '../features/platform_status/data/platform_status_api.dart';
import 'config/app_config.dart';
import 'config/app_info.dart';
import 'error/crash_reporter.dart';
import 'network/api_client.dart';
import 'session/session_controller.dart';
import 'session/session_state.dart';
import 'storage/token_storage.dart';

/// Things that only exist once the app has started (read from the phone) are overridden in `main.dart` and in tests.
final appConfigProvider = Provider<AppConfig>((ref) => throw UnimplementedError('appConfigProvider must be overridden'));

final appInfoProvider = Provider<AppInfo>((ref) => throw UnimplementedError('appInfoProvider must be overridden'));

final tokenStorageProvider = Provider<TokenStorage>((ref) => throw UnimplementedError('tokenStorageProvider must be overridden'));

final crashReporterProvider = Provider<CrashReporter>((ref) => const NoopCrashReporter());

/// One client for the whole app. Every failed call is also passed to the session, which reacts in one place.
final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient(
    config: ref.watch(appConfigProvider),
    info: ref.watch(appInfoProvider),
    readToken: () => ref.read(tokenStorageProvider).read(),
    onFailure: (failure, {required hadToken}) {
      ref.read(sessionControllerProvider.notifier).handleFailure(failure, hadToken: hadToken);
    },
  );
});

final platformStatusApiProvider = Provider<PlatformStatusApi>((ref) => HttpPlatformStatusApi(ref.watch(apiClientProvider)));

final authApiProvider = Provider<AuthApi>((ref) => HttpAuthApi(ref.watch(apiClientProvider)));

final accountApiProvider = Provider<AccountApi>((ref) => HttpAccountApi(ref.watch(apiClientProvider)));

final counselorApiProvider = Provider<CounselorApi>((ref) => HttpCounselorApi(ref.watch(apiClientProvider)));

final availabilityApiProvider = Provider<AvailabilityApi>((ref) => HttpAvailabilityApi(ref.watch(apiClientProvider)));

final bookingApiProvider = Provider<BookingApi>((ref) => HttpBookingApi(ref.watch(apiClientProvider)));

/// "What time is it now" for anything that says "today" or "tomorrow"; tests replace it.
final clockProvider = Provider<DateTime Function()>((ref) => DateTime.now);

/// Who is signed in (null when nobody is). Whatever depends on WHO is looking, such as the counselor list (a member of a
/// closed organization sees more than a stranger), watches this so it starts again from nothing when the person changes,
/// and one person's results are never shown to the next.
final currentUserIdProvider = Provider<int?>((ref) {
  final session = ref.watch(sessionControllerProvider);

  return session is SessionSignedIn ? session.user.id : null;
});
