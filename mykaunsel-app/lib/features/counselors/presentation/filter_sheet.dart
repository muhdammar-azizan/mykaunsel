import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/error/failure_message.dart';
import '../../../l10n/app_localizations.dart';
import '../domain/counselor.dart';
import '../domain/counselor_query.dart';
import 'counselor_labels.dart';
import 'counselor_list_controller.dart';

/// The filters, as a sheet from the bottom. The choices come from what this person can really find, so a filter never
/// leads to an empty list. Returns the new query (the search words are kept), or null when closed without applying.
Future<CounselorQuery?> showFilterSheet(BuildContext context, CounselorQuery current) {
  return showModalBottomSheet<CounselorQuery>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => FilterSheet(current: current),
  );
}

class FilterSheet extends ConsumerStatefulWidget {
  const FilterSheet({super.key, required this.current});

  final CounselorQuery current;

  @override
  ConsumerState<FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends ConsumerState<FilterSheet> {
  late CounselorQuery _draft = widget.current;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final options = ref.watch(counselorFilterOptionsProvider);
    final text = Theme.of(context).textTheme;

    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * .85),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Everything except the "Show results" button scrolls, so large text or a small screen never cuts anything off.
              Flexible(
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Semantics(header: true, child: Text(l10n.filtersTitle, style: text.titleLarge?.copyWith(fontWeight: FontWeight.w800)))),
                          TextButton(
                            onPressed: _draft.activeFilterCount == 0 ? null : () => setState(() => _draft = CounselorQuery(q: _draft.q)),
                            child: Text(l10n.filtersClear),
                          ),
                        ],
                      ),
                      options.when(
                        loading: () => const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator())),
                        error: (error, _) => Padding(
                          padding: const EdgeInsets.symmetric(vertical: 24),
                          child: Column(
                            children: [
                              Text(error is AppFailure ? failureMessage(l10n, error) : l10n.somethingWentWrongBody, textAlign: TextAlign.center),
                              const SizedBox(height: 12),
                              OutlinedButton(onPressed: () => ref.invalidate(counselorFilterOptionsProvider), child: Text(l10n.tryAgain)),
                            ],
                          ),
                        ),
                        data: (data) => Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (data.sessionModes.where((m) => m.label(l10n) != null).isNotEmpty)
                              _Group<SessionMode>(
                                title: l10n.filterSessionType,
                                values: data.sessionModes.where((m) => m.label(l10n) != null).toList(),
                                selected: _draft.mode,
                                label: (m) => m.label(l10n)!,
                                onChanged: (m) => setState(() => _draft = _draft.copyWith(mode: m)),
                              ),
                            if (data.specializations.isNotEmpty)
                              _Group<String>(
                                title: l10n.filterFocusArea,
                                values: data.specializations,
                                selected: _draft.specialization,
                                label: (s) => s,
                                onChanged: (s) => setState(() => _draft = _draft.copyWith(specialization: s)),
                              ),
                            if (data.languages.isNotEmpty)
                              _Group<String>(
                                title: l10n.filterLanguage,
                                values: data.languages,
                                selected: _draft.language,
                                label: (s) => s,
                                onChanged: (s) => setState(() => _draft = _draft.copyWith(language: s)),
                              ),
                            if (data.states.isNotEmpty)
                              _Group<String>(
                                title: l10n.filterState,
                                values: data.states,
                                selected: _draft.state,
                                label: (s) => s,
                                onChanged: (s) => setState(() => _draft = _draft.copyWith(state: s)),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 12),
              FilledButton(onPressed: () => Navigator.pop(context, _draft), child: Text(l10n.filtersApply)),
            ],
          ),
        ),
      ),
    );
  }
}

/// One question with its answers as chips. Tapping the chosen one again clears it.
class _Group<T> extends StatelessWidget {
  const _Group({required this.title, required this.values, required this.selected, required this.label, required this.onChanged});

  final String title;
  final List<T> values;
  final T? selected;
  final String Function(T) label;
  final ValueChanged<T?> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12, bottom: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final value in values)
                ChoiceChip(
                  label: Text(label(value)),
                  selected: selected == value,
                  onSelected: (on) => onChanged(on ? value : null),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
