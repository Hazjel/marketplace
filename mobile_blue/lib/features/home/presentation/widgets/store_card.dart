import 'package:flutter/material.dart';
import 'package:mobile_blue/core/theme/app_colors.dart';
import 'package:mobile_blue/core/theme/app_typography.dart';
import 'package:mobile_blue/features/home/data/models/store_model.dart';

class StoreCard extends StatelessWidget {
  final Store store;
  final VoidCallback? onTap;

  const StoreCard({super.key, required this.store, this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 240, // Fixed width for horizontal scrolling
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.borderLight),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Column(
            children: [
              // Banner
              SizedBox(
                height: 80,
                width: double.infinity,
                child:
                    store.bannerUrl != null
                        ? Image.network(
                          store.bannerUrl!,
                          fit: BoxFit.cover,
                          errorBuilder:
                              (_, __, ___) => Container(
                                color: AppColors.blue.withValues(alpha: 0.1),
                                child: const Icon(
                                  Icons.store,
                                  color: AppColors.blue,
                                ),
                              ),
                        )
                        : Container(
                          color: AppColors.blue.withValues(alpha: 0.1),
                        ),
              ),
              const SizedBox(height: 40), // Space for floating logo
              // Content
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                child: Column(
                  children: [
                    // Name & Badge
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Flexible(
                          child: Text(
                            store.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.bodyL.copyWith(
                              fontWeight: FontWeight.bold,
                              color: AppColors.midnight,
                            ),
                          ),
                        ),
                        if (store.isVerified) ...[
                          const SizedBox(width: 4),
                          const Icon(
                            Icons.verified,
                            color: AppColors.blue,
                            size: 16,
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),

                    // Location & Rating
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(
                          Icons.location_on,
                          size: 12,
                          color: AppColors.mutedTextLight,
                        ),
                        const SizedBox(width: 2),
                        Flexible(
                          child: Text(
                            store.city,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: AppTypography.caption.copyWith(
                              color: AppColors.mutedTextLight,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        const Icon(Icons.star, size: 12, color: Colors.amber),
                        const SizedBox(width: 2),
                        Text(
                          store.rating.toString(),
                          style: AppTypography.caption.copyWith(
                            color: AppColors.mutedTextLight,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 12),
                    // Visit Button
                    SizedBox(
                      height: 36,
                      child: OutlinedButton(
                        onPressed: onTap,
                        style: OutlinedButton.styleFrom(
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(20),
                          ),
                          side: const BorderSide(color: AppColors.borderLight),
                        ),
                        child: Text(
                          'Kunjungi Toko',
                          style: AppTypography.caption.copyWith(
                            fontWeight: FontWeight.bold,
                            color: AppColors.midnight,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                ),
              ),
            ],
          ),

          // Floating Logo
          Positioned(
            top: 45, // 80 (banner) - 35 (half logo)
            left: 0,
            right: 0,
            child: Center(
              child: Container(
                width: 70,
                height: 70,
                decoration: BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 4),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.1),
                      blurRadius: 4,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                clipBehavior: Clip.antiAlias,
                child:
                    store.logoUrl != null
                        ? Image.network(
                          store.logoUrl!,
                          fit: BoxFit.cover,
                          errorBuilder:
                              (_, __, ___) => const Icon(
                                Icons.storefront,
                                color: AppColors.mutedTextLight,
                              ),
                        )
                        : const Icon(
                          Icons.storefront,
                          color: AppColors.mutedTextLight,
                        ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
