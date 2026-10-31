import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';

/// The support address the server knows, or null (then no line about it is shown: the app never invents one).
final supportEmailProvider = FutureProvider.autoDispose<String?>(
  (ref) async {
    try {
      final status = await ref.read(platformStatusApiProvider).fetch();
      final email = status.supportEmail?.trim() ?? '';

      return email.isEmpty ? null : email;
    } on Object {
      return null;
    }
  },
  retry: (_, _) => null,
);

/// Account > Data and privacy: a plain information page. What is kept, who can read it, the Privacy Policy (on the web), and where to ask for a copy.
class DataPrivacyScreen extends ConsumerWidget {
  const DataPrivacyScreen({super.key});

  Future<void> _open(BuildContext context, WidgetRef ref, Uri uri, String failure) async {
    final messenger = ScaffoldMessenger.of(context);
    final ok = await ref.read(urlOpenerProvider)(uri);

    if (!ok) messenger.showSnackBar(SnackBar(content: Text(failure)));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final support = ref.watch(supportEmailProvider).asData?.value;
    final privacy = Uri.parse('${ref.watch(appConfigProvider).apiBaseUrl}/privacy');

    Widget point(IconData icon, String words) => Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.m),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, size: AppSizes.icon), const SizedBox(width: AppSpacing.m), Expanded(child: Text(words))]),
        );

    return Scaffold(
      appBar: AppBar(title: Text(l10n.privacyTitle)),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.l),
          children: [
            Semantics(header: true, child: Text(l10n.privacyHeading, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
            const SizedBox(height: AppSpacing.m),
            point(Icons.schedule, l10n.privacyChatKept),
            point(Icons.visibility_off_outlined, l10n.privacyChatPrivate),
            point(Icons.gavel_outlined, l10n.privacyPdpa),
            const SizedBox(height: AppSpacing.s),
            OutlinedButton.icon(onPressed: () => _open(context, ref, privacy, l10n.privacyOpenFailed), icon: const Icon(Icons.open_in_new), label: Text(l10n.privacyPolicyButton)),
            if (support != null) ...[
              const SizedBox(height: AppSpacing.l),
              Semantics(header: true, child: Text(l10n.privacyCopyHeading, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800))),
              const SizedBox(height: AppSpacing.xs),
              Text(l10n.privacyCopyBody(support)),
              const SizedBox(height: AppSpacing.s),
              OutlinedButton.icon(
                onPressed: () => _open(context, ref, Uri(scheme: 'mailto', path: support), l10n.privacyMailFailed(support)),
                icon: const Icon(Icons.mail_outline),
                label: Text(support),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
