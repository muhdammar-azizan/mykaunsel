import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';
import 'package:mykaunsel/features/requests/domain/request_times.dart';
import 'package:mykaunsel/features/requests/domain/time_request.dart';

import '../../support/fakes.dart';

/// The days and times a person is offered to ask for, worked out from the counselor's weekly hours. The clock stands at Monday 2 November
/// 2026, 09:00 Malaysian time; the earliest bookable start is 11:00 that day.
void main() {
  List<RequestDay> days({WorkingHours? hours}) => requestDays(hours ?? aWorkingHours(), now: testNow);

  test('only days with something to ask for are listed, from today to the last bookable date', () {
    final all = days();

    expect(all.first.date, '2026-11-02');
    expect(all.map((d) => d.day.weekday).toSet(), {1, 4});
    expect(all.last.date, '2026-11-30'); // lastDate is Wednesday 2 Dec: the last Monday is 30 Nov, the last Thursday 26 Nov
    expect(all.length, 9);
  });

  test('times step by the length of the line and every one fits wholly inside its window', () {
    final monday = days().firstWhere((d) => d.date == '2026-11-09');
    final thursday = days().firstWhere((d) => d.date == '2026-11-05');

    expect(monday.times.map((t) => t.clock), ['14:00', '14:30', '15:00', '15:30']);
    expect(thursday.times.map((t) => t.clock), ['10:00', '11:00', '12:00']);
    expect(thursday.times.last.endsAt, DateTime.utc(2026, 11, 5, 5), reason: 'the last hour ends at 13:00 Malaysian time');
  });

  test('a time carries its line: length, mode and place', () {
    final monday = days().first.times.first;

    expect(monday.line.durationMinutes, 30);
    expect(monday.mode, SessionMode.physical);
    expect(monday.line.location?.city, 'Kuantan');
    expect(monday.startsAt, DateTime.utc(2026, 11, 2, 6), reason: '14:00 Malaysian time is 06:00 UTC');
  });

  test('a start sooner than the booking window allows is left out', () {
    final hours = aWorkingHours(lines: const [WorkingHoursLine(weekday: 1, from: '09:00', to: '13:00', durationMinutes: 60, mode: SessionMode.online)]);

    // Earliest start is 11:00 today: 09:00 and 10:00 are gone, 11:00 and 12:00 stay.
    expect(days(hours: hours).first.times.map((t) => t.clock), ['11:00', '12:00']);
  });

  test('a day whose times are all too soon is not listed', () {
    final hours = aWorkingHours(lines: const [WorkingHoursLine(weekday: 1, from: '09:00', to: '11:00', durationMinutes: 60, mode: SessionMode.online)]);

    expect(days(hours: hours).first.date, '2026-11-09');
  });

  test('a window that does not hold one whole time offers nothing', () {
    final hours = aWorkingHours(lines: const [WorkingHoursLine(weekday: 4, from: '10:00', to: '10:45', durationMinutes: 60, mode: SessionMode.online)]);

    expect(days(hours: hours), isEmpty);
  });

  test('no hours, no days', () {
    expect(days(hours: aWorkingHours(lines: const [])), isEmpty);
  });

  test('the date is the Malaysian date even when the phone is somewhere else', () {
    // 23:30 UTC on Sunday 1 Nov is already Monday 2 Nov 07:30 in Malaysia.
    final hours = requestDays(aWorkingHours(), now: DateTime.utc(2026, 11, 1, 23, 30));

    expect(hours.first.date, '2026-11-02');
  });
}
