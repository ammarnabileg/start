import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart' hide TextDirection;
import 'package:loyalty_core/loyalty_core.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../../core/avatar_storage.dart';
import '../qr/qr_providers.dart';

/// تعديل الملف (Edit Profile) — راجع CUSTOMER_APP.md 1.17.
class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  final _nameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  DateTime? _dob;
  String? _avatarUrl;

  bool _initialized = false;
  bool _saving = false;
  bool _uploadingAvatar = false;

  @override
  void dispose() {
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    super.dispose();
  }

  void _seed(AppUser user) {
    if (_initialized) return;
    _nameCtrl.text = user.name;
    _emailCtrl.text = user.email ?? '';
    _dob = user.dateOfBirth;
    _avatarUrl = user.avatarUrl;
    _initialized = true;
  }

  Future<void> _pickAvatar() async {
    if (_uploadingAvatar) return;
    setState(() => _uploadingAvatar = true);
    try {
      final url = await AvatarStorage.pickAndUpload();
      if (!mounted) return;
      if (url != null) {
        setState(() => _avatarUrl = url);
        AppFeedback.toast(context, 'تم اختيار الصورة، احفظ التغييرات لتطبيقها.');
      }
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر رفع الصورة، حاول مرة أخرى.',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _uploadingAvatar = false);
    }
  }

  bool get _nameValid => _nameCtrl.text.trim().length >= 2;
  bool get _emailValid {
    final e = _emailCtrl.text.trim();
    if (e.isEmpty) return true;
    return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(e);
  }

  Future<void> _pickDob() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _dob ?? DateTime(now.year - 20),
      firstDate: DateTime(1920),
      lastDate: now,
      helpText: 'اختر تاريخ ميلادك',
    );
    if (picked != null) setState(() => _dob = picked);
  }

  Future<void> _save() async {
    if (!_nameValid || !_emailValid) return;
    setState(() => _saving = true);
    try {
      final client = Supabase.instance.client;
      final uid = client.auth.currentUser!.id;
      await client.from('users').update({
        'name': _nameCtrl.text.trim(),
        'email': _emailCtrl.text.trim().isEmpty ? null : _emailCtrl.text.trim(),
        'date_of_birth':
            _dob == null ? null : DateFormat('yyyy-MM-dd').format(_dob!),
        'avatar_url': _avatarUrl,
      }).eq('id', uid);
      ref.invalidate(currentUserProvider);
      if (!mounted) return;
      AppFeedback.toast(context, 'تم حفظ التغييرات');
      Navigator.of(context).pop();
    } catch (_) {
      if (mounted) {
        AppFeedback.toast(context, 'تعذّر حفظ التغييرات، حاول مرة أخرى.',
            error: true);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final userAsync = ref.watch(currentUserProvider);
    final theme = Theme.of(context);
    return Scaffold(
      appBar:
          AppBar(title: const Text('تعديل الملف'), centerTitle: true),
      body: userAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
            message: 'تعذّر تحميل الملف',
            onRetry: () => ref.invalidate(currentUserProvider)),
        data: (user) {
          _seed(user);
          return ListView(
            padding: const EdgeInsets.all(AppSpacing.xxl),
            children: [
              Center(
                child: Stack(
                  children: [
                    GestureDetector(
                      onTap: _uploadingAvatar ? null : _pickAvatar,
                      child: Container(
                        height: 96,
                        width: 96,
                        alignment: Alignment.center,
                        clipBehavior: Clip.antiAlias,
                        decoration: const BoxDecoration(
                          gradient: AppColors.goldGradient,
                          shape: BoxShape.circle,
                          boxShadow: [
                            BoxShadow(
                                color: AppColors.shadow,
                                blurRadius: 18,
                                offset: Offset(0, 8)),
                          ],
                        ),
                        child: _uploadingAvatar
                            ? const SizedBox(
                                height: 28,
                                width: 28,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2.4,
                                    color: AppColors.onPrimary),
                              )
                            : (_avatarUrl != null && _avatarUrl!.isNotEmpty)
                                ? CachedNetworkImage(
                                    imageUrl: _avatarUrl!,
                                    fit: BoxFit.cover,
                                    height: 96,
                                    width: 96,
                                    errorWidget: (_, __, ___) => Text(
                                      user.name.characters.first,
                                      style: const TextStyle(
                                          fontSize: 36,
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.onPrimary),
                                    ),
                                  )
                                : Text(
                                    user.name.characters.first,
                                    style: const TextStyle(
                                        fontSize: 36,
                                        fontWeight: FontWeight.w800,
                                        color: AppColors.onPrimary),
                                  ),
                      ),
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: CircleAvatar(
                        radius: 16,
                        backgroundColor: AppColors.primary,
                        child: IconButton(
                          padding: EdgeInsets.zero,
                          iconSize: 18,
                          icon: const Icon(Icons.camera_alt_outlined,
                              color: AppColors.onPrimary),
                          onPressed: _uploadingAvatar ? null : _pickAvatar,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 28),
              Text('الاسم الكامل', style: theme.textTheme.titleMedium),
              const SizedBox(height: 8),
              TextField(
                controller: _nameCtrl,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(hintText: 'اكتب اسمك الكامل'),
              ),
              const SizedBox(height: 18),
              Text('البريد الإلكتروني (اختياري)',
                  style: theme.textTheme.titleMedium),
              const SizedBox(height: 8),
              TextField(
                controller: _emailCtrl,
                keyboardType: TextInputType.emailAddress,
                textDirection: TextDirection.ltr,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  hintText: 'name@example.com',
                  errorText: _emailValid ? null : 'صيغة البريد غير صحيحة',
                ),
              ),
              const SizedBox(height: 18),
              Text('تاريخ الميلاد (اختياري)',
                  style: theme.textTheme.titleMedium),
              const SizedBox(height: 8),
              InkWell(
                onTap: _pickDob,
                borderRadius: BorderRadius.circular(16),
                child: InputDecorator(
                  decoration: const InputDecoration(),
                  child: Row(
                    children: [
                      const Icon(Icons.cake_outlined,
                          size: 20, color: AppColors.textSecondary),
                      const SizedBox(width: 10),
                      Text(
                        _dob == null
                            ? 'اختر تاريخ ميلادك'
                            : DateFormat('yyyy/MM/dd').format(_dob!),
                        style: theme.textTheme.bodyLarge?.copyWith(
                          color: _dob == null
                              ? AppColors.textSecondary
                              : AppColors.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.xxxl),
              PrimaryButton(
                label: 'حفظ',
                icon: Icons.check_rounded,
                loading: _saving,
                onPressed: (_nameValid && _emailValid) ? _save : null,
              ),
            ],
          );
        },
      ),
    );
  }
}
