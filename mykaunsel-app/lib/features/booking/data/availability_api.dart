import '../../../core/network/api_client.dart';
import '../domain/availability.dart';

/// The one call the "Pick a slot" screen makes. It only reads: it never books and never holds a time.
/// Throws only an [AppFailure]: [NotFoundFailure] for every reason the counselor cannot be booked by this person.
abstract class AvailabilityApi {
  /// [from] is a Malaysian date ("2026-11-16"); null means today. Up to 14 days come back.
  Future<Availability> fetch(String counselorId, {String? from});
}

class HttpAvailabilityApi implements AvailabilityApi {
  HttpAvailabilityApi(this._client);

  final ApiClient _client;

  @override
  Future<Availability> fetch(String counselorId, {String? from}) async {
    final response = await _client.get(
      '/counselors/${Uri.encodeComponent(counselorId)}/availability',
      query: {'from': ?from},
    );

    return Availability.fromJson(Map<String, dynamic>.from(response.data as Map));
  }
}
