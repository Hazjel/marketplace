import 'package:mobile_blue/core/api/dio_client.dart';
import 'package:mobile_blue/features/products/data/models/product_model.dart';

abstract class ProductRemoteDataSource {
  Future<List<ProductModel>> getProducts({int page = 1, String? search});
}

class ProductRemoteDataSourceImpl implements ProductRemoteDataSource {
  final DioClient _dioClient;

  ProductRemoteDataSourceImpl(this._dioClient);

  @override
  Future<List<ProductModel>> getProducts({int page = 1, String? search}) async {
    try {
      final Map<String, dynamic> queryParams = {
        'row_per_page': 6,
        'page': page,
      };

      if (search != null && search.isNotEmpty) {
        queryParams['search'] = search;
      }

      final response = await _dioClient.dio.get(
        '/product/all/paginated',
        queryParameters: queryParams,
      );

      final List<dynamic> data = response.data['data']['data'];
      return data.map((json) {
        try {
          return ProductModel.fromJson(json);
        } catch (e) {
          // Log error but continue with other products if possible, or rethrow
          // debugPrint('Error parsing product: $json');
          // debugPrint('Error details: $e');
          rethrow;
        }
      }).toList();
    } catch (e) {
      rethrow;
    }
  }
}
