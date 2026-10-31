import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/providers.dart';
import '../../../core/router/app_router.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../l10n/app_localizations.dart';

/// Three short pages shown once, before the first sign-in. They can be skipped; either way the person is not shown them again.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _controller = PageController();
  int _page = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    await ref.read(onboardingSeenProvider.notifier).markSeen();

    if (mounted) {
      context.go(AppRoutes.signIn);
    }
  }

  void _next(int count) {
    if (_page >= count - 1) {
      _finish();

      return;
    }

    _controller.nextPage(duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final text = Theme.of(context).textTheme;
    final scheme = Theme.of(context).colorScheme;

    final pages = [
      (Icons.chat_bubble_outline, l10n.onboardingTalkTitle, l10n.onboardingTalkBody),
      (Icons.verified_user_outlined, l10n.onboardingFindTitle, l10n.onboardingFindBody),
      (Icons.forum_outlined, l10n.onboardingFeedTitle, l10n.onboardingFeedBody),
    ];
    final last = _page == pages.length - 1;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Column(
              children: [
                Align(
                  alignment: AlignmentDirectional.centerEnd,
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.s),
                    child: TextButton(
                      style: TextButton.styleFrom(minimumSize: const Size(AppSizes.minTouch, AppSizes.minTouch)),
                      onPressed: _finish,
                      child: Text(l10n.onboardingSkip),
                    ),
                  ),
                ),
                Expanded(
                  child: PageView.builder(
                    controller: _controller,
                    itemCount: pages.length,
                    onPageChanged: (value) => setState(() => _page = value),
                    itemBuilder: (_, index) {
                      final (icon, title, body) = pages[index];

                      return Center(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.xl),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              ExcludeSemantics(child: Icon(icon, size: 88, color: scheme.primary)),
                              const SizedBox(height: AppSpacing.xl),
                              Semantics(
                                header: true,
                                child: Text(title, textAlign: TextAlign.center, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                              ),
                              const SizedBox(height: AppSpacing.m),
                              Text(body, textAlign: TextAlign.center, style: text.bodyLarge),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
                Semantics(
                  label: l10n.onboardingStep(_page + 1, pages.length),
                  child: ExcludeSemantics(
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        for (var i = 0; i < pages.length; i++)
                          AnimatedContainer(
                            duration: const Duration(milliseconds: 200),
                            margin: const EdgeInsets.all(AppSpacing.xs),
                            width: i == _page ? 24 : 8,
                            height: 8,
                            decoration: BoxDecoration(
                              color: i == _page ? scheme.primary : scheme.onSurface.withValues(alpha: .25),
                              borderRadius: AppRadii.pillRadius,
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(AppSpacing.xl),
                  child: SizedBox(
                    width: double.infinity,
                    child: FilledButton(onPressed: () => _next(pages.length), child: Text(last ? l10n.onboardingStart : l10n.onboardingNext)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
