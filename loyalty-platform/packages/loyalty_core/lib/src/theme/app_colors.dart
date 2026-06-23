import 'package:flutter/material.dart';

/// هوية Hatchy: أصفر دافئ + بني داكن + كريمي + لمسات ذهبية للوضع الداكن.
class AppColors {
  AppColors._();

  // Primary — الأصفر بتاع Hatchy
  static const primary = Color(0xFFFFC42E);
  static const primaryDark = Color(0xFFF5A800);
  static const primaryLight = Color(0xFFFFE08A);

  // Backgrounds — كريمي دافئ
  static const background = Color(0xFFFFFDF6);
  static const surface = Color(0xFFFFFFFF);
  static const surfaceCream = Color(0xFFFFF6E0);

  // Text — بني داكن (مش أسود) عشان الدفء
  static const textPrimary = Color(0xFF3D2B1F);
  static const textSecondary = Color(0xFF8A7560);
  static const onPrimary = Color(0xFF2E1F14);

  // Dark (داشبورد التاجر الداكن في صور Hatchy)
  static const darkBg = Color(0xFF121212);
  static const darkSurface = Color(0xFF1E1E1E);
  static const gold = Color(0xFFE6B422);

  // Semantic
  static const success = Color(0xFF34C759);
  static const warning = Color(0xFFFF9F0A);
  static const error = Color(0xFFFF3B30);
  static const info = Color(0xFF5AC8FA);

  // Loyalty levels
  static const bronze = Color(0xFFCD7F32);
  static const silver = Color(0xFFB0B0B0);
  static const goldTier = Color(0xFFFFD700);
  static const platinum = Color(0xFFE5E4E2);

  static const divider = Color(0xFFEDE4D3);

  // طبقات/ظلال — للعمق الناعم بستايل Hatchy
  static const shadow = Color(0x14000000); // 8% أسود
  static const shadowSoft = Color(0x0A000000); // 4% أسود
  static const scrim = Color(0x66000000);

  // ألوان دلالية فاتحة (لخلفيات الشارات/التنبيهات)
  static const successBg = Color(0xFFE7F8EC);
  static const warningBg = Color(0xFFFFF3E0);
  static const errorBg = Color(0xFFFFEBEA);
  static const infoBg = Color(0xFFE6F4FF);

  /// تدرّج الهيدر الأصفر (زي الهيرو في الصور).
  static const heroGradient = LinearGradient(
    begin: Alignment.topRight,
    end: Alignment.bottomLeft,
    colors: [Color(0xFFFFD23F), Color(0xFFFFB800)],
  );

  /// تدرّج زرّ أساسي ألمع وأكثر حيوية.
  static const buttonGradient = LinearGradient(
    begin: Alignment.topRight,
    end: Alignment.bottomLeft,
    colors: [Color(0xFFFFD23F), Color(0xFFFFB300)],
  );

  /// تدرّج ذهبي (للمكافآت/المستويات/الصدارة).
  static const goldGradient = LinearGradient(
    begin: Alignment.topRight,
    end: Alignment.bottomLeft,
    colors: [Color(0xFFFFE08A), Color(0xFFE6B422)],
  );

  /// تدرّج داكن دافئ فخم (داشبورد التاجر) — بنّي إسبريسو بدل الأسود القاسي.
  static const darkGradient = LinearGradient(
    begin: Alignment.topRight,
    end: Alignment.bottomLeft,
    colors: [Color(0xFF4A3320), Color(0xFF2A1B11)],
  );
}
