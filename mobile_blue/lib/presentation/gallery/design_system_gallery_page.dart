import 'package:flutter/material.dart';
import 'package:mobile_blue/core/theme/app_colors.dart';
import 'package:mobile_blue/core/theme/app_typography.dart';
import 'package:mobile_blue/core/components/custom_button.dart';
import 'package:mobile_blue/core/components/custom_text_field.dart';

class DesignSystemGalleryPage extends StatelessWidget {
  const DesignSystemGalleryPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mobile Blue DS'),
        backgroundColor: AppColors.surfaceLight,
        elevation: 0,
        foregroundColor: AppColors.midnight,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _sectionHeader('Typography'),
            Text('Heading 1', style: AppTypography.h1),
            Text('Heading 2', style: AppTypography.h2),
            Text('Heading 3', style: AppTypography.h3),
            Text('Heading 4', style: AppTypography.h4),
            const SizedBox(height: 8),
            Text(
              'Body Large - The quick brown fox jumps.',
              style: AppTypography.bodyL,
            ),
            Text(
              'Body Medium - Over the lazy dog.',
              style: AppTypography.bodyM,
            ),
            Text('Body Small - 1234567890.', style: AppTypography.bodyS),
            Text('Caption - Small details.', style: AppTypography.caption),

            _sectionHeader('Colors'),
            Row(
              children: [
                _colorBox(AppColors.blue, 'Primary'),
                _colorBox(AppColors.midnight, 'Midnight'),
                _colorBox(AppColors.softBlue, 'Soft', textColor: Colors.black),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _colorBox(AppColors.success, 'Success'),
                _colorBox(AppColors.warning, 'Warning'),
                _colorBox(AppColors.destructive, 'Error'),
              ],
            ),

            _sectionHeader('Buttons'),
            CustomButton(text: 'Primary Button', onPressed: () {}),
            const SizedBox(height: 12),
            CustomButton(
              text: 'Outlined Button',
              type: ButtonType.outline,
              onPressed: () {},
            ),
            const SizedBox(height: 12),
            CustomButton(text: 'Loading...', isLoading: true, onPressed: () {}),

            _sectionHeader('Inputs'),
            const CustomTextField(
              label: 'Email Address',
              hint: 'Enter your email',
              prefixIcon: Icon(Icons.email_outlined),
            ),
            const SizedBox(height: 16),
            const CustomTextField(
              label: 'Password',
              hint: 'Enter your password',
              obscureText: true,
              prefixIcon: Icon(Icons.lock_outline),
              suffixIcon: Icon(Icons.visibility_off_outlined),
            ),

            _sectionHeader('Cards'),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Card Title', style: AppTypography.h4),
                  const SizedBox(height: 8),
                  Text(
                    'This is a card component displaying content with the standard shadow and border radius.',
                    style: AppTypography.bodyM,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }

  Widget _sectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.only(top: 32, bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title.toUpperCase(),
            style: AppTypography.caption.copyWith(
              color: AppColors.mutedTextLight,
              letterSpacing: 1.2,
              fontWeight: FontWeight.bold,
            ),
          ),
          const Divider(),
        ],
      ),
    );
  }

  Widget _colorBox(Color color, String name, {Color textColor = Colors.white}) {
    return Expanded(
      child: Container(
        height: 80,
        margin: const EdgeInsets.only(right: 8),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(8),
        ),
        alignment: Alignment.center,
        child: Text(
          name,
          style: AppTypography.caption.copyWith(
            color: textColor,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }
}
