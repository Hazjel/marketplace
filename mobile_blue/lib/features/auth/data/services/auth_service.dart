import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class AuthService {
  // Use 127.0.0.1 for Android Emulator with 'adb reverse'
  // Or use 10.0.2.2 for Android Emulator without 'adb reverse'
  // Or use your local IP (e.g., 192.168.1.x) for physical device
  // Using the same baseUrl as HomeService for consistency
  static const String baseUrl = 'http://127.0.0.1:8000/api';
  static const String _tokenKey = 'auth_token';

  Future<void> saveToken(String token) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_tokenKey, token);
    } catch (e) {
      debugPrint('Error saving token: $e');
    }
  }

  Future<String?> getToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString(_tokenKey);
      return token;
    } catch (e) {
      debugPrint('Error retrieving token: $e');
      return null;
    }
  }

  Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    // Optional: Call API to revoke token
  }

  Future<Map<String, dynamic>> login(String email, String password) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/login'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: json.encode({'email': email, 'password': password}),
      );

      debugPrint('Login Response: ${response.statusCode} | ${response.body}');

      final Map<String, dynamic> body = json.decode(response.body);

      if (response.statusCode == 200) {
        return {'success': true, 'data': body};
      } else {
        return {'success': false, 'message': body['message'] ?? 'Login failed'};
      }
    } catch (e) {
      debugPrint('Error logging in: $e');
      return {
        'success': false,
        'message': 'Connection error. Please try again.',
      };
    }
  }

  Future<Map<String, dynamic>> register({
    required String name,
    required String email,
    required String password,
    required String phone,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/register'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: json.encode({
          'name': name,
          'email': email,
          'password': password,
          'phone_number': phone,
          'password_confirmation': password, // Assuming simple reg for now
          'role': 'buyer', // Default role
        }),
      );

      debugPrint(
        'Register Response: ${response.statusCode} | ${response.body}',
      );

      final Map<String, dynamic> body = json.decode(response.body);

      if (response.statusCode == 201 || response.statusCode == 200) {
        return {'success': true, 'data': body};
      } else {
        return {
          'success': false,
          'message': body['message'] ?? 'Registration failed',
          'errors': body['errors'], // For field-specific errors
        };
      }
    } catch (e) {
      debugPrint('Error registering: $e');
      return {
        'success': false,
        'message': 'Connection error. Please try again.',
      };
    }
  }
}
