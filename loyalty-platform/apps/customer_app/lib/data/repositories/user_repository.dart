import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع المستخدم: الجلسة، الملف الشخصي، أعلام التفضيلات، وحذف الحساب.
class UserRepository {
  UserRepository(this._client);
  final SupabaseClient _client;

  String? get currentUserId => _client.auth.currentUser?.id;
  Session? get currentSession => _client.auth.currentSession;

  /// المستخدم الحالي (مع qr_secret لتوليد الـ QR محليًا/أوفلاين).
  Future<AppUser> currentUser() async {
    final uid = _client.auth.currentUser!.id;
    final row = await _client.from('users').select().eq('id', uid).single();
    return AppUser.fromJson(row);
  }

  /// تحديث حقول الملف الشخصي للمستخدم الحالي.
  Future<void> updateProfile(Map<String, dynamic> values) async {
    final uid = _client.auth.currentUser!.id;
    await _client.from('users').update(values).eq('id', uid);
  }

  /// تحديث علم تفضيل واحد (push/proximity/leaderboard …).
  Future<void> updateFlag(String column, bool value) async {
    final uid = _client.auth.currentUser!.id;
    await _client.from('users').update({column: value}).eq('id', uid);
  }

  /// إنشاء/تحديث صف الملف في جدول users (عند التسجيل).
  Future<void> upsertProfile(Map<String, dynamic> values) async {
    await _client.from('users').upsert(values);
  }

  /// حذف الحساب عبر دالة الحافة، ثم إنهاء الجلسة.
  Future<void> deleteAccount() async {
    await _client.functions.invoke('delete-account');
    await _client.auth.signOut();
  }

  // ===== المصادقة =====

  Future<void> signInWithOtp({required String phone}) =>
      _client.auth.signInWithOtp(phone: phone);

  Future<void> signInWithPassword({
    required String phone,
    required String password,
  }) =>
      _client.auth.signInWithPassword(phone: phone, password: password);

  Future<void> verifyOtp({required String phone, required String token}) =>
      _client.auth.verifyOTP(type: OtpType.sms, phone: phone, token: token);

  Future<void> updatePassword(String password) =>
      _client.auth.updateUser(UserAttributes(password: password));

  Future<void> signOut() => _client.auth.signOut();
}

final userRepoProvider = Provider<UserRepository>(
    (ref) => UserRepository(ref.read(supabaseClientProvider)));
