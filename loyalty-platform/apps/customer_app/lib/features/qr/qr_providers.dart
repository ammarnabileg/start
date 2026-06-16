import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../data/repositories/user_repository.dart';

/// المستخدم الحالي (مع qr_secret لتوليد الـ QR محليًا/أوفلاين).
/// ملاحظة: يُفضّل كاش الـ secret في flutter_secure_storage عند أول دخول
/// عشان شاشة الـ QR تشتغل حتى من غير نت.
final currentUserProvider = FutureProvider.autoDispose<AppUser>((ref) async {
  return ref.read(userRepoProvider).currentUser();
});
