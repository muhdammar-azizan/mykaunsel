import '../../../core/network/api_client.dart';
import '../domain/counselor.dart';
import '../domain/counselor_query.dart';

/// The counselor search calls (docs/API.md on the server side). Throws only an [AppFailure].
abstract class CounselorApi {
  Future<CounselorPage> search(CounselorQuery query, {int page = 1});

  Future<CounselorFilterOptions> filters();

  /// Throws [NotFoundFailure] when there is no such counselor OR this person may not see them.
  Future<Counselor> detail(String id);
}

class HttpCounselorApi implements CounselorApi {
  HttpCounselorApi(this._client);

  final ApiClient _client;

  @override
  Future<CounselorPage> search(CounselorQuery query, {int page = 1}) async {
    final response = await _client.get('/counselors', query: query.toParams(page: page));
    final meta = Map<String, dynamic>.from(response.data['meta'] as Map);

    return CounselorPage(
      items: (response.data['data'] as List).map((json) => Counselor.fromJson(Map<String, dynamic>.from(json as Map))).toList(),
      page: meta['page'] as int,
      lastPage: meta['last_page'] as int,
      total: meta['total'] as int,
    );
  }

  @override
  Future<CounselorFilterOptions> filters() async {
    final response = await _client.get('/counselors/filters');

    return CounselorFilterOptions.fromJson(Map<String, dynamic>.from(response.data['data'] as Map));
  }

  @override
  Future<Counselor> detail(String id) async {
    final response = await _client.get('/counselors/${Uri.encodeComponent(id)}');

    return Counselor.fromJson(Map<String, dynamic>.from(response.data['data'] as Map));
  }
}
