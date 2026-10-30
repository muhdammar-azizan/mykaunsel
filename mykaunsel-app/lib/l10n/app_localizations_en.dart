// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'MyKaunsel';

  @override
  String get startingUp => 'Starting MyKaunsel…';

  @override
  String get tryAgain => 'Try again';

  @override
  String get signOut => 'Sign out';

  @override
  String get maintenanceTitle => 'Down for maintenance';

  @override
  String get updateRequiredTitle => 'Please update the app';

  @override
  String get updateRequiredBody =>
      'This version of MyKaunsel is out of date. Please update the app to continue.';

  @override
  String get updateNow => 'Update now';

  @override
  String get noConnectionTitle => 'No connection';

  @override
  String get noConnectionBody =>
      'We could not reach MyKaunsel. Check your internet connection and try again.';

  @override
  String get somethingWentWrongTitle => 'Something went wrong';

  @override
  String get somethingWentWrongBody => 'Please try again in a moment.';

  @override
  String get tooManyRequests =>
      'Too many attempts. Please try again in a moment.';

  @override
  String tooManyRequestsIn(int seconds) {
    return 'Too many attempts. Please try again in $seconds seconds.';
  }

  @override
  String get sessionExpired => 'Your session has ended. Please sign in again.';

  @override
  String signedInAs(String name) {
    return 'Signed in as $name';
  }

  @override
  String get signInTitle => 'Welcome back';

  @override
  String get signInSubtitle => 'Sign in to your MyKaunsel account.';

  @override
  String get emailLabel => 'Email';

  @override
  String get passwordLabel => 'Password';

  @override
  String get emailRequired => 'Enter your email address.';

  @override
  String get passwordRequired => 'Enter your password.';

  @override
  String get signInButton => 'Sign in';

  @override
  String get showPassword => 'Show password';

  @override
  String get hidePassword => 'Hide password';

  @override
  String get twoFactorTitle => 'Two-step verification';

  @override
  String get twoFactorBody =>
      'Enter the 6-digit code from your authenticator app.';

  @override
  String get twoFactorRecoveryBody =>
      'Enter one of your recovery codes. Each code works only once.';

  @override
  String get codeLabel => '6-digit code';

  @override
  String get codeRequired => 'Enter the 6-digit code.';

  @override
  String get recoveryCodeLabel => 'Recovery code';

  @override
  String get recoveryCodeRequired => 'Enter a recovery code.';

  @override
  String get useRecoveryCode => 'Use a recovery code instead';

  @override
  String get useAuthenticatorCode => 'Use the code from my authenticator app';

  @override
  String get verifyButton => 'Verify';

  @override
  String get signInExpired =>
      'Your sign-in took too long. Please sign in again.';

  @override
  String get verifyEmailTitle => 'Confirm your email';

  @override
  String verifyEmailBody(String email) {
    return 'We sent a link to $email. Open it to confirm your address, then come back here and sign in.';
  }

  @override
  String get resendEmail => 'Send the email again';

  @override
  String resendEmailIn(int seconds) {
    return 'Send again in ${seconds}s';
  }

  @override
  String get emailResent =>
      'If that address still needs confirming, we have sent the email again.';

  @override
  String get backToSignIn => 'Back to sign in';

  @override
  String get noAccountYet => 'New to MyKaunsel?';

  @override
  String get createAccountLink => 'Create an account';

  @override
  String get forgotPasswordLink => 'Forgot your password?';

  @override
  String get alreadyHaveAccount => 'Already have an account?';

  @override
  String get signInLink => 'Sign in';

  @override
  String get signUpTitle => 'Create your account';

  @override
  String get signUpSubtitle => 'A few details and you are ready to go.';

  @override
  String get nameLabel => 'Full name';

  @override
  String get nameRequired => 'Enter your name.';

  @override
  String get emailInvalid => 'Enter a valid email address.';

  @override
  String get confirmPasswordLabel => 'Confirm password';

  @override
  String get confirmPasswordRequired => 'Type the password again.';

  @override
  String get passwordsDontMatch => 'The two passwords are not the same.';

  @override
  String get createAccountButton => 'Create account';

  @override
  String get passwordMustHave => 'Your password needs:';

  @override
  String get ruleLength => 'At least 8 characters';

  @override
  String get ruleUpper => 'An upper-case letter';

  @override
  String get ruleLower => 'A lower-case letter';

  @override
  String get ruleNumber => 'A number';

  @override
  String get ruleSymbol => 'A symbol, such as ! or #';

  @override
  String ruleMet(String rule) {
    return '$rule, done';
  }

  @override
  String ruleNotMet(String rule) {
    return '$rule, not yet';
  }

  @override
  String get signUpCheckingTitle => 'Checking if sign-up is open…';

  @override
  String get signUpClosedTitle => 'Sign-up is closed for now';

  @override
  String get forgotTitle => 'Forgot your password?';

  @override
  String get forgotSubtitle =>
      'Enter your email and we will send you a link to choose a new one.';

  @override
  String get sendLinkButton => 'Send the link';

  @override
  String get forgotSentTitle => 'Check your email';

  @override
  String forgotSentBody(String email) {
    return 'If $email has an account, we have emailed a link. Open it, choose a new password, then come back here and sign in.';
  }

  @override
  String get useDifferentEmail => 'Use a different email';

  @override
  String homeGreeting(String name) {
    return 'Hello, $name';
  }

  @override
  String get homeEmptyTitle => 'Your space is being set up';

  @override
  String get homeEmptyBody =>
      'Finding a counselor and booking sessions are coming soon. You are signed in and your account is ready.';

  @override
  String get accountMenu => 'Account';

  @override
  String get signOutEverywhere => 'Sign out of all devices';

  @override
  String get signOutEverywhereTitle => 'Sign out of all devices?';

  @override
  String get signOutEverywhereBody =>
      'Every phone signed in to your account, including this one, will be signed out.';

  @override
  String get cancel => 'Cancel';

  @override
  String get notFoundBody =>
      'We could not find that. It may no longer be available.';

  @override
  String get navHome => 'Home';

  @override
  String get navFind => 'Find';

  @override
  String get navAccount => 'Account';

  @override
  String get findTitle => 'Find a counselor';

  @override
  String get searchHint => 'Search by name';

  @override
  String get clearSearch => 'Clear search';

  @override
  String get filtersButton => 'Filters';

  @override
  String get filtersTitle => 'Filters';

  @override
  String get filtersApply => 'Show results';

  @override
  String get filtersClear => 'Clear all';

  @override
  String get filterFocusArea => 'Focus area';

  @override
  String get filterLanguage => 'Language';

  @override
  String get filterSessionType => 'Session type';

  @override
  String get filterState => 'State';

  @override
  String get anyOption => 'Any';

  @override
  String get sessionOnline => 'Online';

  @override
  String get sessionInPerson => 'In-person';

  @override
  String filtersCount(int count) {
    return 'Filters ($count)';
  }

  @override
  String resultsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count counselors',
      one: '1 counselor',
      zero: 'No counselors',
    );
    return '$_temp0';
  }

  @override
  String get emptyFilteredTitle => 'No counselors found';

  @override
  String get emptyFilteredBody => 'Try a different name, or fewer filters.';

  @override
  String get emptyNoneTitle => 'No counselors yet';

  @override
  String get emptyNoneBody =>
      'Counselors will appear here as soon as they are available. Please check back soon.';

  @override
  String get clearFilters => 'Clear filters';

  @override
  String get loadMoreFailed => 'Could not load more counselors.';

  @override
  String get loadingMore => 'Loading more…';

  @override
  String get verifiedLabel => 'Verified registered counselor';

  @override
  String get independentLabel => 'Independent';

  @override
  String moreFocusAreas(int count) {
    return '+$count more';
  }

  @override
  String nextAvailable(String when) {
    return 'Next free time: $when';
  }

  @override
  String get noUpcomingTimes => 'No free times in the coming weeks';

  @override
  String get today => 'Today';

  @override
  String get tomorrow => 'Tomorrow';

  @override
  String get malaysiaTimeNote => 'All times are Malaysian time.';

  @override
  String inPersonIn(String place) {
    return 'In-person · $place';
  }

  @override
  String minutesShort(int minutes) {
    return '$minutes min';
  }

  @override
  String get profileFocusAreas => 'Focus areas';

  @override
  String get profileLanguages => 'Languages';

  @override
  String get profileSessionTypes => 'Session types';

  @override
  String get profileWhere => 'Where';

  @override
  String get profileWorksWith => 'Works with';

  @override
  String get profileAvailability => 'Free times';

  @override
  String get profileNoTimes =>
      'No free times right now. Please check again soon.';

  @override
  String get profileAcceptsRequests => 'Also takes session requests.';

  @override
  String get seeAvailableTimes => 'See available times';

  @override
  String get profileNotFoundTitle => 'Counselor not available';

  @override
  String get pickSlotTitle => 'Pick a time';

  @override
  String get pickSlotModeLabel => 'Session type';

  @override
  String get pickSlotDatesLabel => 'Choose a date';

  @override
  String pickSlotTimesLabel(String date) {
    return 'Times on $date';
  }

  @override
  String pickSlotDateCard(String weekday, String day, String month, int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count times',
      one: '1 time',
      zero: 'no times',
    );
    return '$weekday $day $month, $_temp0';
  }

  @override
  String pickSlotSlotLabel(String time, int duration, String mode) {
    return '$time, $duration minutes, $mode';
  }

  @override
  String get pickSlotBooked => 'Booked';

  @override
  String get pickSlotSelected => 'Selected';

  @override
  String get pickSlotContinue => 'Continue';

  @override
  String pickSlotContinueAt(String when) {
    return 'Continue with $when';
  }

  @override
  String get pickSlotPickFirst => 'Pick a time to continue';

  @override
  String get pickSlotNoTimesDay => 'No times on this day.';

  @override
  String pickSlotNoTimesDayMode(String mode) {
    return 'No $mode times on this day.';
  }

  @override
  String pickSlotTryMode(String mode) {
    return 'Try $mode';
  }

  @override
  String get pickSlotNoneTitle => 'No times available';

  @override
  String get pickSlotNoneBody =>
      'This counselor has no free times in the next 30 days. Please check again later.';

  @override
  String get pickSlotGoBack => 'Back to counselor';

  @override
  String get pickSlotLoadMore => 'Show later dates';

  @override
  String get pickSlotLoadMoreFailed => 'Could not load later dates.';

  @override
  String get pickSlotRefreshFailed =>
      'Could not refresh. Showing the last times we loaded.';

  @override
  String get pickSlotSelectionLost =>
      'Your selected time is no longer available.';

  @override
  String get pickSlotTaken =>
      'That time was just taken. Please choose another.';

  @override
  String get pickSlotUnavailableTitle => 'Counselor not available';

  @override
  String get pickSlotUnavailableBody =>
      'This counselor cannot be booked right now.';

  @override
  String get pickSlotVerifyTitle => 'Verify your email first';

  @override
  String get pickSlotVerifyBody =>
      'Please confirm your email address before booking a session.';

  @override
  String get pickSlotVerifyButton => 'Verify my email';

  @override
  String pickSlotWaitSeconds(int seconds) {
    return 'Please wait $seconds seconds before trying again.';
  }

  @override
  String get pickSlotTimeZoneNote =>
      'Times are in Malaysian time (UTC+8). Your phone is set to a different time zone.';

  @override
  String get pickSlotRefreshing => 'Updating times…';

  @override
  String get confirmBookingTitle => 'Confirm booking';

  @override
  String get confirmBannerChecking => 'Checking availability…';

  @override
  String get confirmBannerOffline => 'No internet connection';

  @override
  String get confirmBannerChanged =>
      'Details changed. Please review and confirm again.';

  @override
  String get confirmCheckAgain => 'Check again';

  @override
  String get confirmVerifiedBadge => 'Verified';

  @override
  String get confirmRowDate => 'Date';

  @override
  String get confirmRowTime => 'Time';

  @override
  String get confirmRowDuration => 'Duration';

  @override
  String get confirmRowType => 'Type';

  @override
  String confirmRowLabelled(String label, String value) {
    return '$label: $value';
  }

  @override
  String confirmTimeValue(String start, String end) {
    return '$start – $end, Malaysia time';
  }

  @override
  String confirmTimeSpoken(String start, String end) {
    return '$start to $end, Malaysia time';
  }

  @override
  String confirmDuration(int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: '$minutes minutes',
      one: '1 minute',
    );
    return '$_temp0';
  }

  @override
  String get confirmChooseOtherTime => 'Choose a different time';

  @override
  String get confirmBeforeTitle => 'Before you confirm';

  @override
  String confirmPolicyCancel(int hours) {
    String _temp0 = intl.Intl.pluralLogic(
      hours,
      locale: localeName,
      other: '$hours hours',
      one: '1 hour',
    );
    return 'You can cancel up to $_temp0 before the session.';
  }

  @override
  String confirmPolicyNoCancel(int hours) {
    String _temp0 = intl.Intl.pluralLogic(
      hours,
      locale: localeName,
      other: '$hours hours',
      one: '1 hour',
    );
    return 'This session starts in under $_temp0, so it can\'t be cancelled after booking.';
  }

  @override
  String confirmPolicyReschedule(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count times',
      one: '1 time',
    );
    return 'You can reschedule up to $_temp0.';
  }

  @override
  String get confirmPolicyNoReschedule => 'This session can\'t be rescheduled.';

  @override
  String confirmPolicyLinkOnline(int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: '$minutes minutes',
      one: '1 minute',
    );
    return 'Your meeting link will appear in your booking $_temp0 before the session.';
  }

  @override
  String get confirmPolicyAddressInPerson =>
      'The full address is shown in your booking after you confirm.';

  @override
  String get confirmWarning => 'Important';

  @override
  String confirmUpcomingCount(int count, int max) {
    return 'You have $count of $max upcoming sessions.';
  }

  @override
  String get confirmAcknowledge =>
      'I understand this session will not be recorded, per platform policy.';

  @override
  String get confirmButton => 'Confirm booking';

  @override
  String get confirmButtonBusy => 'Confirming…';

  @override
  String get confirmWhyChecking => 'Checking availability';

  @override
  String get confirmWhyOffline => 'No internet connection';

  @override
  String get confirmWhyUnavailable => 'This session can\'t be booked right now';

  @override
  String get confirmWhyTick => 'Tick the box to continue';

  @override
  String get confirmBackBusy => 'Please wait while we confirm your booking';

  @override
  String get confirmErrTimeConflict =>
      'You already have a session at this time.';

  @override
  String confirmErrLimit(int max) {
    return 'You can have up to $max upcoming sessions. Cancel or complete one to book another.';
  }

  @override
  String get confirmErrVerify => 'Verify your email to book a session.';

  @override
  String get confirmErrCounselor =>
      'This counselor is no longer available for booking.';

  @override
  String get confirmFindAnother => 'Find another counselor';

  @override
  String confirmErrWait(int seconds) {
    return 'Too many attempts. Please wait $seconds seconds and try again.';
  }

  @override
  String get confirmErrWaitGeneric =>
      'Too many attempts. Please wait a moment and try again.';

  @override
  String get confirmErrServer =>
      'Something went wrong on our side. Your booking may or may not have been made. Try again: you will not be booked twice.';

  @override
  String get confirmErrUnknown =>
      'We couldn\'t confirm your booking. Check My Bookings before trying again.';

  @override
  String get confirmGoHome => 'Go to Home';

  @override
  String get navBookings => 'Bookings';

  @override
  String get bookingsTitle => 'Bookings';

  @override
  String get bookingsTabUpcoming => 'Upcoming';

  @override
  String get bookingsTabPast => 'Past';

  @override
  String get bookingsEmptyUpcomingTitle => 'No upcoming sessions';

  @override
  String get bookingsEmptyUpcomingBody =>
      'When you book a session, it will appear here.';

  @override
  String get bookingsEmptyPastTitle => 'No past sessions yet';

  @override
  String get bookingsEmptyPastBody =>
      'Your finished and cancelled sessions will appear here.';

  @override
  String get bookingsLoadMoreFailed => 'Could not load more bookings.';

  @override
  String get bookingStatusConfirmed => 'Confirmed';

  @override
  String get bookingStatusCompleted => 'Completed';

  @override
  String get bookingStatusCancelled => 'Cancelled';

  @override
  String get bookingStatusNoShow => 'No-show';

  @override
  String get bookingStatusUnknown => 'Updated';

  @override
  String get bookingStartingSoon => 'Starting soon';

  @override
  String get bookAgain => 'Book again';

  @override
  String bookingItemLabel(
    String when,
    String counselor,
    String type,
    String status,
  ) {
    return '$when, $counselor, $type, $status';
  }

  @override
  String get bookingDetailTitle => 'Booking details';

  @override
  String get bookingNotFound => 'We couldn\'t find this booking.';

  @override
  String get bookingReference => 'Reference';

  @override
  String get bookingSessionDetails => 'Session details';

  @override
  String bookingMeetingOpensLater(String time) {
    return 'Your meeting link will appear at $time.';
  }

  @override
  String get bookingMeetingAvailable =>
      'Your session is open. Join when you are ready.';

  @override
  String get bookingMeetingNotAdded =>
      'Your counselor has not added the meeting link yet. Please check again shortly.';

  @override
  String get bookingMeetingEnded => 'This session has ended.';

  @override
  String get bookingJoinSession => 'Join session';

  @override
  String get bookingLinkFailed => 'Could not open the link.';

  @override
  String get bookingAddress => 'Address';

  @override
  String get bookingGetDirections => 'Get directions';

  @override
  String get bookingCancelledNote => 'This booking was cancelled.';

  @override
  String bookingCanCancelUntil(String when) {
    return 'You can cancel until $when.';
  }

  @override
  String get bookingCannotCancel => 'This booking can no longer be cancelled.';

  @override
  String get bookingCancelButton => 'Cancel booking';

  @override
  String get bookingCancelSheetTitle => 'Cancel this booking?';

  @override
  String bookingCancelSheetBody(String name, String when) {
    return 'Your session with $name on $when will be cancelled and the time will be released.';
  }

  @override
  String get bookingKeep => 'Keep booking';

  @override
  String get bookingCancelling => 'Cancelling…';

  @override
  String get bookingCancelClosedTitle => 'This booking can\'t be cancelled';

  @override
  String get bookingCancelOk => 'OK';

  @override
  String get bookingCancelledSnack => 'Booking cancelled.';

  @override
  String get bookingCancelNotCancellable =>
      'This booking can\'t be cancelled any more.';

  @override
  String get bookingCancelServer =>
      'Something went wrong on our side. Your booking may or may not have been cancelled. Try again: it is safe to repeat.';

  @override
  String get bookingRescheduleButton => 'Reschedule';

  @override
  String bookingRescheduleLeft(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'You can reschedule this booking $count more times.',
      one: 'You can reschedule this booking 1 more time.',
    );
    return '$_temp0';
  }

  @override
  String get bookingRescheduleUnavailable =>
      'This booking can\'t be rescheduled.';

  @override
  String get bookingRescheduleLimit =>
      'This booking has already been rescheduled the most times it can be.';

  @override
  String get rescheduleTitle => 'Choose a new time';

  @override
  String get rescheduleSheetTitle => 'Move to this time?';

  @override
  String rescheduleSheetBody(String name, String from, String to) {
    return 'Your session with $name will move from $from to $to. The time you have now will be released.';
  }

  @override
  String get rescheduleMove => 'Move booking';

  @override
  String get rescheduleMoving => 'Moving…';

  @override
  String get rescheduleKeepCurrent => 'Keep current time';

  @override
  String get rescheduleChooseAnother => 'Choose another time';

  @override
  String get rescheduleEndedTitle => 'This booking can\'t be rescheduled';

  @override
  String get rescheduleSlotTaken =>
      'That time was just taken. Please choose another.';

  @override
  String get rescheduleSlotChanged =>
      'This time changed while you were looking. Please choose again.';

  @override
  String rescheduleClosed(int hours) {
    String _temp0 = intl.Intl.pluralLogic(
      hours,
      locale: localeName,
      other: '$hours hours',
      one: '1 hour',
    );
    return 'This session starts in under $_temp0, so it can\'t be rescheduled.';
  }

  @override
  String get rescheduleNotPossible =>
      'This booking can\'t be rescheduled any more.';

  @override
  String get rescheduleGone =>
      'This booking, or this time, is no longer available.';

  @override
  String get rescheduleServer =>
      'Something went wrong on our side. Your booking may or may not have been moved. Try again: it is safe to repeat.';

  @override
  String get rescheduledSnack => 'Booking rescheduled.';

  @override
  String get bookingConfirmedTitle => 'Booking confirmed';

  @override
  String get bookingConfirmedSubtitle => 'Your session is booked.';

  @override
  String bookingConfirmedWith(String name) {
    return 'Your session with $name';
  }

  @override
  String get bookingViewBooking => 'View booking';

  @override
  String get bookingBackHome => 'Back to home';

  @override
  String get accountTitle => 'Account';

  @override
  String get homeFindTitle => 'Find a counselor';

  @override
  String get homeFindBody =>
      'Browse verified counselors and see when they are free.';

  @override
  String get accountProfile => 'Profile';

  @override
  String get accountPassword => 'Change password';

  @override
  String get accountDevices => 'Signed-in devices';

  @override
  String get profileTitle => 'Your profile';

  @override
  String get profileSave => 'Save changes';

  @override
  String get profileSaved => 'Profile saved.';

  @override
  String get profileEmailNote =>
      'To change your email address, use the MyKaunsel website.';

  @override
  String get timezoneLabel => 'Time zone';

  @override
  String get passwordTitle => 'Change password';

  @override
  String get passwordNote =>
      'Changing your password signs you out on all your other devices.';

  @override
  String get currentPasswordLabel => 'Current password';

  @override
  String get currentPasswordRequired => 'Enter your current password.';

  @override
  String get newPasswordLabel => 'New password';

  @override
  String get confirmNewPasswordLabel => 'Confirm new password';

  @override
  String get changePasswordButton => 'Change password';

  @override
  String get passwordChanged =>
      'Password updated. Your other devices were signed out.';

  @override
  String get devicesTitle => 'Signed-in devices';

  @override
  String get devicesNote =>
      'These are the phones signed in to your account. Sign out any you do not recognise or no longer use.';

  @override
  String get thisPhone => 'This phone';

  @override
  String get deviceSignOut => 'Sign out';

  @override
  String deviceSignOutTitle(String name) {
    return 'Sign out $name?';
  }

  @override
  String get deviceSignOutBody =>
      'Enter your password to confirm. That phone will have to sign in again.';

  @override
  String get deviceSignedOut => 'That device was signed out.';

  @override
  String get lastUsedNow => 'Active just now';

  @override
  String lastUsedMinutes(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Last used $count minutes ago',
      one: 'Last used 1 minute ago',
    );
    return '$_temp0';
  }

  @override
  String lastUsedHours(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Last used $count hours ago',
      one: 'Last used 1 hour ago',
    );
    return '$_temp0';
  }

  @override
  String lastUsedDays(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Last used $count days ago',
      one: 'Last used yesterday',
    );
    return '$_temp0';
  }

  @override
  String lastUsedOn(String date) {
    return 'Last used $date';
  }

  @override
  String get neverUsed => 'Not used yet';
}
