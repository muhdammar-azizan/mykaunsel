import 'package:intl/intl.dart';

import '../../l10n/app_localizations.dart';

/// "Active just now", "Last used 5 minutes ago", "Last used yesterday", "Last used 3 Oct 2026". Relative, so the phone's
/// own time zone does not matter.
String formatLastUsed(AppLocalizations l10n, DateTime? lastUsed, {required DateTime now, required String locale}) {
  if (lastUsed == null) {
    return l10n.neverUsed;
  }

  final ago = now.difference(lastUsed);

  if (ago.inMinutes < 1) return l10n.lastUsedNow;
  if (ago.inMinutes < 60) return l10n.lastUsedMinutes(ago.inMinutes);
  if (ago.inHours < 24) return l10n.lastUsedHours(ago.inHours);
  if (ago.inDays < 7) return l10n.lastUsedDays(ago.inDays);

  return l10n.lastUsedOn(DateFormat('d MMM yyyy', locale).format(lastUsed.toUtc().add(const Duration(hours: 8))));
}
