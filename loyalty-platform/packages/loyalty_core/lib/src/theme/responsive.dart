import 'package:flutter/widgets.dart';

/// نقاط القطع (breakpoints) لدعم الهواتف الصغيرة/المتوسطة/الكبيرة والتابلت.
class Breakpoints {
  Breakpoints._();
  static const double smallPhone = 360; // أقل من ده = هاتف صغير
  static const double phone = 480;
  static const double tablet = 720; // أكبر من ده = تابلت
}

/// امتدادات استجابة على BuildContext — تمنع الـ overflow وتتكيّف مع المقاس.
extension ResponsiveContext on BuildContext {
  Size get screenSize => MediaQuery.sizeOf(this);
  double get screenW => screenSize.width;
  double get screenH => screenSize.height;

  bool get isSmallPhone => screenW < Breakpoints.smallPhone;
  bool get isTablet => screenW >= Breakpoints.tablet;

  /// يختار قيمة حسب المقاس (افتراضي = phone).
  T responsive<T>({required T mobile, T? smallPhone, T? tablet}) {
    if (isTablet && tablet != null) return tablet;
    if (isSmallPhone && smallPhone != null) return smallPhone;
    return mobile;
  }

  /// مقاس عنصر بطولٍ أقصى لكنه لا يتعدّى عرض الشاشة (يمنع القصّ/الـ overflow).
  /// مثال: حجم الـ QR = context.cappedSize(240) → يصغر تلقائيًا على الشاشات الضيقة.
  double cappedSize(double max, {double horizontalPadding = 48}) {
    final avail = screenW - horizontalPadding;
    return avail < max ? avail : max;
  }

  /// أقصى عرض للمحتوى على التابلت (يمنع تمدّد النصوص بشكل قبيح).
  double get contentMaxWidth => isTablet ? 640 : double.infinity;
}

/// يضع المحتوى في وسط الشاشة بعرض أقصى على التابلت (Responsive container).
class ResponsiveCenter extends StatelessWidget {
  final Widget child;
  final double maxWidth;
  const ResponsiveCenter({super.key, required this.child, this.maxWidth = 640});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: child,
      ),
    );
  }
}
