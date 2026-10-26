import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../core/providers.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/last_used.dart';
import '../../../shared/widgets/password_field.dart';
import '../domain/signed_in_device.dart';
import 'account_controllers.dart';

/// The phones signed in to this account, with a way to sign out the ones that are not this phone (lost phone, an old one).
class DevicesScreen extends ConsumerWidget {
  const DevicesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final devices = ref.watch(devicesProvider);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.devicesTitle)),
      body: SafeArea(
        child: devices.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(error is AppFailure ? failureMessage(l10n, error) : l10n.somethingWentWrongBody, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton(onPressed: () => ref.invalidate(devicesProvider), child: Text(l10n.tryAgain)),
                ],
              ),
            ),
          ),
          data: (list) => RefreshIndicator(
            onRefresh: () => ref.refresh(devicesProvider.future),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(24),
              children: [
                Text(l10n.devicesNote, style: Theme.of(context).textTheme.bodyMedium),
                const SizedBox(height: 16),
                for (final device in list) _DeviceTile(device: device),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DeviceTile extends ConsumerWidget {
  const _DeviceTile({required this.device});

  final SignedInDevice device;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final scheme = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    return Card(
      elevation: 0,
      color: Colors.white,
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: BorderSide(color: scheme.onSurface.withValues(alpha: .1))),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.smartphone, color: device.current ? scheme.primary : scheme.onSurface.withValues(alpha: .6)),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(device.name, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 2),
                  if (device.current)
                    Text(l10n.thisPhone, style: text.bodySmall?.copyWith(color: scheme.primary, fontWeight: FontWeight.w700))
                  else
                    Text(formatLastUsed(l10n, device.lastUsedAt, now: ref.watch(clockProvider)(), locale: Localizations.localeOf(context).toLanguageTag()), style: text.bodySmall),
                ],
              ),
            ),
            if (!device.current) TextButton(onPressed: () => _confirmSignOut(context, ref), child: Text(l10n.deviceSignOut)),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmSignOut(BuildContext context, WidgetRef ref) async {
    final signedOut = await showDialog<bool>(context: context, builder: (context) => _PasswordDialog(device: device));

    if (signedOut == true && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppLocalizations.of(context).deviceSignedOut)));
    }
  }
}

/// Asks for the password, and signs the device out. Stays open with the reason when it did not work.
class _PasswordDialog extends ConsumerStatefulWidget {
  const _PasswordDialog({required this.device});

  final SignedInDevice device;

  @override
  ConsumerState<_PasswordDialog> createState() => _PasswordDialogState();
}

class _PasswordDialogState extends ConsumerState<_PasswordDialog> {
  final _password = TextEditingController();
  bool _busy = false;
  AppFailure? _failure;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_password.text.isEmpty || _busy) {
      return;
    }

    setState(() {
      _busy = true;
      _failure = null;
    });

    try {
      await revokeDevice(ref, widget.device.id, _password.text);
      if (mounted) Navigator.pop(context, true);
    } on NotFoundFailure {
      // Already gone (signed out from somewhere else): the list is simply refreshed.
      ref.invalidate(devicesProvider);
      if (mounted) Navigator.pop(context, true);
    } on AppFailure catch (failure) {
      if (mounted) {
        setState(() {
          _busy = false;
          _failure = failure;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final failure = _failure;
    final wrongPassword = failure is ValidationFailure ? failure.errorFor('current_password') : null;

    return AlertDialog(
      title: Text(l10n.deviceSignOutTitle(widget.device.name)),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.deviceSignOutBody),
            const SizedBox(height: 16),
            PasswordField(controller: _password, label: l10n.passwordLabel, enabled: !_busy, errorText: wrongPassword, onSubmitted: (_) => _submit()),
            if (failure != null && wrongPassword == null)
              Padding(padding: const EdgeInsets.only(top: 12), child: Text(failureMessage(l10n, failure), style: TextStyle(color: Theme.of(context).colorScheme.error))),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: _busy ? null : () => Navigator.pop(context, false), child: Text(l10n.cancel)),
        FilledButton(
          onPressed: _busy ? null : _submit,
          child: _busy ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white)) : Text(l10n.deviceSignOut),
        ),
      ],
    );
  }
}
