import 'package:characters/characters.dart';

extension StringInitial on String {
  /// أول حرف (grapheme) بأمان — يرجّع '؟' لو النص فارغ، فلا ينهار الـ UI
  /// عند اسم فارغ (بدل StateError من characters.first).
  String get initialOrQuestion =>
      trim().characters.isEmpty ? '؟' : trim().characters.first;
}
