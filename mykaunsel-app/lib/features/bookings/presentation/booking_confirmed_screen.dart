import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../../booking/domain/booking_preview.dart';
import 'booking_labels.dart';
import 'my_bookings_controller.dart';

/// "Booking confirmed": the booking was made. What was booked, then "View booking" and "Back to home". The phone's Back goes Home too: there is
/// nothing to go back to, the booking is done. (The "request sent" variant for Mode B comes with Mode B.)
class BookingConfirmedScreen extends ConsumerStatefulWidget {
  const BookingConfirmedScreen({super.key, required this.booking});

  final CreatedBooking booking;

  @override
  ConsumerState<BookingConfirmedScreen> createState() => _BookingConfirmedScreenState();
}

class _BookingConfirmedScreenState extends ConsumerState<BookingConfirmedScreen> {
  @override
  void initState() {
    super.initState();
    // The Bookings tab must show the new booking.
    Future.microtask(() => ref.invalidate(myBookingsControllerProvider));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final locale = Localizations.localeOf(context).toLanguageTag();
    final text = Theme.of(context).textTheme;
    final scheme = Theme.of(context).colorScheme;
    final booking = widget.booking;
    final start = malaysiaWallClock(booking.startsAt);
    final end = malaysiaWallClock(booking.endsAt);

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) context.go(AppRoutes.home);
      },
      child: Scaffold(
        body: SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.xl),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SizedBox(height: AppSpacing.xl),
                ExcludeSemantics(child: Icon(Icons.check_circle, size: 56, color: scheme.primary)),
                const SizedBox(height: AppSpacing.l),
                Semantics(liveRegion: true, header: true, child: Text(l10n.bookingConfirmedTitle, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800))),
                const SizedBox(height: AppSpacing.xs),
                Text(l10n.bookingConfirmedSubtitle, style: text.bodyLarge),
                const SizedBox(height: AppSpacing.xl),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(AppSpacing.l),
                  decoration: BoxDecoration(color: scheme.cardFill, borderRadius: AppRadii.cardRadius, border: Border.all(color: scheme.cardBorder)),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l10n.bookingConfirmedWith(booking.counselorName), style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                      const SizedBox(height: AppSpacing.s),
                      Text(bookingDate(booking.startsAt, locale)),
                      Text(l10n.confirmTimeValue(DateFormat.jm(locale).format(start), DateFormat.jm(locale).format(end))),
                      Text(bookingType(l10n, booking.mode, booking.location)),
                    ],
                  ),
                ),
                const Spacer(),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(onPressed: () => context.go(AppRoutes.bookingPath(booking.id)), child: Text(l10n.bookingViewBooking)),
                ),
                const SizedBox(height: AppSpacing.s),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(onPressed: () => context.go(AppRoutes.home), child: Text(l10n.bookingBackHome)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
