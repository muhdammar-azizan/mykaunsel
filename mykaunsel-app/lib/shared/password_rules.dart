/// The password rules, the same as the server's (`Password::min(8)->mixedCase()->numbers()->symbols()`), so the form can
/// tell the person what is missing as they type. The server stays the judge: this is only early help.
class PasswordRules {
  const PasswordRules._({
    required this.hasMinLength,
    required this.hasUpper,
    required this.hasLower,
    required this.hasNumber,
    required this.hasSymbol,
  });

  factory PasswordRules.of(String password) {
    return PasswordRules._(
      hasMinLength: password.runes.length >= minLength,
      hasUpper: RegExp(r'\p{Lu}', unicode: true).hasMatch(password),
      hasLower: RegExp(r'\p{Ll}', unicode: true).hasMatch(password),
      hasNumber: RegExp(r'\p{N}', unicode: true).hasMatch(password),
      hasSymbol: RegExp(r'[\p{Z}\p{S}\p{P}]', unicode: true).hasMatch(password),
    );
  }

  static const minLength = 8;

  final bool hasMinLength;
  final bool hasUpper;
  final bool hasLower;
  final bool hasNumber;
  final bool hasSymbol;

  bool get isStrong => hasMinLength && hasUpper && hasLower && hasNumber && hasSymbol;
}
