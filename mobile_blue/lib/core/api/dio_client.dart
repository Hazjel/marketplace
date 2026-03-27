import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

class DioClient {
  late Dio _dio;

  final String _baseUrl = 'http://10.0.2.2:8000/api';

  DioClient() {
    _dio = Dio(
      BaseOptions(
        baseUrl: _baseUrl,
        connectTimeout: const Duration(seconds: 60),
        receiveTimeout: const Duration(seconds: 60),
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final prefs = await SharedPreferences.getInstance();
          final token = prefs.getString('auth_token');

          if (token != null) {
            options.headers['Authorization'] =
                'Bearer $token'; // Fix: Ensure 'Bearer ' prefix
          }
          // Add Accept header for Laravel API
          options.headers['Accept'] = 'application/json';

          debugPrint('--> ${options.method.toUpperCase()} ${options.uri}');
          debugPrint('Headers: ${options.headers}');
          debugPrint('Body: ${options.data}');

          return handler.next(options);
        },
        onResponse: (response, handler) {
          debugPrint(
            '<-- ${response.statusCode} ${response.requestOptions.uri}',
          );
          debugPrint('Response: ${response.data}');
          return handler.next(response);
        },
        onError: (DioException e, handler) {
          debugPrint(
            '<-- Error ${e.response?.statusCode} ${e.requestOptions.uri}',
          );
          debugPrint('Message: ${e.message}');
          if (e.response != null) {
            debugPrint('Error Data: ${e.response?.data}');
          }
          return handler.next(e);
        },
      ),
    );
  }

  Dio get dio => _dio;
}
