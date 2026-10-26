import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure_message.dart';
import '../../../core/router/app_router.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/counselor_query.dart';
import 'counselor_card.dart';
import 'counselor_list_controller.dart';
import 'filter_sheet.dart';

/// "Find a counselor": search by name, filters, and the results with endless scrolling and pull-to-refresh.
class CounselorsScreen extends ConsumerStatefulWidget {
  const CounselorsScreen({super.key});

  @override
  ConsumerState<CounselorsScreen> createState() => _CounselorsScreenState();
}

class _CounselorsScreenState extends ConsumerState<CounselorsScreen> {
  static const _debounce = Duration(milliseconds: 400);
  static const _nearEnd = 400.0;

  final _search = TextEditingController();
  final _scroll = ScrollController();
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.hasClients && _scroll.position.extentAfter < _nearEnd) {
        ref.read(counselorListControllerProvider.notifier).loadMore();
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  /// Waits until the person stops typing, and never searches for a single letter (the server needs two).
  void _onSearchChanged(String value) {
    _timer?.cancel();
    _timer = Timer(_debounce, () {
      final words = value.trim();
      final current = ref.read(counselorListControllerProvider).query;

      if (words.length == 1) {
        return;
      }

      _apply(current.copyWith(q: words.isEmpty ? null : words));
    });
  }

  void _clearSearch() {
    _timer?.cancel();
    _search.clear();
    _apply(ref.read(counselorListControllerProvider).query.copyWith(q: null));
  }

  void _apply(CounselorQuery query) {
    if (_scroll.hasClients) {
      _scroll.jumpTo(0);
    }

    ref.read(counselorListControllerProvider.notifier).setQuery(query);
  }

  Future<void> _openFilters() async {
    final current = ref.read(counselorListControllerProvider).query;
    final result = await showFilterSheet(context, current);

    if (result != null && mounted) {
      _apply(result);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final state = ref.watch(counselorListControllerProvider);
    final filters = state.query.activeFilterCount;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.findTitle)),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _search,
                      textInputAction: TextInputAction.search,
                      onChanged: (value) {
                        setState(() {}); // shows or hides the clear button
                        _onSearchChanged(value);
                      },
                      onSubmitted: (value) {
                        _timer?.cancel();
                        final words = value.trim();
                        if (words.length != 1) _apply(state.query.copyWith(q: words.isEmpty ? null : words));
                      },
                      decoration: InputDecoration(
                        hintText: l10n.searchHint,
                        prefixIcon: const Icon(Icons.search),
                        suffixIcon: _search.text.isEmpty ? null : IconButton(tooltip: l10n.clearSearch, icon: const Icon(Icons.close), onPressed: _clearSearch),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  filters == 0
                      ? OutlinedButton.icon(onPressed: _openFilters, icon: const Icon(Icons.tune), label: Text(l10n.filtersButton))
                      : FilledButton.icon(onPressed: _openFilters, icon: const Icon(Icons.tune), label: Text(l10n.filtersCount(filters))),
                ],
              ),
            ),
            if (state.loadedOnce && !state.loading)
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
                child: Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: Semantics(liveRegion: true, child: Text(l10n.resultsCount(state.total), style: text.bodySmall)),
                ),
              ),
            Expanded(child: _Results(state: state, scroll: _scroll, onClear: () {
              _timer?.cancel();
              _search.clear();
              _apply(const CounselorQuery());
            })),
          ],
        ),
      ),
    );
  }
}

class _Results extends ConsumerWidget {
  const _Results({required this.state, required this.scroll, required this.onClear});

  final CounselorListState state;
  final ScrollController scroll;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final controller = ref.read(counselorListControllerProvider.notifier);

    // First load (or a new search) with nothing to show yet.
    if (state.items.isEmpty && state.loading) {
      return const Center(child: CircularProgressIndicator());
    }

    // Could not load, and there is nothing to show.
    if (state.items.isEmpty && state.failure != null) {
      return _Message(
        icon: Icons.cloud_off_outlined,
        title: l10n.somethingWentWrongTitle,
        body: failureMessage(l10n, state.failure!),
        actionLabel: l10n.tryAgain,
        onAction: controller.refresh,
      );
    }

    // Asked, and nobody matched (or there is nobody at all).
    if (state.items.isEmpty && state.loadedOnce) {
      final filtering = state.query.isFiltering;

      return RefreshIndicator(
        onRefresh: controller.refresh,
        child: LayoutBuilder(
          builder: (context, box) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: [
              SizedBox(
                height: box.maxHeight,
                child: _Message(
                  icon: Icons.search_off,
                  title: filtering ? l10n.emptyFilteredTitle : l10n.emptyNoneTitle,
                  body: filtering ? l10n.emptyFilteredBody : l10n.emptyNoneBody,
                  actionLabel: filtering ? l10n.clearFilters : null,
                  onAction: filtering ? onClear : null,
                ),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView.builder(
        controller: scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        itemCount: state.items.length + 1,
        itemBuilder: (context, index) {
          if (index < state.items.length) {
            final counselor = state.items[index];

            return CounselorCard(counselor: counselor, onTap: () => context.push(AppRoutes.counselorPath(counselor.id)));
          }

          // After the last card: loading more, a failure to load more, or nothing.
          if (state.loadingMore) {
            return Padding(
              padding: const EdgeInsets.all(16),
              child: Center(child: Semantics(label: l10n.loadingMore, child: const CircularProgressIndicator())),
            );
          }

          if (state.failure != null) {
            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Column(
                children: [
                  Text(l10n.loadMoreFailed, textAlign: TextAlign.center),
                  TextButton(onPressed: controller.loadMore, child: Text(l10n.tryAgain)),
                ],
              ),
            );
          }

          return const SizedBox.shrink();
        },
      ),
    );
  }
}

class _Message extends StatelessWidget {
  const _Message({required this.icon, required this.title, required this.body, this.actionLabel, this.onAction});

  final IconData icon;
  final String title;
  final String body;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: Theme.of(context).colorScheme.onSurface.withValues(alpha: .4)),
            const SizedBox(height: 16),
            Text(title, textAlign: TextAlign.center, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            Text(body, textAlign: TextAlign.center, style: text.bodyMedium),
            if (actionLabel != null) ...[
              const SizedBox(height: 20),
              FilledButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}
