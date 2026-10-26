import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/status_screen.dart';

/// The app could not find out where to send the person (no connection, server problem). Nothing is lost: the saved
/// sign-in is kept, and "try again" starts over.
class StartupErrorScreen extends ConsumerWidget {
  const StartupErrorScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final session = ref.watch(sessionControllerProvider);
    final offline = session is SessionStartupFailed && session.failure is NetworkFailure;

    return StatusScreen(
      title: offline ? l10n.noConnectionTitle : l10n.somethingWentWrongTitle,
      body: offline ? l10n.noConnectionBody : l10n.somethingWentWrongBody,
      primaryLabel: l10n.tryAgain,
      onPrimary: () => ref.read(sessionControllerProvider.notifier).start(),
    );
  }
}
