import 'package:mobile_blue/core/utils/image_helper.dart';

class Product {
  final String id;
  final String name;
  final String slug;
  final String price;
  final String? imageUrl;
  final int? minPrice;
  final int? maxPrice;

  final String description;
  final List<String> images;
  final double rating;
  final int sold;
  final StoreInfo store;

  Product({
    required this.id,
    required this.name,
    required this.slug,
    required this.price,
    this.imageUrl,
    this.minPrice,
    this.maxPrice,
    this.description = '',
    this.images = const [],
    this.rating = 0.0,
    this.sold = 0,
    required this.store,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      price: json['price']?.toString() ?? '0',
      imageUrl: ImageHelper.sanitizeUrl(json['thumbnail']),
      minPrice: json['min_price'],
      maxPrice: json['max_price'],
      description: json['description'] ?? '',
      images:
          json['product_images'] != null
              ? (json['product_images'] as List)
                  .map((e) => ImageHelper.sanitizeUrl(e['image'] as String)!)
                  .toList()
              : [],
      rating:
          json['rating'] != null
              ? double.tryParse(json['rating'].toString()) ?? 0.0
              : 0.0,
      sold:
          json['total_sold'] != null
              ? int.tryParse(json['total_sold'].toString()) ?? 0
              : 0,
      store:
          json['store'] != null
              ? StoreInfo.fromJson(json['store'])
              : StoreInfo(id: '', name: '', logo: ''),
    );
  }
}

class StoreInfo {
  final String id;
  final String name;
  final String logo;

  StoreInfo({required this.id, required this.name, required this.logo});

  factory StoreInfo.fromJson(Map<String, dynamic> json) {
    return StoreInfo(
      id: json['id']?.toString() ?? '',
      name: json['name'] ?? '',
      logo: json['logo'] ?? '',
    );
  }
}
