import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/password_field.dart';
import 'account_controllers.dart';

/// Account > Delete account. It says what goes, what ends and what stays BEFORE asking for the password and the word DELETE, because it can not
/// be undone. The server does the work (docs/ACCOUNT_DELETION.md); when it is done this phone signs itself out and the router leaves the page.
class DeleteAccountScreen extends ConsumerStatefulWidget {
  const DeleteAccountScreen({super.key});

  @override
  ConsumerState<DeleteAccountScreen> createState() => _DeleteAccountScreenState();
}

class _DeleteAccountScreenState extends ConsumerState<DeleteAccountScreen> {
  final _password = TextEditingController();
  final _word = TextEditingController();

  @override
  void dispose() {
    _password.dispose();
    _word.dispose();
    super.dispose();
  }

  Future<void> _delete() async {
    await ref.read(deleteAccountControllerProvider.notifier).delete(_password.text);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final scheme = Theme.of(context).colorScheme;
    final form = ref.watch(deleteAccountControllerProvider);
    final failure = form.failure;
    final passwordError = form.fieldError('current_password');
    // A wrong password is shown under its field; everything else (a counselor's account, too many tries, no connection) above the button.
    final general = failure != null && passwordError == null && failure is! ValidationFailure ? failureMessage(l10n, failure) : null;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.deleteAccountTitle)),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(AppSpacing.xl),
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Semantics(header: true, child: Text(l10n.deleteAccountWarning, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800, color: scheme.error))),
                  const SizedBox(height: AppSpacing.l),
                  _Section(title: l10n.deleteErasedTitle, body: l10n.deleteErasedBody),
                  _Section(title: l10n.deleteEndedTitle, body: l10n.deleteEndedBody),
                  _Section(title: l10n.deleteKeptTitle, body: l10n.deleteKeptBody),
                  const SizedBox(height: AppSpacing.m),
                  PasswordField(
                    controller: _password,
                    label: l10n.currentPasswordLabel,
                    enabled: !form.submitting,
                    textInputAction: TextInputAction.next,
                    errorText: passwordError,
                  ),
                  const SizedBox(height: AppSpacing.l),
                  TextField(
                    controller: _word,
                    enabled: !form.submitting,
                    autocorrect: false,
                    enableSuggestions: false,
                    textCapitalization: TextCapitalization.characters,
                    inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z]')), const _UpperCase()],
                    decoration: InputDecoration(labelText: l10n.deleteTypeLabel, helperText: l10n.deleteTypeHelp),
                  ),
                  if (general != null)
                    Padding(
                      padding: const EdgeInsets.only(top: AppSpacing.m),
                      child: Semantics(liveRegion: true, child: Text(general, style: TextStyle(color: scheme.error))),
                    ),
                  const SizedBox(height: AppSpacing.xl),
                  ListenableBuilder(
                    listenable: Listenable.merge([_password, _word]),
                    builder: (context, _) => FilledButton(
                      style: FilledButton.styleFrom(backgroundColor: scheme.error, foregroundColor: scheme.onError),
                      onPressed: form.submitting || _password.text.isEmpty || _word.text != 'DELETE' ? null : _delete,
                      child: form.submitting
                          ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5))
                          : Text(l10n.deleteAccountButton),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.m),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: AppSpacing.xs),
          Text(body, style: text.bodyMedium),
        ],
      ),
    );
  }
}

/// The word is typed in capitals, so what is typed shows as capitals at once.
class _UpperCase extends TextInputFormatter {
  const _UpperCase();

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) => newValue.copyWith(text: newValue.text.toUpperCase());
}
