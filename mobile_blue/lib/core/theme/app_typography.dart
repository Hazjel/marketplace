import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Blukios Typography System
/// Font: Plus Jakarta Sans — geometric, modern, friendly
class AppTypography {
  static TextStyle get h1 => GoogleFonts.plusJakartaSans(
    fontSize: 36,
    fontWeight: FontWeight.bold,
    height: 1.1,
  );

  static TextStyle get h2 => GoogleFonts.plusJakartaSans(
    fontSize: 30,
    fontWeight: FontWeight.bold,
    height: 1.2,
  );

  static TextStyle get h3 => GoogleFonts.plusJakartaSans(
    fontSize: 24,
    fontWeight: FontWeight.w600,
    height: 1.2,
  );

  static TextStyle get h4 => GoogleFonts.plusJakartaSans(
    fontSize: 20,
    fontWeight: FontWeight.w600,
    height: 1.3,
  );

  static TextStyle get bodyL => GoogleFonts.plusJakartaSans(
    fontSize: 18,
    fontWeight: FontWeight.w400,
    height: 1.6,
  );

  static TextStyle get bodyM => GoogleFonts.plusJakartaSans(
    fontSize: 16,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );

  static TextStyle get bodyS => GoogleFonts.plusJakartaSans(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );

  static TextStyle get caption => GoogleFonts.plusJakartaSans(
    fontSize: 12,
    fontWeight: FontWeight.w500,
    height: 1.4,
  );
}
