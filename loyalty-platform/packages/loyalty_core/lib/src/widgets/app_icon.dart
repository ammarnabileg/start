import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import 'app_icon_registry.dart';

/// أيقونة موحّدة على هيئة SVG (نمط Hatchy: خط ثابت 2px، 24-grid).
///
/// تأخذ نفس [IconData] المستخدَم مع [Icon] الأصلي، فتكون بديلًا مباشرًا:
/// لو الأيقونة موجودة في سجلّ الـ SVG تُرسَم منه، وإلا ترجع للأيقونة المدمجة
/// (Material) تلقائيًا — فلا تنكسر أي شاشة لو أيقونة مش متضمّنة بعد.
class AppIcon extends StatelessWidget {
  /// nullable مثل [Icon] تمامًا — لو null نرسم مساحة فارغة بنفس الحجم.
  final IconData? icon;
  final double? size;
  final Color? color;
  final String? semanticLabel;

  const AppIcon(this.icon, {super.key, this.size, this.color, this.semanticLabel});

  @override
  Widget build(BuildContext context) {
    final resolvedSizeForNull = size ?? IconTheme.of(context).size ?? 24.0;
    if (icon == null) {
      return SizedBox(width: resolvedSizeForNull, height: resolvedSizeForNull);
    }
    final asset = kAppIconAssets[icon];
    final resolvedColor =
        color ?? IconTheme.of(context).color ?? const Color(0xFF1A1A1A);
    final resolvedSize = size ?? IconTheme.of(context).size ?? 24.0;

    if (asset == null) {
      // غير متضمّنة في سجلّ SVG → نرجع للأيقونة المدمجة (تبقى vector وسليمة).
      return Icon(icon,
          size: resolvedSize, color: resolvedColor, semanticLabel: semanticLabel);
    }

    return SvgPicture.asset(
      '$kIconAssetDir/$asset.svg',
      package: 'loyalty_core',
      width: resolvedSize,
      height: resolvedSize,
      colorFilter: ColorFilter.mode(resolvedColor, BlendMode.srcIn),
      semanticsLabel: semanticLabel,
    );
  }
}
