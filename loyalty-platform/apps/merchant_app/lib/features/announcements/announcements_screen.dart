import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/merchant_providers.dart';

/// 2.12 — الإعلانات.
class AnnouncementsScreen extends ConsumerStatefulWidget {
  const AnnouncementsScreen({super.key});

  @override
  ConsumerState<AnnouncementsScreen> createState() =>
      _AnnouncementsScreenState();
}

class _AnnouncementsScreenState extends ConsumerState<AnnouncementsScreen> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _body = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      // TODO: استدعاء edge function `send-push` لكل عملاء التاجر المفعّلين opt-in.
      // مثال:
      // await Supabase.instance.client.functions.invoke('send-push', body: {
      //   'merchant_id': staff.merchantId,
      //   'title': _title.text.trim(),
      //   'body': _body.text.trim(),
      // });
      debugPrint('TODO send-push for merchant ${staff.merchantId}');
      await Future<void>.delayed(const Duration(milliseconds: 400));
      if (mounted) {
        _title.clear();
        _body.clear();
        await AppFeedback.success(
          context,
          title: 'تم إرسال الإعلان',
          message: 'وصل إشعارك إلى كل عملائك المفعّلين.',
        );
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر الإرسال', error: true);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('الإعلانات')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            AppCard(
              gradient: AppColors.goldGradient,
              child: Row(
                children: [
                  Container(
                    height: 48,
                    width: 48,
                    decoration: BoxDecoration(
                      color: AppColors.surface.withValues(alpha: .35),
                      borderRadius: BorderRadius.circular(AppRadii.md),
                    ),
                    child: const Icon(Icons.campaign_rounded,
                        color: AppColors.onPrimary),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('أرسل إشعارًا لكل عملائك',
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(color: AppColors.onPrimary)),
                        const SizedBox(height: 2),
                        Text('يصل فورًا لكل من فعّل الإشعارات.',
                            style: TextStyle(
                                color: AppColors.onPrimary
                                    .withValues(alpha: .85),
                                fontSize: 12)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            const SectionHeader(title: 'محتوى الإعلان'),
            const SizedBox(height: 8),
            AppCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextFormField(
                    controller: _title,
                    decoration:
                        const InputDecoration(labelText: 'عنوان الإشعار'),
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _body,
                    decoration: const InputDecoration(labelText: 'النص'),
                    maxLines: 4,
                    validator: (v) =>
                        (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'إرسال',
              icon: Icons.send_rounded,
              loading: _busy,
              onPressed: _send,
            ),
          ],
        ).animate().fadeIn(duration: 300.ms).slideY(begin: .04, end: 0),
      ),
    );
  }
}
