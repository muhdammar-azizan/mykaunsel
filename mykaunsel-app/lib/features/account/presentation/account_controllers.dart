import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../shared/submit_state.dart';
import '../domain/signed_in_device.dart';

/// Time zones offered in the app (the platform serves Malaysia). The server accepts every Asian zone and UTC.
const supportedTimezones = <String, String>{
  'Asia/Kuala_Lumpur': 'Malaysia (Kuala Lumpur)',
  'Asia/Kuching': 'Malaysia (Kuching)',
  'Asia/Singapore': 'Singapore',
  'Asia/Brunei': 'Brunei',
  'Asia/Jakarta': 'Indonesia (Jakarta)',
  'Asia/Bangkok': 'Thailand (Bangkok)',
  'Asia/Manila': 'Philippines (Manila)',
  'UTC': 'UTC',
};

// ---- profile ----------------------------------------------------------------------------

final profileControllerProvider = NotifierProvider.autoDispose<ProfileController, SubmitState>(ProfileController.new);

class ProfileController extends Notifier<SubmitState> {
  @override
  SubmitState build() => SubmitState.idle;

  /// True when saved. The whole app (greeting, account tab) then shows the new name.
  Future<bool> save({required String name, required String timezone}) async {
    if (state.submitting) {
      return false;
    }

    state = SubmitState.busy;

    try {
      final user = await ref.read(accountApiProvider).updateProfile(name: name.trim(), timezone: timezone);
      ref.read(sessionControllerProvider.notifier).updateUser(user);
      if (ref.mounted) state = SubmitState.idle;

      return true;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = SubmitState(failure: failure);

      return false;
    }
  }
}

// ---- password ---------------------------------------------------------------------------

final passwordControllerProvider = NotifierProvider.autoDispose<PasswordController, SubmitState>(PasswordController.new);

class PasswordController extends Notifier<SubmitState> {
  @override
  SubmitState build() => SubmitState.idle;

  /// True when changed. The server has then signed every other phone out; this one stays signed in.
  Future<bool> change({required String currentPassword, required String newPassword}) async {
    if (state.submitting) {
      return false;
    }

    state = SubmitState.busy;

    try {
      await ref.read(accountApiProvider).changePassword(currentPassword: currentPassword, newPassword: newPassword);
      if (ref.mounted) state = SubmitState.idle;

      return true;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = SubmitState(failure: failure);

      return false;
    }
  }
}

// ---- devices ----------------------------------------------------------------------------

/// The phones signed in to this account. Asked again for the next person who signs in.
final devicesProvider = FutureProvider.autoDispose<List<SignedInDevice>>((ref) {
  ref.watch(currentUserIdProvider);

  return ref.read(accountApiProvider).devices();
});

/// Signs another phone out (asks for the password) and refreshes the list.
Future<void> revokeDevice(WidgetRef ref, int id, String currentPassword) async {
  await ref.read(accountApiProvider).revokeDevice(id, currentPassword: currentPassword);
  ref.invalidate(devicesProvider);
}
