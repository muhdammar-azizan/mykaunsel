import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import 'my_requests_controller.dart';

enum WithdrawProblem {
  /// It was answered or has closed meanwhile: the page is refreshed and says so.
  notPending,
  notFound,
  rateLimited,

  /// No answer: the request may or may not have been withdrawn. Trying again is SAFE (withdrawing twice changes nothing more).
  network,
  server,
  other,
}

class WithdrawState {
  const WithdrawState({this.busy = false, this.problem, this.waitSeconds, this.done = false});

  final bool busy;
  final WithdrawProblem? problem;
  final int? waitSeconds;
  final bool done;
}

final withdrawRequestControllerProvider = NotifierProvider.autoDispose.family<WithdrawRequestController, WithdrawState, String>(WithdrawRequestController.new);

/// Takes back one waiting request. The server decides; this only asks and reports.
class WithdrawRequestController extends Notifier<WithdrawState> {
  WithdrawRequestController(this.requestId);

  final String requestId;

  @override
  WithdrawState build() => const WithdrawState();

  Future<void> withdraw() async {
    if (state.busy || state.done) return;

    state = const WithdrawState(busy: true);

    try {
      await ref.read(requestsApiProvider).withdraw(requestId);

      if (!ref.mounted) return;

      _refreshWhatIsShown();
      state = const WithdrawState(done: true);
    } on AppFailure catch (failure) {
      if (!ref.mounted) return;

      state = switch (failure) {
        ConflictFailure(code: 'request_not_pending') => _refreshed(WithdrawProblem.notPending),
        NotFoundFailure() => const WithdrawState(problem: WithdrawProblem.notFound),
        RateLimitedFailure(:final retryAfterSeconds) => WithdrawState(problem: WithdrawProblem.rateLimited, waitSeconds: retryAfterSeconds),
        NetworkFailure() => const WithdrawState(problem: WithdrawProblem.network),
        ServerFailure() => const WithdrawState(problem: WithdrawProblem.server),
        UnauthorizedFailure() => const WithdrawState(), // the router takes the person to sign-in
        _ => const WithdrawState(problem: WithdrawProblem.other),
      };
    }
  }

  WithdrawState _refreshed(WithdrawProblem problem) {
    _refreshWhatIsShown();

    return WithdrawState(problem: problem);
  }

  void _refreshWhatIsShown() {
    ref.invalidate(myRequestProvider(requestId));
    ref.invalidate(myRequestsControllerProvider);
  }
}
