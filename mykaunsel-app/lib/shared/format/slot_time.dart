import 'package:intl/intl.dart';

/// Malaysia has no daylight saving time: always UTC+8.
const _malaysiaOffset = Duration(hours: 8);

/// The wall-clock time in Malaysia for an instant, as a plain DateTime to read fields from (it is NOT a real UTC value).
DateTime malaysiaWallClock(DateTime instant) => instant.toUtc().add(_malaysiaOffset);

/// "Today, 2:00 PM", "Tomorrow, 10:00 AM" or "Mon 26 Oct, 10:00 AM" — always in Malaysian time, because that is where
/// the counselor is (and where an in-person session takes place), whatever the phone's own clock says.
String formatSlotStart(DateTime startsAt, {required DateTime now, required String locale, required String today, required String tomorrow}) {
  final start = malaysiaWallClock(startsAt);
  final here = malaysiaWallClock(now);
  final days = DateTime.utc(start.year, start.month, start.day).difference(DateTime.utc(here.year, here.month, here.day)).inDays;
  final time = DateFormat.jm(locale).format(start);

  if (days == 0) return '$today, $time';
  if (days == 1) return '$tomorrow, $time';

  return '${DateFormat('EEE d MMM', locale).format(start)}, $time';
}

/// "Mon 26 Oct" — to group a counselor's times by day.
String formatSlotDay(DateTime startsAt, {required String locale}) => DateFormat('EEEE d MMMM', locale).format(malaysiaWallClock(startsAt));

/// "10:00 AM".
String formatSlotTime(DateTime startsAt, {required String locale}) => DateFormat.jm(locale).format(malaysiaWallClock(startsAt));
