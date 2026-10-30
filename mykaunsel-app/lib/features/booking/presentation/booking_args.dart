import '../../counselors/domain/counselor.dart';
import '../domain/availability.dart';

/// What the counselor's page already knows, passed to "Pick a slot" so the name and badge are on screen at once.
class BookingCounselorSummary {
  const BookingCounselorSummary({required this.id, required this.name, this.verified = true});

  final String id;
  final String name;
  final bool verified;

  factory BookingCounselorSummary.fromCounselor(Counselor counselor) =>
      BookingCounselorSummary(id: counselor.id, name: counselor.name, verified: counselor.verified);
}

/// What "Pick a slot" hands to "Confirm booking": which counselor and slot, and a snapshot of the time for display. Picking makes no
/// network write; the confirm screen checks everything again.
class ConfirmBookingArgs {
  const ConfirmBookingArgs({
    required this.counselorId,
    required this.counselorName,
    required this.slotId,
    required this.mode,
    required this.startsAt,
    required this.durationMinutes,
    this.location,
  });

  factory ConfirmBookingArgs.from(BookingCounselorSummary counselor, AvailabilitySlot slot) => ConfirmBookingArgs(
        counselorId: counselor.id,
        counselorName: counselor.name,
        slotId: slot.id,
        mode: slot.mode,
        startsAt: slot.startsAt,
        durationMinutes: slot.durationMinutes,
        location: slot.location,
      );

  final String counselorId;
  final String counselorName;
  final String slotId;
  final SessionMode mode;
  final DateTime startsAt;
  final int durationMinutes;
  final SlotLocation? location;
}
