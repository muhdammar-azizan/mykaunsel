import 'package:flutter/material.dart';

import '../../l10n/app_localizations.dart';
import '../password_rules.dart';

/// What a password still needs, ticking off as the person types. Each line is read out in full by a screen reader
/// ("An upper-case letter, done"), so the state is not only a colour.
class PasswordChecklist extends StatelessWidget {
  const PasswordChecklist({super.key, required this.password});

  final String password;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final rules = PasswordRules.of(password);
    final text = Theme.of(context).textTheme;

    final items = [
      (l10n.ruleLength, rules.hasMinLength),
      (l10n.ruleUpper, rules.hasUpper),
      (l10n.ruleLower, rules.hasLower),
      (l10n.ruleNumber, rules.hasNumber),
      (l10n.ruleSymbol, rules.hasSymbol),
    ];

    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 12, 4, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.passwordMustHave, style: text.bodySmall?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          for (final (label, met) in items)
            Semantics(
              label: met ? l10n.ruleMet(label) : l10n.ruleNotMet(label),
              excludeSemantics: true,
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Row(
                  children: [
                    Icon(met ? Icons.check_circle : Icons.radio_button_unchecked, size: 18, color: met ? Colors.green.shade700 : Colors.grey),
                    const SizedBox(width: 8),
                    Expanded(child: Text(label, style: text.bodySmall)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
