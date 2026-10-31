import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../booking/presentation/confirm_booking_controller.dart' show idempotencyKeyFactoryProvider;
import '../domain/feed.dart';
import 'feed_controller.dart';
import 'feed_support.dart';

/// Where the post's size is limited (characters as the server counts them: whole characters, not code units).
const feedMaxPostChars = 500;

/// Writes a new post, or (with [editing]) changes one of the person's own. Text only. It goes out under the person's own name, and the screen says so.
/// A post that did not go stays on screen; sending the same words again uses the same client id, so nothing is stored twice.
class NewPostScreen extends ConsumerStatefulWidget {
  const NewPostScreen({super.key, this.editing});

  final FeedPost? editing;

  @override
  ConsumerState<NewPostScreen> createState() => _NewPostScreenState();
}

class _NewPostScreenState extends ConsumerState<NewPostScreen> {
  late final TextEditingController _text = TextEditingController(text: widget.editing?.text ?? '');
  bool _sending = false;
  AppFailure? _failure;
  String? _attemptText;
  String? _attemptKey;

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final words = _text.text.trim();

    if (words.isEmpty || words.runes.length > feedMaxPostChars || _sending) return;

    setState(() {
      _sending = true;
      _failure = null;
    });

    final api = ref.read(feedApiProvider);
    final list = ref.read(feedControllerProvider.notifier);
    final editing = widget.editing;

    try {
      if (editing != null) {
        final post = await api.edit(editing.id, words);

        if (!mounted) return;

        list.replace(post);
        ref.read(feedPostControllerProvider(editing.id).notifier).edited(post);
        context.pop();

        return;
      }

      if (_attemptText != words) {
        _attemptText = words;
        _attemptKey = ref.read(idempotencyKeyFactoryProvider)();
      }

      final made = await api.create(words, clientId: _attemptKey!);

      if (!mounted) return;

      list.prepend(made.item);

      if (made.crisis) await showFeedCrisisSheet(context, made.helplines);

      if (mounted) context.pop();
    } on AppFailure catch (failure) {
      if (!mounted) return;

      setState(() {
        _sending = false;
        _failure = failure;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final session = ref.watch(sessionControllerProvider);
    final name = session is SessionSignedIn ? session.user.name : '';
    final editing = widget.editing != null;

    return Scaffold(
      appBar: AppBar(
        title: Text(editing ? l10n.feedEditPostTitle : l10n.feedNewPostTitle),
        actions: [
          ListenableBuilder(
            listenable: _text,
            builder: (context, _) {
              final used = _text.text.trim().runes.length;
              final ready = used > 0 && used <= feedMaxPostChars && !_sending;

              return Padding(
                padding: const EdgeInsets.only(right: AppSpacing.s),
                child: _sending
                    ? const Padding(padding: EdgeInsets.all(AppSpacing.m), child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 3)))
                    : TextButton(
                        style: TextButton.styleFrom(minimumSize: const Size(AppSizes.minTouch, AppSizes.minTouch)),
                        onPressed: ready ? _submit : null,
                        child: Text(editing ? l10n.feedSaveAction : l10n.feedPostAction),
                      ),
              );
            },
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.l),
          children: [
            if (!editing && name.isNotEmpty) Padding(padding: const EdgeInsets.only(bottom: AppSpacing.m), child: Text(l10n.feedPostingAs(name), style: Theme.of(context).textTheme.bodyMedium)),
            TextField(
              controller: _text,
              autofocus: true,
              minLines: 6,
              maxLines: 12,
              textCapitalization: TextCapitalization.sentences,
              keyboardType: TextInputType.multiline,
              inputFormatters: const [RuneLimitFormatter(feedMaxPostChars)],
              decoration: InputDecoration(hintText: l10n.feedPostHint, border: const OutlineInputBorder()),
              enabled: !_sending,
            ),
            const SizedBox(height: AppSpacing.xs),
            ListenableBuilder(
              listenable: _text,
              builder: (context, _) {
                final used = _text.text.runes.length;

                return Align(
                  alignment: Alignment.centerRight,
                  child: Semantics(liveRegion: false, child: Text(l10n.feedCharCount(used, feedMaxPostChars), style: Theme.of(context).textTheme.bodySmall?.copyWith(color: scheme.mutedText))),
                );
              },
            ),
            if (_failure != null)
              Padding(
                padding: const EdgeInsets.only(top: AppSpacing.m),
                child: Semantics(liveRegion: true, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Icon(Icons.error_outline, color: scheme.error),
                  const SizedBox(width: AppSpacing.s),
                  Expanded(child: Text(feedFailureText(l10n, _failure!), style: TextStyle(color: scheme.error))),
                ])),
              ),
          ],
        ),
      ),
    );
  }
}

/// Stops typing and pasting at the limit, counting whole characters.
class RuneLimitFormatter extends TextInputFormatter {
  const RuneLimitFormatter(this.max);

  final int max;

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    if (newValue.text.runes.length <= max) return newValue;

    final cut = String.fromCharCodes(newValue.text.runes.take(max));

    return TextEditingValue(text: cut, selection: TextSelection.collapsed(offset: cut.length));
  }
}
