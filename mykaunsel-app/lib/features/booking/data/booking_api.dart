import '../../../core/network/api_client.dart';
import '../domain/booking_preview.dart';

/// The two calls of the confirm screen. Throws only an [AppFailure]: [NotFoundFailure] for every reason the counselor cannot be booked, and a
/// [ConflictFailure] (409) whose `code` says what happened.
abstract class BookingApi {
  /// Read only: claims nothing.
  Future<BookingPreview> preview(String slotId);

  /// The booking itself. The same [idempotencyKey] with the same request never books twice, so it is safe to repeat when the answer is lost.
  Future<CreatedBooking> create({
    required String idempotencyKey,
    required String slotId,
    required DateTime expectedStartsAt,
    required int expectedDurationMinutes,
    required String expectedMode,
  });
}

class HttpBookingApi implements BookingApi {
  HttpBookingApi(this._client);

  final ApiClient _client;

  @override
  Future<BookingPreview> preview(String slotId) async {
    final response = await _client.get('/bookings/preview', query: {'slot': slotId});

    return BookingPreview.fromJson(response.data);
  }

  @override
  Future<CreatedBooking> create({
    required String idempotencyKey,
    required String slotId,
    required DateTime expectedStartsAt,
    required int expectedDurationMinutes,
    required String expectedMode,
  }) async {
    final response = await _client.post(
      '/bookings',
      headers: {'Idempotency-Key': idempotencyKey},
      data: {
        'slot': slotId,
        // The instant, with the +08:00 the server sent; the server compares instants.
        'acknowledged_policy': true,
        'expected_starts_at': expectedStartsAt.toUtc().toIso8601String(),
        'expected_duration_minutes': expectedDurationMinutes,
        'expected_mode': expectedMode,
      },
    );

    return CreatedBooking.fromResponse(response.data);
  }
}
