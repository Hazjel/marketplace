import 'package:mobile_blue/core/utils/image_helper.dart';

class Store {
  final int id;
  final String name;
  final String username;
  final String? logoUrl;
  final String? bannerUrl;
  final String city;
  final bool isVerified;
  final double rating;
  final int reviewCount;

  Store({
    required this.id,
    required this.name,
    required this.username,
    this.logoUrl,
    this.bannerUrl,
    this.city = 'Jakarta', // Default fallback
    this.isVerified = false,
    this.rating = 0.0,
    this.reviewCount = 0,
  });

  factory Store.fromJson(Map<String, dynamic> json) {
    return Store(
      id:
          json['id'] is int
              ? json['id']
              : int.tryParse(json['id'].toString()) ?? 0,
      name: json['name'] ?? 'Unknown Store',
      username: json['username'] ?? '',
      // API might return full URL or partial path, assuming full or handling logic similar to Product
      logoUrl: ImageHelper.sanitizeUrl(json['logo']),
      bannerUrl: ImageHelper.sanitizeUrl(json['banner']),
      city: json['city'] ?? 'Jakarta',
      isVerified: json['is_verified'] == 1 || json['is_verified'] == true,
      // Random rating logic from frontend? Or API?
      // Web uses: 4.9 ({{ Math.floor(Math.random() * 50) + 1 }}k) -> Mocking for consistency if API missing
      rating: 4.8,
      reviewCount: 120, // Mock
    );
  }
}
