import 'package:flutter/material.dart';

/// MyKaunsel's colours, the same as the web (tailwind.config.js → brand.*).
abstract final class AppColors {
  static const blue = Color(0xFF0A4F88);
  static const deepBlue = Color(0xFF073A66);
  static const yellow = Color(0xFFE3AF39);
  static const red = Color(0xFFD62839);
  static const ink = Color(0xFF1B2A4E);
  static const cream = Color(0xFFFAF8F5);
}

ThemeData buildAppTheme() {
  final scheme = ColorScheme.fromSeed(seedColor: AppColors.blue, brightness: Brightness.light).copyWith(
    primary: AppColors.blue,
    onPrimary: Colors.white,
    secondary: AppColors.yellow,
    error: AppColors.red,
    onSurface: AppColors.ink,
    surface: AppColors.cream,
  );

  const pill = StadiumBorder();
  // 48 logical pixels is the smallest comfortable (and accessible) tap target.
  const minButton = Size(64, 48);

  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    fontFamily: 'PlusJakartaSans',
    scaffoldBackgroundColor: AppColors.cream,
    appBarTheme: const AppBarTheme(
      backgroundColor: AppColors.cream,
      foregroundColor: AppColors.ink,
      elevation: 0,
      scrolledUnderElevation: 0,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(minimumSize: minButton, shape: pill, textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(minimumSize: minButton, shape: pill, textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(minimumSize: minButton, shape: pill, textStyle: const TextStyle(fontWeight: FontWeight.w600)),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: AppColors.ink.withValues(alpha: .15))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: AppColors.ink.withValues(alpha: .15))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.blue, width: 2)),
      errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.red)),
      focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.red, width: 2)),
    ),
  );
}
