import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/chat.dart';

/// Where to get help, from the server; if the server can't be reached, the same lines built into the app. Help is never behind a failure.
final helplinesProvider = FutureProvider.autoDispose<List<Helpline>>(
  (ref) async {
    try {
      final lines = await ref.read(chatApiProvider).helplines();

      return lines.isEmpty ? fallbackHelplines : lines;
    } on Object {
      return fallbackHelplines;
    }
  },
  retry: (_, _) => null,
);

/// Starts a call. If the phone can't, says so and gives the number to dial.
Future<void> callHelpline(BuildContext context, WidgetRef ref, Helpline line) async {
  final l10n = AppLocalizations.of(context);
  final messenger = ScaffoldMessenger.of(context);
  final ok = await ref.read(urlOpenerProvider)(Uri(scheme: 'tel', path: line.number));

  if (!ok) messenger.showSnackBar(SnackBar(content: Text(l10n.crisisCallFailed(line.number))));
}

/// The call buttons: big, one per line, spoken as "Call HEAL 15555".
class HelplineButtons extends ConsumerWidget {
  const HelplineButtons({super.key, required this.lines});

  final List<Helpline> lines;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final line in lines)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.s),
            child: FilledButton.icon(
              onPressed: () => callHelpline(context, ref, line),
              icon: const Icon(Icons.call),
              label: Text(l10n.crisisCall(line.name, line.number)),
            ),
          ),
      ],
    );
  }
}

/// "Need help now?": always reachable from the chat, whatever has been said.
Future<void> showHelpSheet(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (_) => const _HelpSheet(),
  );
}

class _HelpSheet extends ConsumerWidget {
  const _HelpSheet();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final lines = ref.watch(helplinesProvider).asData?.value ?? fallbackHelplines;

    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.xl, 0, AppSpacing.xl, AppSpacing.xl),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Semantics(header: true, child: Text(l10n.chatHelp, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
            const SizedBox(height: AppSpacing.m),
            Text(l10n.helpSheetBody),
            const SizedBox(height: AppSpacing.l),
            HelplineButtons(lines: lines),
            const SizedBox(height: AppSpacing.s),
            Text(l10n.crisisEmergency, style: text.bodyMedium),
            const SizedBox(height: AppSpacing.l),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () {
                  Navigator.of(context).pop();
                  context.go(AppRoutes.counselors);
                },
                child: Text(l10n.crisisFindCounselor),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
