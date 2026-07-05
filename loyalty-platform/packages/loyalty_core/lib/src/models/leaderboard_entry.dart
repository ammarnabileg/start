/// صف في لوحة الصدارة (عام أو ستور). يرجع من RPC global_leaderboard/store_leaderboard.
class LeaderboardEntry {
  final int rank;
  final String userId;
  final String displayName;
  final int totalPoints;

  const LeaderboardEntry({
    required this.rank,
    required this.userId,
    required this.displayName,
    required this.totalPoints,
  });

  factory LeaderboardEntry.fromJson(Map<String, dynamic> j) => LeaderboardEntry(
        rank: (j['rank'] as num).toInt(),
        userId: j['user_id'] as String,
        displayName: j['display_name'] as String? ?? 'مستخدم',
        totalPoints: (j['total_points'] as num).toInt(),
      );
}
