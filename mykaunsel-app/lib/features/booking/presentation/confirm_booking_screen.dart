import 'package:flutter/material.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import 'booking_args.dart';

/// Placeholder until the "Confirm booking" step is built: shows what was picked and goes back. It books nothing.
class ConfirmBookingScreen extends StatelessWidget {
  const ConfirmBookingScreen({super.key, required this.args});

  final ConfirmBookingArgs args;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();

    return Scaffold(
      appBar: AppBar(title: Text(l10n.confirmBookingTitle)),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(args.counselorName, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: AppSpacing.s),
              Text('${formatSlotDay(args.startsAt, locale: locale)}, ${formatSlotTime(args.startsAt, locale: locale)}'),
              const SizedBox(height: AppSpacing.l),
              Text(l10n.confirmBookingComingSoon),
            ],
          ),
        ),
      ),
    );
  }
}
