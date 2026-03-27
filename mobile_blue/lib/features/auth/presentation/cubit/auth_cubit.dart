import 'dart:io';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../domain/repositories/auth_repository.dart';
import '../../domain/usecases/login_usecase.dart';
import '../../domain/usecases/register_usecase.dart';
import 'auth_state.dart';

class AuthCubit extends Cubit<AuthState> {
  final LoginUseCase _loginUseCase;
  final RegisterUseCase _registerUseCase;
  // Add AuthRepository for loginWithGoogle and getUser
  final AuthRepository _authRepository;

  // Constructor
  AuthCubit(this._loginUseCase, this._registerUseCase, this._authRepository)
    : super(AuthInitial());

  // Login Method
  Future<void> login(String email, String password) async {
    emit(AuthLoading());
    try {
      final user = await _loginUseCase.call(email, password);
      // user is now User entity, perfectly valid for AuthSuccess
      emit(AuthSuccess(user));
    } catch (e) {
      emit(AuthFailure(e.toString()));
    }
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    required String phoneNumber,
    required File profilePicture,
  }) async {
    emit(AuthLoading());
    try {
      final user = await _registerUseCase.call(
        name: name,
        email: email,
        password: password,
        phoneNumber: phoneNumber,
        profilePicture: profilePicture,
      );
      emit(AuthSuccess(user));
    } catch (e) {
      emit(AuthFailure(e.toString()));
    }
  }

  Future<void> loginWithGoogle() async {
    try {
      emit(AuthLoading());
      await _authRepository.loginWithGoogle();
      // We don't emit success here because the success requires the callback
      // The state remains loading or we can reset to initial if we want,
      // but usually we wait for the app to pause/resume or the link to come in.
    } catch (e) {
      emit(AuthFailure(e.toString()));
    }
  }

  Future<void> googleLoginSuccess(String token) async {
    try {
      emit(AuthLoading());
      // Save token manually in shared prefs because repository logic normally does it on login
      // But here we bypass repo login. Ideally repo has a method for this.
      // But keeping it simple: save token -> fetch user.

      // Wait, AuthRepositoryImpl only saves token in login().
      // We need to save it here too.
      // Better to have saveToken in Repository.
      // For now, I'll access SharedPreferences here or add saveToken to Repo.
      // Adding saveToken to Repo is cleaner but requires interface change again.
      // I will just use shared_preferences here for speed, or assume getUser() uses the token
      // if I set it in the client. Wait, DioClient reads from SharedPreferences.
      // So I MUST save to SharedPreferences FIRST.

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('auth_token', token);

      final user = await _authRepository.getUser();
      emit(AuthSuccess(user));
    } catch (e) {
      emit(AuthFailure(e.toString()));
    }
  }

  Future<void> checkAuthStatus() async {
    emit(AuthLoading());
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');

      if (token != null) {
        final user = await _authRepository.getUser();
        emit(AuthSuccess(user));
      } else {
        emit(AuthInitial());
      }
    } catch (e) {
      // Token invalid or network error
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('auth_token');
      emit(AuthInitial());
    }
  }
}
