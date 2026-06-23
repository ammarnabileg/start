import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'app_colors.dart';

/// Tajawal للعربي (نظيف ويدعم RTL). الأرقام بنفس الخط.
class AppTypography {
  AppTypography._();

  static TextTheme textTheme(Brightness brightness) {
    final base = brightness == Brightness.dark
        ? ThemeData.dark().textTheme
        : ThemeData.light().textTheme;
    final color = brightness == Brightness.dark
        ? Colors.white
        : AppColors.textPrimary;

    return GoogleFonts.tajawalTextTheme(base).copyWith(
      displayLarge: _t(28, FontWeight.w700, color),
      headlineMedium: _t(24, FontWeight.w700, color),
      titleLarge: _t(20, FontWeight.w600, color),
      titleMedium: _t(17, FontWeight.w600, color),
      bodyLarge: _t(16, FontWeight.w400, color),
      bodyMedium: _t(14, FontWeight.w400, color),
      labelLarge: _t(15, FontWeight.w600, color),
      bodySmall: _t(13, FontWeight.w400, AppColors.textSecondary),
    ).apply(fontFamily: GoogleFonts.tajawal().fontFamily);
  }

  static TextStyle _t(double size, FontWeight weight, Color color) =>
      GoogleFonts.tajawal(fontSize: size, fontWeight: weight, color: color);
}
