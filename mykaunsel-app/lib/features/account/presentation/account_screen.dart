import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../../l10n/app_localizations.dart';
import '../../chat/presentation/ai_consent_controller.dart';
import '../../chat/presentation/ai_consent_widgets.dart';

/// Who is signed in, and signing out. (Profile editing, password and devices come with the settings feature.)
class AccountScreen extends ConsumerWidget {
  const AccountScreen({super.key});

  Future<void> _signOutEverywhere(BuildContext context, WidgetRef ref) async {
    final l10n = AppLocalizations.of(context);

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.signOutEverywhereTitle),
        content: Text(l10n.signOutEverywhereBody),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.cancel)),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(l10n.signOutEverywhere)),
        ],
      ),
    );

    if (confirmed == true) {
      await ref.read(sessionControllerProvider.notifier).signOut(everywhere: true);
    }
  }

  /// Turning the chat on asks first (the same words as in the chat); turning it off asks whether they are sure. Either way the server decides.
  Future<void> _toggleAiChat(BuildContext context, WidgetRef ref, bool on) async {
    final l10n = AppLocalizations.of(context);

    if (on) {
      await showAiConsentDialog(context);

      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.accountAiOffTitle),
        content: Text(l10n.accountAiOffBody),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.cancel)),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(l10n.accountAiOffConfirm)),
        ],
      ),
    );

    if (confirmed == true) await ref.read(aiConsentControllerProvider.notifier).set(false);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final session = ref.watch(sessionControllerProvider);
    final user = session is SessionSignedIn ? session.user : null;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.accountTitle)),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: Theme.of(context).colorScheme.primary.withValues(alpha: .12),
                  child: Text(_initials(user?.name ?? ''), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800, color: Theme.of(context).colorScheme.primary)),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(user?.name ?? '', style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                      Text(user?.email ?? '', style: text.bodyMedium),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            ListTile(leading: const Icon(Icons.person_outline), title: Text(l10n.accountProfile), trailing: const Icon(Icons.chevron_right), onTap: () => context.push(AppRoutes.accountProfile)),
            ListTile(leading: const Icon(Icons.lock_outline), title: Text(l10n.accountPassword), trailing: const Icon(Icons.chevron_right), onTap: () => context.push(AppRoutes.accountPassword)),
            ListTile(leading: const Icon(Icons.smartphone), title: Text(l10n.accountDevices), trailing: const Icon(Icons.chevron_right), onTap: () => context.push(AppRoutes.accountDevices)),
            SwitchListTile(
              secondary: const Icon(Icons.chat_bubble_outline),
              title: Text(l10n.accountAiChat),
              subtitle: Text(user?.aiConsent == true ? l10n.accountAiChatOn : l10n.accountAiChatOff),
              value: user?.aiConsent == true,
              onChanged: (on) => _toggleAiChat(context, ref, on),
            ),
            const Divider(height: 32),
            ListTile(
              leading: const Icon(Icons.logout),
              title: Text(l10n.signOut),
              onTap: () => ref.read(sessionControllerProvider.notifier).signOut(),
            ),
            ListTile(
              leading: const Icon(Icons.devices_other_outlined),
              title: Text(l10n.signOutEverywhere),
              onTap: () => _signOutEverywhere(context, ref),
            ),
          ],
        ),
      ),
    );
  }
}

String _initials(String name) {
  final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();

  if (parts.isEmpty) return '?';
  if (parts.length == 1) return parts.first.characters.first.toUpperCase();

  return (parts.first.characters.first + parts.last.characters.first).toUpperCase();
}
