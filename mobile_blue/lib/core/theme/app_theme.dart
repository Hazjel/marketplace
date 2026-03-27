import 'package:flutter/material.dart';
import 'app_colors.dart';
import 'app_typography.dart';

class AppTheme {
  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      scaffoldBackgroundColor: AppColors.backgroundLight,
      colorScheme: const ColorScheme.light(
        primary: AppColors.blue,
        secondary: AppColors.softBlue,
        error: AppColors.destructive,
        surface: AppColors.surfaceLight,
        onSurface: AppColors.midnight,
        outline: AppColors.borderLight,
      ),
      textTheme: TextTheme(
        displayLarge: AppTypography.h1.copyWith(color: AppColors.midnight),
        displayMedium: AppTypography.h2.copyWith(color: AppColors.midnight),
        displaySmall: AppTypography.h3.copyWith(color: AppColors.midnight),
        headlineMedium: AppTypography.h4.copyWith(color: AppColors.midnight),
        bodyLarge: AppTypography.bodyL.copyWith(color: AppColors.midnight),
        bodyMedium: AppTypography.bodyM.copyWith(color: AppColors.midnight),
        bodySmall: AppTypography.bodyS.copyWith(
          color: AppColors.mutedTextLight,
        ),
        labelSmall: AppTypography.caption.copyWith(
          color: AppColors.mutedTextLight,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.blue,
          foregroundColor: Colors.white,
          textStyle: AppTypography.bodyM.copyWith(fontWeight: FontWeight.w600),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          elevation: 0,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surfaceLight,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 14,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.borderLight),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.borderLight),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.blue, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.destructive),
        ),
        hintStyle: AppTypography.bodyM.copyWith(
          color: AppColors.mutedTextLight,
        ),
      ),
      cardTheme: CardTheme(
        color: AppColors.surfaceLight,
        shadowColor: Colors.black.withValues(alpha: 0.05),
        elevation: 2,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: EdgeInsets.zero,
      ),
    );
  }
}
