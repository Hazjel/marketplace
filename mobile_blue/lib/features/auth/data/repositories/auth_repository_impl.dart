import 'dart:io';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../domain/entities/user.dart';
import '../../domain/repositories/auth_repository.dart';
import '../datasources/auth_remote_datasource.dart';

class AuthRepositoryImpl implements AuthRepository {
  final AuthRemoteDataSource _remoteDataSource;

  AuthRepositoryImpl(this._remoteDataSource);

  @override
  Future<User> login(String email, String password) async {
    try {
      final user = await _remoteDataSource.login(email, password);

      if (user.token != null) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', user.token!);
      }

      return user;
    } catch (e) {
      rethrow;
    }
  }

  @override
  Future<User> getUser() async {
    return await _remoteDataSource.getUser();
  }

  @override
  Future<void> loginWithGoogle() async {
    final Uri url = Uri.parse(
      'http://10.0.2.2:8000/api/auth/google/redirect?platform=mobile',
    );
    if (!await launchUrl(url, mode: LaunchMode.externalApplication)) {
      throw Exception('Could not launch Google Login');
    }
  }

  @override
  Future<User> register({
    required String name,
    required String email,
    required String password,
    required String phoneNumber,
    required File profilePicture,
  }) async {
    return await _remoteDataSource.register(
      name: name,
      email: email,
      password: password,
      phoneNumber: phoneNumber,
      profilePicturePath: profilePicture.path,
    );
  }
}
