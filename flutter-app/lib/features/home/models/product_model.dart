class ProductModel {
  final int id;
  final String name;
  final String slug;
  final String? description;
  final int price;
  final int stock;
  final double weight;
  final String condition;
  final String? thumbnail;
  final int totalSold;
  final StoreMini? store;

  ProductModel({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    required this.price,
    required this.stock,
    required this.weight,
    required this.condition,
    this.thumbnail,
    required this.totalSold,
    this.store,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: json['id'],
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      description: json['description'],
      price: json['price'] ?? 0,
      stock: json['stock'] ?? 0,
      weight: (json['weight'] ?? 0).toDouble(),
      condition: json['condition'] ?? 'new',
      thumbnail: json['thumbnail'],
      totalSold: json['total_sold'] ?? 0,
      store: json['store'] != null ? StoreMini.fromJson(json['store']) : null,
    );
  }
}

class StoreMini {
  final int id;
  final String name;
  final String? logo;

  StoreMini({required this.id, required this.name, this.logo});

  factory StoreMini.fromJson(Map<String, dynamic> json) {
    return StoreMini(
      id: json['id'],
      name: json['name'] ?? '',
      logo: json['logo'],
    );
  }
}
