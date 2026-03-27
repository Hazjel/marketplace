import 'package:flutter_bloc/flutter_bloc.dart';
import '../../domain/repositories/category_repository.dart';
import 'category_state.dart';

class CategoryCubit extends Cubit<CategoryState> {
  final CategoryRepository repository;

  CategoryCubit(this.repository) : super(CategoryInitial());

  Future<void> fetchCategories() async {
    try {
      emit(CategoryLoading());
      final categories = await repository.getCategories();
      emit(CategorySuccess(categories));
    } catch (e) {
      emit(CategoryFailure(e.toString()));
    }
  }
}
