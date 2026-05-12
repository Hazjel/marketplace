class CartModel {
  final int id;
  final int productId;
  final String productName;
  final String? productThumbnail;
  final int price;
  final int quantity;
  final int stock;
  final String? storeName;

  CartModel({
    required this.id,
    required this.productId,
    required this.productName,
    this.productThumbnail,
    required this.price,
    required this.quantity,
    required this.stock,
    this.storeName,
  });

  factory CartModel.fromJson(Map<String, dynamic> json) {
    final product = json['product'] ?? {};
    return CartModel(
      id: json['id'],
      productId: json['product_id'] ?? product['id'] ?? 0,
      productName: product['name'] ?? '',
      productThumbnail: product['thumbnail'],
      price: product['price'] ?? 0,
      quantity: json['quantity'] ?? 1,
      stock: product['stock'] ?? 0,
      storeName: product['store']?['name'],
    );
  }

  int get subtotal => price * quantity;
}
