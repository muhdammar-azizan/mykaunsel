import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/failure_message.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/my_organization.dart';
import 'organization_controller.dart';

/// Account > Your organization: join with a reference code, see that the request is waiting, or leave. Everything the rules decide (one
/// organization, what leaving cancels) is decided by the server; this screen says it plainly before the person acts.
class OrganizationScreen extends ConsumerStatefulWidget {
  const OrganizationScreen({super.key});

  @override
  ConsumerState<OrganizationScreen> createState() => _OrganizationScreenState();
}

class _OrganizationScreenState extends ConsumerState<OrganizationScreen> {
  final _code = TextEditingController();

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _join() async {
    if (_code.text.trim().isEmpty) return;

    final joined = await ref.read(organizationControllerProvider.notifier).join(_code.text);

    if (joined) _code.clear();
  }

  Future<void> _leave(MyOrganization organization) async {
    final l10n = AppLocalizations.of(context);
    final graceDays = organization.graceDays;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        scrollable: true, // large text on a small screen must scroll, not overflow
        title: Text(organization.waiting ? l10n.orgWithdrawTitle : l10n.orgLeaveTitle(organization.name)),
        content: Text(organization.waiting ? l10n.orgWithdrawBody : l10n.orgLeaveBody(organization.name, graceDays)),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: Text(l10n.cancel)),
          FilledButton(onPressed: () => Navigator.of(context).pop(true), child: Text(organization.waiting ? l10n.orgWithdrawConfirm : l10n.orgLeaveConfirm)),
        ],
      ),
    );

    if (confirmed == true && mounted) {
      await ref.read(organizationControllerProvider.notifier).leave();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final state = ref.watch(organizationControllerProvider);
    final text = Theme.of(context).textTheme;
    final scheme = Theme.of(context).colorScheme;
    final organization = state.organization;

    Widget body;

    if (state.loading) {
      body = const Center(child: CircularProgressIndicator());
    } else if (state.loadFailure != null && organization == null) {
      body = Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xxl),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(failureMessage(l10n, state.loadFailure!), textAlign: TextAlign.center),
            const SizedBox(height: AppSpacing.l),
            FilledButton(onPressed: ref.read(organizationControllerProvider.notifier).load, child: Text(l10n.tryAgain)),
          ]),
        ),
      );
    } else {
      final failure = state.actionFailure;

      body = ListView(
        padding: const EdgeInsets.all(AppSpacing.l),
        children: [
          if (state.left case final left?) _LeftNotice(left: left),
          if (organization == null) ...[
            Text(l10n.orgNoneIntro, style: text.bodyLarge),
            const SizedBox(height: AppSpacing.l),
            TextField(
              controller: _code,
              enabled: !state.working,
              textCapitalization: TextCapitalization.characters,
              autocorrect: false,
              enableSuggestions: false,
              maxLength: 16,
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9 \-]')), const _UpperCase()],
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _join(),
              decoration: InputDecoration(labelText: l10n.orgCodeLabel, counterText: ''),
            ),
          ] else if (organization.waiting) ...[
            Semantics(header: true, child: Text(l10n.orgWaitingTitle(organization.name), style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
            const SizedBox(height: AppSpacing.s),
            Text(l10n.orgWaitingBody(organization.name), style: text.bodyLarge),
          ] else ...[
            Semantics(header: true, child: Text(l10n.orgMemberTitle(organization.name), style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
            const SizedBox(height: AppSpacing.s),
            Text(l10n.orgMemberBody(organization.name), style: text.bodyLarge),
          ],
          if (failure != null)
            Padding(
              padding: const EdgeInsets.only(top: AppSpacing.m),
              child: Semantics(liveRegion: true, child: Text(failureMessage(l10n, failure), style: TextStyle(color: scheme.error))),
            ),
          const SizedBox(height: AppSpacing.xl),
          if (organization == null)
            FilledButton(onPressed: state.working ? null : _join, child: Text(l10n.orgJoin))
          else
            OutlinedButton(
              onPressed: state.working ? null : () => _leave(organization),
              child: Text(organization.waiting ? l10n.orgWithdraw : l10n.orgLeave),
            ),
          if (state.working) const Padding(padding: EdgeInsets.only(top: AppSpacing.l), child: Center(child: CircularProgressIndicator())),
        ],
      );
    }

    return Scaffold(appBar: AppBar(title: Text(l10n.orgTitle)), body: SafeArea(child: body));
  }
}

/// Said once, right after leaving: what happened to the person's sessions.
class _LeftNotice extends StatelessWidget {
  const _LeftNotice({required this.left});

  final LeftOrganization left;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.xl),
      child: Semantics(
        liveRegion: true,
        container: true,
        child: Card(
          margin: EdgeInsets.zero,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.l),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(children: [
                  const Icon(Icons.check_circle_outline),
                  const SizedBox(width: AppSpacing.s),
                  Expanded(child: Text(l10n.orgLeftTitle(left.name), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800))),
                ]),
                if (left.cancelled > 0) ...[const SizedBox(height: AppSpacing.s), Text(l10n.orgLeftCancelled(left.cancelled))],
                if (left.kept > 0) ...[const SizedBox(height: AppSpacing.s), Text(l10n.orgLeftKept(left.kept))],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// A code is capitals (the server reads either), so what is typed or pasted shows as capitals at once.
class _UpperCase extends TextInputFormatter {
  const _UpperCase();

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) => newValue.copyWith(text: newValue.text.toUpperCase());
}
