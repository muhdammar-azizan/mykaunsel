import 'package:flutter/material.dart';

import '../../../l10n/app_localizations.dart';
import '../../../shared/widgets/status_screen.dart';

/// Shown while the app asks the server whether it may open (see SessionController.start).
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return StatusScreen(body: AppLocalizations.of(context).startingUp, showProgress: true);
  }
}
