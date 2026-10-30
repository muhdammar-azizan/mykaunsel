import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[Locale('en')];

  /// No description provided for @appName.
  ///
  /// In en, this message translates to:
  /// **'MyKaunsel'**
  String get appName;

  /// No description provided for @startingUp.
  ///
  /// In en, this message translates to:
  /// **'Starting MyKaunsel…'**
  String get startingUp;

  /// No description provided for @tryAgain.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get tryAgain;

  /// No description provided for @signOut.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get signOut;

  /// No description provided for @maintenanceTitle.
  ///
  /// In en, this message translates to:
  /// **'Down for maintenance'**
  String get maintenanceTitle;

  /// No description provided for @updateRequiredTitle.
  ///
  /// In en, this message translates to:
  /// **'Please update the app'**
  String get updateRequiredTitle;

  /// No description provided for @updateRequiredBody.
  ///
  /// In en, this message translates to:
  /// **'This version of MyKaunsel is out of date. Please update the app to continue.'**
  String get updateRequiredBody;

  /// No description provided for @updateNow.
  ///
  /// In en, this message translates to:
  /// **'Update now'**
  String get updateNow;

  /// No description provided for @noConnectionTitle.
  ///
  /// In en, this message translates to:
  /// **'No connection'**
  String get noConnectionTitle;

  /// No description provided for @noConnectionBody.
  ///
  /// In en, this message translates to:
  /// **'We could not reach MyKaunsel. Check your internet connection and try again.'**
  String get noConnectionBody;

  /// No description provided for @somethingWentWrongTitle.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong'**
  String get somethingWentWrongTitle;

  /// No description provided for @somethingWentWrongBody.
  ///
  /// In en, this message translates to:
  /// **'Please try again in a moment.'**
  String get somethingWentWrongBody;

  /// No description provided for @tooManyRequests.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Please try again in a moment.'**
  String get tooManyRequests;

  /// No description provided for @tooManyRequestsIn.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Please try again in {seconds} seconds.'**
  String tooManyRequestsIn(int seconds);

  /// No description provided for @sessionExpired.
  ///
  /// In en, this message translates to:
  /// **'Your session has ended. Please sign in again.'**
  String get sessionExpired;

  /// No description provided for @signedInAs.
  ///
  /// In en, this message translates to:
  /// **'Signed in as {name}'**
  String signedInAs(String name);

  /// No description provided for @signInTitle.
  ///
  /// In en, this message translates to:
  /// **'Welcome back'**
  String get signInTitle;

  /// No description provided for @signInSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Sign in to your MyKaunsel account.'**
  String get signInSubtitle;

  /// No description provided for @emailLabel.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get emailLabel;

  /// No description provided for @passwordLabel.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get passwordLabel;

  /// No description provided for @emailRequired.
  ///
  /// In en, this message translates to:
  /// **'Enter your email address.'**
  String get emailRequired;

  /// No description provided for @passwordRequired.
  ///
  /// In en, this message translates to:
  /// **'Enter your password.'**
  String get passwordRequired;

  /// No description provided for @signInButton.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signInButton;

  /// No description provided for @showPassword.
  ///
  /// In en, this message translates to:
  /// **'Show password'**
  String get showPassword;

  /// No description provided for @hidePassword.
  ///
  /// In en, this message translates to:
  /// **'Hide password'**
  String get hidePassword;

  /// No description provided for @twoFactorTitle.
  ///
  /// In en, this message translates to:
  /// **'Two-step verification'**
  String get twoFactorTitle;

  /// No description provided for @twoFactorBody.
  ///
  /// In en, this message translates to:
  /// **'Enter the 6-digit code from your authenticator app.'**
  String get twoFactorBody;

  /// No description provided for @twoFactorRecoveryBody.
  ///
  /// In en, this message translates to:
  /// **'Enter one of your recovery codes. Each code works only once.'**
  String get twoFactorRecoveryBody;

  /// No description provided for @codeLabel.
  ///
  /// In en, this message translates to:
  /// **'6-digit code'**
  String get codeLabel;

  /// No description provided for @codeRequired.
  ///
  /// In en, this message translates to:
  /// **'Enter the 6-digit code.'**
  String get codeRequired;

  /// No description provided for @recoveryCodeLabel.
  ///
  /// In en, this message translates to:
  /// **'Recovery code'**
  String get recoveryCodeLabel;

  /// No description provided for @recoveryCodeRequired.
  ///
  /// In en, this message translates to:
  /// **'Enter a recovery code.'**
  String get recoveryCodeRequired;

  /// No description provided for @useRecoveryCode.
  ///
  /// In en, this message translates to:
  /// **'Use a recovery code instead'**
  String get useRecoveryCode;

  /// No description provided for @useAuthenticatorCode.
  ///
  /// In en, this message translates to:
  /// **'Use the code from my authenticator app'**
  String get useAuthenticatorCode;

  /// No description provided for @verifyButton.
  ///
  /// In en, this message translates to:
  /// **'Verify'**
  String get verifyButton;

  /// No description provided for @signInExpired.
  ///
  /// In en, this message translates to:
  /// **'Your sign-in took too long. Please sign in again.'**
  String get signInExpired;

  /// No description provided for @verifyEmailTitle.
  ///
  /// In en, this message translates to:
  /// **'Confirm your email'**
  String get verifyEmailTitle;

  /// No description provided for @verifyEmailBody.
  ///
  /// In en, this message translates to:
  /// **'We sent a link to {email}. Open it to confirm your address, then come back here and sign in.'**
  String verifyEmailBody(String email);

  /// No description provided for @resendEmail.
  ///
  /// In en, this message translates to:
  /// **'Send the email again'**
  String get resendEmail;

  /// No description provided for @resendEmailIn.
  ///
  /// In en, this message translates to:
  /// **'Send again in {seconds}s'**
  String resendEmailIn(int seconds);

  /// No description provided for @emailResent.
  ///
  /// In en, this message translates to:
  /// **'If that address still needs confirming, we have sent the email again.'**
  String get emailResent;

  /// No description provided for @backToSignIn.
  ///
  /// In en, this message translates to:
  /// **'Back to sign in'**
  String get backToSignIn;

  /// No description provided for @noAccountYet.
  ///
  /// In en, this message translates to:
  /// **'New to MyKaunsel?'**
  String get noAccountYet;

  /// No description provided for @createAccountLink.
  ///
  /// In en, this message translates to:
  /// **'Create an account'**
  String get createAccountLink;

  /// No description provided for @forgotPasswordLink.
  ///
  /// In en, this message translates to:
  /// **'Forgot your password?'**
  String get forgotPasswordLink;

  /// No description provided for @alreadyHaveAccount.
  ///
  /// In en, this message translates to:
  /// **'Already have an account?'**
  String get alreadyHaveAccount;

  /// No description provided for @signInLink.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signInLink;

  /// No description provided for @signUpTitle.
  ///
  /// In en, this message translates to:
  /// **'Create your account'**
  String get signUpTitle;

  /// No description provided for @signUpSubtitle.
  ///
  /// In en, this message translates to:
  /// **'A few details and you are ready to go.'**
  String get signUpSubtitle;

  /// No description provided for @nameLabel.
  ///
  /// In en, this message translates to:
  /// **'Full name'**
  String get nameLabel;

  /// No description provided for @nameRequired.
  ///
  /// In en, this message translates to:
  /// **'Enter your name.'**
  String get nameRequired;

  /// No description provided for @emailInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid email address.'**
  String get emailInvalid;

  /// No description provided for @confirmPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'Confirm password'**
  String get confirmPasswordLabel;

  /// No description provided for @confirmPasswordRequired.
  ///
  /// In en, this message translates to:
  /// **'Type the password again.'**
  String get confirmPasswordRequired;

  /// No description provided for @passwordsDontMatch.
  ///
  /// In en, this message translates to:
  /// **'The two passwords are not the same.'**
  String get passwordsDontMatch;

  /// No description provided for @createAccountButton.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get createAccountButton;

  /// No description provided for @passwordMustHave.
  ///
  /// In en, this message translates to:
  /// **'Your password needs:'**
  String get passwordMustHave;

  /// No description provided for @ruleLength.
  ///
  /// In en, this message translates to:
  /// **'At least 8 characters'**
  String get ruleLength;

  /// No description provided for @ruleUpper.
  ///
  /// In en, this message translates to:
  /// **'An upper-case letter'**
  String get ruleUpper;

  /// No description provided for @ruleLower.
  ///
  /// In en, this message translates to:
  /// **'A lower-case letter'**
  String get ruleLower;

  /// No description provided for @ruleNumber.
  ///
  /// In en, this message translates to:
  /// **'A number'**
  String get ruleNumber;

  /// No description provided for @ruleSymbol.
  ///
  /// In en, this message translates to:
  /// **'A symbol, such as ! or #'**
  String get ruleSymbol;

  /// No description provided for @ruleMet.
  ///
  /// In en, this message translates to:
  /// **'{rule}, done'**
  String ruleMet(String rule);

  /// No description provided for @ruleNotMet.
  ///
  /// In en, this message translates to:
  /// **'{rule}, not yet'**
  String ruleNotMet(String rule);

  /// No description provided for @signUpCheckingTitle.
  ///
  /// In en, this message translates to:
  /// **'Checking if sign-up is open…'**
  String get signUpCheckingTitle;

  /// No description provided for @signUpClosedTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign-up is closed for now'**
  String get signUpClosedTitle;

  /// No description provided for @forgotTitle.
  ///
  /// In en, this message translates to:
  /// **'Forgot your password?'**
  String get forgotTitle;

  /// No description provided for @forgotSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Enter your email and we will send you a link to choose a new one.'**
  String get forgotSubtitle;

  /// No description provided for @sendLinkButton.
  ///
  /// In en, this message translates to:
  /// **'Send the link'**
  String get sendLinkButton;

  /// No description provided for @forgotSentTitle.
  ///
  /// In en, this message translates to:
  /// **'Check your email'**
  String get forgotSentTitle;

  /// No description provided for @forgotSentBody.
  ///
  /// In en, this message translates to:
  /// **'If {email} has an account, we have emailed a link. Open it, choose a new password, then come back here and sign in.'**
  String forgotSentBody(String email);

  /// No description provided for @useDifferentEmail.
  ///
  /// In en, this message translates to:
  /// **'Use a different email'**
  String get useDifferentEmail;

  /// No description provided for @homeGreeting.
  ///
  /// In en, this message translates to:
  /// **'Hello, {name}'**
  String homeGreeting(String name);

  /// No description provided for @homeEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'Your space is being set up'**
  String get homeEmptyTitle;

  /// No description provided for @homeEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'Finding a counselor and booking sessions are coming soon. You are signed in and your account is ready.'**
  String get homeEmptyBody;

  /// No description provided for @accountMenu.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get accountMenu;

  /// No description provided for @signOutEverywhere.
  ///
  /// In en, this message translates to:
  /// **'Sign out of all devices'**
  String get signOutEverywhere;

  /// No description provided for @signOutEverywhereTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign out of all devices?'**
  String get signOutEverywhereTitle;

  /// No description provided for @signOutEverywhereBody.
  ///
  /// In en, this message translates to:
  /// **'Every phone signed in to your account, including this one, will be signed out.'**
  String get signOutEverywhereBody;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @notFoundBody.
  ///
  /// In en, this message translates to:
  /// **'We could not find that. It may no longer be available.'**
  String get notFoundBody;

  /// No description provided for @navHome.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get navHome;

  /// No description provided for @navFind.
  ///
  /// In en, this message translates to:
  /// **'Find'**
  String get navFind;

  /// No description provided for @navAccount.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get navAccount;

  /// No description provided for @findTitle.
  ///
  /// In en, this message translates to:
  /// **'Find a counselor'**
  String get findTitle;

  /// No description provided for @searchHint.
  ///
  /// In en, this message translates to:
  /// **'Search by name'**
  String get searchHint;

  /// No description provided for @clearSearch.
  ///
  /// In en, this message translates to:
  /// **'Clear search'**
  String get clearSearch;

  /// No description provided for @filtersButton.
  ///
  /// In en, this message translates to:
  /// **'Filters'**
  String get filtersButton;

  /// No description provided for @filtersTitle.
  ///
  /// In en, this message translates to:
  /// **'Filters'**
  String get filtersTitle;

  /// No description provided for @filtersApply.
  ///
  /// In en, this message translates to:
  /// **'Show results'**
  String get filtersApply;

  /// No description provided for @filtersClear.
  ///
  /// In en, this message translates to:
  /// **'Clear all'**
  String get filtersClear;

  /// No description provided for @filterFocusArea.
  ///
  /// In en, this message translates to:
  /// **'Focus area'**
  String get filterFocusArea;

  /// No description provided for @filterLanguage.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get filterLanguage;

  /// No description provided for @filterSessionType.
  ///
  /// In en, this message translates to:
  /// **'Session type'**
  String get filterSessionType;

  /// No description provided for @filterState.
  ///
  /// In en, this message translates to:
  /// **'State'**
  String get filterState;

  /// No description provided for @anyOption.
  ///
  /// In en, this message translates to:
  /// **'Any'**
  String get anyOption;

  /// No description provided for @sessionOnline.
  ///
  /// In en, this message translates to:
  /// **'Online'**
  String get sessionOnline;

  /// No description provided for @sessionInPerson.
  ///
  /// In en, this message translates to:
  /// **'In-person'**
  String get sessionInPerson;

  /// No description provided for @filtersCount.
  ///
  /// In en, this message translates to:
  /// **'Filters ({count})'**
  String filtersCount(int count);

  /// No description provided for @resultsCount.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =0{No counselors} =1{1 counselor} other{{count} counselors}}'**
  String resultsCount(int count);

  /// No description provided for @emptyFilteredTitle.
  ///
  /// In en, this message translates to:
  /// **'No counselors found'**
  String get emptyFilteredTitle;

  /// No description provided for @emptyFilteredBody.
  ///
  /// In en, this message translates to:
  /// **'Try a different name, or fewer filters.'**
  String get emptyFilteredBody;

  /// No description provided for @emptyNoneTitle.
  ///
  /// In en, this message translates to:
  /// **'No counselors yet'**
  String get emptyNoneTitle;

  /// No description provided for @emptyNoneBody.
  ///
  /// In en, this message translates to:
  /// **'Counselors will appear here as soon as they are available. Please check back soon.'**
  String get emptyNoneBody;

  /// No description provided for @clearFilters.
  ///
  /// In en, this message translates to:
  /// **'Clear filters'**
  String get clearFilters;

  /// No description provided for @loadMoreFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not load more counselors.'**
  String get loadMoreFailed;

  /// No description provided for @loadingMore.
  ///
  /// In en, this message translates to:
  /// **'Loading more…'**
  String get loadingMore;

  /// No description provided for @verifiedLabel.
  ///
  /// In en, this message translates to:
  /// **'Verified registered counselor'**
  String get verifiedLabel;

  /// No description provided for @independentLabel.
  ///
  /// In en, this message translates to:
  /// **'Independent'**
  String get independentLabel;

  /// No description provided for @moreFocusAreas.
  ///
  /// In en, this message translates to:
  /// **'+{count} more'**
  String moreFocusAreas(int count);

  /// No description provided for @nextAvailable.
  ///
  /// In en, this message translates to:
  /// **'Next free time: {when}'**
  String nextAvailable(String when);

  /// No description provided for @noUpcomingTimes.
  ///
  /// In en, this message translates to:
  /// **'No free times in the coming weeks'**
  String get noUpcomingTimes;

  /// No description provided for @today.
  ///
  /// In en, this message translates to:
  /// **'Today'**
  String get today;

  /// No description provided for @tomorrow.
  ///
  /// In en, this message translates to:
  /// **'Tomorrow'**
  String get tomorrow;

  /// No description provided for @malaysiaTimeNote.
  ///
  /// In en, this message translates to:
  /// **'All times are Malaysian time.'**
  String get malaysiaTimeNote;

  /// No description provided for @inPersonIn.
  ///
  /// In en, this message translates to:
  /// **'In-person · {place}'**
  String inPersonIn(String place);

  /// No description provided for @minutesShort.
  ///
  /// In en, this message translates to:
  /// **'{minutes} min'**
  String minutesShort(int minutes);

  /// No description provided for @profileFocusAreas.
  ///
  /// In en, this message translates to:
  /// **'Focus areas'**
  String get profileFocusAreas;

  /// No description provided for @profileLanguages.
  ///
  /// In en, this message translates to:
  /// **'Languages'**
  String get profileLanguages;

  /// No description provided for @profileSessionTypes.
  ///
  /// In en, this message translates to:
  /// **'Session types'**
  String get profileSessionTypes;

  /// No description provided for @profileWhere.
  ///
  /// In en, this message translates to:
  /// **'Where'**
  String get profileWhere;

  /// No description provided for @profileWorksWith.
  ///
  /// In en, this message translates to:
  /// **'Works with'**
  String get profileWorksWith;

  /// No description provided for @profileAvailability.
  ///
  /// In en, this message translates to:
  /// **'Free times'**
  String get profileAvailability;

  /// No description provided for @profileNoTimes.
  ///
  /// In en, this message translates to:
  /// **'No free times right now. Please check again soon.'**
  String get profileNoTimes;

  /// No description provided for @profileAcceptsRequests.
  ///
  /// In en, this message translates to:
  /// **'Also takes session requests.'**
  String get profileAcceptsRequests;

  /// No description provided for @seeAvailableTimes.
  ///
  /// In en, this message translates to:
  /// **'See available times'**
  String get seeAvailableTimes;

  /// No description provided for @profileNotFoundTitle.
  ///
  /// In en, this message translates to:
  /// **'Counselor not available'**
  String get profileNotFoundTitle;

  /// No description provided for @pickSlotTitle.
  ///
  /// In en, this message translates to:
  /// **'Pick a time'**
  String get pickSlotTitle;

  /// No description provided for @pickSlotModeLabel.
  ///
  /// In en, this message translates to:
  /// **'Session type'**
  String get pickSlotModeLabel;

  /// No description provided for @pickSlotDatesLabel.
  ///
  /// In en, this message translates to:
  /// **'Choose a date'**
  String get pickSlotDatesLabel;

  /// No description provided for @pickSlotTimesLabel.
  ///
  /// In en, this message translates to:
  /// **'Times on {date}'**
  String pickSlotTimesLabel(String date);

  /// No description provided for @pickSlotDateCard.
  ///
  /// In en, this message translates to:
  /// **'{weekday} {day} {month}, {count, plural, =0{no times} =1{1 time} other{{count} times}}'**
  String pickSlotDateCard(String weekday, String day, String month, int count);

  /// No description provided for @pickSlotSlotLabel.
  ///
  /// In en, this message translates to:
  /// **'{time}, {duration} minutes, {mode}'**
  String pickSlotSlotLabel(String time, int duration, String mode);

  /// No description provided for @pickSlotBooked.
  ///
  /// In en, this message translates to:
  /// **'Booked'**
  String get pickSlotBooked;

  /// No description provided for @pickSlotSelected.
  ///
  /// In en, this message translates to:
  /// **'Selected'**
  String get pickSlotSelected;

  /// No description provided for @pickSlotContinue.
  ///
  /// In en, this message translates to:
  /// **'Continue'**
  String get pickSlotContinue;

  /// No description provided for @pickSlotContinueAt.
  ///
  /// In en, this message translates to:
  /// **'Continue with {when}'**
  String pickSlotContinueAt(String when);

  /// No description provided for @pickSlotPickFirst.
  ///
  /// In en, this message translates to:
  /// **'Pick a time to continue'**
  String get pickSlotPickFirst;

  /// No description provided for @pickSlotNoTimesDay.
  ///
  /// In en, this message translates to:
  /// **'No times on this day.'**
  String get pickSlotNoTimesDay;

  /// No description provided for @pickSlotNoTimesDayMode.
  ///
  /// In en, this message translates to:
  /// **'No {mode} times on this day.'**
  String pickSlotNoTimesDayMode(String mode);

  /// No description provided for @pickSlotTryMode.
  ///
  /// In en, this message translates to:
  /// **'Try {mode}'**
  String pickSlotTryMode(String mode);

  /// No description provided for @pickSlotNoneTitle.
  ///
  /// In en, this message translates to:
  /// **'No times available'**
  String get pickSlotNoneTitle;

  /// No description provided for @pickSlotNoneBody.
  ///
  /// In en, this message translates to:
  /// **'This counselor has no free times in the next 30 days. Please check again later.'**
  String get pickSlotNoneBody;

  /// No description provided for @pickSlotGoBack.
  ///
  /// In en, this message translates to:
  /// **'Back to counselor'**
  String get pickSlotGoBack;

  /// No description provided for @pickSlotLoadMore.
  ///
  /// In en, this message translates to:
  /// **'Show later dates'**
  String get pickSlotLoadMore;

  /// No description provided for @pickSlotLoadMoreFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not load later dates.'**
  String get pickSlotLoadMoreFailed;

  /// No description provided for @pickSlotRefreshFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not refresh. Showing the last times we loaded.'**
  String get pickSlotRefreshFailed;

  /// No description provided for @pickSlotSelectionLost.
  ///
  /// In en, this message translates to:
  /// **'Your selected time is no longer available.'**
  String get pickSlotSelectionLost;

  /// No description provided for @pickSlotTaken.
  ///
  /// In en, this message translates to:
  /// **'That time was just taken. Please choose another.'**
  String get pickSlotTaken;

  /// No description provided for @pickSlotUnavailableTitle.
  ///
  /// In en, this message translates to:
  /// **'Counselor not available'**
  String get pickSlotUnavailableTitle;

  /// No description provided for @pickSlotUnavailableBody.
  ///
  /// In en, this message translates to:
  /// **'This counselor cannot be booked right now.'**
  String get pickSlotUnavailableBody;

  /// No description provided for @pickSlotVerifyTitle.
  ///
  /// In en, this message translates to:
  /// **'Verify your email first'**
  String get pickSlotVerifyTitle;

  /// No description provided for @pickSlotVerifyBody.
  ///
  /// In en, this message translates to:
  /// **'Please confirm your email address before booking a session.'**
  String get pickSlotVerifyBody;

  /// No description provided for @pickSlotVerifyButton.
  ///
  /// In en, this message translates to:
  /// **'Verify my email'**
  String get pickSlotVerifyButton;

  /// No description provided for @pickSlotWaitSeconds.
  ///
  /// In en, this message translates to:
  /// **'Please wait {seconds} seconds before trying again.'**
  String pickSlotWaitSeconds(int seconds);

  /// No description provided for @pickSlotTimeZoneNote.
  ///
  /// In en, this message translates to:
  /// **'Times are in Malaysian time (UTC+8). Your phone is set to a different time zone.'**
  String get pickSlotTimeZoneNote;

  /// No description provided for @pickSlotRefreshing.
  ///
  /// In en, this message translates to:
  /// **'Updating times…'**
  String get pickSlotRefreshing;

  /// No description provided for @confirmBookingTitle.
  ///
  /// In en, this message translates to:
  /// **'Confirm booking'**
  String get confirmBookingTitle;

  /// No description provided for @confirmBannerChecking.
  ///
  /// In en, this message translates to:
  /// **'Checking availability…'**
  String get confirmBannerChecking;

  /// No description provided for @confirmBannerOffline.
  ///
  /// In en, this message translates to:
  /// **'No internet connection'**
  String get confirmBannerOffline;

  /// No description provided for @confirmBannerChanged.
  ///
  /// In en, this message translates to:
  /// **'Details changed. Please review and confirm again.'**
  String get confirmBannerChanged;

  /// No description provided for @confirmCheckAgain.
  ///
  /// In en, this message translates to:
  /// **'Check again'**
  String get confirmCheckAgain;

  /// No description provided for @confirmVerifiedBadge.
  ///
  /// In en, this message translates to:
  /// **'Verified'**
  String get confirmVerifiedBadge;

  /// No description provided for @confirmRowDate.
  ///
  /// In en, this message translates to:
  /// **'Date'**
  String get confirmRowDate;

  /// No description provided for @confirmRowTime.
  ///
  /// In en, this message translates to:
  /// **'Time'**
  String get confirmRowTime;

  /// No description provided for @confirmRowDuration.
  ///
  /// In en, this message translates to:
  /// **'Duration'**
  String get confirmRowDuration;

  /// No description provided for @confirmRowType.
  ///
  /// In en, this message translates to:
  /// **'Type'**
  String get confirmRowType;

  /// No description provided for @confirmRowLabelled.
  ///
  /// In en, this message translates to:
  /// **'{label}: {value}'**
  String confirmRowLabelled(String label, String value);

  /// No description provided for @confirmTimeValue.
  ///
  /// In en, this message translates to:
  /// **'{start} – {end}, Malaysia time'**
  String confirmTimeValue(String start, String end);

  /// No description provided for @confirmTimeSpoken.
  ///
  /// In en, this message translates to:
  /// **'{start} to {end}, Malaysia time'**
  String confirmTimeSpoken(String start, String end);

  /// No description provided for @confirmDuration.
  ///
  /// In en, this message translates to:
  /// **'{minutes, plural, =1{1 minute} other{{minutes} minutes}}'**
  String confirmDuration(int minutes);

  /// No description provided for @confirmChooseOtherTime.
  ///
  /// In en, this message translates to:
  /// **'Choose a different time'**
  String get confirmChooseOtherTime;

  /// No description provided for @confirmBeforeTitle.
  ///
  /// In en, this message translates to:
  /// **'Before you confirm'**
  String get confirmBeforeTitle;

  /// No description provided for @confirmPolicyCancel.
  ///
  /// In en, this message translates to:
  /// **'You can cancel up to {hours, plural, =1{1 hour} other{{hours} hours}} before the session.'**
  String confirmPolicyCancel(int hours);

  /// No description provided for @confirmPolicyNoCancel.
  ///
  /// In en, this message translates to:
  /// **'This session starts in under {hours, plural, =1{1 hour} other{{hours} hours}}, so it can\'t be cancelled after booking.'**
  String confirmPolicyNoCancel(int hours);

  /// No description provided for @confirmPolicyReschedule.
  ///
  /// In en, this message translates to:
  /// **'You can reschedule up to {count, plural, =1{1 time} other{{count} times}}.'**
  String confirmPolicyReschedule(int count);

  /// No description provided for @confirmPolicyNoReschedule.
  ///
  /// In en, this message translates to:
  /// **'This session can\'t be rescheduled.'**
  String get confirmPolicyNoReschedule;

  /// No description provided for @confirmPolicyLinkOnline.
  ///
  /// In en, this message translates to:
  /// **'Your meeting link will appear in your booking {minutes, plural, =1{1 minute} other{{minutes} minutes}} before the session.'**
  String confirmPolicyLinkOnline(int minutes);

  /// No description provided for @confirmPolicyAddressInPerson.
  ///
  /// In en, this message translates to:
  /// **'The full address is shown in your booking after you confirm.'**
  String get confirmPolicyAddressInPerson;

  /// No description provided for @confirmWarning.
  ///
  /// In en, this message translates to:
  /// **'Important'**
  String get confirmWarning;

  /// No description provided for @confirmUpcomingCount.
  ///
  /// In en, this message translates to:
  /// **'You have {count} of {max} upcoming sessions.'**
  String confirmUpcomingCount(int count, int max);

  /// No description provided for @confirmAcknowledge.
  ///
  /// In en, this message translates to:
  /// **'I understand this session will not be recorded, per platform policy.'**
  String get confirmAcknowledge;

  /// No description provided for @confirmButton.
  ///
  /// In en, this message translates to:
  /// **'Confirm booking'**
  String get confirmButton;

  /// No description provided for @confirmButtonBusy.
  ///
  /// In en, this message translates to:
  /// **'Confirming…'**
  String get confirmButtonBusy;

  /// No description provided for @confirmWhyChecking.
  ///
  /// In en, this message translates to:
  /// **'Checking availability'**
  String get confirmWhyChecking;

  /// No description provided for @confirmWhyOffline.
  ///
  /// In en, this message translates to:
  /// **'No internet connection'**
  String get confirmWhyOffline;

  /// No description provided for @confirmWhyUnavailable.
  ///
  /// In en, this message translates to:
  /// **'This session can\'t be booked right now'**
  String get confirmWhyUnavailable;

  /// No description provided for @confirmWhyTick.
  ///
  /// In en, this message translates to:
  /// **'Tick the box to continue'**
  String get confirmWhyTick;

  /// No description provided for @confirmBackBusy.
  ///
  /// In en, this message translates to:
  /// **'Please wait while we confirm your booking'**
  String get confirmBackBusy;

  /// No description provided for @confirmErrTimeConflict.
  ///
  /// In en, this message translates to:
  /// **'You already have a session at this time.'**
  String get confirmErrTimeConflict;

  /// No description provided for @confirmErrLimit.
  ///
  /// In en, this message translates to:
  /// **'You can have up to {max} upcoming sessions. Cancel or complete one to book another.'**
  String confirmErrLimit(int max);

  /// No description provided for @confirmErrVerify.
  ///
  /// In en, this message translates to:
  /// **'Verify your email to book a session.'**
  String get confirmErrVerify;

  /// No description provided for @confirmErrCounselor.
  ///
  /// In en, this message translates to:
  /// **'This counselor is no longer available for booking.'**
  String get confirmErrCounselor;

  /// No description provided for @confirmFindAnother.
  ///
  /// In en, this message translates to:
  /// **'Find another counselor'**
  String get confirmFindAnother;

  /// No description provided for @confirmErrWait.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Please wait {seconds} seconds and try again.'**
  String confirmErrWait(int seconds);

  /// No description provided for @confirmErrWaitGeneric.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Please wait a moment and try again.'**
  String get confirmErrWaitGeneric;

  /// No description provided for @confirmErrServer.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong on our side. Your booking may or may not have been made. Try again: you will not be booked twice.'**
  String get confirmErrServer;

  /// No description provided for @confirmErrUnknown.
  ///
  /// In en, this message translates to:
  /// **'We couldn\'t confirm your booking. Check My Bookings before trying again.'**
  String get confirmErrUnknown;

  /// No description provided for @confirmGoHome.
  ///
  /// In en, this message translates to:
  /// **'Go to Home'**
  String get confirmGoHome;

  /// No description provided for @navBookings.
  ///
  /// In en, this message translates to:
  /// **'Bookings'**
  String get navBookings;

  /// No description provided for @bookingsTitle.
  ///
  /// In en, this message translates to:
  /// **'Bookings'**
  String get bookingsTitle;

  /// No description provided for @bookingsTabUpcoming.
  ///
  /// In en, this message translates to:
  /// **'Upcoming'**
  String get bookingsTabUpcoming;

  /// No description provided for @bookingsTabPast.
  ///
  /// In en, this message translates to:
  /// **'Past'**
  String get bookingsTabPast;

  /// No description provided for @bookingsEmptyUpcomingTitle.
  ///
  /// In en, this message translates to:
  /// **'No upcoming sessions'**
  String get bookingsEmptyUpcomingTitle;

  /// No description provided for @bookingsEmptyUpcomingBody.
  ///
  /// In en, this message translates to:
  /// **'When you book a session, it will appear here.'**
  String get bookingsEmptyUpcomingBody;

  /// No description provided for @bookingsEmptyPastTitle.
  ///
  /// In en, this message translates to:
  /// **'No past sessions yet'**
  String get bookingsEmptyPastTitle;

  /// No description provided for @bookingsEmptyPastBody.
  ///
  /// In en, this message translates to:
  /// **'Your finished and cancelled sessions will appear here.'**
  String get bookingsEmptyPastBody;

  /// No description provided for @bookingsLoadMoreFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not load more bookings.'**
  String get bookingsLoadMoreFailed;

  /// No description provided for @bookingStatusConfirmed.
  ///
  /// In en, this message translates to:
  /// **'Confirmed'**
  String get bookingStatusConfirmed;

  /// No description provided for @bookingStatusCompleted.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get bookingStatusCompleted;

  /// No description provided for @bookingStatusCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get bookingStatusCancelled;

  /// No description provided for @bookingStatusNoShow.
  ///
  /// In en, this message translates to:
  /// **'No-show'**
  String get bookingStatusNoShow;

  /// No description provided for @bookingStatusUnknown.
  ///
  /// In en, this message translates to:
  /// **'Updated'**
  String get bookingStatusUnknown;

  /// No description provided for @bookingStartingSoon.
  ///
  /// In en, this message translates to:
  /// **'Starting soon'**
  String get bookingStartingSoon;

  /// No description provided for @bookAgain.
  ///
  /// In en, this message translates to:
  /// **'Book again'**
  String get bookAgain;

  /// No description provided for @bookingItemLabel.
  ///
  /// In en, this message translates to:
  /// **'{when}, {counselor}, {type}, {status}'**
  String bookingItemLabel(
    String when,
    String counselor,
    String type,
    String status,
  );

  /// No description provided for @bookingDetailTitle.
  ///
  /// In en, this message translates to:
  /// **'Booking details'**
  String get bookingDetailTitle;

  /// No description provided for @bookingNotFound.
  ///
  /// In en, this message translates to:
  /// **'We couldn\'t find this booking.'**
  String get bookingNotFound;

  /// No description provided for @bookingReference.
  ///
  /// In en, this message translates to:
  /// **'Reference'**
  String get bookingReference;

  /// No description provided for @bookingSessionDetails.
  ///
  /// In en, this message translates to:
  /// **'Session details'**
  String get bookingSessionDetails;

  /// No description provided for @bookingMeetingOpensLater.
  ///
  /// In en, this message translates to:
  /// **'Your meeting link will appear at {time}.'**
  String bookingMeetingOpensLater(String time);

  /// No description provided for @bookingMeetingAvailable.
  ///
  /// In en, this message translates to:
  /// **'Your session is open. Join when you are ready.'**
  String get bookingMeetingAvailable;

  /// No description provided for @bookingMeetingNotAdded.
  ///
  /// In en, this message translates to:
  /// **'Your counselor has not added the meeting link yet. Please check again shortly.'**
  String get bookingMeetingNotAdded;

  /// No description provided for @bookingMeetingEnded.
  ///
  /// In en, this message translates to:
  /// **'This session has ended.'**
  String get bookingMeetingEnded;

  /// No description provided for @bookingJoinSession.
  ///
  /// In en, this message translates to:
  /// **'Join session'**
  String get bookingJoinSession;

  /// No description provided for @bookingLinkFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not open the link.'**
  String get bookingLinkFailed;

  /// No description provided for @bookingAddress.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get bookingAddress;

  /// No description provided for @bookingGetDirections.
  ///
  /// In en, this message translates to:
  /// **'Get directions'**
  String get bookingGetDirections;

  /// No description provided for @bookingCancelledNote.
  ///
  /// In en, this message translates to:
  /// **'This booking was cancelled.'**
  String get bookingCancelledNote;

  /// No description provided for @bookingCanCancelUntil.
  ///
  /// In en, this message translates to:
  /// **'You can cancel until {when}.'**
  String bookingCanCancelUntil(String when);

  /// No description provided for @bookingCannotCancel.
  ///
  /// In en, this message translates to:
  /// **'This booking can no longer be cancelled.'**
  String get bookingCannotCancel;

  /// No description provided for @bookingCancelButton.
  ///
  /// In en, this message translates to:
  /// **'Cancel booking'**
  String get bookingCancelButton;

  /// No description provided for @bookingCancelSheetTitle.
  ///
  /// In en, this message translates to:
  /// **'Cancel this booking?'**
  String get bookingCancelSheetTitle;

  /// No description provided for @bookingCancelSheetBody.
  ///
  /// In en, this message translates to:
  /// **'Your session with {name} on {when} will be cancelled and the time will be released.'**
  String bookingCancelSheetBody(String name, String when);

  /// No description provided for @bookingKeep.
  ///
  /// In en, this message translates to:
  /// **'Keep booking'**
  String get bookingKeep;

  /// No description provided for @bookingCancelling.
  ///
  /// In en, this message translates to:
  /// **'Cancelling…'**
  String get bookingCancelling;

  /// No description provided for @bookingCancelClosedTitle.
  ///
  /// In en, this message translates to:
  /// **'This booking can\'t be cancelled'**
  String get bookingCancelClosedTitle;

  /// No description provided for @bookingCancelOk.
  ///
  /// In en, this message translates to:
  /// **'OK'**
  String get bookingCancelOk;

  /// No description provided for @bookingCancelledSnack.
  ///
  /// In en, this message translates to:
  /// **'Booking cancelled.'**
  String get bookingCancelledSnack;

  /// No description provided for @bookingCancelNotCancellable.
  ///
  /// In en, this message translates to:
  /// **'This booking can\'t be cancelled any more.'**
  String get bookingCancelNotCancellable;

  /// No description provided for @bookingCancelServer.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong on our side. Your booking may or may not have been cancelled. Try again: it is safe to repeat.'**
  String get bookingCancelServer;

  /// No description provided for @bookingRescheduleButton.
  ///
  /// In en, this message translates to:
  /// **'Reschedule'**
  String get bookingRescheduleButton;

  /// No description provided for @bookingRescheduleLeft.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{You can reschedule this booking 1 more time.} other{You can reschedule this booking {count} more times.}}'**
  String bookingRescheduleLeft(int count);

  /// No description provided for @bookingRescheduleUnavailable.
  ///
  /// In en, this message translates to:
  /// **'This booking can\'t be rescheduled.'**
  String get bookingRescheduleUnavailable;

  /// No description provided for @bookingRescheduleLimit.
  ///
  /// In en, this message translates to:
  /// **'This booking has already been rescheduled the most times it can be.'**
  String get bookingRescheduleLimit;

  /// No description provided for @rescheduleTitle.
  ///
  /// In en, this message translates to:
  /// **'Choose a new time'**
  String get rescheduleTitle;

  /// No description provided for @rescheduleSheetTitle.
  ///
  /// In en, this message translates to:
  /// **'Move to this time?'**
  String get rescheduleSheetTitle;

  /// No description provided for @rescheduleSheetBody.
  ///
  /// In en, this message translates to:
  /// **'Your session with {name} will move from {from} to {to}. The time you have now will be released.'**
  String rescheduleSheetBody(String name, String from, String to);

  /// No description provided for @rescheduleMove.
  ///
  /// In en, this message translates to:
  /// **'Move booking'**
  String get rescheduleMove;

  /// No description provided for @rescheduleMoving.
  ///
  /// In en, this message translates to:
  /// **'Moving…'**
  String get rescheduleMoving;

  /// No description provided for @rescheduleKeepCurrent.
  ///
  /// In en, this message translates to:
  /// **'Keep current time'**
  String get rescheduleKeepCurrent;

  /// No description provided for @rescheduleChooseAnother.
  ///
  /// In en, this message translates to:
  /// **'Choose another time'**
  String get rescheduleChooseAnother;

  /// No description provided for @rescheduleEndedTitle.
  ///
  /// In en, this message translates to:
  /// **'This booking can\'t be rescheduled'**
  String get rescheduleEndedTitle;

  /// No description provided for @rescheduleSlotTaken.
  ///
  /// In en, this message translates to:
  /// **'That time was just taken. Please choose another.'**
  String get rescheduleSlotTaken;

  /// No description provided for @rescheduleSlotChanged.
  ///
  /// In en, this message translates to:
  /// **'This time changed while you were looking. Please choose again.'**
  String get rescheduleSlotChanged;

  /// No description provided for @rescheduleClosed.
  ///
  /// In en, this message translates to:
  /// **'This session starts in under {hours, plural, =1{1 hour} other{{hours} hours}}, so it can\'t be rescheduled.'**
  String rescheduleClosed(int hours);

  /// No description provided for @rescheduleNotPossible.
  ///
  /// In en, this message translates to:
  /// **'This booking can\'t be rescheduled any more.'**
  String get rescheduleNotPossible;

  /// No description provided for @rescheduleGone.
  ///
  /// In en, this message translates to:
  /// **'This booking, or this time, is no longer available.'**
  String get rescheduleGone;

  /// No description provided for @rescheduleServer.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong on our side. Your booking may or may not have been moved. Try again: it is safe to repeat.'**
  String get rescheduleServer;

  /// No description provided for @rescheduledSnack.
  ///
  /// In en, this message translates to:
  /// **'Booking rescheduled.'**
  String get rescheduledSnack;

  /// No description provided for @counselorAskTime.
  ///
  /// In en, this message translates to:
  /// **'Ask for a different time'**
  String get counselorAskTime;

  /// No description provided for @counselorAskTimeHint.
  ///
  /// In en, this message translates to:
  /// **'Not what you need? Ask {name} for a time inside their working hours.'**
  String counselorAskTimeHint(String name);

  /// No description provided for @bookingsTabRequests.
  ///
  /// In en, this message translates to:
  /// **'Requests'**
  String get bookingsTabRequests;

  /// No description provided for @requestsEmptyTitle.
  ///
  /// In en, this message translates to:
  /// **'No requests yet'**
  String get requestsEmptyTitle;

  /// No description provided for @requestsEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'When you ask a counselor for a time, it shows here until they answer.'**
  String get requestsEmptyBody;

  /// No description provided for @requestTitle.
  ///
  /// In en, this message translates to:
  /// **'Ask for a time'**
  String get requestTitle;

  /// No description provided for @requestIntro.
  ///
  /// In en, this message translates to:
  /// **'Choose a time inside {name}\'s working hours. {name} has {hours, plural, =1{1 hour} other{{hours} hours}} to answer, and nothing is booked until they approve. If they do not answer, the request closes by itself.'**
  String requestIntro(String name, int hours);

  /// No description provided for @requestNoHours.
  ///
  /// In en, this message translates to:
  /// **'This counselor has not set working hours yet, so you can\'t ask for a time. Try their available times instead.'**
  String get requestNoHours;

  /// No description provided for @requestNoTimes.
  ///
  /// In en, this message translates to:
  /// **'There is no time to ask for in the coming weeks.'**
  String get requestNoTimes;

  /// No description provided for @requestNoteLabel.
  ///
  /// In en, this message translates to:
  /// **'Note for the counselor (optional)'**
  String get requestNoteLabel;

  /// No description provided for @requestNoteHint.
  ///
  /// In en, this message translates to:
  /// **'What would you like to talk about?'**
  String get requestNoteHint;

  /// No description provided for @requestPickFirst.
  ///
  /// In en, this message translates to:
  /// **'Pick a time to ask for'**
  String get requestPickFirst;

  /// No description provided for @requestSendAt.
  ///
  /// In en, this message translates to:
  /// **'Ask for {when}'**
  String requestSendAt(String when);

  /// No description provided for @requestSending.
  ///
  /// In en, this message translates to:
  /// **'Sending…'**
  String get requestSending;

  /// No description provided for @requestErrTaken.
  ///
  /// In en, this message translates to:
  /// **'That time isn\'t available. Please choose another.'**
  String get requestErrTaken;

  /// No description provided for @requestErrInvalid.
  ///
  /// In en, this message translates to:
  /// **'That time is outside the counselor\'s working hours. Please choose another.'**
  String get requestErrInvalid;

  /// No description provided for @requestErrLimit.
  ///
  /// In en, this message translates to:
  /// **'You can have up to {max} requests waiting for an answer. Wait for an answer or withdraw one.'**
  String requestErrLimit(int max);

  /// No description provided for @requestErrClosed.
  ///
  /// In en, this message translates to:
  /// **'This counselor isn\'t taking time requests right now.'**
  String get requestErrClosed;

  /// No description provided for @requestErrServer.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong on our side. Your request may or may not have been sent. Try again: it will not be sent twice.'**
  String get requestErrServer;

  /// No description provided for @requestStatusPending.
  ///
  /// In en, this message translates to:
  /// **'Waiting for an answer'**
  String get requestStatusPending;

  /// No description provided for @requestStatusApproved.
  ///
  /// In en, this message translates to:
  /// **'Approved'**
  String get requestStatusApproved;

  /// No description provided for @requestStatusDeclined.
  ///
  /// In en, this message translates to:
  /// **'Declined'**
  String get requestStatusDeclined;

  /// No description provided for @requestStatusExpired.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get requestStatusExpired;

  /// No description provided for @requestStatusWithdrawn.
  ///
  /// In en, this message translates to:
  /// **'Withdrawn'**
  String get requestStatusWithdrawn;

  /// No description provided for @requestStatusUnknown.
  ///
  /// In en, this message translates to:
  /// **'Unknown'**
  String get requestStatusUnknown;

  /// No description provided for @requestDetailTitle.
  ///
  /// In en, this message translates to:
  /// **'Request'**
  String get requestDetailTitle;

  /// No description provided for @requestWaitUntil.
  ///
  /// In en, this message translates to:
  /// **'{name} can answer until {when}.'**
  String requestWaitUntil(String name, String when);

  /// No description provided for @requestApprovedNote.
  ///
  /// In en, this message translates to:
  /// **'Approved. Your session is booked.'**
  String get requestApprovedNote;

  /// No description provided for @requestDeclinedNote.
  ///
  /// In en, this message translates to:
  /// **'The counselor couldn\'t take this request.'**
  String get requestDeclinedNote;

  /// No description provided for @requestExpiredNote.
  ///
  /// In en, this message translates to:
  /// **'Nobody answered in time, so this request closed.'**
  String get requestExpiredNote;

  /// No description provided for @requestWithdrawnNote.
  ///
  /// In en, this message translates to:
  /// **'You withdrew this request.'**
  String get requestWithdrawnNote;

  /// No description provided for @requestYourNote.
  ///
  /// In en, this message translates to:
  /// **'Your note'**
  String get requestYourNote;

  /// No description provided for @requestTheirMessage.
  ///
  /// In en, this message translates to:
  /// **'Their message'**
  String get requestTheirMessage;

  /// No description provided for @requestViewBooking.
  ///
  /// In en, this message translates to:
  /// **'View booking'**
  String get requestViewBooking;

  /// No description provided for @requestChooseAnother.
  ///
  /// In en, this message translates to:
  /// **'Choose another time'**
  String get requestChooseAnother;

  /// No description provided for @requestWithdraw.
  ///
  /// In en, this message translates to:
  /// **'Withdraw request'**
  String get requestWithdraw;

  /// No description provided for @requestWithdrawTitle.
  ///
  /// In en, this message translates to:
  /// **'Withdraw this request?'**
  String get requestWithdrawTitle;

  /// No description provided for @requestWithdrawBody.
  ///
  /// In en, this message translates to:
  /// **'{name} will no longer be asked for this time. You can ask for another time.'**
  String requestWithdrawBody(String name);

  /// No description provided for @requestKeep.
  ///
  /// In en, this message translates to:
  /// **'Keep request'**
  String get requestKeep;

  /// No description provided for @requestWithdrawing.
  ///
  /// In en, this message translates to:
  /// **'Withdrawing…'**
  String get requestWithdrawing;

  /// No description provided for @requestWithdrawnSnack.
  ///
  /// In en, this message translates to:
  /// **'Request withdrawn.'**
  String get requestWithdrawnSnack;

  /// No description provided for @requestNotPending.
  ///
  /// In en, this message translates to:
  /// **'This request was already answered or has closed.'**
  String get requestNotPending;

  /// No description provided for @requestWithdrawServer.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong on our side. Your request may or may not have been withdrawn. Try again: it is safe to repeat.'**
  String get requestWithdrawServer;

  /// No description provided for @requestNotFound.
  ///
  /// In en, this message translates to:
  /// **'We couldn\'t find this request.'**
  String get requestNotFound;

  /// No description provided for @bookingConfirmedTitle.
  ///
  /// In en, this message translates to:
  /// **'Booking confirmed'**
  String get bookingConfirmedTitle;

  /// No description provided for @bookingConfirmedSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Your session is booked.'**
  String get bookingConfirmedSubtitle;

  /// No description provided for @bookingConfirmedWith.
  ///
  /// In en, this message translates to:
  /// **'Your session with {name}'**
  String bookingConfirmedWith(String name);

  /// No description provided for @bookingViewBooking.
  ///
  /// In en, this message translates to:
  /// **'View booking'**
  String get bookingViewBooking;

  /// No description provided for @bookingBackHome.
  ///
  /// In en, this message translates to:
  /// **'Back to home'**
  String get bookingBackHome;

  /// No description provided for @accountTitle.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get accountTitle;

  /// No description provided for @homeFindTitle.
  ///
  /// In en, this message translates to:
  /// **'Find a counselor'**
  String get homeFindTitle;

  /// No description provided for @homeFindBody.
  ///
  /// In en, this message translates to:
  /// **'Browse verified counselors and see when they are free.'**
  String get homeFindBody;

  /// No description provided for @accountProfile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get accountProfile;

  /// No description provided for @accountPassword.
  ///
  /// In en, this message translates to:
  /// **'Change password'**
  String get accountPassword;

  /// No description provided for @accountDevices.
  ///
  /// In en, this message translates to:
  /// **'Signed-in devices'**
  String get accountDevices;

  /// No description provided for @profileTitle.
  ///
  /// In en, this message translates to:
  /// **'Your profile'**
  String get profileTitle;

  /// No description provided for @profileSave.
  ///
  /// In en, this message translates to:
  /// **'Save changes'**
  String get profileSave;

  /// No description provided for @profileSaved.
  ///
  /// In en, this message translates to:
  /// **'Profile saved.'**
  String get profileSaved;

  /// No description provided for @profileEmailNote.
  ///
  /// In en, this message translates to:
  /// **'To change your email address, use the MyKaunsel website.'**
  String get profileEmailNote;

  /// No description provided for @timezoneLabel.
  ///
  /// In en, this message translates to:
  /// **'Time zone'**
  String get timezoneLabel;

  /// No description provided for @passwordTitle.
  ///
  /// In en, this message translates to:
  /// **'Change password'**
  String get passwordTitle;

  /// No description provided for @passwordNote.
  ///
  /// In en, this message translates to:
  /// **'Changing your password signs you out on all your other devices.'**
  String get passwordNote;

  /// No description provided for @currentPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'Current password'**
  String get currentPasswordLabel;

  /// No description provided for @currentPasswordRequired.
  ///
  /// In en, this message translates to:
  /// **'Enter your current password.'**
  String get currentPasswordRequired;

  /// No description provided for @newPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'New password'**
  String get newPasswordLabel;

  /// No description provided for @confirmNewPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'Confirm new password'**
  String get confirmNewPasswordLabel;

  /// No description provided for @changePasswordButton.
  ///
  /// In en, this message translates to:
  /// **'Change password'**
  String get changePasswordButton;

  /// No description provided for @passwordChanged.
  ///
  /// In en, this message translates to:
  /// **'Password updated. Your other devices were signed out.'**
  String get passwordChanged;

  /// No description provided for @devicesTitle.
  ///
  /// In en, this message translates to:
  /// **'Signed-in devices'**
  String get devicesTitle;

  /// No description provided for @devicesNote.
  ///
  /// In en, this message translates to:
  /// **'These are the phones signed in to your account. Sign out any you do not recognise or no longer use.'**
  String get devicesNote;

  /// No description provided for @thisPhone.
  ///
  /// In en, this message translates to:
  /// **'This phone'**
  String get thisPhone;

  /// No description provided for @deviceSignOut.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get deviceSignOut;

  /// No description provided for @deviceSignOutTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign out {name}?'**
  String deviceSignOutTitle(String name);

  /// No description provided for @deviceSignOutBody.
  ///
  /// In en, this message translates to:
  /// **'Enter your password to confirm. That phone will have to sign in again.'**
  String get deviceSignOutBody;

  /// No description provided for @deviceSignedOut.
  ///
  /// In en, this message translates to:
  /// **'That device was signed out.'**
  String get deviceSignedOut;

  /// No description provided for @lastUsedNow.
  ///
  /// In en, this message translates to:
  /// **'Active just now'**
  String get lastUsedNow;

  /// No description provided for @lastUsedMinutes.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{Last used 1 minute ago} other{Last used {count} minutes ago}}'**
  String lastUsedMinutes(int count);

  /// No description provided for @lastUsedHours.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{Last used 1 hour ago} other{Last used {count} hours ago}}'**
  String lastUsedHours(int count);

  /// No description provided for @lastUsedDays.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{Last used yesterday} other{Last used {count} days ago}}'**
  String lastUsedDays(int count);

  /// No description provided for @lastUsedOn.
  ///
  /// In en, this message translates to:
  /// **'Last used {date}'**
  String lastUsedOn(String date);

  /// No description provided for @neverUsed.
  ///
  /// In en, this message translates to:
  /// **'Not used yet'**
  String get neverUsed;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
