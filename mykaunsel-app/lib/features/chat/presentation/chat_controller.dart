import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../booking/presentation/confirm_booking_controller.dart' show idempotencyKeyFactoryProvider;
import '../../counselors/domain/counselor.dart';
import '../domain/chat.dart';

/// What the person sees in the conversation, in order.
sealed class ChatItem {
  const ChatItem();
}

/// The person's own message. [problem] is set when sending failed; [id] is the server's once it is stored.
class UserItem extends ChatItem {
  const UserItem({required this.localId, required this.text, required this.clientId, this.sending = false, this.problem, this.waitSeconds});

  final String localId;
  final String text;

  /// Made once per message: sending the same message again with it stores nothing twice.
  final String clientId;
  final bool sending;
  final SendProblem? problem;
  final int? waitSeconds;

  UserItem copyWith({bool? sending, SendProblem? problem, bool clearProblem = false, int? waitSeconds}) => UserItem(
        localId: localId,
        text: text,
        clientId: clientId,
        sending: sending ?? this.sending,
        problem: clearProblem ? null : (problem ?? this.problem),
        waitSeconds: clearProblem ? null : (waitSeconds ?? this.waitSeconds),
      );
}

class AssistantItem extends ChatItem {
  const AssistantItem({required this.localId, required this.text});

  final String localId;
  final String text;
}

/// The model could not answer: the message is kept and the person is told kindly. [busy] while they asked again.
class UnavailableItem extends ChatItem {
  const UnavailableItem({this.busy = false, this.problem});

  final bool busy;
  final SendProblem? problem;
}

/// The calm card shown after a message the server flagged. It never blocks the chat.
class CrisisItem extends ChatItem {
  const CrisisItem(this.helplines);

  final List<Helpline> helplines;
}

/// Counselors the person may book, offered by the server.
class SuggestionItem extends ChatItem {
  const SuggestionItem(this.counselors);

  final List<Counselor> counselors;
}

/// A line the app says itself ("this is a new conversation").
class NoticeItem extends ChatItem {
  const NoticeItem(this.kind);

  final NoticeKind kind;
}

enum NoticeKind { newConversation }

/// Why a message did not go, and so what the bubble says and offers.
enum SendProblem {
  /// No answer: the message may or may not have been stored. Sending it again is SAFE (same client id).
  network,
  server,
  rateLimited,

  /// The daily limit: wait until tomorrow.
  dailyLimit,
  full,
  other,
}

class ChatState {
  const ChatState({this.items = const [], this.sessionId, this.busy = false, this.consentLost = false});

  final List<ChatItem> items;

  /// The server's id of the conversation, once the first message was stored.
  final String? sessionId;

  /// A message is on its way, or an answer is awaited.
  final bool busy;

  /// The server said the person has not agreed (any more): the screen goes back to asking.
  final bool consentLost;

  bool get isEmpty => items.isEmpty;

  ChatState copyWith({List<ChatItem>? items, String? sessionId, bool clearSession = false, bool? busy, bool? consentLost}) => ChatState(
        items: items ?? this.items,
        sessionId: clearSession ? null : (sessionId ?? this.sessionId),
        busy: busy ?? this.busy,
        consentLost: consentLost ?? this.consentLost,
      );
}

/// The current conversation. It lives as long as the signed-in person does (leaving the tab keeps it) and starts from nothing for the next person.
final chatControllerProvider = NotifierProvider<ChatController, ChatState>(ChatController.new);

/// The chat. The server does everything that matters (consent, crisis detection, the model, the limits); this keeps what is on screen, sends,
/// and asks again for what did not arrive. Asking again is always safe: a message carries a client id, an answer is asked for by conversation.
class ChatController extends Notifier<ChatState> {
  int _counter = 0;

  @override
  ChatState build() {
    ref.watch(currentUserIdProvider);

    return const ChatState();
  }

  String _newLocalId() => 'local-${++_counter}';

  Future<void> send(String raw) async {
    final text = raw.trim();

    if (text.isEmpty || state.busy) return;

    final item = UserItem(localId: _newLocalId(), text: text, clientId: ref.read(idempotencyKeyFactoryProvider)(), sending: true);

    state = state.copyWith(items: [...state.items, item], busy: true);

    await _deliver(item);
  }

  /// Sends a message that did not go once more, with the same client id.
  Future<void> retrySend(String localId) async {
    if (state.busy) return;

    final item = state.items.whereType<UserItem>().where((i) => i.localId == localId && i.problem != null).firstOrNull;

    if (item == null) return;

    final sending = item.copyWith(sending: true, clearProblem: true);

    state = state.copyWith(items: [for (final i in state.items) i == item ? sending : i], busy: true);

    await _deliver(sending);
  }

  Future<void> _deliver(UserItem item, {bool startedNew = false}) async {
    try {
      final result = await ref.read(chatApiProvider).send(item.text, sessionId: startedNew ? null : state.sessionId, clientId: item.clientId);

      if (!ref.mounted) return;

      _apply(item, result);
    } on AppFailure catch (failure) {
      if (!ref.mounted) return;

      switch (failure) {
        case ConflictFailure(code: 'session_ended') when !startedNew:
          // The server ended that conversation meanwhile (it was quiet for a while): this message starts a new one.
          state = state.copyWith(clearSession: true, items: [...state.items.where((i) => i != item), const NoticeItem(NoticeKind.newConversation), item]);
          await _deliver(item, startedNew: true);
        case ForbiddenFailure(code: 'ai_consent_required'):
          // Consent was withdrawn elsewhere: nothing was stored. The message goes, the screen asks again.
          _refreshUserConsent(false);
          state = state.copyWith(items: state.items.where((i) => i != item).toList(), busy: false, consentLost: true);
        case UnauthorizedFailure():
          state = state.copyWith(items: [for (final i in state.items) i == item ? item.copyWith(sending: false) : i], busy: false); // the router takes them to sign-in
        default:
          _failed(item, failure);
      }
    }
  }

  void _apply(UserItem sent, ChatSendResult result) {
    final items = <ChatItem>[
      for (final i in state.items)
        if (i == sent) UserItem(localId: sent.localId, text: sent.text, clientId: sent.clientId) else i,
    ];

    if (result.reply != null) {
      items.add(AssistantItem(localId: result.reply!.id, text: result.reply!.text));
    } else if (result.assistantUnavailable) {
      items.add(const UnavailableItem());
    }

    if (result.crisis) items.add(CrisisItem(result.helplines.isEmpty ? fallbackHelplines : result.helplines));
    if (result.counselors.isNotEmpty) items.add(SuggestionItem(result.counselors));

    state = state.copyWith(items: items, sessionId: result.session.id, busy: false);
  }

  void _failed(UserItem item, AppFailure failure) {
    final (problem, wait) = switch (failure) {
      NetworkFailure() => (SendProblem.network, null),
      ServerFailure() => (SendProblem.server, null),
      // The daily limit comes with a wait until tomorrow: hours, not seconds.
      RateLimitedFailure(:final retryAfterSeconds) when (retryAfterSeconds ?? 0) > 3600 => (SendProblem.dailyLimit, null),
      RateLimitedFailure(:final retryAfterSeconds) => (SendProblem.rateLimited, retryAfterSeconds),
      ConflictFailure(code: 'session_full') => (SendProblem.full, null),
      _ => (SendProblem.other, null),
    };

    state = state.copyWith(items: [for (final i in state.items) i == item ? item.copyWith(sending: false, problem: problem, waitSeconds: wait) : i], busy: false);
  }

  /// Asks again for the answer the model could not give.
  Future<void> retryReply() async {
    final id = state.sessionId;

    if (id == null || state.busy || !state.items.any((i) => i is UnavailableItem)) return;

    state = state.copyWith(items: [for (final i in state.items) i is UnavailableItem ? const UnavailableItem(busy: true) : i], busy: true);

    try {
      final result = await ref.read(chatApiProvider).reply(id);

      if (!ref.mounted) return;

      final replaced = <ChatItem>[];

      for (final i in state.items) {
        if (i is UnavailableItem) {
          if (result.reply != null) replaced.add(AssistantItem(localId: result.reply!.id, text: result.reply!.text));
          if (result.reply == null) replaced.add(const UnavailableItem());
        } else {
          replaced.add(i);
        }
      }

      state = state.copyWith(items: replaced, busy: false);
    } on AppFailure catch (failure) {
      if (!ref.mounted) return;

      final problem = switch (failure) {
        NetworkFailure() => SendProblem.network,
        ServerFailure() => SendProblem.server,
        RateLimitedFailure() => SendProblem.rateLimited,
        _ => SendProblem.other,
      };

      state = state.copyWith(items: [for (final i in state.items) i is UnavailableItem ? UnavailableItem(problem: problem) : i], busy: false);
    }
  }

  /// Starts a fresh conversation. The old one is ended on the server as well as can be (if that does not get through, the server ends it
  /// by itself once it has been quiet for a while).
  void newConversation() {
    final id = state.sessionId;

    state = const ChatState();

    if (id != null) {
      ref.read(chatApiProvider).end(id).then<void>((_) {}, onError: (Object _) {});
    }
  }

  void acknowledgeConsentLost() => state = state.copyWith(consentLost: false);

  /// Drops a card the person has read (the crisis card's "Continue chatting").
  void dismiss(ChatItem item) => state = state.copyWith(items: state.items.where((i) => i != item).toList());

  void _refreshUserConsent(bool consent) {
    final current = ref.read(sessionControllerProvider);

    if (current is SessionSignedIn) ref.read(sessionControllerProvider.notifier).updateUser(current.user.copyWith(aiConsent: consent));
  }
}
