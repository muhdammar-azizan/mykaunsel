import 'package:flutter_riverpod/flutter_riverpod.dart';

/// "That time was just taken": said by the confirm screen, heard by the picker of the same counselor, which then dims the time and asks for
/// another. A signal (not a value handed back by `pop`) because the picker may be below the confirm screen without having opened it: after a
/// sign-in the address opens both. [count] makes two signals about the same time two different events.
typedef SlotTaken = ({String counselorId, String slotId, int count});

class SlotTakenSignal extends Notifier<SlotTaken?> {
  @override
  SlotTaken? build() => null;

  void clear() => state = null;

  void say(String counselorId, String slotId) => state = (counselorId: counselorId, slotId: slotId, count: (state?.count ?? 0) + 1);
}

final slotTakenProvider = NotifierProvider<SlotTakenSignal, SlotTaken?>(SlotTakenSignal.new);
