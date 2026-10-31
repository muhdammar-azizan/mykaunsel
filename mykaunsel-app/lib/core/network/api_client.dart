import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../config/app_info.dart';
import '../error/app_failure.dart';
import 'failure_mapper.dart';

typedef TokenReader = Future<String?> Function();

/// Told about every failed call, so the session can react in ONE place (token expired → sign out, maintenance →
/// maintenance screen, app too old → update screen) instead of every screen doing it. [hadToken] says whether the
/// request was sent as a signed-in person (a 401 on the sign-in form itself is not "your session ended").
typedef FailureListener = void Function(AppFailure failure, {required bool hadToken});

/// A successful answer: the status code (202 means "second step needed" on sign-in) and the JSON body.
class ApiResponse {
  const ApiResponse(this.statusCode, this.data);

  final int statusCode;
  final Map<String, dynamic> data;
}

/// The only way the app talks to the server. Adds the app version / platform / token headers, applies sensible
/// timeouts and turns every error into an [AppFailure]. Never logs request bodies (they hold passwords).
class ApiClient {
  ApiClient({
    required AppConfig config,
    required AppInfo info,
    required TokenReader readToken,
    required FailureListener onFailure,
    Dio? dio,
  })  : _readToken = readToken,
        _onFailure = onFailure,
        _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = config.apiUrl
      ..connectTimeout = const Duration(seconds: 15)
      ..sendTimeout = const Duration(seconds: 30)
      ..receiveTimeout = const Duration(seconds: 30)
      ..responseType = ResponseType.json
      ..headers = {
        'Accept': 'application/json',
        'X-App-Version': info.version,
        'X-App-Platform': info.platform,
      };
  }

  final Dio _dio;
  final TokenReader _readToken;
  final FailureListener _onFailure;

  Future<ApiResponse> get(String path, {Map<String, dynamic>? query}) => _send('GET', path, query: query);

  /// [headers] are extra headers for this one request (the booking sends its Idempotency-Key this way). They are never logged.
  Future<ApiResponse> post(String path, {Map<String, dynamic>? data, Map<String, String>? headers}) => _send('POST', path, data: data, headers: headers);

  Future<ApiResponse> put(String path, {Map<String, dynamic>? data}) => _send('PUT', path, data: data);

  Future<ApiResponse> patch(String path, {Map<String, dynamic>? data}) => _send('PATCH', path, data: data);

  Future<ApiResponse> delete(String path, {Map<String, dynamic>? data}) => _send('DELETE', path, data: data);

  Future<ApiResponse> _send(String method, String path, {Map<String, dynamic>? data, Map<String, dynamic>? query, Map<String, String>? headers}) async {
    final token = await _readToken();

    try {
      final response = await _dio.request<dynamic>(
        path,
        data: data,
        queryParameters: query,
        options: Options(method: method, headers: {if (token != null) 'Authorization': 'Bearer $token', ...?headers}),
      );

      return ApiResponse(
        response.statusCode ?? 200,
        response.data is Map ? Map<String, dynamic>.from(response.data as Map) : <String, dynamic>{},
      );
    } on DioException catch (e) {
      final failure = mapDioException(e);
      _onFailure(failure, hadToken: token != null);

      throw failure;
    }
  }
}
