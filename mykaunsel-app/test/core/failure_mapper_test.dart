import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/core/error/app_failure.dart';
import 'package:mykaunsel/core/network/failure_mapper.dart';

DioException badResponse(int status, [Object? body, Map<String, List<String>>? headers]) {
  final options = RequestOptions(path: '/x');

  return DioException(
    requestOptions: options,
    type: DioExceptionType.badResponse,
    response: Response(requestOptions: options, statusCode: status, data: body, headers: Headers.fromMap(headers ?? {})),
  );
}

void main() {
  test('401 means the person must sign in again', () {
    expect(mapDioException(badResponse(401, {'message': 'Unauthenticated.'})), isA<UnauthorizedFailure>());
  });

  test('404 means not there (or not allowed to see): one failure for both', () {
    expect(mapDioException(badResponse(404, {'message': 'Not Found'})), isA<NotFoundFailure>());
    expect(mapDioException(badResponse(404, '<html>nginx</html>')), isA<NotFoundFailure>());
  });

  test('403 email_not_verified is its own failure; any other 403 keeps the message and code', () {
    final unverified = mapDioException(badResponse(403, {'message': 'Confirm your email.', 'code': 'email_not_verified'}));
    expect(unverified, isA<EmailNotVerifiedFailure>());
    expect((unverified as EmailNotVerifiedFailure).message, 'Confirm your email.');

    final closed = mapDioException(badResponse(403, {'message': 'Sign-up is closed.'})) as ForbiddenFailure;
    expect(closed.message, 'Sign-up is closed.');
    expect(closed.code, isNull);
  });

  test('422 carries the message and the errors per field', () {
    final failure = mapDioException(badResponse(422, {
      'message': 'The email field is required.',
      'errors': {
        'email': ['The email field is required.'],
        'password': ['Too short.', 'Needs a symbol.'],
      },
    })) as ValidationFailure;

    expect(failure.errorFor('email'), 'The email field is required.');
    expect(failure.fieldErrors['password'], hasLength(2));
    expect(failure.errorFor('name'), isNull);
  });

  test('426 asks for an update and keeps the store link', () {
    final failure = mapDioException(badResponse(426, {'message': 'Update.', 'code': 'app_update_required', 'store_url': 'https://play.example/app'})) as UpdateRequiredFailure;

    expect(failure.storeUrl, 'https://play.example/app');
  });

  test('429 reads Retry-After', () {
    final failure = mapDioException(badResponse(429, {'message': 'Too many.'}, {'retry-after': ['42']})) as RateLimitedFailure;

    expect(failure.retryAfterSeconds, 42);
    expect((mapDioException(badResponse(429, {})) as RateLimitedFailure).retryAfterSeconds, isNull);
  });

  test('503 is maintenance and keeps the admin\'s words', () {
    final failure = mapDioException(badResponse(503, {'message': 'Back at 6 PM.'})) as MaintenanceFailure;

    expect(failure.message, 'Back at 6 PM.');
  });

  test('other server errors, and answers that are not JSON, are a server failure', () {
    expect((mapDioException(badResponse(500, '<html>oops</html>')) as ServerFailure).statusCode, 500);
    expect(mapDioException(badResponse(502)), isA<ServerFailure>());
    expect(mapDioException(badResponse(503, '<html>nginx</html>')), isA<MaintenanceFailure>());
  });

  test('timeouts and connection problems are network failures', () {
    final options = RequestOptions(path: '/x');

    expect((mapDioException(DioException(requestOptions: options, type: DioExceptionType.connectionTimeout)) as NetworkFailure).timedOut, isTrue);
    expect((mapDioException(DioException(requestOptions: options, type: DioExceptionType.receiveTimeout)) as NetworkFailure).timedOut, isTrue);
    expect((mapDioException(DioException(requestOptions: options, type: DioExceptionType.connectionError)) as NetworkFailure).timedOut, isFalse);
    expect(mapDioException(DioException(requestOptions: options, type: DioExceptionType.unknown, error: const SocketException('no route'))), isA<NetworkFailure>());
    expect(mapDioException(DioException(requestOptions: options, type: DioExceptionType.unknown, error: StateError('bug'))), isA<ServerFailure>());
  });
}
