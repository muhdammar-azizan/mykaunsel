import '../../../shared/format/slot_time.dart';
import '../../counselors/domain/counselor.dart';
import 'time_request.dart';

/// One start time a person can ask for, with the weekly-hours line it comes from (which decides its length, mode and place).
class RequestTime {
  const RequestTime({required this.minutes, required this.startsAt, required this.line});

  /// Minutes after midnight, Malaysian time.
  final int minutes;

  /// The instant it starts.
  final DateTime startsAt;
  final WorkingHoursLine line;

  /// "HH:mm", what the server is sent.
  String get clock => '${(minutes ~/ 60).toString().padLeft(2, '0')}:${(minutes % 60).toString().padLeft(2, '0')}';

  DateTime get endsAt => startsAt.add(Duration(minutes: line.durationMinutes));

  SessionMode get mode => line.mode;
}

/// One day with something to ask for.
class RequestDay {
  const RequestDay({required this.date, required this.day, required this.times});

  /// "2026-11-05", what the server is sent.
  final String date;

  /// The year, month and day as a plain value to read the weekday and the day number from (it is NOT an instant).
  final DateTime day;
  final List<RequestTime> times;
}

/// The days (from today, Malaysian time, to the last bookable date) and the times on them that a person can ask for: every start that fits
/// wholly inside a weekly-hours line, in steps of that line's length, and not sooner than the booking window allows. The server decides in
/// the end (the counselor's calendar is not known here); this only offers what it could accept. Pure, so it is tested without a screen.
List<RequestDay> requestDays(WorkingHours hours, {required DateTime now}) {
  final today = malaysiaWallClock(now);
  final last = _parse(hours.bookingWindow.lastDate);
  final days = <RequestDay>[];

  for (var day = DateTime.utc(today.year, today.month, today.day); !day.isAfter(last); day = day.add(const Duration(days: 1))) {
    final times = <RequestTime>[];

    for (final line in hours.hours.where((l) => l.weekday == day.weekday)) {
      for (var at = line.fromMinutes; at + line.durationMinutes <= line.toMinutes; at += line.durationMinutes) {
        // Malaysia is UTC+8 all year: the wall clock minus eight hours is the instant.
        final start = DateTime.utc(day.year, day.month, day.day, at ~/ 60, at % 60).subtract(const Duration(hours: 8));

        if (!start.isBefore(hours.bookingWindow.earliestStart)) times.add(RequestTime(minutes: at, startsAt: start, line: line));
      }
    }

    if (times.isNotEmpty) {
      times.sort((a, b) => a.minutes.compareTo(b.minutes));
      days.add(RequestDay(date: _format(day), day: day, times: times));
    }
  }

  return days;
}

DateTime _parse(String date) {
  final parts = date.split('-').map(int.parse).toList();

  return DateTime.utc(parts[0], parts[1], parts[2]);
}

String _format(DateTime day) => '${day.year.toString().padLeft(4, '0')}-${day.month.toString().padLeft(2, '0')}-${day.day.toString().padLeft(2, '0')}';
