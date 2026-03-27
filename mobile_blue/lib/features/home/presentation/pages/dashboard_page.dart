import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:mobile_blue/features/auth/presentation/pages/login_page.dart';
import 'package:mobile_blue/features/products/presentation/cubit/product_cubit.dart';
import 'package:mobile_blue/features/products/presentation/cubit/product_state.dart';
import 'package:mobile_blue/features/products/presentation/widgets/product_card.dart';

import '../cubit/category_cubit.dart';
import '../widgets/category_list.dart';
import '../widgets/home_banner.dart';
import '../widgets/home_header.dart';
import 'search_page.dart';

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    context.read<ProductCubit>().fetchProducts();
    context.read<CategoryCubit>().fetchCategories();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_isBottom) {
      context.read<ProductCubit>().fetchProducts();
    }
  }

  bool get _isBottom {
    if (!_scrollController.hasClients) return false;
    final maxScroll = _scrollController.position.maxScrollExtent;
    final currentScroll = _scrollController.offset;
    return currentScroll >= (maxScroll * 0.9);
  }

  Future<void> _logout(BuildContext context) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    if (context.mounted) {
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (context) => const LoginPage()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            // Header (Search Bar)
            HomeHeader(
              onSearchTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (context) => const SearchPage()),
                );
              },
              onCartTap: () {},
              onNotificationTap: () {},
              onMenuTap: () => _logout(context),
            ),

            // Scrollable Content
            Expanded(
              child: BlocBuilder<ProductCubit, ProductState>(
                builder: (context, state) {
                  // Pull to Refresh wrapper
                  return RefreshIndicator(
                    onRefresh: () async {
                      await context.read<ProductCubit>().fetchProducts(
                        isRefresh: true,
                      );
                    },
                    child: CustomScrollView(
                      controller: _scrollController,
                      slivers: [
                        // Banner
                        const SliverToBoxAdapter(
                          child: Padding(
                            padding: EdgeInsets.only(top: 8, bottom: 16),
                            child: HomeBanner(),
                          ),
                        ),

                        // Categories
                        const SliverToBoxAdapter(
                          child: Padding(
                            padding: EdgeInsets.only(bottom: 16),
                            child: CategoryList(),
                          ),
                        ),

                        // Product Grid Header
                        const SliverToBoxAdapter(
                          child: Padding(
                            padding: EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 8,
                            ),
                            child: Text(
                              'Rekomendasi Untukmu',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ),

                        // Product Grid State Handling
                        if (state.status == ProductStatus.loading &&
                            state.products.isEmpty)
                          const SliverFillRemaining(
                            child: Center(child: CircularProgressIndicator()),
                          )
                        else if (state.status == ProductStatus.failure &&
                            state.products.isEmpty)
                          SliverFillRemaining(
                            child: Center(child: Text(state.errorMessage)),
                          )
                        else ...[
                          SliverPadding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 8,
                            ),
                            sliver: SliverGrid(
                              delegate: SliverChildBuilderDelegate((
                                context,
                                index,
                              ) {
                                final product = state.products[index];
                                return ProductCard(
                                  product: product,
                                  onTap: () {
                                    // TODO: Go to Detail Page
                                  },
                                );
                              }, childCount: state.products.length),
                              gridDelegate:
                                  const SliverGridDelegateWithFixedCrossAxisCount(
                                    crossAxisCount: 2,
                                    childAspectRatio: 0.6,
                                    crossAxisSpacing: 16,
                                    mainAxisSpacing: 16,
                                  ),
                            ),
                          ),

                          // Bottom Loading Indicator
                          if (!state.hasReachedMax)
                            const SliverToBoxAdapter(
                              child: Padding(
                                padding: EdgeInsets.all(16.0),
                                child: Center(
                                  child: CircularProgressIndicator(),
                                ),
                              ),
                            ),
                        ],

                        // Bottom Padding
                        const SliverToBoxAdapter(child: SizedBox(height: 24)),
                      ],
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
