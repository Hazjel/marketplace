import 'dart:io';
import '../repositories/auth_repository.dart';
import '../entities/user.dart';

class RegisterUseCase {
  final AuthRepository _repository;

  RegisterUseCase(this._repository);

  Future<User> call({
    required String name,
    required String email,
    required String password,
    required String phoneNumber,
    required File profilePicture,
  }) async {
    return await _repository.register(
      name: name,
      email: email,
      password: password,
      phoneNumber: phoneNumber,
      profilePicture: profilePicture,
    );
  }
}
