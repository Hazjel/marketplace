import 'package:equatable/equatable.dart';
import 'package:mobile_blue/features/products/domain/entities/product.dart';

enum ProductStatus { initial, loading, success, failure }

class ProductState extends Equatable {
  final ProductStatus status;
  final List<Product> products;
  final bool hasReachedMax;
  final String errorMessage;
  final int page;

  const ProductState({
    this.status = ProductStatus.initial,
    this.products = const [],
    this.hasReachedMax = false,
    this.errorMessage = '',
    this.page = 1,
  });

  ProductState copyWith({
    ProductStatus? status,
    List<Product>? products,
    bool? hasReachedMax,
    String? errorMessage,
    int? page,
  }) {
    return ProductState(
      status: status ?? this.status,
      products: products ?? this.products,
      hasReachedMax: hasReachedMax ?? this.hasReachedMax,
      errorMessage: errorMessage ?? this.errorMessage,
      page: page ?? this.page,
    );
  }

  @override
  List<Object> get props => [
    status,
    products,
    hasReachedMax,
    errorMessage,
    page,
  ];
}
