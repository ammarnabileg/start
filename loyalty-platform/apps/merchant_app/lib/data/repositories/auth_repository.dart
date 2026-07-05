import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع المصادقة وعمليات تسجيل الدخول/التسجيل.
class AuthRepository {
  AuthRepository(this._client);
  final SupabaseClient _client;

  bool get isLoggedIn => _client.auth.currentUser != null;
  String? get currentUserId => _client.auth.currentUser?.id;

  Future<void> signInWithPasswordEmail(String email, String password) {
    return _client.auth.signInWithPassword(email: email, password: password);
  }

  Future<void> signInWithPasswordPhone(String phone, String password) {
    return _client.auth.signInWithPassword(phone: phone, password: password);
  }

  Future<void> resetPasswordForEmail(String email) {
    return _client.auth.resetPasswordForEmail(email);
  }

  Future<void> signInWithOtp(String phone) {
    return _client.auth.signInWithOtp(phone: phone);
  }

  Future<void> verifyOtp(String phone, String token) {
    return _client.auth.verifyOTP(
      type: OtpType.sms,
      phone: phone,
      token: token,
    );
  }

  Future<void> signOut() {
    return _client.auth.signOut();
  }

  Future<FunctionResponse> claimStaff() {
    return _client.functions.invoke('claim-staff');
  }
}

final authRepoProvider = Provider<AuthRepository>(
    (ref) => AuthRepository(ref.read(supabaseClientProvider)));
