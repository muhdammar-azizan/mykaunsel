import '../../../core/network/api_client.dart';
import '../domain/platform_status.dart';

abstract class PlatformStatusApi {
  Future<PlatformStatus> fetch();
}

class HttpPlatformStatusApi implements PlatformStatusApi {
  HttpPlatformStatusApi(this._client);

  final ApiClient _client;

  @override
  Future<PlatformStatus> fetch() async {
    final response = await _client.get('/status');

    return PlatformStatus.fromJson(response.data);
  }
}
