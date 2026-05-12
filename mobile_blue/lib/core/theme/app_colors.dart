import 'package:flutter/material.dart';

/// Blukios Design Tokens
/// Brand: "Blue" + "Kios" — trusted digital marketplace
class AppColors {
  // Primary: Electric Cobalt
  static const Color blue = Color(0xFF2563EB);
  static const Color midnight = Color(0xFF0F172A);
  static const Color softBlue = Color(0xFFDBEAFE);

  // Secondary: Warm Amber (accent)
  static const Color amber = Color(0xFFF59E0B);
  static const Color amberDark = Color(0xFFD97706);

  // Semantic
  static const Color success = Color(0xFF10B981);
  static const Color warning = Color(0xFFEAB308);
  static const Color destructive = Color(0xFFEF4444);
  static const Color info = Color(0xFF3B82F6);

  // Neutrals (Light)
  static const Color backgroundLight = Color(0xFFF8FAFC);
  static const Color surfaceLight = Color(0xFFFFFFFF);
  static const Color borderLight = Color(0xFFE2E8F0);
  static const Color mutedTextLight = Color(0xFF64748B);

  // Neutrals (Dark)
  static const Color backgroundDark = Color(0xFF0F172A);
  static const Color surfaceDark = Color(0xFF1E293B);
  static const Color borderDark = Color(0xFF334155);
  static const Color mutedTextDark = Color(0xFF94A3B8);

  // Alias
  static const Color surface = surfaceLight;
  static const Color primary = blue;
  static const Color textDark = midnight;
}
