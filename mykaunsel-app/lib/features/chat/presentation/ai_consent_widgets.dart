import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import 'ai_consent_controller.dart';

/// What the person agrees to, in four short lines. The same words wherever consent is asked (the chat, and Account).
class AiConsentPoints extends StatelessWidget {
  const AiConsentPoints({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    final points = [l10n.chatConsentPoint1, l10n.chatConsentPoint2, l10n.chatConsentPoint3, l10n.chatConsentPoint4];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(l10n.chatConsentIntro),
        const SizedBox(height: AppSpacing.m),
        for (final point in points)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.s),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Padding(padding: EdgeInsets.only(top: 2), child: Icon(Icons.check_circle_outline, size: AppSizes.icon - 4)),
                const SizedBox(width: AppSpacing.s),
                Expanded(child: Text(point)),
              ],
            ),
          ),
      ],
    );
  }
}

/// The same four lines, to read only (from the sign-up form): nothing is saved here.
Future<void> showAiConsentInfo(BuildContext context) {
  final l10n = AppLocalizations.of(context);

  return showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      title: Text(l10n.chatConsentTitle),
      content: const SingleChildScrollView(child: AiConsentPoints()),
      actions: [TextButton(onPressed: () => Navigator.pop(context), child: Text(l10n.bookingCancelOk))],
    ),
  );
}

/// "Before you chat" with "I agree": asked in Account when the person turns the chat on. True once the server has their yes.
Future<bool> showAiConsentDialog(BuildContext context) async {
  final agreed = await showDialog<bool>(context: context, builder: (_) => const _ConsentDialog());

  return agreed ?? false;
}

class _ConsentDialog extends ConsumerWidget {
  const _ConsentDialog();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final state = ref.watch(aiConsentControllerProvider);

    return AlertDialog(
      title: Text(l10n.chatConsentTitle),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const AiConsentPoints(),
            if (state.failure != null) ...[
              const SizedBox(height: AppSpacing.s),
              Semantics(liveRegion: true, child: Text(l10n.chatConsentFailed, style: TextStyle(color: Theme.of(context).colorScheme.error))),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: state.busy ? null : () => Navigator.pop(context, false), child: Text(l10n.cancel)),
        FilledButton(
          onPressed: state.busy
              ? null
              : () async {
                  final ok = await ref.read(aiConsentControllerProvider.notifier).set(true);

                  if (ok && context.mounted) Navigator.pop(context, true);
                },
          child: Text(state.busy ? l10n.chatConsentSaving : l10n.chatConsentAgree),
        ),
      ],
    );
  }
}
