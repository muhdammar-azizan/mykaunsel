import 'dart:io';

import 'package:dio/dio.dart';

import '../error/app_failure.dart';

/// Turns a raw Dio error into one of the app's [AppFailure] types. The only place that knows what each HTTP status
/// means (see docs/API.md on the server side).
AppFailure mapDioException(DioException e) {
  switch (e.type) {
    case DioExceptionType.connectionTimeout:
    case DioExceptionType.sendTimeout:
    case DioExceptionType.receiveTimeout:
    case DioExceptionType.transformTimeout:
      return const NetworkFailure(timedOut: true);
    case DioExceptionType.connectionError:
    case DioExceptionType.badCertificate:
    case DioExceptionType.cancel:
      return const NetworkFailure();
    case DioExceptionType.badResponse:
      final response = e.response;

      return response == null ? const ServerFailure() : mapResponse(response);
    case DioExceptionType.unknown:
      final inner = e.error;

      return inner is SocketException || inner is HandshakeException || inner is TlsException ? const NetworkFailure() : const ServerFailure();
  }
}

AppFailure mapResponse(Response<dynamic> response) {
  final status = response.statusCode ?? 0;
  final body = response.data is Map ? Map<String, dynamic>.from(response.data as Map) : <String, dynamic>{};
  final message = body['message'] is String ? body['message'] as String : '';
  final code = body['code'] is String ? body['code'] as String : null;

  switch (status) {
    case 401:
      return const UnauthorizedFailure();
    case 403:
      return code == 'email_not_verified' ? EmailNotVerifiedFailure(message) : ForbiddenFailure(message, code: code);
    case 404:
      return const NotFoundFailure();
    case 422:
      return ValidationFailure(message, fieldErrors: _fieldErrors(body['errors']));
    case 426:
      return UpdateRequiredFailure(message, storeUrl: body['store_url'] is String ? body['store_url'] as String : null);
    case 429:
      return RateLimitedFailure(retryAfterSeconds: int.tryParse(response.headers.value('retry-after') ?? ''));
    case 503:
      return MaintenanceFailure(message);
    default:
      return ServerFailure(statusCode: status);
  }
}

Map<String, List<String>> _fieldErrors(Object? raw) {
  if (raw is! Map) {
    return const {};
  }

  return {
    for (final entry in raw.entries)
      entry.key.toString(): [
        if (entry.value is List) ...(entry.value as List).map((m) => m.toString()) else entry.value.toString(),
      ],
  };
}
