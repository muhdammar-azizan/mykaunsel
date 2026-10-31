import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/notification_preference.dart';

class NotificationSettingsState {
  const NotificationSettingsState({this.items = const [], this.loading = true, this.failure, this.saving, this.saveFailed = false});

  final List<NotificationPreference> items;
  final bool loading;
  final AppFailure? failure;

  /// The key being saved now, if any.
  final String? saving;

  /// The last change did not go through (the switch shows what the server has).
  final bool saveFailed;

  NotificationSettingsState copyWith({List<NotificationPreference>? items, bool? loading, AppFailure? failure, bool clearFailure = false, String? saving, bool clearSaving = false, bool? saveFailed}) =>
      NotificationSettingsState(
        items: items ?? this.items,
        loading: loading ?? this.loading,
        failure: clearFailure ? null : (failure ?? this.failure),
        saving: clearSaving ? null : (saving ?? this.saving),
        saveFailed: saveFailed ?? this.saveFailed,
      );
}

final notificationSettingsProvider = NotifierProvider.autoDispose<NotificationSettingsController, NotificationSettingsState>(NotificationSettingsController.new);

/// The e-mails a person can switch off. Every switch shows what the SERVER has: it moves only once the server has answered, never ahead of it.
class NotificationSettingsController extends Notifier<NotificationSettingsState> {
  @override
  NotificationSettingsState build() {
    ref.watch(currentUserIdProvider);
    Future.microtask(load);

    return const NotificationSettingsState();
  }

  Future<void> load() async {
    state = state.copyWith(loading: state.items.isEmpty, clearFailure: true);

    try {
      final items = await ref.read(accountApiProvider).notificationPreferences();

      if (ref.mounted) state = state.copyWith(items: items, loading: false);
    } on AppFailure catch (failure) {
      if (ref.mounted) state = state.copyWith(loading: false, failure: failure);
    }
  }

  Future<void> set(String key, bool enabled) async {
    if (state.saving != null) return;

    state = state.copyWith(saving: key, saveFailed: false);

    try {
      final items = await ref.read(accountApiProvider).setNotificationPreference(key, enabled);

      if (ref.mounted) state = state.copyWith(items: items, clearSaving: true);
    } on AppFailure {
      if (ref.mounted) state = state.copyWith(clearSaving: true, saveFailed: true);
    }
  }
}

/// Account > Notifications. E-mail only: MyKaunsel sends no push notifications and shows no in-app notices.
class NotificationSettingsScreen extends ConsumerWidget {
  const NotificationSettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final state = ref.watch(notificationSettingsProvider);
    final controller = ref.read(notificationSettingsProvider.notifier);

    Widget body;

    if (state.loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (state.failure != null && state.items.isEmpty) {
      body = Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(failureMessage(l10n, state.failure!), textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.l),
            FilledButton(onPressed: controller.load, child: Text(l10n.tryAgain)),
          ]),
        ),
      );
    } else {
      body = ListView(
        padding: const EdgeInsets.all(AppSpacing.l),
        children: [
          Text(l10n.notificationsIntro),
          const SizedBox(height: AppSpacing.m),
          if (state.saveFailed) Padding(padding: const EdgeInsets.only(bottom: AppSpacing.s), child: Semantics(liveRegion: true, child: Text(l10n.notificationsSaveFailed, style: TextStyle(color: Theme.of(context).colorScheme.error)))),
          for (final item in state.items)
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(_title(l10n, item)),
              subtitle: Text(_body(l10n, item)),
              value: item.enabled,
              onChanged: state.saving == null ? (on) => controller.set(item.key, on) : null,
            ),
        ],
      );
    }

    return Scaffold(appBar: AppBar(title: Text(l10n.notificationsTitle)), body: SafeArea(child: body));
  }

  // The words are the app's own (so they can be translated); a choice this version does not know yet shows what the server called it.
  String _title(AppLocalizations l10n, NotificationPreference item) => item.key == 'booking_emails' ? l10n.notificationBookingsTitle : item.label;

  String _body(AppLocalizations l10n, NotificationPreference item) => item.key == 'booking_emails' ? l10n.notificationBookingsBody : item.description;
}
