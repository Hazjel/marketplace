import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTypography {
  static TextStyle get h1 => GoogleFonts.lexendDeca(
    fontSize: 36,
    fontWeight: FontWeight.bold,
    height: 1.1,
  );

  static TextStyle get h2 => GoogleFonts.lexendDeca(
    fontSize: 30,
    fontWeight: FontWeight.bold,
    height: 1.2,
  );

  static TextStyle get h3 => GoogleFonts.lexendDeca(
    fontSize: 24,
    fontWeight: FontWeight.w600, // Semibold
    height: 1.2,
  );

  static TextStyle get h4 => GoogleFonts.lexendDeca(
    fontSize: 20,
    fontWeight: FontWeight.w600, // Semibold
    height: 1.3,
  );

  static TextStyle get bodyL => GoogleFonts.lexendDeca(
    fontSize: 18,
    fontWeight: FontWeight.w400, // Regular
    height: 1.6,
  );

  static TextStyle get bodyM => GoogleFonts.lexendDeca(
    fontSize: 16,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );

  static TextStyle get bodyS => GoogleFonts.lexendDeca(
    fontSize: 14,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );

  static TextStyle get caption => GoogleFonts.lexendDeca(
    fontSize: 12,
    fontWeight: FontWeight.w500, // Medium
    height: 1.4,
  );
}
