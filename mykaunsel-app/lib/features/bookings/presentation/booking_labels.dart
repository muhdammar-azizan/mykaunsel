import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../l10n/app_localizations.dart';
import '../../../shared/format/slot_time.dart';
import '../../counselors/domain/counselor.dart';
import '../../counselors/presentation/counselor_labels.dart';
import '../../booking/domain/availability.dart' show SlotLocation;
import '../domain/my_booking.dart';

/// The words and the symbol for a booking's state. A state is always a word AND an icon, never colour alone.
extension BookingStateLabel on BookingState {
  String label(AppLocalizations l10n) => switch (this) {
        BookingState.confirmed => l10n.bookingStatusConfirmed,
        BookingState.completed => l10n.bookingStatusCompleted,
        BookingState.cancelled => l10n.bookingStatusCancelled,
        BookingState.noShow => l10n.bookingStatusNoShow,
        BookingState.unknown => l10n.bookingStatusUnknown,
      };

  IconData get icon => switch (this) {
        BookingState.confirmed => Icons.check_circle_outline,
        BookingState.completed => Icons.task_alt,
        BookingState.cancelled => Icons.cancel_outlined,
        BookingState.noShow => Icons.person_off_outlined,
        BookingState.unknown => Icons.info_outline,
      };
}

/// "Tomorrow, 10:00 AM – 10:50 AM" in Malaysian time, whatever the phone's own zone says.
String bookingWhen(AppLocalizations l10n, {required DateTime startsAt, required DateTime endsAt, required DateTime now, required String locale}) {
  final start = formatSlotStart(startsAt, now: now, locale: locale, today: l10n.today, tomorrow: l10n.tomorrow);

  return '$start – ${formatSlotTime(endsAt, locale: locale)}';
}

/// "Online" or "In-person · Klinik Damai, Kuantan".
String bookingType(AppLocalizations l10n, SessionMode mode, SlotLocation? location) {
  if (mode == SessionMode.physical && location != null) return l10n.inPersonIn('${location.name}, ${location.city}');

  return mode.label(l10n) ?? '';
}

/// "Tuesday, 3 November 2026" (Malaysian date).
String bookingDate(DateTime startsAt, String locale) => DateFormat('EEEE, d MMMM y', locale).format(malaysiaWallClock(startsAt));
