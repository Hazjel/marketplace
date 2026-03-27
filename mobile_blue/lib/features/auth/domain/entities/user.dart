import 'package:equatable/equatable.dart';

class User extends Equatable {
  final String id;
  final String name;
  final String username;
  final String email;
  final String role;
  final String? profilePicture;
  final String? token;

  const User({
    required this.id,
    required this.name,
    required this.username,
    required this.email,
    required this.role,
    this.profilePicture,
    this.token,
  });

  @override
  List<Object?> get props => [
    id,
    name,
    username,
    email,
    role,
    profilePicture,
    token,
  ];
}
