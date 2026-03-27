import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';
import '../../../../core/api/dio_client.dart';
import '../models/user_model.dart';
// Note: We return UserModel here because Data Source deals with Models (JSON handling)

class AuthRemoteDataSource {
  final DioClient _dioClient;

  AuthRemoteDataSource(this._dioClient);

  Future<UserModel> login(String email, String password) async {
    try {
      final response = await _dioClient.dio.post(
        '/login',
        data: {'email': email, 'password': password},
      );

      debugPrint('Login Response: ${response.data}');

      if (response.data['success'] == true) {
        return UserModel.fromJson(response.data['data']);
      } else {
        throw Exception(response.data['message'] ?? 'Login failed');
      }
    } on DioException catch (e) {
      if (e.response != null) {
        throw Exception(e.response?.data['message'] ?? 'Login failed');
      } else {
        throw Exception('Network error: ${e.message}');
      }
    } catch (e) {
      rethrow;
    }
  }

  Future<UserModel> getUser() async {
    try {
      final response = await _dioClient.dio.get('/me');

      if (response.data['success'] == true) {
        return UserModel.fromJson(response.data['data']);
      } else {
        throw Exception(response.data['message'] ?? 'Failed to get user');
      }
    } on DioException catch (e) {
      if (e.response != null) {
        throw Exception(e.response?.data['message'] ?? 'Failed to get user');
      } else {
        throw Exception('Network error: ${e.message}');
      }
    } catch (e) {
      rethrow;
    }
  }

  Future<UserModel> register({
    required String name,
    required String email,
    required String password,
    required String phoneNumber,
    required String profilePicturePath,
  }) async {
    try {
      String fileName = profilePicturePath.split('/').last;
      FormData formData = FormData.fromMap({
        'name': name,
        'email': email,
        'password': password,
        'phone_number': phoneNumber,
        'role': 'buyer',
        'profile_picture': await MultipartFile.fromFile(
          profilePicturePath,
          filename: fileName,
        ),
      });

      final response = await _dioClient.dio.post('/register', data: formData);

      debugPrint('Register Response: ${response.data}');

      if (response.data['success'] == true) {
        return UserModel.fromJson(response.data['data']);
      } else {
        throw Exception(response.data['message'] ?? 'Registration failed');
      }
    } on DioException catch (e) {
      if (e.response != null) {
        throw Exception(e.response?.data['message'] ?? 'Registration failed');
      } else {
        throw Exception('Network error: ${e.message}');
      }
    } catch (e) {
      rethrow;
    }
  }
}
