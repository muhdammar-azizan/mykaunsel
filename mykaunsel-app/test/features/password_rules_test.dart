import 'package:flutter_test/flutter_test.dart';
import 'package:mykaunsel/shared/password_rules.dart';

void main() {
  test('every rule is checked on its own', () {
    final empty = PasswordRules.of('');
    expect([empty.hasMinLength, empty.hasUpper, empty.hasLower, empty.hasNumber, empty.hasSymbol], everyElement(isFalse));

    expect(PasswordRules.of('abcdefgh').hasMinLength, isTrue);
    expect(PasswordRules.of('abcdefg').hasMinLength, isFalse);
    expect(PasswordRules.of('abC').hasUpper, isTrue);
    expect(PasswordRules.of('abc').hasUpper, isFalse);
    expect(PasswordRules.of('ABc').hasLower, isTrue);
    expect(PasswordRules.of('ABC').hasLower, isFalse);
    expect(PasswordRules.of('ab1').hasNumber, isTrue);
    expect(PasswordRules.of('ab').hasNumber, isFalse);
    expect(PasswordRules.of('ab!').hasSymbol, isTrue);
    expect(PasswordRules.of('ab').hasSymbol, isFalse);
  });

  test('a password is strong only when it has all of them', () {
    expect(PasswordRules.of('Str0ng-Pass').isStrong, isTrue);
    expect(PasswordRules.of('Str0ngPass').isStrong, isFalse); // no symbol
    expect(PasswordRules.of('str0ng-pass').isStrong, isFalse); // no upper-case
    expect(PasswordRules.of('STR0NG-PASS').isStrong, isFalse); // no lower-case
    expect(PasswordRules.of('Strong-Pass').isStrong, isFalse); // no number
    expect(PasswordRules.of('St0-g').isStrong, isFalse); // too short
  });

  test('a space counts as a symbol, like the server', () {
    expect(PasswordRules.of('Pass word 1').isStrong, isTrue);
  });

  test('characters outside A–Z are understood', () {
    expect(PasswordRules.of('Ünï-çödé1').isStrong, isTrue);
  });

  test('the length counts characters, not bytes', () {
    expect(PasswordRules.of('Aa1!😀😀😀😀').hasMinLength, isTrue);
  });
}
