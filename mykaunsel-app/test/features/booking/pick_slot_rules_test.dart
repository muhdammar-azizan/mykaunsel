import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/features/booking/domain/availability.dart';
import 'package:mykaunsel/features/booking/presentation/pick_slot_state.dart';
import 'package:mykaunsel/features/counselors/domain/counselor.dart';

import '../../support/fakes.dart';

void main() {
  final now = testNow;
  const both = [SessionMode.online, SessionMode.physical];

  PickSlotState loaded(Availability answer) => PickSlotRules.afterFirstLoad(const PickSlotState(), answer, now: now);

  group('first load', () {
    test('starts on the only mode offered and the nearest day with a free time', () {
      final state = loaded(anAvailability([
        anAvailabilityDay(0, [anAvailabilitySlot('a', state: SlotState.booked)]),
        anAvailabilityDay(1, [anAvailabilitySlot('b', days: 1)]),
      ]));

      expect(state.mode, SessionMode.online);
      expect(state.selectedDate, '2026-11-03');
      expect(state.selected, isNull); // nothing is pre-selected
      expect(state.loading, isFalse);
    });

    test('with both modes it starts on the one with the earliest free time', () {
      final state = loaded(anAvailability([
        anAvailabilityDay(1, [
          anAvailabilitySlot('on', days: 1, hour: 15),
          anAvailabilitySlot('ph', days: 1, hour: 9, mode: SessionMode.physical),
        ]),
      ], modes: both));

      expect(state.mode, SessionMode.physical);
    });

    test('a tie goes to online', () {
      final state = loaded(anAvailability([
        anAvailabilityDay(1, [
          anAvailabilitySlot('ph', days: 1, mode: SessionMode.physical),
          anAvailabilitySlot('on', days: 1),
        ]),
      ], modes: both));

      expect(state.mode, SessionMode.online);
    });

    test('with nothing free it stays on the first day and reports nothing available', () {
      final state = loaded(anAvailability([anAvailabilityDay(0, [anAvailabilitySlot('a', state: SlotState.booked)])]));

      expect(state.selectedDate, '2026-11-02');
      expect(state.anyAvailable, isFalse);
    });

    test('a mode this app does not know is never offered', () {
      expect(PickSlotRules.knownModes([SessionMode.unknown, SessionMode.online]), [SessionMode.online]);
    });
  });

  group('choosing', () {
    PickSlotState twoDays() => loaded(anAvailability([
          anAvailabilityDay(1, [
            anAvailabilitySlot('a1', days: 1),
            anAvailabilitySlot('a2', days: 1, hour: 11),
            anAvailabilitySlot('ph1', days: 1, hour: 12, mode: SessionMode.physical),
          ]),
          anAvailabilityDay(2, [anAvailabilitySlot('b1', days: 2)]),
        ], modes: both));

    test('one free time at a time; picking another replaces it', () {
      var state = twoDays();
      state = PickSlotRules.selectSlot(state, state.daySlots.first);
      state = PickSlotRules.selectSlot(state, state.daySlots[1]);

      expect(state.selected!.id, 'a2');
    });

    test('a booked time does nothing', () {
      var state = loaded(anAvailability([
        anAvailabilityDay(1, [anAvailabilitySlot('x', days: 1, state: SlotState.booked), anAvailabilitySlot('y', days: 1, hour: 11)]),
      ]));
      state = PickSlotRules.selectSlot(state, state.daySlots.first);

      expect(state.selected, isNull);
    });

    test('a time of an unknown state cannot be picked', () {
      var state = loaded(anAvailability([
        anAvailabilityDay(1, [anAvailabilitySlot('x', days: 1, state: SlotState.unknown), anAvailabilitySlot('y', days: 1, hour: 11)]),
      ]));
      state = PickSlotRules.selectSlot(state, state.daySlots.first);

      expect(state.selected, isNull);
    });

    test('choosing another date clears the picked time', () {
      var state = twoDays();
      state = PickSlotRules.selectSlot(state, state.daySlots.first);
      state = PickSlotRules.selectDate(state, '2026-11-04');

      expect(state.selectedDate, '2026-11-04');
      expect(state.selected, isNull);
    });

    test('a date that is not loaded is ignored', () {
      final state = twoDays();

      expect(PickSlotRules.selectDate(state, '2030-01-01').selectedDate, state.selectedDate);
    });

    test('changing the mode clears a picked time of the other mode', () {
      var state = twoDays();
      state = PickSlotRules.selectSlot(state, state.daySlots.first);
      state = PickSlotRules.selectMode(state, SessionMode.physical);

      expect(state.mode, SessionMode.physical);
      expect(state.selected, isNull);
      expect(state.daySlots.map((s) => s.id), ['ph1']);
    });

    test('changing to a mode with nothing on this day moves to the nearest day that has something', () {
      var state = loaded(anAvailability([
        anAvailabilityDay(1, [anAvailabilitySlot('a1', days: 1)]),
        anAvailabilityDay(2, [anAvailabilitySlot('p1', days: 2, mode: SessionMode.physical)]),
      ], modes: both));
      state = PickSlotRules.selectMode(state, SessionMode.physical);

      expect(state.selectedDate, '2026-11-04');
    });

    test('a mode the counselor does not offer is ignored', () {
      final state = loaded(anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1)])]));

      expect(PickSlotRules.selectMode(state, SessionMode.physical).mode, SessionMode.online);
    });
  });

  group('refresh', () {
    test('keeps the picked time while it is still free', () {
      final first = anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1), anAvailabilitySlot('b', days: 1, hour: 11)])]);
      var state = loaded(first);
      state = PickSlotRules.selectSlot(state, state.daySlots.first);
      state = PickSlotRules.afterRefresh(state, first, now: now, days: first.days);

      expect(state.selected?.id, 'a');
      expect(state.notice, isNull);
    });

    test('drops the picked time and says so when it is gone', () {
      var state = loaded(anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1), anAvailabilitySlot('b', days: 1, hour: 11)])]));
      state = PickSlotRules.selectSlot(state, state.daySlots.first);
      final fresh = anAvailability([
        anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1, state: SlotState.booked), anAvailabilitySlot('b', days: 1, hour: 11)]),
      ]);
      state = PickSlotRules.afterRefresh(state, fresh, now: now, days: fresh.days);

      expect(state.selected, isNull);
      expect(state.notice, PickSlotNotice.selectionLost);
    });

    test('drops it too when the time has vanished from the answer', () {
      var state = loaded(anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1)])]));
      state = PickSlotRules.selectSlot(state, state.daySlots.first);
      final fresh = anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('z', days: 1, hour: 14)])]);
      state = PickSlotRules.afterRefresh(state, fresh, now: now, days: fresh.days);

      expect(state.notice, PickSlotNotice.selectionLost);
    });

    test('moves to the nearest day when the chosen day has nothing left', () {
      var state = loaded(anAvailability([
        anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1)]),
        anAvailabilityDay(2, [anAvailabilitySlot('b', days: 2)]),
      ]));
      final fresh = anAvailability([
        anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1, state: SlotState.booked)]),
        anAvailabilityDay(2, [anAvailabilitySlot('b', days: 2)]),
      ]);
      state = PickSlotRules.afterRefresh(state, fresh, now: now, days: fresh.days);

      expect(state.selectedDate, '2026-11-04');
    });
  });

  group('more days and taken times', () {
    test('later days are added once, never twice', () {
      var state = loaded(anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1)])], nextFrom: '2026-11-16'));
      state = PickSlotRules.withMoreDays(
        state,
        anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1)]), anAvailabilityDay(14, [anAvailabilitySlot('z', days: 14)])]),
      );

      expect(state.days.map((d) => d.date), ['2026-11-03', '2026-11-16']);
      expect(state.nextFrom, isNull);
    });

    test('a time the confirm step found taken shows as booked and its pick is cleared', () {
      var state = loaded(anAvailability([anAvailabilityDay(1, [anAvailabilitySlot('a', days: 1), anAvailabilitySlot('b', days: 1, hour: 11)])]));
      state = PickSlotRules.selectSlot(state, state.daySlots.first);
      state = PickSlotRules.markTaken(state, 'a');

      expect(state.selected, isNull);
      expect(state.notice, PickSlotNotice.slotTaken);
      expect(state.daySlots.first.state, SlotState.booked);
      expect(PickSlotRules.selectSlot(state, state.daySlots.first).selected, isNull);
    });
  });
}
