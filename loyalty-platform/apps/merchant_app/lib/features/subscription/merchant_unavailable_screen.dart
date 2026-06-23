import 'package:flutter/material.dart';
import 'package:loyalty_core/loyalty_core.dart';

import '../subscription/manage_subscription_screen.dart';

/// شاشة "المتجر غير متاح" — تظهر عند تعليق المتجر أو انتهاء الاشتراك/التجربة.
/// ودّية وكاملة الشاشة مع زر لإدارة الاشتراك وملاحظة للتواصل مع الدعم.
class MerchantUnavailableScreen extends StatelessWidget {
  final String? reason;
  const MerchantUnavailableScreen({super.key, this.reason});

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        body: Column(
          children: [
            const HeroHeader(title: 'متجرك غير متاح حاليًا'),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(AppSpacing.lg),
                children: [
                  const SizedBox(height: AppSpacing.lg),
                  Center(
                    child: Container(
                      width: 96,
                      height: 96,
                      decoration: const BoxDecoration(
                        color: AppColors.surfaceCream,
                        shape: BoxShape.circle,
                      ),
                      child: const AppIcon(
                        Icons.storefront_outlined,
                        size: 48,
                        color: AppColors.primaryDark,
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'متجرك غير متاح حاليًا',
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        const SizedBox(height: AppSpacing.sm),
                        Text(
                          reason ??
                              'قد يكون حساب متجرك معلّقًا، أو انتهى اشتراكك أو فترتك التجريبية. '
                                  'يمكنك تجديد الاشتراك أو ترقيته من إدارة الاشتراك لاستعادة الوصول الكامل.',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  PrimaryButton(
                    label: 'إدارة الاشتراك',
                    icon: Icons.workspace_premium_outlined,
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => const ManageSubscriptionScreen(),
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  AppCard(
                    color: AppColors.infoBg,
                    child: Row(
                      children: [
                        const AppIcon(Icons.support_agent_outlined,
                            color: AppColors.info),
                        const SizedBox(width: AppSpacing.md),
                        Expanded(
                          child: Text(
                            'تحتاج مساعدة؟ تواصل مع الدعم على support@wataddigital.com وسنعيد تفعيل متجرك بأسرع وقت.',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
