import '../entities/product.dart';

abstract class ProductRepository {
  Future<List<Product>> getProducts({int page = 1, String? search});
}
