import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/feed.dart';
import 'feed_controller.dart';
import 'feed_support.dart';

/// The people this person muted. No automatic retry: a failure is shown with "Try again".
final mutedPeopleProvider = FutureProvider.autoDispose<List<FeedMuted>>(
  (ref) {
    ref.watch(currentUserIdProvider);

    return ref.read(feedApiProvider).mutes();
  },
  retry: (_, _) => null,
);

/// Who is muted, each with an Unmute button. Unmuting brings their posts back the next time the feed loads.
class MutedPeopleScreen extends ConsumerWidget {
  const MutedPeopleScreen({super.key});

  Future<void> _unmute(BuildContext context, WidgetRef ref, FeedMuted person) async {
    final messenger = ScaffoldMessenger.of(context);
    final l10n = AppLocalizations.of(context);

    try {
      await ref.read(feedApiProvider).unmute(person.id);
    } on NotFoundFailure {
      // already unmuted
    } on AppFailure catch (failure) {
      messenger.showSnackBar(SnackBar(content: Text(feedFailureText(l10n, failure))));

      return;
    }

    ref.invalidate(mutedPeopleProvider);
    ref.read(feedControllerProvider.notifier).refresh();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final people = ref.watch(mutedPeopleProvider);

    final body = people.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(error is AppFailure ? feedFailureText(l10n, error) : l10n.somethingWentWrongBody, textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.l),
            FilledButton(onPressed: () => ref.invalidate(mutedPeopleProvider), child: Text(l10n.tryAgain)),
          ]),
        ),
      ),
      data: (list) => list.isEmpty
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.xl),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.volume_off_outlined, size: 48, color: Theme.of(context).colorScheme.primary),
                  const SizedBox(height: AppSpacing.m),
                  Semantics(header: true, child: Text(l10n.feedMutedEmptyTitle, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
                  const SizedBox(height: AppSpacing.s),
                  Text(l10n.feedMutedEmptyBody, textAlign: TextAlign.center),
                ]),
              ),
            )
          : ListView(
              padding: const EdgeInsets.all(AppSpacing.l),
              children: [
                for (final person in list)
                  ListTile(
                    key: ValueKey(person.id),
                    minTileHeight: AppSizes.minTouch,
                    title: Text(person.name),
                    trailing: Semantics(
                      label: l10n.feedUnmuteName(person.name),
                      button: true,
                      excludeSemantics: true,
                      child: OutlinedButton(onPressed: () => _unmute(context, ref, person), child: Text(l10n.feedUnmute)),
                    ),
                  ),
              ],
            ),
    );

    return Scaffold(appBar: AppBar(title: Text(l10n.feedMutedTitle)), body: SafeArea(child: body));
  }
}
