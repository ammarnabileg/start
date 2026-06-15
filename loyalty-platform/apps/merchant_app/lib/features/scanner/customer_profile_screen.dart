import 'package:flutter/material.dart';
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// ملف العميل بعد المسح — قلب شغل الكاشير. الأزرار الأربعة تستدعي Edge Functions.
class CustomerProfileScreen extends StatefulWidget {
  final Map<String, dynamic> data; // ناتج verify-qr
  const CustomerProfileScreen({super.key, required this.data});
  @override
  State<CustomerProfileScreen> createState() => _CustomerProfileScreenState();
}

class _CustomerProfileScreenState extends State<CustomerProfileScreen> {
  late Map<String, dynamic> d = widget.data;
  bool _busy = false;

  String get _userId => (d['user']['id']) as String;

  Future<void> _call(String fn, Map<String, dynamic> body,
      {required String okMsg}) async {
    setState(() => _busy = true);
    try {
      final res = await Supabase.instance.client.functions
          .invoke(fn, body: body);
      if (res.data?['error'] != null) {
        _snack(res.data['error'] as String);
      } else {
        _snack(okMsg);
        if (res.data?['available_points'] != null) {
          setState(() => d['available_points'] = res.data['available_points']);
        }
      }
    } catch (_) {
      _snack('تعذّر تنفيذ العملية، تحقق من الاتصال');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _snack(String m) => ScaffoldMessenger.of(context)
      .showSnackBar(SnackBar(content: Text(m)));

  Future<void> _addPoints() async {
    final pts = await showModalBottomSheet<int>(
      context: context,
      builder: (_) => const _QuickPointsSheet(),
    );
    if (pts != null) {
      await _call('add-points', {'user_id': _userId, 'points': pts},
          okMsg: 'تمت إضافة $pts نقطة');
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = d['user'] as Map<String, dynamic>;
    final isNew = d['is_new_customer'] == true;
    final visited = d['visited_today'] == true;

    return Scaffold(
      appBar: AppBar(title: const Text('ملف العميل')),
      body: Stack(
        children: [
          ListView(
            padding: const EdgeInsets.all(16),
            children: [
              AppCard(
                child: Column(
                  children: [
                    Row(
                      children: [
                        CircleAvatar(
                            radius: 26,
                            backgroundColor: AppColors.primaryLight,
                            child: Text(
                                (user['name'] as String).characters.first,
                                style: const TextStyle(
                                    fontSize: 20, fontWeight: FontWeight.w800))),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(user['name'] as String,
                                  style:
                                      Theme.of(context).textTheme.titleLarge),
                              if (d['level_name'] != null)
                                Text(d['level_name'] as String,
                                    style:
                                        Theme.of(context).textTheme.bodySmall),
                            ],
                          ),
                        ),
                        if (isNew)
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 10, vertical: 6),
                            decoration: BoxDecoration(
                                color: AppColors.success.withValues(alpha: .15),
                                borderRadius: BorderRadius.circular(16)),
                            child: const Text('عميل جديد 🎉',
                                style: TextStyle(
                                    color: AppColors.success,
                                    fontWeight: FontWeight.w700)),
                          ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _Stat('النقاط المتاحة', '${d['available_points']}'),
                        _Stat('زيارة اليوم', visited ? 'تم' : 'لم تُسجّل'),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 1.6,
                children: [
                  _ActionTile(
                      icon: Icons.event_available_rounded,
                      label: 'تسجيل زيارة',
                      onTap: () => _call('record-visit', {'user_id': _userId},
                          okMsg: 'تم تسجيل الزيارة')),
                  _ActionTile(
                      icon: Icons.add_circle_outline_rounded,
                      label: 'إضافة نقاط',
                      onTap: _addPoints),
                  _ActionTile(
                      icon: Icons.redeem_rounded,
                      label: 'استبدال مكافأة',
                      onTap: () {/* TODO: اختيار مكافأة → confirm-redemption */}),
                  _ActionTile(
                      icon: Icons.confirmation_num_outlined,
                      label: 'تطبيق كوبون',
                      onTap: () {/* TODO: apply-coupon */}),
                ],
              ),
            ],
          ),
          if (_busy) const ColoredBox(color: Colors.black26, child: LoadingView()),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final String label, value;
  const _Stat(this.label, this.value);
  @override
  Widget build(BuildContext context) => Column(
        children: [
          Text(value, style: Theme.of(context).textTheme.headlineMedium),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      );
}

class _ActionTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _ActionTile(
      {required this.icon, required this.label, required this.onTap});
  @override
  Widget build(BuildContext context) => AppCard(
        onTap: onTap,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 30, color: AppColors.primaryDark),
            const SizedBox(height: 8),
            Text(label, style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
      );
}

class _QuickPointsSheet extends StatelessWidget {
  const _QuickPointsSheet();
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('إضافة نقاط', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          Wrap(
            spacing: 12,
            children: [10, 20, 50, 100]
                .map((p) => ActionChip(
                      label: Text('+$p'),
                      onPressed: () => Navigator.pop(context, p),
                    ))
                .toList(),
          ),
        ],
      ),
    );
  }
}
