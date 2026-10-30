import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/router/app_router.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/session/session_state.dart';
import 'pending_auth.dart';

/// A signed-in person whose email turns out not to be confirmed cannot open "check your email" (signed-in people are sent home). So: remember
/// the address, sign out, and open that page. Used by every screen that meets a `403 email_not_verified`.
Future<void> startEmailVerification(BuildContext context, WidgetRef ref) async {
  final session = ref.read(sessionControllerProvider);
  final email = session is SessionSignedIn ? session.user.email : null;

  if (email == null) return;

  ref.read(pendingVerificationEmailProvider.notifier).set(email);
  await ref.read(sessionControllerProvider.notifier).signOut();

  if (context.mounted) context.go(AppRoutes.verifyEmail);
}
