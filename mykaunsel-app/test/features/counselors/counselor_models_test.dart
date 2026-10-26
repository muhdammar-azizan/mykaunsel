import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';
import 'package:mykaunsel/features/counselors/domain/counselor_query.dart';

Map<String, dynamic> fixture(String name) => jsonDecode(File('test/fixtures/$name').readAsStringSync()) as Map<String, dynamic>;

/// The fixtures are what the real Laravel server answered (with the sample data). If the server changes a field, these fail.
void main() {
  test('every card in the real list answer is understood', () {
    final cards = (fixture('counselors_list.json')['data'] as List).map((j) => Counselor.fromJson(j as Map<String, dynamic>)).toList();

    expect(cards, isNotEmpty);

    final aina = cards.firstWhere((c) => c.name.contains('Aina'));
    expect(aina.id, hasLength(26));
    expect(aina.verified, isTrue);
    expect(aina.contexts.single.type, ContextType.organization);
    expect(aina.contexts.single.organization, 'Klinik Kaunseling Damai');
    expect(aina.specializations, isNotEmpty);
    expect(aina.specializationsCount, aina.specializations.length);
    expect(aina.languages, contains('English'));
    expect(aina.sessionModes, containsAll([SessionMode.online, SessionMode.physical]));
    expect(aina.locations.single.city, 'Kuantan');
    expect(aina.acceptsRequests, isNull); // cards do not carry it
    expect(aina.upcomingSlots, isEmpty);
  });

  test('a slot keeps Malaysian time: +08:00 is read as the right moment', () {
    final aina = Counselor.fromJson((fixture('counselor_detail.json')['data']) as Map<String, dynamic>);
    final next = aina.nextAvailable!;

    expect(next.startsAt.isUtc, isTrue);
    expect(next.startsAt.hour, anyOf(2, 6)); // 10:00 or 14:00 at +08:00 is 02:00 / 06:00 UTC
    expect(next.durationMinutes, 60);
    expect(next.sessionMode, SessionMode.online);
    expect(next.city, isNull);
    expect(next.context!.type, ContextType.organization);
  });

  test('the real profile answer has the extras and the in-person slots name only a city and state', () {
    final aina = Counselor.fromJson((fixture('counselor_detail.json')['data']) as Map<String, dynamic>);

    expect(aina.acceptsRequests, isTrue);
    expect(aina.upcomingSlots, hasLength(10));

    final inPerson = aina.upcomingSlots.where((s) => s.sessionMode == SessionMode.physical);
    expect(inPerson, isNotEmpty);
    expect(inPerson.every((s) => s.city == 'Kuantan' && s.state == 'Pahang'), isTrue);
  });

  test('the real filter answer', () {
    final options = CounselorFilterOptions.fromJson(fixture('counselors_filters.json')['data'] as Map<String, dynamic>);

    expect(options.specializations, contains('Trauma'));
    expect(options.languages, contains('Mandarin'));
    expect(options.sessionModes, [SessionMode.online, SessionMode.physical]);
    expect(options.states, ['Pahang']);
  });

  test('a kind of session or context this app does not know yet does not crash it', () {
    final c = Counselor.fromJson({
      'id': 'abc',
      'name': 'Future Counselor',
      'contexts': [
        {'type': 'franchise', 'organization': 'X'},
      ],
      'session_modes': ['online', 'telepathy'],
      'next_available': {'starts_at': '2027-01-01T10:00:00+08:00', 'duration_minutes': 30, 'session_mode': 'hologram', 'city': null, 'state': null, 'context': null},
      'a_field_added_later': 123,
    });

    expect(c.contexts.single.type, ContextType.unknown);
    expect(c.sessionModes, [SessionMode.online, SessionMode.unknown]);
    expect(c.nextAvailable!.sessionMode, SessionMode.unknown);
    expect(c.verified, isTrue); // defaults for anything missing
    expect(c.specializations, isEmpty);
  });

  group('query', () {
    test('only what is set is sent, and page 1 is not sent', () {
      expect(const CounselorQuery().toParams(), isEmpty);
      expect(const CounselorQuery(q: '').toParams(), isEmpty);
      expect(
        const CounselorQuery(q: 'aina', specialization: 'Trauma', language: 'English', mode: SessionMode.physical, state: 'Pahang').toParams(page: 3),
        {'q': 'aina', 'specialization': 'Trauma', 'language': 'English', 'mode': 'physical', 'state': 'Pahang', 'page': 3},
      );
    });

    test('filters are counted without the search words', () {
      expect(const CounselorQuery(q: 'aina').activeFilterCount, 0);
      expect(const CounselorQuery(q: 'aina').isFiltering, isTrue);
      expect(const CounselorQuery(language: 'English', state: 'Pahang').activeFilterCount, 2);
      expect(const CounselorQuery().isFiltering, isFalse);
    });

    test('two equal queries are equal (so the same search is not run twice)', () {
      expect(const CounselorQuery(q: 'a', language: 'English'), const CounselorQuery(q: 'a', language: 'English'));
      expect(const CounselorQuery(q: 'a'), isNot(const CounselorQuery(q: 'b')));
    });
  });
}
