import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'core/api/dio_client.dart';
import 'features/auth/data/datasources/auth_remote_datasource.dart';
import 'features/auth/data/repositories/auth_repository_impl.dart';
import 'features/auth/domain/usecases/login_usecase.dart';
import 'features/auth/domain/usecases/register_usecase.dart';
import 'features/auth/presentation/cubit/auth_cubit.dart';
import 'features/auth/presentation/pages/splash_page.dart';
import 'features/products/data/datasources/product_remote_datasource.dart';
import 'features/products/data/repositories/product_repository_impl.dart';
import 'features/products/presentation/cubit/product_cubit.dart';
import 'features/home/data/datasources/category_remote_datasource.dart';
import 'features/home/data/repositories/category_repository_impl.dart';
import 'features/home/presentation/cubit/category_cubit.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  // Dependency Injection setup (Manual for now)
  final dioClient = DioClient();

  // Auth Feature
  final authRemoteDataSource = AuthRemoteDataSource(dioClient);
  final authRepository = AuthRepositoryImpl(authRemoteDataSource);
  final loginUseCase = LoginUseCase(authRepository);
  final registerUseCase = RegisterUseCase(authRepository);
  final authCubit = AuthCubit(loginUseCase, registerUseCase, authRepository);

  // Product Feature
  final productRemoteDataSource = ProductRemoteDataSourceImpl(dioClient);
  final productRepository = ProductRepositoryImpl(productRemoteDataSource);
  final productCubit = ProductCubit(productRepository);

  // Category Feature
  final categoryRemoteDataSource = CategoryRemoteDataSourceImpl(dioClient);
  final categoryRepository = CategoryRepositoryImpl(
    remoteDataSource: categoryRemoteDataSource,
  );
  final categoryCubit = CategoryCubit(categoryRepository);

  runApp(
    MultiBlocProvider(
      providers: [
        BlocProvider<AuthCubit>.value(value: authCubit),
        BlocProvider<ProductCubit>.value(value: productCubit),
        BlocProvider<CategoryCubit>.value(value: categoryCubit),
      ],
      child: const MyApp(),
    ),
  );
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Blukios',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.blue),
        useMaterial3: true,
      ),
      home: const SplashPage(),
    );
  }
}
