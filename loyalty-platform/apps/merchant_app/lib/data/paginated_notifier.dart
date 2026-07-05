import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:loyalty_core/loyalty_core.dart';

/// Notifier عام لإدارة قائمة مرقّمة (Pagination).
/// يستدعي [_fetch] بإزاحة وحدّ، ويضيف الصفحات تباعًا.
class PaginatedNotifier<T> extends StateNotifier<PaginatedState<T>> {
  PaginatedNotifier(this._fetch) : super(const PaginatedState()) {
    loadMore();
  }

  final Future<List<T>> Function(int offset, int limit) _fetch;
  static const _pageSize = 20;

  Future<void> loadMore() async {
    if (state.isLoading || !state.hasMore) return;
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final batch = await _fetch(state.items.length, _pageSize);
      state = state.copyWith(
        items: [...state.items, ...batch],
        isLoading: false,
        hasMore: batch.length == _pageSize,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e);
    }
  }

  Future<void> refresh() async {
    state = const PaginatedState();
    await loadMore();
  }
}
