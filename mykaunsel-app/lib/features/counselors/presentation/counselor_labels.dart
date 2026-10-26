import 'package:flutter/widgets.dart' show StringCharacters;

import '../../../l10n/app_localizations.dart';
import '../domain/counselor.dart';

/// The words for the things a counselor's data is made of.
extension SessionModeLabel on SessionMode {
  /// Null for a kind this version of the app does not know (it is simply not shown).
  String? label(AppLocalizations l10n) => switch (this) {
        SessionMode.online => l10n.sessionOnline,
        SessionMode.physical => l10n.sessionInPerson,
        SessionMode.unknown => null,
      };
}

extension CounselorContextLabel on CounselorContext {
  String? label(AppLocalizations l10n) => switch (type) {
        ContextType.independent => l10n.independentLabel,
        ContextType.organization => organization,
        ContextType.unknown => null,
      };
}

extension CounselorLocationLabel on CounselorLocation {
  /// "Kuantan, Pahang".
  String get place => '$city, $state';
}

extension CounselorInitials on Counselor {
  /// Two letters for the round avatar (there are no photos yet). Titles such as "Kaunselor" are skipped.
  String get initials {
    const skip = {'kaunselor', 'counselor', 'dr', 'dr.', 'puan', 'encik', 'cik', 'prof', 'prof.'};
    final words = name.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
    final useful = words.where((w) => !skip.contains(w.toLowerCase())).toList();
    final parts = useful.isEmpty ? words : useful;

    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.characters.first.toUpperCase();

    return (parts.first.characters.first + parts.last.characters.first).toUpperCase();
  }
}
