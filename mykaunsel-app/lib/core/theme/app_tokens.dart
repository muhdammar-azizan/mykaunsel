import 'package:flutter/material.dart';

/// Numbers for space, corners and sizes, so a screen never writes a raw one. (Colours come from the theme's ColorScheme.)
abstract final class AppSpacing {
  static const double xs = 4;
  static const double s = 8;
  static const double m = 12;
  static const double l = 16;
  static const double xl = 24;
  static const double xxl = 32;
}

abstract final class AppRadii {
  static const double chip = 12;
  static const double card = 16;
  static const double pill = 999;

  static BorderRadius get chipRadius => BorderRadius.circular(chip);

  static BorderRadius get cardRadius => BorderRadius.circular(card);

  static BorderRadius get pillRadius => BorderRadius.circular(pill);
}

abstract final class AppSizes {
  /// The smallest comfortable (and accessible) tap target, in logical pixels.
  static const double minTouch = 44;

  static const double avatar = 44;

  static const double dateCardWidth = 68;

  static const double dateCardHeight = 92;

  static const double borderThin = 1;

  static const double borderSelected = 2;

  static const double icon = 20;
}

/// The look of the booking screens, from the colours the theme already has. A screen asks for "selected" or "booked", never for a hex.
extension BookingColors on ColorScheme {
  /// A selected date card: dark.
  Color get dateSelectedFill => onSurface;

  Color get dateSelectedText => surface;

  /// A slot or a date card that can be tapped.
  Color get cardFill => surfaceContainerLowest;

  Color get cardBorder => onSurface.withValues(alpha: .15);

  /// A selected slot: tinted, with a thicker border in the main colour (and a check mark: never colour alone).
  Color get slotSelectedFill => primary.withValues(alpha: .08);

  Color get slotSelectedBorder => primary;

  /// Booked or unavailable: dimmed, but still readable.
  Color get inactiveFill => onSurface.withValues(alpha: .05);

  Color get inactiveText => onSurface.withValues(alpha: .65);

  Color get disabledText => onSurface.withValues(alpha: .45);

  /// A line of explanation under or beside the main text.
  Color get mutedText => onSurface.withValues(alpha: .7);

  /// The bars of a loading placeholder.
  Color get skeleton => onSurface.withValues(alpha: .08);

  Color get bannerFill => secondary.withValues(alpha: .2);

  /// The calm amber of the crisis card, and its border. Warm, not alarming; never colour alone (the card has a title and an icon).
  Color get crisisFill => brightness == Brightness.dark ? const Color(0xFF3A3020) : const Color(0xFFFFF3D6);

  Color get crisisBorder => brightness == Brightness.dark ? const Color(0xFF8A7440) : const Color(0xFFE8C26A);
}
