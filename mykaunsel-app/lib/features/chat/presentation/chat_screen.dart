import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../counselors/presentation/counselor_card.dart';
import 'ai_consent_controller.dart';
import 'ai_consent_widgets.dart';
import 'chat_controller.dart';
import 'help_lines.dart';

const _maxChars = 1000;

/// The Chat tab. A person who has not agreed to AI processing is asked first (nothing is sent or stored before). Then it is a conversation with
/// an AI companion that listens, never a counselor: short replies, a calm card with phone lines when the server sees worrying words, counselors
/// to book when it helps, and a "Need help now?" button that is always one tap away.
class ChatScreen extends ConsumerWidget {
  const ChatScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final session = ref.watch(sessionControllerProvider);
    final consent = session is SessionSignedIn && session.user.aiConsent;
    final state = ref.watch(chatControllerProvider);
    final controller = ref.read(chatControllerProvider.notifier);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.chatTitle),
        actions: [
          if (consent && !state.isEmpty)
            IconButton(tooltip: l10n.chatNew, icon: const Icon(Icons.add_comment_outlined), onPressed: state.busy ? null : controller.newConversation),
          IconButton(tooltip: l10n.chatHistory, icon: const Icon(Icons.history), onPressed: () => context.push(AppRoutes.chatHistory)),
          IconButton(tooltip: l10n.chatHelp, icon: const Icon(Icons.support_agent_outlined), onPressed: () => showHelpSheet(context)),
        ],
      ),
      body: SafeArea(child: consent ? const _Conversation() : const _ConsentGate()),
    );
  }
}

class _ConsentGate extends ConsumerWidget {
  const _ConsentGate();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final consent = ref.watch(aiConsentControllerProvider);

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.xl),
      children: [
        Semantics(header: true, child: Text(l10n.chatConsentTitle, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800))),
        const SizedBox(height: AppSpacing.m),
        const AiConsentPoints(),
        if (consent.failure != null) ...[
          const SizedBox(height: AppSpacing.m),
          Semantics(liveRegion: true, child: Text(l10n.chatConsentFailed, style: TextStyle(color: Theme.of(context).colorScheme.error))),
        ],
        const SizedBox(height: AppSpacing.xl),
        FilledButton(
          onPressed: consent.busy ? null : () => ref.read(aiConsentControllerProvider.notifier).set(true),
          child: Text(consent.busy ? l10n.chatConsentSaving : l10n.chatConsentAgree),
        ),
        const SizedBox(height: AppSpacing.s),
        OutlinedButton(onPressed: consent.busy ? null : () => context.go(AppRoutes.home), child: Text(l10n.chatConsentLater)),
      ],
    );
  }
}

class _Conversation extends ConsumerStatefulWidget {
  const _Conversation();

  @override
  ConsumerState<_Conversation> createState() => _ConversationState();
}

class _ConversationState extends ConsumerState<_Conversation> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final _focus = FocusNode();
  int _shown = 0;

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _send() {
    final text = _input.text.trim();

    if (text.isEmpty || ref.read(chatControllerProvider).busy) return;

    _input.clear();
    ref.read(chatControllerProvider.notifier).send(text);
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
    });
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final state = ref.watch(chatControllerProvider);
    final session = ref.watch(sessionControllerProvider);
    final name = session is SessionSignedIn ? session.user.name.trim().split(RegExp(r'\s+')).first : '';

    if (state.items.length != _shown) {
      _shown = state.items.length;
      _scrollToEnd();
    }

    return Column(
      children: [
        Expanded(
          child: ListView(
            controller: _scroll,
            padding: const EdgeInsets.all(AppSpacing.l),
            children: [
              if (state.isEmpty) _Greeting(name: name),
              for (final item in state.items) _ItemView(item: item),
              if (_awaiting(state)) const _Thinking(),
            ],
          ),
        ),
        _InputBar(controller: _input, focus: _focus, busy: state.busy, onSend: _send),
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.s),
          child: Text(l10n.chatDisclaimer, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Theme.of(context).colorScheme.mutedText)),
        ),
      ],
    );
  }

  /// The person's message is stored and the answer is awaited: the last thing on screen is theirs and nothing says it failed.
  bool _awaiting(ChatState state) => state.busy && state.items.isNotEmpty && state.items.last is UserItem && (state.items.last as UserItem).problem == null;
}

class _Greeting extends StatelessWidget {
  const _Greeting({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xl),
      child: Column(
        children: [
          const Icon(Icons.favorite_border, size: 40),
          const SizedBox(height: AppSpacing.m),
          Semantics(header: true, child: Text(l10n.chatGreeting(name), textAlign: TextAlign.center, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
          const SizedBox(height: AppSpacing.s),
          Text(l10n.chatGreetingBody, textAlign: TextAlign.center),
        ],
      ),
    );
  }
}

class _InputBar extends StatelessWidget {
  const _InputBar({required this.controller, required this.focus, required this.busy, required this.onSend});

  final TextEditingController controller;
  final FocusNode focus;
  final bool busy;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Material(
      elevation: 4,
      color: Theme.of(context).colorScheme.surface,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.l, AppSpacing.s, AppSpacing.s, AppSpacing.s),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: TextField(
                controller: controller,
                focusNode: focus,
                minLines: 1,
                maxLines: 5,
                maxLength: _maxChars,
                textCapitalization: TextCapitalization.sentences,
                textInputAction: TextInputAction.newline,
                buildCounter: (context, {required currentLength, required isFocused, required maxLength}) =>
                    currentLength > _maxChars - 100 ? Text('$currentLength/$maxLength') : null,
                decoration: InputDecoration(hintText: l10n.chatInputHint, border: const OutlineInputBorder()),
              ),
            ),
            const SizedBox(width: AppSpacing.xs),
            Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.xs),
              child: IconButton.filled(
                tooltip: l10n.chatSend,
                constraints: const BoxConstraints(minWidth: AppSizes.minTouch, minHeight: AppSizes.minTouch),
                onPressed: busy ? null : onSend,
                icon: const Icon(Icons.send),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Thinking extends StatelessWidget {
  const _Thinking();

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Semantics(
      liveRegion: true,
      label: l10n.chatThinking,
      child: Align(
        alignment: AlignmentDirectional.centerStart,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
              const SizedBox(width: AppSpacing.s),
              Text(l10n.chatThinking, style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
      ),
    );
  }
}

class _ItemView extends ConsumerWidget {
  const _ItemView({required this.item});

  final ChatItem item;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final controller = ref.read(chatControllerProvider.notifier);

    return switch (item) {
      UserItem u => _Bubble(mine: true, text: u.text, semantics: l10n.chatYouSaid(u.text), footer: u.problem == null ? (u.sending ? l10n.chatSending : null) : null, problem: u, onRetry: () => controller.retrySend(u.localId)),
      AssistantItem a => _Bubble(mine: false, text: a.text, semantics: l10n.chatCompanionSaid(a.text)),
      UnavailableItem u => _Unavailable(item: u, onRetry: controller.retryReply),
      CrisisItem c => _CrisisCard(item: c),
      SuggestionItem s => _SuggestionCard(item: s),
      NoticeItem _ => Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.s),
          child: Semantics(liveRegion: true, child: Center(child: Text(l10n.chatNoticeNew, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodySmall))),
        ),
    };
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.mine, required this.text, required this.semantics, this.footer, this.problem, this.onRetry});

  final bool mine;
  final String text;
  final String semantics;
  final String? footer;
  final UserItem? problem;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final failed = problem?.problem;

    return Align(
      alignment: mine ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .85),
          child: Column(
            crossAxisAlignment: mine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
            children: [
              Semantics(
                container: true,
                label: semantics,
                excludeSemantics: true,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: AppSpacing.m, vertical: AppSpacing.s + 2),
                  decoration: BoxDecoration(
                    color: mine ? scheme.primary : scheme.cardFill,
                    border: mine ? null : Border.all(color: scheme.cardBorder),
                    borderRadius: AppRadii.cardRadius,
                  ),
                  child: SelectableText(text, style: TextStyle(color: mine ? scheme.onPrimary : scheme.onSurface)),
                ),
              ),
              if (footer != null) Padding(padding: const EdgeInsets.only(top: 2), child: Text(footer!, style: Theme.of(context).textTheme.bodySmall)),
              if (failed != null) ...[
                const SizedBox(height: AppSpacing.xs),
                Semantics(
                  liveRegion: true,
                  container: true,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.error_outline, size: AppSizes.icon - 4, color: scheme.error),
                      const SizedBox(width: AppSpacing.xs),
                      Flexible(child: Text(_problemText(l10n, problem!), style: TextStyle(color: scheme.error))),
                    ],
                  ),
                ),
                if (failed != SendProblem.dailyLimit && failed != SendProblem.full)
                  TextButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: Text(l10n.chatSendAgain)),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

String _problemText(AppLocalizations l10n, UserItem item) => switch (item.problem!) {
      SendProblem.network => l10n.noConnectionBody,
      SendProblem.server => l10n.chatErrServer,
      SendProblem.rateLimited => item.waitSeconds == null ? l10n.confirmErrWaitGeneric : l10n.chatErrWait(item.waitSeconds!),
      SendProblem.dailyLimit => l10n.chatErrDaily,
      SendProblem.full => l10n.chatErrFull,
      SendProblem.other => l10n.somethingWentWrongBody,
    };

/// The model could not answer: a kind message, the person's words are safe, and one button to ask again.
class _Unavailable extends StatelessWidget {
  const _Unavailable({required this.item, required this.onRetry});

  final UnavailableItem item;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
      child: Semantics(
        liveRegion: true,
        container: true,
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.m),
          decoration: BoxDecoration(color: scheme.inactiveFill, borderRadius: AppRadii.cardRadius),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [const Icon(Icons.cloud_off_outlined, size: AppSizes.icon), const SizedBox(width: AppSpacing.s), Expanded(child: Text(l10n.chatUnavailable))]),
              if (item.problem == SendProblem.network) Padding(padding: const EdgeInsets.only(top: AppSpacing.xs), child: Text(l10n.noConnectionBody, style: Theme.of(context).textTheme.bodySmall)),
              TextButton.icon(onPressed: item.busy ? null : onRetry, icon: const Icon(Icons.refresh), label: Text(item.busy ? l10n.chatRetrying : l10n.tryAgain)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Amber and calm. It never blocks the chat: the person can call, find a counselor, or simply carry on.
class _CrisisCard extends ConsumerWidget {
  const _CrisisCard({required this.item});

  final CrisisItem item;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.s),
      child: Semantics(
        container: true,
        liveRegion: true,
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.l),
          decoration: BoxDecoration(color: scheme.crisisFill, borderRadius: AppRadii.cardRadius, border: Border.all(color: scheme.crisisBorder)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(children: [const Icon(Icons.favorite_border), const SizedBox(width: AppSpacing.s), Expanded(child: Semantics(header: true, child: Text(l10n.crisisTitle, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800))))]),
              const SizedBox(height: AppSpacing.s),
              Text(l10n.crisisBody),
              const SizedBox(height: AppSpacing.m),
              HelplineButtons(lines: item.helplines),
              Text(l10n.crisisEmergency, style: text.bodyMedium),
              const SizedBox(height: AppSpacing.m),
              OutlinedButton(onPressed: () => context.go(AppRoutes.counselors), child: Text(l10n.crisisFindCounselor)),
              const SizedBox(height: AppSpacing.s),
              TextButton(onPressed: () => ref.read(chatControllerProvider.notifier).dismiss(item), child: Text(l10n.crisisContinue)),
            ],
          ),
        ),
      ),
    );
  }
}

class _SuggestionCard extends StatelessWidget {
  const _SuggestionCard({required this.item});

  final SuggestionItem item;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AppSpacing.s),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(header: true, child: Text(l10n.chatSuggestTitle, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800))),
          Text(l10n.chatSuggestBody, style: text.bodySmall),
          const SizedBox(height: AppSpacing.s),
          for (final counselor in item.counselors) CounselorCard(counselor: counselor, onTap: () => context.go(AppRoutes.counselorPath(counselor.id))),
        ],
      ),
    );
  }
}
