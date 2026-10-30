import '../../../core/network/api_client.dart';
import '../domain/time_request.dart';

/// Time requests (Mode B): asking a counselor for a time inside their weekly hours. Throws only an [AppFailure]; a counselor the person may
/// not see and a request that is not theirs are [NotFoundFailure].
abstract class RequestsApi {
  Future<WorkingHours> workingHours(String counselorId);

  /// Asks for a time. Safe to repeat: the same request while it waits answers the one that exists, so a lost answer can simply be asked again.
  /// A [ConflictFailure] says why not (`slot_taken`, `time_conflict`, `request_limit_reached`, `requests_closed`); a [ValidationFailure]
  /// means the time is not inside the counselor's hours.
  Future<TimeRequest> send(String counselorId, {required String date, required String time, String? message});

  Future<RequestsPage> list({int page = 1});

  Future<TimeRequest> detail(String id);

  /// Takes back a request that still waits. Safe to repeat. A [ConflictFailure] `request_not_pending` once it was answered or expired.
  Future<TimeRequest> withdraw(String id);
}

class HttpRequestsApi implements RequestsApi {
  HttpRequestsApi(this._client);

  final ApiClient _client;

  @override
  Future<WorkingHours> workingHours(String counselorId) async {
    final response = await _client.get('/counselors/${Uri.encodeComponent(counselorId)}/working-hours');

    return WorkingHours.fromJson(response.data);
  }

  @override
  Future<TimeRequest> send(String counselorId, {required String date, required String time, String? message}) async {
    final note = message?.trim() ?? '';
    final response = await _client.post(
      '/counselors/${Uri.encodeComponent(counselorId)}/requests',
      data: {'date': date, 'time': time, if (note.isNotEmpty) 'message': note},
    );

    return TimeRequest.fromResponse(response.data);
  }

  @override
  Future<RequestsPage> list({int page = 1}) async {
    final response = await _client.get('/requests', query: {if (page > 1) 'page': page});

    return RequestsPage.fromJson(response.data);
  }

  @override
  Future<TimeRequest> detail(String id) async {
    final response = await _client.get('/requests/${Uri.encodeComponent(id)}');

    return TimeRequest.fromResponse(response.data);
  }

  @override
  Future<TimeRequest> withdraw(String id) async {
    final response = await _client.post('/requests/${Uri.encodeComponent(id)}/withdraw');

    return TimeRequest.fromResponse(response.data);
  }
}
