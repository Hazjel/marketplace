import 'package:flutter/material.dart';

class HomeHeader extends StatelessWidget {
  final VoidCallback? onSearchTap;
  final VoidCallback? onCartTap;
  final VoidCallback? onNotificationTap;
  final VoidCallback? onMenuTap;

  const HomeHeader({
    super.key,
    this.onSearchTap,
    this.onCartTap,
    this.onNotificationTap,
    this.onMenuTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      color: Colors.white,
      child: Row(
        children: [
          // Search Bar
          Expanded(
            child: GestureDetector(
              onTap: onSearchTap,
              child: Container(
                height: 40,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: Colors.grey[100],
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.grey[300]!),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.search, color: Colors.grey),
                    const SizedBox(width: 8),
                    Text(
                      'Cari barang di sini...',
                      style: TextStyle(color: Colors.grey[500], fontSize: 13),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          // Actions
          GestureDetector(
            onTap: onNotificationTap,
            child: const Icon(Icons.notifications_outlined, color: Colors.grey),
          ),
          const SizedBox(width: 12),
          GestureDetector(
            onTap: onCartTap,
            child: const Icon(Icons.shopping_cart_outlined, color: Colors.grey),
          ),
          const SizedBox(width: 12),
          GestureDetector(
            onTap: onMenuTap,
            child: const Icon(Icons.menu, color: Colors.grey),
          ),
        ],
      ),
    );
  }
}
