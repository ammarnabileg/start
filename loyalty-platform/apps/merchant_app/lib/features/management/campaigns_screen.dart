import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../../core/media_storage.dart';
import '../../core/merchant_providers.dart';
import '../../core/perm_gate.dart';
import '../../data/repositories/campaigns_repository.dart';
import '../../data/repositories/entity_branches_repository.dart';
import 'branch_target_field.dart';

/// قائمة حملات الزيارة من جدول visit_campaigns.
final campaignsProvider =
    FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final staff = await ref.watch(currentStaffProvider.future);
  return ref.read(campaignsRepoProvider).fetchCampaigns(staff.merchantId);
});

/// 2.10.أ — حملات الزيارة.
class CampaignsScreen extends ConsumerWidget {
  const CampaignsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(campaignsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('بطاقات الأختام')),
      floatingActionButton: PermFab(
        resource: PermResource.campaigns,
        label: 'بطاقة جديدة',
        onPressed: () => _openEditor(context, ref, null),
      ),
      body: async.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ErrorView(
          message: 'تعذّر تحميل الحملات',
          onRetry: () => ref.invalidate(campaignsProvider),
        ),
        data: (rows) {
          if (rows.isEmpty) {
            return EmptyView(
              icon: Icons.repeat_rounded,
              title: 'لا توجد بطاقات بعد',
              message: 'أنشئ أول بطاقة أختام (شراء/تبرّع) لمكافأة عملائك على التكرار.',
              actionLabel: 'إنشاء حملة',
              onAction: ref.permCan(PermResource.campaigns, PermAction.create)
                  ? () => _openEditor(context, ref, null)
                  : null,
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: rows.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final c = rows[i];
              return AppCard(
                onTap: () => _openEditor(context, ref, c),
                child: Row(
                  children: [
                    Container(
                      height: 48,
                      width: 48,
                      decoration: BoxDecoration(
                        color: AppColors.info.withValues(alpha: .15),
                        borderRadius: BorderRadius.circular(AppRadii.md),
                      ),
                      child: const AppIcon(Icons.repeat_rounded,
                          color: AppColors.info),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(c['name'] as String? ?? '—',
                              style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 4),
                          Text(
                            '${c['required_visits'] ?? 0} ختم → ${c['reward_name'] ?? ''}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ),
                    _ActiveChip(active: c['active'] == true),
                  ],
                ),
              )
                  .animate()
                  .fadeIn(duration: 300.ms, delay: (40 * i).ms)
                  .slideY(begin: .06, end: 0);
            },
          );
        },
      ),
    );
  }

  Future<void> _openEditor(
      BuildContext context, WidgetRef ref, Map<String, dynamic>? existing) async {
    final readOnly =
        existing != null && !ref.permCan(PermResource.campaigns, PermAction.edit);
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CampaignEditor(existing: existing, readOnly: readOnly),
    );
    if (saved == true) ref.invalidate(campaignsProvider);
  }
}

class _ActiveChip extends StatelessWidget {
  final bool active;
  const _ActiveChip({required this.active});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: (active ? AppColors.success : AppColors.textSecondary)
              .withValues(alpha: .15),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Text(active ? 'مفعّلة' : 'متوقفة',
            style: TextStyle(
                color: active ? AppColors.success : AppColors.textSecondary,
                fontWeight: FontWeight.w700,
                fontSize: 12)),
      );
}

/// حقل اختيار صورة مع معاينة، يُستخدم في محرّر الحملة.
class _ImagePickerField extends StatelessWidget {
  final String? imageUrl;
  final bool uploading;
  final VoidCallback onPick;
  const _ImagePickerField({
    required this.imageUrl,
    required this.uploading,
    required this.onPick,
  });

  @override
  Widget build(BuildContext context) {
    final hasImage = imageUrl != null && imageUrl!.isNotEmpty;
    return Row(
      children: [
        Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            color: AppColors.surfaceCream,
            borderRadius: BorderRadius.circular(AppRadii.md),
            image: hasImage
                ? DecorationImage(
                    image: NetworkImage(imageUrl!), fit: BoxFit.cover)
                : null,
          ),
          child: hasImage
              ? null
              : const AppIcon(Icons.image_outlined,
                  color: AppColors.textSecondary),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: OutlinedButton.icon(
            onPressed: uploading ? null : onPick,
            icon: uploading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const AppIcon(Icons.photo_library_outlined),
            label: const Text('اختيار صورة'),
          ),
        ),
      ],
    );
  }
}

class _CampaignEditor extends ConsumerStatefulWidget {
  final Map<String, dynamic>? existing;
  final bool readOnly;
  const _CampaignEditor({this.existing, this.readOnly = false});
  @override
  ConsumerState<_CampaignEditor> createState() => _CampaignEditorState();
}

class _CampaignEditorState extends ConsumerState<_CampaignEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _description;
  late final TextEditingController _visits;
  late final TextEditingController _actionLabel;
  late final TextEditingController _pointsPerStamp;
  late final TextEditingController _rewardPoints;
  late final TextEditingController _rewardName;
  late final TextEditingController _rewardDesc;
  String _actionType = 'purchase';
  late bool _active;
  String? _imageUrl; // صورة المكافأة (آخر ختم)
  String? _stampImageUrl; // صورة الختم
  String? _bannerImageUrl; // بنر الهيدر (اختياري)
  String? _uploadingWhich; // أي صورة تُرفع الآن
  bool _busy = false;
  final BranchTargetController _branchTarget = BranchTargetController();
  bool _targetLoaded = false;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    if (e == null) {
      _targetLoaded = true;
    } else {
      _loadTarget(e['id'] as String);
    }
    _name = TextEditingController(text: e?['name'] as String? ?? '');
    _description =
        TextEditingController(text: e?['description'] as String? ?? '');
    _visits =
        TextEditingController(text: (e?['required_visits'] ?? '').toString());
    _actionLabel =
        TextEditingController(text: e?['action_label'] as String? ?? '');
    _pointsPerStamp = TextEditingController(
        text: (e?['points_per_stamp'] ?? 0).toString());
    _rewardPoints =
        TextEditingController(text: (e?['reward_points'] ?? 0).toString());
    _rewardName =
        TextEditingController(text: e?['reward_name'] as String? ?? '');
    _rewardDesc =
        TextEditingController(text: e?['reward_description'] as String? ?? '');
    _actionType = e?['action_type'] as String? ?? 'purchase';
    _active = e?['active'] as bool? ?? true;
    _imageUrl = e?['reward_image_url'] as String?;
    _stampImageUrl = e?['stamp_image_url'] as String?;
    _bannerImageUrl = e?['banner_image_url'] as String?;
  }

  Future<void> _pickInto(String which, ValueChanged<String?> set) async {
    setState(() => _uploadingWhich = which);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      final url = await MediaStorage.pickAndUpload(
        bucket: 'merchant-media',
        folder: staff.merchantId,
      );
      if (!mounted) return;
      if (url != null) {
        setState(() => set(url));
        AppFeedback.toast(context, 'تم رفع الصورة');
      } else {
        AppFeedback.toast(context, 'تعذّر رفع الصورة', error: true);
      }
    } catch (_) {
      if (mounted) AppFeedback.toast(context, 'تعذّر رفع الصورة', error: true);
    } finally {
      if (mounted) setState(() => _uploadingWhich = null);
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    _visits.dispose();
    _actionLabel.dispose();
    _pointsPerStamp.dispose();
    _rewardPoints.dispose();
    _rewardName.dispose();
    _rewardDesc.dispose();
    super.dispose();
  }

  Future<void> _loadTarget(String id) async {
    final ids = await ref
        .read(entityBranchesRepoProvider)
        .branchIdsFor('campaign', id);
    if (mounted) {
      setState(() {
        _branchTarget.selected.addAll(ids);
        _targetLoaded = true;
      });
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      final staff = await ref.read(currentStaffProvider.future);
      final payload = {
        'merchant_id': staff.merchantId,
        'name': _name.text.trim(),
        'description': _description.text.trim(),
        'action_type': _actionType,
        'action_label': _actionLabel.text.trim(),
        'required_visits': int.tryParse(_visits.text.trim()) ?? 1,
        'points_per_stamp': int.tryParse(_pointsPerStamp.text.trim()) ?? 0,
        'reward_points': int.tryParse(_rewardPoints.text.trim()) ?? 0,
        'reward_name': _rewardName.text.trim(),
        'reward_description': _rewardDesc.text.trim(),
        'reward_image_url': _imageUrl,
        'stamp_image_url': _stampImageUrl,
        'banner_image_url': _bannerImageUrl,
        'active': _active,
      };
      final repo = ref.read(campaignsRepoProvider);
      final String campaignId;
      if (widget.existing == null) {
        campaignId = await repo.insertCampaign(payload);
      } else {
        campaignId = widget.existing!['id'] as String;
        await repo.updateCampaign(campaignId, payload);
      }
      await ref.read(entityBranchesRepoProvider).setBranches(
          'campaign', campaignId, staff.merchantId, _branchTarget.branchIds);
      if (mounted) {
        Navigator.pop(context, true);
        AppFeedback.toast(context, 'تم حفظ الحملة');
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر الحفظ', error: true);
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(widget.existing == null ? 'حملة جديدة' : 'تعديل الحملة',
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),
            if (widget.readOnly) const ReadOnlyNotice(),
            // بنر الهيدر (اختياري)
            const _ImgLabel('بنر الهيدر (اختياري)'),
            _ImagePickerField(
              imageUrl: _bannerImageUrl,
              uploading: _uploadingWhich == 'banner',
              onPick: () =>
                  _pickInto('banner', (u) => _bannerImageUrl = u),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم البطاقة'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _description,
              decoration: const InputDecoration(labelText: 'الوصف'),
              maxLines: 2,
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _actionType,
              decoration: const InputDecoration(labelText: 'نوع التكرار'),
              items: const [
                DropdownMenuItem(value: 'purchase', child: Text('شراء')),
                DropdownMenuItem(value: 'donation', child: Text('تبرّع')),
                DropdownMenuItem(value: 'visit', child: Text('زيارة')),
                DropdownMenuItem(value: 'custom', child: Text('مخصّص')),
              ],
              onChanged: (v) => setState(() => _actionType = v ?? 'purchase'),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _actionLabel,
              decoration: const InputDecoration(
                  labelText: 'وصف التكرار (مثال: شراء قهوة)'),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _visits,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'عدد التكرارات'),
              validator: (v) {
                final n = int.tryParse(v?.trim() ?? '');
                if (n == null || n <= 0) return 'أدخل رقمًا صحيحًا';
                return null;
              },
            ),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(
                child: TextFormField(
                  controller: _pointsPerStamp,
                  keyboardType: TextInputType.number,
                  decoration:
                      const InputDecoration(labelText: 'نقاط لكل ختم'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextFormField(
                  controller: _rewardPoints,
                  keyboardType: TextInputType.number,
                  decoration:
                      const InputDecoration(labelText: 'نقاط المكافأة'),
                ),
              ),
            ]),
            const SizedBox(height: 12),
            const _ImgLabel('صورة الختم (افتراضي: شعار التطبيق)'),
            _ImagePickerField(
              imageUrl: _stampImageUrl,
              uploading: _uploadingWhich == 'stamp',
              onPick: () => _pickInto('stamp', (u) => _stampImageUrl = u),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _rewardName,
              decoration: const InputDecoration(labelText: 'اسم المكافأة'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _rewardDesc,
              decoration: const InputDecoration(labelText: 'وصف المكافأة'),
              maxLines: 2,
            ),
            const SizedBox(height: 12),
            const _ImgLabel('صورة المكافأة (الخانة الأخيرة)'),
            _ImagePickerField(
              imageUrl: _imageUrl,
              uploading: _uploadingWhich == 'reward',
              onPick: () => _pickInto('reward', (u) => _imageUrl = u),
            ),
            const SizedBox(height: 8),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('مفعّلة'),
              value: _active,
              onChanged: (v) => setState(() => _active = v),
            ),
            const Divider(height: 24),
            if (_targetLoaded)
              BranchTargetField(controller: _branchTarget)
            else
              const Padding(
                  padding: EdgeInsets.all(8), child: LinearProgressIndicator()),
            const SizedBox(height: 16),
            if (!widget.readOnly)
              PrimaryButton(label: 'حفظ', loading: _busy, onPressed: _save),
            if (widget.existing != null)
              DeleteActionButton(
                resource: PermResource.campaigns,
                onDelete: () => ref
                    .read(campaignsRepoProvider)
                    .deleteCampaign(widget.existing!['id'] as String),
              ),
          ],
        ),
        ),
      ),
    );
  }
}

/// عنوان صغير فوق منتقي صورة.
class _ImgLabel extends StatelessWidget {
  final String text;
  const _ImgLabel(this.text);
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Text(text,
            style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: AppColors.textSecondary)),
      );
}
