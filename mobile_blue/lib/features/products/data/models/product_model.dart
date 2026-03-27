import '../../domain/entities/product.dart';

class ProductModel extends Product {
  const ProductModel({
    required super.id,
    required super.name,
    required super.slug,
    required super.price,
    super.thumbnail,
    super.storeName,
    super.storeCity,
    super.rating,
    super.soldCount,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: json['id'],
      name: json['name'],
      slug: json['slug'],
      price:
          (json['price'] is num)
              ? (json['price'] as num).toDouble()
              : double.tryParse(json['price'].toString()) ?? 0.0,
      thumbnail: json['thumbnail'],
      storeName: json['store']?['name'],
      storeCity: json['store']?['city'],
      rating:
          (json['rating'] is num)
              ? (json['rating'] as num).toDouble()
              : double.tryParse(json['rating'].toString()) ?? 0.0,
      soldCount:
          (json['total_sold'] is num)
              ? (json['total_sold'] as num).toInt()
              : int.tryParse(json['total_sold'].toString()) ?? 0,
    );
  }
}
