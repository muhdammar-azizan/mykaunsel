import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../../counselors/presentation/counselor_labels.dart';
import '../domain/booking_preview.dart';

/// PLACEHOLDER. The real "Booking confirmed" screen has its own specification and replaces this one. It only proves the booking was made:
/// who, when, what kind, and a way home. Back goes Home (there is nothing to go back to: the booking is done).
class BookingConfirmedPlaceholderScreen extends StatelessWidget {
  const BookingConfirmedPlaceholderScreen({super.key, required this.booking});

  final CreatedBooking booking;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final text = Theme.of(context).textTheme;
    final start = malaysiaWallClock(booking.startsAt);
    final end = malaysiaWallClock(booking.endsAt);
    final place = booking.location == null ? null : l10n.inPersonIn('${booking.location!.name}, ${booking.location!.city}');

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go(AppRoutes.home);
      },
      child: Scaffold(
        appBar: AppBar(title: Text(l10n.confirmPlaceholderTitle), automaticallyImplyLeading: false),
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Semantics(liveRegion: true, header: true, child: Text(l10n.confirmPlaceholderTitle, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800))),
                const SizedBox(height: AppSpacing.m),
                Text(l10n.confirmPlaceholderWith(booking.counselorName), style: text.titleMedium),
                const SizedBox(height: AppSpacing.s),
                Text(DateFormat('EEEE, d MMMM y', locale).format(start)),
                Text(l10n.confirmTimeValue(DateFormat.jm(locale).format(start), DateFormat.jm(locale).format(end))),
                Text(place ?? booking.mode.label(l10n) ?? ''),
                const SizedBox(height: AppSpacing.l),
                Text(l10n.confirmPlaceholderNote, style: text.bodySmall),
                const Spacer(),
                SizedBox(width: double.infinity, child: FilledButton(onPressed: () => context.go(AppRoutes.home), child: Text(l10n.confirmGoHome))),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
