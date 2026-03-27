import 'package:flutter_bloc/flutter_bloc.dart';
import '../../domain/repositories/product_repository.dart';
import 'product_state.dart';

class ProductCubit extends Cubit<ProductState> {
  final ProductRepository _productRepository;

  ProductCubit(this._productRepository) : super(const ProductState());

  Future<void> fetchProducts({bool isRefresh = false, String? search}) async {
    if (state.hasReachedMax && !isRefresh && search == null) return;

    try {
      if (state.status == ProductStatus.initial ||
          isRefresh ||
          search != null) {
        emit(state.copyWith(status: ProductStatus.loading));
        final products = await _productRepository.getProducts(
          page: 1,
          search: search,
        );
        emit(
          state.copyWith(
            status: ProductStatus.success,
            products: products,
            hasReachedMax: products.length < 6, // row_per_page is 6
            page: 1,
          ),
        );
      } else {
        // Load More
        final nextPage = state.page + 1;
        final products = await _productRepository.getProducts(page: nextPage);

        if (products.isEmpty) {
          emit(state.copyWith(hasReachedMax: true));
        } else {
          emit(
            state.copyWith(
              status: ProductStatus.success,
              products: List.of(state.products)..addAll(products),
              hasReachedMax: products.length < 6,
              page: nextPage,
            ),
          );
        }
      }
    } catch (e) {
      if (e.toString().contains('DioException')) {
        emit(
          state.copyWith(
            status: ProductStatus.failure,
            errorMessage: 'Gagal memuat produk. Periksa koneksi internet.',
          ),
        );
      } else {
        emit(
          state.copyWith(
            status: ProductStatus.failure,
            errorMessage: e.toString(),
          ),
        );
      }
    }
  }
}
