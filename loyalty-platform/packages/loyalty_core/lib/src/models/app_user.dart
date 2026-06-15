/// مستخدم (عميل). متطابق مع جدول public.users.
class AppUser {
  final String id;
  final String name;
  final String phone;
  final String? email;
  final DateTime? dateOfBirth;
  final String qrSecret;
  final String referralCode;
  final bool pushOptIn;
  final bool proximityOptIn;
  final bool leaderboardOptIn;
  final String? avatarUrl;

  const AppUser({
    required this.id,
    required this.name,
    required this.phone,
    required this.qrSecret,
    required this.referralCode,
    this.email,
    this.dateOfBirth,
    this.pushOptIn = false,
    this.proximityOptIn = false,
    this.leaderboardOptIn = true,
    this.avatarUrl,
  });

  factory AppUser.fromJson(Map<String, dynamic> j) => AppUser(
        id: j['id'] as String,
        name: j['name'] as String,
        phone: j['phone'] as String,
        email: j['email'] as String?,
        dateOfBirth: j['date_of_birth'] == null
            ? null
            : DateTime.parse(j['date_of_birth'] as String),
        qrSecret: j['qr_secret'] as String,
        referralCode: j['referral_code'] as String,
        pushOptIn: j['push_opt_in'] as bool? ?? false,
        proximityOptIn: j['proximity_opt_in'] as bool? ?? false,
        leaderboardOptIn: j['leaderboard_opt_in'] as bool? ?? true,
        avatarUrl: j['avatar_url'] as String?,
      );
}
