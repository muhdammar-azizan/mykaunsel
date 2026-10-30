import '../../booking/presentation/booking_args.dart';
import '../domain/my_booking.dart';

/// What the picker needs to move ONE booking: which booking, with which counselor, and the time it has now (shown in the confirmation). Handed
/// over in the route's `extra`; without it (an old link) the route goes back to the booking.
class RescheduleTarget {
  const RescheduleTarget({required this.bookingId, required this.counselor, required this.startsAt, required this.endsAt});

  factory RescheduleTarget.from(MyBookingDetail booking) => RescheduleTarget(
        bookingId: booking.id,
        counselor: BookingCounselorSummary(id: booking.counselor.id ?? '', name: booking.counselor.displayName),
        startsAt: booking.startsAt,
        endsAt: booking.endsAt,
      );

  final String bookingId;
  final BookingCounselorSummary counselor;
  final DateTime startsAt;
  final DateTime endsAt;
}
