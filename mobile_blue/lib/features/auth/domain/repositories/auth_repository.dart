import 'dart:io';
import '../entities/user.dart';

abstract class AuthRepository {
  Future<User> login(String email, String password);
  Future<User> getUser();
  Future<void> loginWithGoogle();
  Future<User> register({
    required String name,
    required String email,
    required String password,
    required String phoneNumber,
    required File profilePicture,
  });
}
