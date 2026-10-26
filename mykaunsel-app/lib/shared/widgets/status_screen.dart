import 'package:flutter/material.dart';

/// A full-screen message with an optional button or two: splash, maintenance, "please update", "no connection".
class StatusScreen extends StatelessWidget {
  const StatusScreen({
    super.key,
    this.title,
    this.body,
    this.showProgress = false,
    this.primaryLabel,
    this.onPrimary,
    this.secondaryLabel,
    this.onSecondary,
  });

  final String? title;
  final String? body;
  final bool showProgress;
  final String? primaryLabel;
  final VoidCallback? onPrimary;
  final String? secondaryLabel;
  final VoidCallback? onSecondary;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Image.asset('assets/images/mykaunsel-icon.png', height: 72, semanticLabel: 'MyKaunsel'),
                  const SizedBox(height: 24),
                  if (title != null)
                    Text(title!, textAlign: TextAlign.center, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                  if (body != null && body!.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(body!, textAlign: TextAlign.center, style: text.bodyLarge),
                  ],
                  if (showProgress) ...[
                    const SizedBox(height: 28),
                    const Center(child: CircularProgressIndicator()),
                  ],
                  if (primaryLabel != null) ...[
                    const SizedBox(height: 28),
                    FilledButton(onPressed: onPrimary, child: Text(primaryLabel!)),
                  ],
                  if (secondaryLabel != null) ...[
                    const SizedBox(height: 8),
                    TextButton(onPressed: onSecondary, child: Text(secondaryLabel!)),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
