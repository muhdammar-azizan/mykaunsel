import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:mykaunsel/shared/format/slot_time.dart';

String fmt(String startsAt, {String now = '2026-11-02T09:00:00+08:00'}) => formatSlotStart(
      DateTime.parse(startsAt),
      now: DateTime.parse(now),
      locale: 'en',
      today: 'Today',
      tomorrow: 'Tomorrow',
    ).replaceAll(' ', ' ');

void main() {
  setUpAll(() => initializeDateFormatting('en'));

  test('today, tomorrow and later days', () {
    expect(fmt('2026-11-02T14:00:00+08:00'), 'Today, 2:00 PM');
    expect(fmt('2026-11-03T10:00:00+08:00'), 'Tomorrow, 10:00 AM');
    expect(fmt('2026-11-05T10:30:00+08:00'), 'Thu 5 Nov, 10:30 AM');
    expect(fmt('2027-01-04T08:00:00+08:00'), 'Mon 4 Jan, 8:00 AM');
  });

  test("the day is Malaysia's day, even when it is another day in UTC", () {
    // 00:30 on 3 Nov in Malaysia is 16:30 on 2 Nov UTC.
    expect(fmt('2026-11-03T00:30:00+08:00'), 'Tomorrow, 12:30 AM');
    // 07:59 on 3 Nov in Malaysia is still 2 Nov in UTC; "now" 23:30 on 2 Nov in Malaysia is 15:30 UTC.
    expect(fmt('2026-11-03T07:59:00+08:00', now: '2026-11-02T23:30:00+08:00'), 'Tomorrow, 7:59 AM');
    expect(fmt('2026-11-02T23:45:00+08:00', now: '2026-11-02T23:30:00+08:00'), 'Today, 11:45 PM');
  });

  test('the same moment written with another offset reads the same', () {
    expect(fmt('2026-11-03T02:00:00Z'), 'Tomorrow, 10:00 AM');
    expect(fmt('2026-11-03T10:00:00+08:00'), 'Tomorrow, 10:00 AM');
  });

  test('month and year boundaries', () {
    expect(fmt('2026-12-01T10:00:00+08:00', now: '2026-11-30T09:00:00+08:00'), 'Tomorrow, 10:00 AM');
    expect(fmt('2027-01-01T10:00:00+08:00', now: '2026-12-31T20:00:00+08:00'), 'Tomorrow, 10:00 AM');
  });

  test('day heading and time', () {
    expect(formatSlotDay(DateTime.parse('2026-11-03T10:00:00+08:00'), locale: 'en'), 'Tuesday 3 November');
    expect(formatSlotTime(DateTime.parse('2026-11-03T14:00:00+08:00'), locale: 'en').replaceAll(' ', ' '), '2:00 PM');
  });
}
