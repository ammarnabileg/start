import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// متحكّم لغة التطبيق (عربي/إنجليزي) مع حفظ الاختيار.
/// الاتجاه يتبع اللغة: عربي = RTL، إنجليزي = LTR.
final localeProvider =
    StateNotifierProvider<LocaleController, Locale>((ref) => LocaleController());

class LocaleController extends StateNotifier<Locale> {
  LocaleController() : super(const Locale('ar')) {
    _load();
  }

  static const _storage = FlutterSecureStorage();
  static const _key = 'app_locale';

  Future<void> _load() async {
    try {
      final v = await _storage.read(key: _key);
      if (v == 'en') state = const Locale('en');
    } catch (_) {}
  }

  Future<void> setLocale(Locale locale) async {
    state = locale;
    try {
      await _storage.write(key: _key, value: locale.languageCode);
    } catch (_) {}
  }

  bool get isArabic => state.languageCode == 'ar';
}
