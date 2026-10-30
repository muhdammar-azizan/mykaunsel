import '../../../core/network/api_client.dart';
import '../domain/my_booking.dart';

/// The person's OWN bookings, read only. Throws only an [AppFailure]; a booking that is not theirs and one that does not exist are both
/// [NotFoundFailure].
abstract class MyBookingsApi {
  Future<BookingsPage> list(BookingsScope scope, {int page = 1});

  Future<MyBookingDetail> detail(String id);

  /// Cancels the person's own booking and gives the time back. Safe to repeat: cancelling one that is already cancelled answers the same
  /// booking and changes nothing, so a lost answer can simply be asked again. A [ConflictFailure] says why not (`cancellation_closed`,
  /// `not_cancellable`).
  Future<MyBookingDetail> cancel(String id);
}

class HttpMyBookingsApi implements MyBookingsApi {
  HttpMyBookingsApi(this._client);

  final ApiClient _client;

  @override
  Future<BookingsPage> list(BookingsScope scope, {int page = 1}) async {
    final response = await _client.get('/bookings', query: {'scope': scope.name, if (page > 1) 'page': page});

    return BookingsPage.fromJson(response.data);
  }

  @override
  Future<MyBookingDetail> detail(String id) async {
    final response = await _client.get('/bookings/${Uri.encodeComponent(id)}');

    return MyBookingDetail.fromResponse(response.data);
  }

  @override
  Future<MyBookingDetail> cancel(String id) async {
    final response = await _client.post('/bookings/${Uri.encodeComponent(id)}/cancel');

    return MyBookingDetail.fromResponse(response.data);
  }
}
