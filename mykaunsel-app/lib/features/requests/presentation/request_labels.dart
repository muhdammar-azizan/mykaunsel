import 'package:flutter/material.dart';

import '../../../l10n/app_localizations.dart';
import '../domain/time_request.dart';

/// The words and the symbol for a request's state. A state is always a word AND an icon, never colour alone.
extension RequestStateLabel on RequestState {
  String label(AppLocalizations l10n) => switch (this) {
        RequestState.pending => l10n.requestStatusPending,
        RequestState.approved => l10n.requestStatusApproved,
        RequestState.rejected => l10n.requestStatusDeclined,
        RequestState.expired => l10n.requestStatusExpired,
        RequestState.withdrawn => l10n.requestStatusWithdrawn,
        RequestState.unknown => l10n.requestStatusUnknown,
      };

  IconData get icon => switch (this) {
        RequestState.pending => Icons.hourglass_empty,
        RequestState.approved => Icons.check_circle_outline,
        RequestState.rejected => Icons.block,
        RequestState.expired => Icons.timer_off_outlined,
        RequestState.withdrawn => Icons.undo,
        RequestState.unknown => Icons.info_outline,
      };
}
