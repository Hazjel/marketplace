import 'package:flutter/material.dart';
import 'package:mobile_blue/core/theme/app_colors.dart';
import 'package:mobile_blue/core/theme/app_typography.dart';
import 'package:mobile_blue/features/home/data/models/product_model.dart';
// import 'package:mobile_blue/features/products/data/services/product_service.dart';
import 'package:mobile_blue/core/components/custom_button.dart';
import 'package:intl/intl.dart';

class ProductDetailPage extends StatefulWidget {
  final String slug;

  const ProductDetailPage({super.key, required this.slug});

  @override
  State<ProductDetailPage> createState() => _ProductDetailPageState();
}

class _ProductDetailPageState extends State<ProductDetailPage> {
  // final ProductService _productService = ProductService();
  Product? _product;
  bool _isLoading = true;
  int _currentImageIndex = 0;

  @override
  void initState() {
    super.initState();
    _fetchProduct();
  }

  Future<void> _fetchProduct() async {
    // TODO: Implement Product Detail Fetching using Cubit/Repository
    // final product = await _productService.fetchProductBySlug(widget.slug);
    if (mounted) {
      setState(() {
        // _product = product;
        _isLoading = false;
      });
    }
  }

  String _formatCurrency(String price) {
    if (price.isEmpty) return 'Rp 0';
    try {
      final number = double.parse(price);
      final currencyFormatter = NumberFormat.currency(
        locale: 'id_ID',
        symbol: 'Rp ',
        decimalDigits: 0,
      );
      return currencyFormatter.format(number);
    } catch (e) {
      return price;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (_product == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Product Not Found')),
        body: const Center(child: Text('Product not found')),
      );
    }

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Back Button & Header (Overlay)
                    Stack(
                      children: [
                        // Image Settings
                        SizedBox(
                          height: 375,
                          width: double.infinity,
                          child: PageView.builder(
                            itemCount: _product!.images.length,
                            onPageChanged: (index) {
                              setState(() {
                                _currentImageIndex = index;
                              });
                            },
                            itemBuilder: (context, index) {
                              return Image.network(
                                _product!.images[index],
                                fit: BoxFit.cover,
                                errorBuilder: (context, error, stackTrace) {
                                  return Container(
                                    color: Colors.grey[200],
                                    child: const Icon(
                                      Icons.image_not_supported,
                                      size: 50,
                                      color: Colors.grey,
                                    ),
                                  );
                                },
                              );
                            },
                          ),
                        ),
                        // Back Button
                        Positioned(
                          top: 16,
                          left: 16,
                          child: InkWell(
                            onTap: () => Navigator.pop(context),
                            child: Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.8),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.arrow_back,
                                color: AppColors.midnight,
                              ),
                            ),
                          ),
                        ),
                        // Image Indicator
                        if (_product!.images.length > 1)
                          Positioned(
                            bottom: 16,
                            right: 16,
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.black.withValues(alpha: 0.6),
                                borderRadius: BorderRadius.circular(16),
                              ),
                              child: Text(
                                '${_currentImageIndex + 1}/${_product!.images.length}',
                                style: AppTypography.bodyS.copyWith(
                                  color: Colors.white,
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),

                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          // Price
                          Text(
                            _formatCurrency(_product!.price),
                            style: AppTypography.h3.copyWith(
                              color: AppColors.midnight,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 8),

                          // Name
                          Text(
                            _product!.name,
                            style: AppTypography.bodyL.copyWith(
                              color: AppColors.midnight,
                            ),
                          ),
                          const SizedBox(height: 12),

                          // Rating & Sold
                          Row(
                            children: [
                              const Icon(
                                Icons.star,
                                color: Colors.amber,
                                size: 16,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                '${_product!.rating}',
                                style: AppTypography.bodyS.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                height: 12,
                                width: 1,
                                color: Colors.grey[300],
                              ),
                              const SizedBox(width: 8),
                              Text(
                                '${_product!.sold} Terjual',
                                style: AppTypography.bodyS.copyWith(
                                  color: AppColors.mutedTextLight,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 24),

                          // Description
                          Text(
                            'Deskripsi Produk',
                            style: AppTypography.h4.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            _product!.description,
                            style: AppTypography.bodyM.copyWith(
                              color: AppColors.mutedTextLight,
                              height: 1.5,
                            ),
                          ),
                          const SizedBox(height: 24),

                          // Store Info
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              border: Border.all(color: AppColors.borderLight),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              children: [
                                CircleAvatar(
                                  radius: 24,
                                  backgroundImage: NetworkImage(
                                    _product!.store.logo,
                                  ),
                                  onBackgroundImageError: (_, __) {},
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        _product!.store.name,
                                        style: AppTypography.bodyM.copyWith(
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      const Text(
                                        'Online',
                                        style: TextStyle(
                                          color: Colors.green,
                                          fontSize: 12,
                                          fontWeight: FontWeight.bold,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                OutlinedButton(
                                  onPressed: () {},
                                  style: OutlinedButton.styleFrom(
                                    side: const BorderSide(
                                      color: AppColors.blue,
                                    ),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                  ),
                                  child: Text(
                                    'Kunjungi',
                                    style: AppTypography.bodyS.copyWith(
                                      color: AppColors.blue,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Bottom Action Bar
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    offset: const Offset(0, -4),
                    blurRadius: 16,
                  ),
                ],
              ),
              child: Row(
                children: [
                  // Chat Button
                  Container(
                    decoration: BoxDecoration(
                      border: Border.all(color: AppColors.borderLight),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: IconButton(
                      icon: const Icon(
                        Icons.chat_bubble_outline,
                        color: AppColors.mutedTextLight,
                      ),
                      onPressed: () {},
                    ),
                  ),
                  const SizedBox(width: 12),
                  // Add to Cart
                  Expanded(
                    child: CustomButton(
                      text: '+ Keranjang',
                      onPressed: () {
                        // TODO: Implement Add to Cart
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Added to Cart!')),
                        );
                      },
                      type: ButtonType.secondary,
                    ),
                  ),
                  const SizedBox(width: 12),
                  // Buy Now
                  Expanded(
                    child: CustomButton(
                      text: 'Beli Langsung',
                      onPressed: () {
                        // TODO: Implement Buy Now
                      },
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
