class TransactionModel {
  final int id;
  final String code;
  final String status;
  final int totalPrice;
  final int shippingCost;
  final String? paymentUrl;
  final String? shippingMethod;
  final String? trackingNumber;
  final String? createdAt;
  final List<TransactionDetailModel>? details;

  TransactionModel({
    required this.id,
    required this.code,
    required this.status,
    required this.totalPrice,
    required this.shippingCost,
    this.paymentUrl,
    this.shippingMethod,
    this.trackingNumber,
    this.createdAt,
    this.details,
  });

  factory TransactionModel.fromJson(Map<String, dynamic> json) {
    return TransactionModel(
      id: json['id'],
      code: json['code'] ?? '',
      status: json['status'] ?? 'pending',
      totalPrice: json['total_price'] ?? 0,
      shippingCost: json['shipping_cost'] ?? 0,
      paymentUrl: json['payment_url'],
      shippingMethod: json['shipping_method'],
      trackingNumber: json['tracking_number'],
      createdAt: json['created_at'],
      details: json['details'] != null
          ? (json['details'] as List)
              .map((e) => TransactionDetailModel.fromJson(e))
              .toList()
          : null,
    );
  }

  int get grandTotal => totalPrice + shippingCost;

  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'Menunggu Pembayaran';
      case 'paid':
        return 'Dibayar';
      case 'processing':
        return 'Diproses';
      case 'shipped':
        return 'Dikirim';
      case 'delivered':
        return 'Diterima';
      case 'completed':
        return 'Selesai';
      case 'cancelled':
        return 'Dibatalkan';
      case 'expired':
        return 'Kedaluwarsa';
      default:
        return status;
    }
  }
}

class TransactionDetailModel {
  final int id;
  final int productId;
  final String productName;
  final String? productThumbnail;
  final int price;
  final int quantity;

  TransactionDetailModel({
    required this.id,
    required this.productId,
    required this.productName,
    this.productThumbnail,
    required this.price,
    required this.quantity,
  });

  factory TransactionDetailModel.fromJson(Map<String, dynamic> json) {
    return TransactionDetailModel(
      id: json['id'],
      productId: json['product_id'] ?? 0,
      productName: json['product']?['name'] ?? json['product_name'] ?? '',
      productThumbnail: json['product']?['thumbnail'],
      price: json['price'] ?? 0,
      quantity: json['quantity'] ?? 1,
    );
  }

  int get subtotal => price * quantity;
}
