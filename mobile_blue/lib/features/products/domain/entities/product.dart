import 'package:equatable/equatable.dart';

class Product extends Equatable {
  final String id;
  final String name;
  final String slug;
  final double price;
  final String? thumbnail;
  final String? storeName;
  final String? storeCity;
  final double rating;
  final int soldCount;

  const Product({
    required this.id,
    required this.name,
    required this.slug,
    required this.price,
    this.thumbnail,
    this.storeName,
    this.storeCity,
    this.rating = 0.0,
    this.soldCount = 0,
  });

  @override
  List<Object?> get props => [
    id,
    name,
    slug,
    price,
    thumbnail,
    storeName,
    storeCity,
    rating,
    soldCount,
  ];
}
