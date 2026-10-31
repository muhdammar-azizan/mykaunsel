import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../core/providers.dart';
import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/submit_state.dart';
import '../../../shared/widgets/auth_scaffold.dart';
import '../../platform_status/domain/platform_status.dart';

/// Which kinds of sign-up are open right now, asked fresh each time the page opens (a Platform Admin may have changed it). A kind the server does
/// not mention is taken as open; the server still refuses at the end if it is not.
final registrationStatusProvider = FutureProvider.autoDispose<Map<String, RegistrationStatus>>((ref) async {
  final status = await ref.read(platformStatusApiProvider).fetch();

  return status.registration;
});

/// What asking for the sign-up link did. [sent] is the same whether or not the address belongs to anyone.
class SignupLinkState {
  const SignupLinkState({this.submit = SubmitState.idle, this.sent = false});

  final SubmitState submit;
  final bool sent;
}

final signupLinkControllerProvider = NotifierProvider.autoDispose<SignupLinkController, SignupLinkState>(SignupLinkController.new);

class SignupLinkController extends Notifier<SignupLinkState> {
  @override
  SignupLinkState build() => const SignupLinkState();

  /// [type] is `organization` or `counselor` (those accounts are made on a computer).
  Future<void> send(String type, String email) async {
    if (state.submit.submitting) return;

    state = const SignupLinkState(submit: SubmitState.busy);

    try {
      await ref.read(authApiProvider).requestSignupLink(type: type, email: email.trim().toLowerCase());

      if (ref.mounted) state = const SignupLinkState(sent: true);
    } on AppFailure catch (failure) {
      if (ref.mounted) state = SignupLinkState(submit: SubmitState(failure: failure));
    }
  }
}

/// "Choose how to sign up". A person signs up in the app as an individual; an organization or a counselor is sent a link to finish on a computer.
/// A kind that is closed says so, with the Platform Admin's own words, here as on every other sign-up page.
class ChooseAccountTypeScreen extends ConsumerWidget {
  const ChooseAccountTypeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final status = ref.watch(registrationStatusProvider);

    return AuthScaffold(
      title: l10n.chooseTypeTitle,
      subtitle: l10n.chooseTypeSubtitle,
      showBack: true,
      children: status.when(
        loading: () => const [Center(child: CircularProgressIndicator())],
        error: (error, _) => [
          Text(error is AppFailure ? failureMessage(l10n, error) : l10n.somethingWentWrongBody, textAlign: TextAlign.center),
          const SizedBox(height: 24),
          FilledButton(onPressed: () => ref.invalidate(registrationStatusProvider), child: Text(l10n.tryAgain)),
        ],
        data: (all) {
          RegistrationStatus of(String key) => all[key] ?? const RegistrationStatus(open: true);

          return [
            _TypeCard(
              icon: Icons.person_outline,
              title: l10n.chooseTypeIndividual,
              body: l10n.chooseTypeIndividualBody,
              closed: !of('individual').open,
              onTap: () => of('individual').open ? context.push(AppRoutes.signUp) : _showClosed(context, of('individual')),
            ),
            const SizedBox(height: AppSpacing.m),
            _TypeCard(
              icon: Icons.apartment_outlined,
              title: l10n.chooseTypeOrganization,
              body: l10n.chooseTypeOrganizationBody,
              closed: !of('organization').open,
              onTap: () => of('organization').open ? _showLinkSheet(context, 'organization') : _showClosed(context, of('organization')),
            ),
            const SizedBox(height: AppSpacing.m),
            _TypeCard(
              icon: Icons.psychology_alt_outlined,
              title: l10n.chooseTypeCounselor,
              body: l10n.chooseTypeCounselorBody,
              closed: !of('counselor').open,
              onTap: () => of('counselor').open ? _showLinkSheet(context, 'counselor') : _showClosed(context, of('counselor')),
            ),
            const SizedBox(height: AppSpacing.l),
            Wrap(
              alignment: WrapAlignment.center,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [Text(l10n.alreadyHaveAccount), TextButton(onPressed: () => context.go(AppRoutes.signIn), child: Text(l10n.signInLink))],
            ),
          ];
        },
      ),
    );
  }

  void _showClosed(BuildContext context, RegistrationStatus status) {
    final l10n = AppLocalizations.of(context);

    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) => Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.xl, 0, AppSpacing.xl, AppSpacing.xl),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Semantics(header: true, child: Text(l10n.signUpClosedTitle, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
            const SizedBox(height: AppSpacing.m),
            Text(status.message ?? ''),
            const SizedBox(height: AppSpacing.l),
            SizedBox(width: double.infinity, child: FilledButton(onPressed: () => Navigator.pop(context), child: Text(l10n.bookingCancelOk))),
          ],
        ),
      ),
    );
  }

  void _showLinkSheet(BuildContext context, String type) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => _LinkSheet(type: type),
    );
  }
}

class _TypeCard extends StatelessWidget {
  const _TypeCard({required this.icon, required this.title, required this.body, required this.closed, required this.onTap});

  final IconData icon;
  final String title;
  final String body;
  final bool closed;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    return Material(
      color: closed ? scheme.inactiveFill : scheme.cardFill,
      shape: RoundedRectangleBorder(borderRadius: AppRadii.cardRadius, side: BorderSide(color: scheme.cardBorder)),
      child: InkWell(
        borderRadius: AppRadii.cardRadius,
        onTap: onTap,
        child: Semantics(
          button: true,
          excludeSemantics: true,
          label: [title, body, if (closed) l10n.chooseTypeClosed].join(', '),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: AppSizes.minTouch),
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.l),
              child: Row(
                children: [
                  Icon(icon, size: 32, color: closed ? scheme.disabledText : scheme.primary),
                  const SizedBox(width: AppSpacing.l),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(title, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                        const SizedBox(height: AppSpacing.xs),
                        Text(body, style: text.bodyMedium),
                        if (closed)
                          Padding(
                            padding: const EdgeInsets.only(top: AppSpacing.s),
                            child: Row(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.lock_outline, size: AppSizes.icon - 4), const SizedBox(width: AppSpacing.xs), Flexible(child: Text(l10n.chooseTypeClosed, style: text.labelLarge))]),
                          ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// "Sign up on a computer": an email address, and the link is sent there. The same words afterwards whoever the address belongs to.
class _LinkSheet extends ConsumerStatefulWidget {
  const _LinkSheet({required this.type});

  final String type;

  @override
  ConsumerState<_LinkSheet> createState() => _LinkSheetState();
}

class _LinkSheetState extends ConsumerState<_LinkSheet> {
  static final _emailPattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    await ref.read(signupLinkControllerProvider.notifier).send(widget.type, _email.text);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final state = ref.watch(signupLinkControllerProvider);
    final busy = state.submit.submitting;
    final failure = state.submit.failure;

    return Padding(
      padding: EdgeInsets.fromLTRB(AppSpacing.xl, 0, AppSpacing.xl, AppSpacing.xl + MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: state.sent
              ? [
                  Semantics(header: true, liveRegion: true, child: Text(l10n.signupLinkSentTitle, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
                  const SizedBox(height: AppSpacing.m),
                  Text(l10n.signupLinkSentBody),
                  const SizedBox(height: AppSpacing.xl),
                  FilledButton(onPressed: () => Navigator.pop(context), child: Text(l10n.signupLinkDone)),
                ]
              : [
                  Semantics(header: true, child: Text(l10n.signupLinkTitle, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800))),
                  const SizedBox(height: AppSpacing.m),
                  Text(widget.type == 'organization' ? l10n.signupLinkOrganizationBody : l10n.signupLinkCounselorBody),
                  const SizedBox(height: AppSpacing.l),
                  if (failure != null) ...[
                    Semantics(liveRegion: true, child: Text(failureMessage(l10n, failure), style: TextStyle(color: Theme.of(context).colorScheme.error))),
                    const SizedBox(height: AppSpacing.m),
                  ],
                  Form(
                    key: _formKey,
                    child: TextFormField(
                      controller: _email,
                      enabled: !busy,
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      enableSuggestions: false,
                      autofillHints: const [AutofillHints.email],
                      validator: (value) {
                        final entered = (value ?? '').trim();

                        if (entered.isEmpty) return l10n.emailRequired;

                        return _emailPattern.hasMatch(entered) ? null : l10n.emailInvalid;
                      },
                      onFieldSubmitted: (_) => _send(),
                      decoration: InputDecoration(labelText: l10n.emailLabel),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.l),
                  FilledButton(onPressed: busy ? null : _send, child: Text(busy ? l10n.signupLinkSending : l10n.signupLinkSend)),
                ],
        ),
      ),
    );
  }
}
