import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/auth_scaffold.dart';
import 'account_controllers.dart';

/// Name and time zone. The email address is shown but not changed here (it needs a confirmation step: the website).
class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late String _timezone;
  late final String _savedName;
  late final String _savedTimezone;

  @override
  void initState() {
    super.initState();
    final session = ref.read(sessionControllerProvider);
    final user = session is SessionSignedIn ? session.user : null;

    _savedName = user?.name ?? '';
    _savedTimezone = user?.timezone ?? 'Asia/Kuala_Lumpur';
    _name = TextEditingController(text: _savedName)..addListener(() => setState(() {}));
    _timezone = _savedTimezone;
  }

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  bool get _changed => _name.text.trim() != _savedName || _timezone != _savedTimezone;

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final saved = await ref.read(profileControllerProvider.notifier).save(name: _name.text, timezone: _timezone);

    if (saved && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AppLocalizations.of(context).profileSaved)));
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final form = ref.watch(profileControllerProvider);
    final session = ref.watch(sessionControllerProvider);
    final email = session is SessionSignedIn ? session.user.email : '';

    // A zone this app does not list (set on the web) is still shown, so saving never silently changes it.
    final zones = {...supportedTimezones, if (!supportedTimezones.containsKey(_timezone)) _timezone: _timezone};

    return Scaffold(
      appBar: AppBar(title: Text(l10n.profileTitle)),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    FailureBanner(failure: form.failure, hideFieldErrors: const ['name', 'timezone']),
                    TextFormField(
                      controller: _name,
                      enabled: !form.submitting,
                      textCapitalization: TextCapitalization.words,
                      textInputAction: TextInputAction.done,
                      autofillHints: const [AutofillHints.name],
                      validator: (value) => (value ?? '').trim().length < 2 ? l10n.nameRequired : null,
                      decoration: InputDecoration(labelText: l10n.nameLabel, errorText: form.fieldError('name')),
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      initialValue: email,
                      enabled: false,
                      decoration: InputDecoration(labelText: l10n.emailLabel, helperText: l10n.profileEmailNote, helperMaxLines: 3),
                    ),
                    const SizedBox(height: 16),
                    DropdownButtonFormField<String>(
                      initialValue: _timezone,
                      isExpanded: true,
                      decoration: InputDecoration(labelText: l10n.timezoneLabel, errorText: form.fieldError('timezone')),
                      items: [for (final zone in zones.entries) DropdownMenuItem(value: zone.key, child: Text(zone.value, overflow: TextOverflow.ellipsis))],
                      onChanged: form.submitting ? null : (value) => setState(() => _timezone = value ?? _timezone),
                    ),
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: form.submitting || !_changed ? null : _save,
                      child: form.submitting
                          ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                          : Text(l10n.profileSave),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
