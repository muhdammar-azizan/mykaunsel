import 'package:freezed_annotation/freezed_annotation.dart';

import 'counselor.dart';

part 'counselor_query.freezed.dart';
part 'counselor_query.g.dart';

/// What the person typed and chose on the search screen.
@freezed
abstract class CounselorQuery with _$CounselorQuery {
  const CounselorQuery._();

  const factory CounselorQuery({
    String? q,
    String? specialization,
    String? language,
    SessionMode? mode,
    String? state,
  }) = _CounselorQuery;

  /// How many filters (not counting the search words) are on.
  int get activeFilterCount => [specialization, language, mode, state].where((v) => v != null).length;

  bool get isFiltering => activeFilterCount > 0 || (q?.isNotEmpty ?? false);

  /// Only what is set: an empty value is never sent.
  Map<String, dynamic> toParams({int page = 1}) => {
        if (q != null && q!.isNotEmpty) 'q': q,
        'specialization': ?specialization,
        'language': ?language,
        if (mode != null) 'mode': mode!.name,
        'state': ?state,
        if (page > 1) 'page': page,
      };
}

/// The choices on the filter screen, from what this person can really find.
@freezed
abstract class CounselorFilterOptions with _$CounselorFilterOptions {
  const factory CounselorFilterOptions({
    @Default([]) List<String> specializations,
    @Default([]) List<String> languages,
    @JsonKey(unknownEnumValue: SessionMode.unknown) @Default([]) List<SessionMode> sessionModes,
    @Default([]) List<String> states,
  }) = _CounselorFilterOptions;

  factory CounselorFilterOptions.fromJson(Map<String, dynamic> json) => _$CounselorFilterOptionsFromJson(json);
}

/// One page of results.
class CounselorPage {
  const CounselorPage({required this.items, required this.page, required this.lastPage, required this.total});

  final List<Counselor> items;
  final int page;
  final int lastPage;
  final int total;

  bool get hasMore => page < lastPage;
}
