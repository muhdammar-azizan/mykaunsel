import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/time_request.dart';
import 'withdraw_request_controller.dart';

/// Opens the withdraw sheet. Resolves to true once the request has been withdrawn.
Future<bool> showWithdrawRequestSheet(BuildContext context, TimeRequest request) async {
  final done = await showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (_) => WithdrawRequestSheet(request: request),
  );

  return done ?? false;
}

/// "Withdraw this request?": what will happen, "Withdraw request" and "Keep request". While it works nothing can close it, and withdrawing twice
/// changes nothing more, so any answer that does not arrive is simply asked again.
class WithdrawRequestSheet extends ConsumerWidget {
  const WithdrawRequestSheet({super.key, required this.request});

  final TimeRequest request;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;
    final provider = withdrawRequestControllerProvider(request.id);
    final state = ref.watch(provider);
    final controller = ref.read(provider.notifier);

    ref.listen(provider, (_, now) {
      if (now.done) Navigator.of(context).pop(true);
    });

    final problem = state.problem;
    final ended = problem == WithdrawProblem.notPending || problem == WithdrawProblem.notFound;
    final retrying = problem == WithdrawProblem.network || problem == WithdrawProblem.server;

    return PopScope(
      canPop: !state.busy,
      child: Padding(
        padding: EdgeInsets.fromLTRB(AppSpacing.xl, 0, AppSpacing.xl, AppSpacing.xl + MediaQuery.viewInsetsOf(context).bottom),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Semantics(header: true, liveRegion: true, child: Text(l10n.requestWithdrawTitle, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
              const SizedBox(height: AppSpacing.m),
              if (!ended) Text(l10n.requestWithdrawBody(request.counselor.displayName)),
              if (problem != null) ...[
                const SizedBox(height: AppSpacing.m),
                Semantics(
                  liveRegion: true,
                  container: true,
                  child: Container(
                    padding: const EdgeInsets.all(AppSpacing.m),
                    decoration: BoxDecoration(color: scheme.errorContainer, borderRadius: AppRadii.chipRadius),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.error_outline, size: AppSizes.icon),
                        const SizedBox(width: AppSpacing.s),
                        Expanded(child: Text(_message(l10n, problem, state.waitSeconds))),
                      ],
                    ),
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.xl),
              if (ended)
                SizedBox(width: double.infinity, child: FilledButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.bookingCancelOk)))
              else ...[
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: state.busy ? null : controller.withdraw,
                    style: FilledButton.styleFrom(backgroundColor: scheme.error, foregroundColor: scheme.onError),
                    child: state.busy
                        ? Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: scheme.onError)),
                              const SizedBox(width: AppSpacing.s),
                              Text(l10n.requestWithdrawing),
                            ],
                          )
                        : Text(retrying ? l10n.tryAgain : l10n.requestWithdraw),
                  ),
                ),
                const SizedBox(height: AppSpacing.s),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(onPressed: state.busy ? null : () => Navigator.of(context).pop(false), child: Text(l10n.requestKeep)),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  String _message(AppLocalizations l10n, WithdrawProblem problem, int? waitSeconds) => switch (problem) {
        WithdrawProblem.notPending => l10n.requestNotPending,
        WithdrawProblem.notFound => l10n.requestNotFound,
        WithdrawProblem.rateLimited => waitSeconds == null ? l10n.confirmErrWaitGeneric : l10n.confirmErrWait(waitSeconds),
        WithdrawProblem.network => l10n.noConnectionBody,
        WithdrawProblem.server => l10n.requestWithdrawServer,
        WithdrawProblem.other => l10n.somethingWentWrongBody,
      };
}
