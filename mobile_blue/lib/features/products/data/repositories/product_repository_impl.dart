import '../../domain/entities/product.dart';
import '../../domain/repositories/product_repository.dart';
import '../datasources/product_remote_datasource.dart';

class ProductRepositoryImpl implements ProductRepository {
  final ProductRemoteDataSource _remoteDataSource;

  ProductRepositoryImpl(this._remoteDataSource);

  @override
  @override
  Future<List<Product>> getProducts({int page = 1, String? search}) async {
    return await _remoteDataSource.getProducts(page: page, search: search);
  }
}
