import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/status_screen.dart';

/// Maintenance mode is on. The words are the Platform Admin's (Registration Control → maintenance message).
class MaintenanceScreen extends ConsumerWidget {
  const MaintenanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final session = ref.watch(sessionControllerProvider);
    final message = session is SessionMaintenance ? session.message : '';

    return StatusScreen(
      title: l10n.maintenanceTitle,
      body: message,
      primaryLabel: l10n.tryAgain,
      onPrimary: () => ref.read(sessionControllerProvider.notifier).start(),
    );
  }
}
