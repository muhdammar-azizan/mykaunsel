import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/status_screen.dart';

/// This app version is older than the server allows: the only way forward is the store.
class UpdateRequiredScreen extends ConsumerWidget {
  const UpdateRequiredScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final session = ref.watch(sessionControllerProvider);
    final message = session is SessionUpdateRequired && session.message.isNotEmpty ? session.message : l10n.updateRequiredBody;
    final storeUrl = session is SessionUpdateRequired ? session.storeUrl : null;

    return StatusScreen(
      title: l10n.updateRequiredTitle,
      body: message,
      // No store link yet (the app is not published): there is nothing to open, so no button is shown.
      primaryLabel: storeUrl == null || storeUrl.isEmpty ? null : l10n.updateNow,
      onPrimary: storeUrl == null ? null : () => launchUrl(Uri.parse(storeUrl), mode: LaunchMode.externalApplication),
    );
  }
}
