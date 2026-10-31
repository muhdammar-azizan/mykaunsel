import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';

/// The frame of the signed-in app: a bar at the bottom to move between Home, Chat, Bookings, Feed and Account. Each tab keeps its own place
/// (scroll position, open profile) while you visit another. Finding a counselor is a place of its own (opened from Home and from the help cards),
/// but it has no tab: while you are there, Home is the lit one.
class AppShell extends StatelessWidget {
  const AppShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  /// The router's branches, in order: Home, Find, Chat, Bookings, Feed, Account. These are the ones with a tab.
  static const _tabBranches = [0, 2, 3, 4, 5];

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final current = navigationShell.currentIndex;
    final selected = _tabBranches.contains(current) ? _tabBranches.indexOf(current) : 0;

    return Scaffold(
      body: navigationShell,
      // Five tabs: at the largest text sizes a label such as "Bookings" would break in the middle of the word, so the bar's text stops growing at 1.1.
      bottomNavigationBar: MediaQuery.withClampedTextScaling(
        maxScaleFactor: 1.1,
        child: NavigationBar(
          selectedIndex: selected,
          // Tapping the tab you are already on goes back to its first page.
          onDestinationSelected: (index) => navigationShell.goBranch(_tabBranches[index], initialLocation: _tabBranches[index] == current),
          destinations: [
            NavigationDestination(icon: const Icon(Icons.home_outlined), selectedIcon: const Icon(Icons.home), label: l10n.navHome),
            NavigationDestination(icon: const Icon(Icons.chat_bubble_outline), selectedIcon: const Icon(Icons.chat_bubble), label: l10n.navChat),
            NavigationDestination(icon: const Icon(Icons.event_note_outlined), selectedIcon: const Icon(Icons.event_note), label: l10n.navBookings),
            NavigationDestination(icon: const Icon(Icons.forum_outlined), selectedIcon: const Icon(Icons.forum), label: l10n.navFeed),
            NavigationDestination(icon: const Icon(Icons.person_outline), selectedIcon: const Icon(Icons.person), label: l10n.navAccount),
          ],
        ),
      ),
    );
  }
}
