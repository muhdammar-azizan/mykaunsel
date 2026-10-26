import 'package:flutter/material.dart';

import '../../core/error/app_failure.dart';
import '../../core/error/failure_message.dart';
import '../../l10n/app_localizations.dart';

/// The page frame of every account screen: logo, title, the form, scrolls when the keyboard is open, and stays a
/// readable width on a tablet.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({super.key, required this.title, this.subtitle, required this.children, this.showBack = false});

  final String title;
  final String? subtitle;
  final List<Widget> children;
  final bool showBack;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Scaffold(
      appBar: showBack ? AppBar() : null,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Align(child: Image.asset('assets/images/mykaunsel-icon.png', height: 56, semanticLabel: 'MyKaunsel')),
                  const SizedBox(height: 20),
                  Semantics(
                    header: true,
                    child: Text(title, textAlign: TextAlign.center, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 8),
                    Text(subtitle!, textAlign: TextAlign.center, style: text.bodyLarge),
                  ],
                  const SizedBox(height: 24),
                  ...children,
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// A red box with the reason the last attempt failed. Announced to screen readers when it appears.
/// Field-level validation messages are shown under their field instead, so those are not repeated here.
class FailureBanner extends StatelessWidget {
  const FailureBanner({super.key, required this.failure, this.hideFieldErrors = const []});

  final AppFailure? failure;

  /// Fields whose own message is already shown under the field.
  final List<String> hideFieldErrors;

  @override
  Widget build(BuildContext context) {
    final f = failure;

    if (f == null) {
      return const SizedBox.shrink();
    }

    // A validation failure whose messages are all shown under their fields needs no banner.
    if (f is ValidationFailure && f.fieldErrors.isNotEmpty && f.fieldErrors.keys.every(hideFieldErrors.contains)) {
      return const SizedBox.shrink();
    }

    final scheme = Theme.of(context).colorScheme;

    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Semantics(
        liveRegion: true,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: scheme.error.withValues(alpha: .08),
            border: Border.all(color: scheme.error.withValues(alpha: .35)),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Text(failureMessage(AppLocalizations.of(context), f), style: TextStyle(color: scheme.error, fontWeight: FontWeight.w600)),
        ),
      ),
    );
  }
}
