import 'package:flutter/material.dart';
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
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('تم إرسال الإعلان')));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('تعذّر الإرسال')));
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
            Text('أرسل إشعارًا لكل عملائك',
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 16),
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
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'إرسال',
              icon: Icons.send_rounded,
              loading: _busy,
              onPressed: _send,
            ),
          ],
        ),
      ),
    );
  }
}
