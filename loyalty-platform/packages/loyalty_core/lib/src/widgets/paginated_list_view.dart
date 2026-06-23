import 'package:flutter/material.dart';
import 'state_views.dart';

/// حالة قائمة مرقّمة (Pagination) — تُدار من Notifier في طبقة الـ data.
class PaginatedState<T> {
  final List<T> items;
  final bool isLoading; // تحميل صفحة (أولى أو إضافية)
  final bool hasMore;
  final Object? error;

  const PaginatedState({
    this.items = const [],
    this.isLoading = false,
    this.hasMore = true,
    this.error,
  });

  bool get isInitial => items.isEmpty && isLoading;
  bool get isEmptyResult => items.isEmpty && !isLoading && error == null;

  PaginatedState<T> copyWith({
    List<T>? items,
    bool? isLoading,
    bool? hasMore,
    Object? error,
    bool clearError = false,
  }) =>
      PaginatedState<T>(
        items: items ?? this.items,
        isLoading: isLoading ?? this.isLoading,
        hasMore: hasMore ?? this.hasMore,
        error: clearError ? null : (error ?? this.error),
      );
}

/// قائمة مرقّمة قابلة لإعادة الاستخدام: تحميل تلقائي عند الاقتراب من النهاية،
/// سحب-للتحديث، حالات تحميل/فاضي/خطأ موحّدة، وتذييل تحميل.
class PaginatedListView<T> extends StatefulWidget {
  final PaginatedState<T> state;
  final Future<void> Function() onLoadMore;
  final Future<void> Function() onRefresh;
  final Widget Function(BuildContext, T, int) itemBuilder;
  final Widget Function(BuildContext, int)? separatorBuilder;
  final EdgeInsetsGeometry padding;
  final Widget? header;
  final Widget? emptyView;
  final String emptyTitle;
  final String? emptyMessage;
  final IconData emptyIcon;

  const PaginatedListView({
    super.key,
    required this.state,
    required this.onLoadMore,
    required this.onRefresh,
    required this.itemBuilder,
    this.separatorBuilder,
    this.padding = const EdgeInsets.all(16),
    this.header,
    this.emptyView,
    this.emptyTitle = 'لا توجد عناصر',
    this.emptyMessage,
    this.emptyIcon = Icons.inbox_outlined,
  });

  @override
  State<PaginatedListView<T>> createState() => _PaginatedListViewState<T>();
}

class _PaginatedListViewState<T> extends State<PaginatedListView<T>> {
  final _controller = ScrollController();

  @override
  void initState() {
    super.initState();
    _controller.addListener(_onScroll);
  }

  @override
  void dispose() {
    _controller.removeListener(_onScroll);
    _controller.dispose();
    super.dispose();
  }

  void _onScroll() {
    final s = widget.state;
    if (!s.isLoading &&
        s.hasMore &&
        _controller.position.pixels >=
            _controller.position.maxScrollExtent - 320) {
      widget.onLoadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.state;

    // الصفحة الأولى تُحمَّل → skeleton.
    if (s.isInitial) return const SkeletonList();

    // خطأ والقائمة فاضية → عرض خطأ مع إعادة محاولة.
    if (s.error != null && s.items.isEmpty) {
      return ErrorView(
          message: 'تعذّر تحميل البيانات', onRetry: widget.onRefresh);
    }

    // فاضية فعلًا.
    if (s.isEmptyResult) {
      return widget.emptyView ??
          EmptyView(
            icon: widget.emptyIcon,
            title: widget.emptyTitle,
            message: widget.emptyMessage,
          );
    }

    final headerCount = widget.header != null ? 1 : 0;
    final footerCount = (s.hasMore || (s.isLoading && s.items.isNotEmpty)) ? 1 : 0;

    return RefreshIndicator(
      onRefresh: widget.onRefresh,
      child: ListView.separated(
        controller: _controller,
        padding: widget.padding,
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: headerCount + s.items.length + footerCount,
        separatorBuilder: (ctx, i) {
          if (i < headerCount) return const SizedBox.shrink();
          return widget.separatorBuilder?.call(ctx, i - headerCount) ??
              const SizedBox(height: 10);
        },
        itemBuilder: (ctx, i) {
          if (i < headerCount) return widget.header!;
          final idx = i - headerCount;
          if (idx >= s.items.length) {
            // تذييل التحميل
            return const Padding(
              padding: EdgeInsets.symmetric(vertical: 20),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          return widget.itemBuilder(ctx, s.items[idx], idx);
        },
      ),
    );
  }
}
