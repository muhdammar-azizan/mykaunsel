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
  String get counselorAskTime => 'Ask for a different time';

  @override
  String counselorAskTimeHint(String name) {
    return 'Not what you need? Ask $name for a time inside their working hours.';
  }

  @override
  String get bookingsTabRequests => 'Requests';

  @override
  String get requestsEmptyTitle => 'No requests yet';

  @override
  String get requestsEmptyBody =>
      'When you ask a counselor for a time, it shows here until they answer.';

  @override
  String get requestTitle => 'Ask for a time';

  @override
  String requestIntro(String name, int hours) {
    String _temp0 = intl.Intl.pluralLogic(
      hours,
      locale: localeName,
      other: '$hours hours',
      one: '1 hour',
    );
    return 'Choose a time inside $name\'s working hours. They have $_temp0 to answer, and nothing is booked until they approve. If they do not answer, the request closes by itself.';
  }

  @override
  String get requestNoHours =>
      'This counselor has not set working hours yet, so you can\'t ask for a time. Try their available times instead.';

  @override
  String get requestNoTimes =>
      'There is no time to ask for in the coming weeks.';

  @override
  String get requestNoteLabel => 'Note for the counselor (optional)';

  @override
  String get requestNoteHint => 'What would you like to talk about?';

  @override
  String get requestPickFirst => 'Pick a time to ask for';

  @override
  String requestSendAt(String when) {
    return 'Ask for $when';
  }

  @override
  String get requestSending => 'Sending…';

  @override
  String get requestErrTaken =>
      'That time isn\'t available. Please choose another.';

  @override
  String get requestErrInvalid =>
      'That time is outside the counselor\'s working hours. Please choose another.';

  @override
  String requestErrLimit(int max) {
    return 'You can have up to $max requests waiting for an answer. Wait for an answer or withdraw one.';
  }

  @override
  String get requestErrClosed =>
      'This counselor isn\'t taking time requests right now.';

  @override
  String get requestErrServer =>
      'Something went wrong on our side. Your request may or may not have been sent. Try again: it will not be sent twice.';

  @override
  String get requestStatusPending => 'Waiting for an answer';

  @override
  String get requestStatusApproved => 'Approved';

  @override
  String get requestStatusDeclined => 'Declined';

  @override
  String get requestStatusExpired => 'Expired';

  @override
  String get requestStatusWithdrawn => 'Withdrawn';

  @override
  String get requestStatusUnknown => 'Unknown';

  @override
  String get requestDetailTitle => 'Request';

  @override
  String requestWaitUntil(String name, String when) {
    return '$name can answer until $when.';
  }

  @override
  String get requestApprovedNote => 'Approved. Your session is booked.';

  @override
  String get requestDeclinedNote =>
      'The counselor couldn\'t take this request.';

  @override
  String get requestExpiredNote =>
      'Nobody answered in time, so this request closed.';

  @override
  String get requestWithdrawnNote => 'You withdrew this request.';

  @override
  String get requestYourNote => 'Your note';

  @override
  String get requestTheirMessage => 'Their message';

  @override
  String get requestViewBooking => 'View booking';

  @override
  String get requestChooseAnother => 'Choose another time';

  @override
  String get requestWithdraw => 'Withdraw request';

  @override
  String get requestWithdrawTitle => 'Withdraw this request?';

  @override
  String requestWithdrawBody(String name) {
    return '$name will no longer be asked for this time. You can ask for another time.';
  }

  @override
  String get requestKeep => 'Keep request';

  @override
  String get requestWithdrawing => 'Withdrawing…';

  @override
  String get requestWithdrawnSnack => 'Request withdrawn.';

  @override
  String get requestNotPending =>
      'This request was already answered or has closed.';

  @override
  String get requestWithdrawServer =>
      'Something went wrong on our side. Your request may or may not have been withdrawn. Try again: it is safe to repeat.';

  @override
  String get requestNotFound => 'We couldn\'t find this request.';

  @override
  String get signUpConsent =>
      'I agree that my chat messages are processed by an AI service to write replies. I can turn this off at any time in Account.';

  @override
  String get signUpConsentMore => 'What this means';

  @override
  String get signUpConsentRequired => 'Please agree to continue.';

  @override
  String get homeChatTitle => 'Talk it through';

  @override
  String get homeChatBody =>
      'Chat with an AI companion about how you feel. It listens; it is not a counselor.';

  @override
  String get homeHelpTitle => 'Need help right now?';

  @override
  String get homeHelpBody => 'See phone lines you can call.';

  @override
  String get chooseTypeTitle => 'Create your account';

  @override
  String get chooseTypeSubtitle => 'Who is signing up?';

  @override
  String get chooseTypeIndividual => 'Individual';

  @override
  String get chooseTypeIndividualBody =>
      'Talk to a counselor, book sessions and use the chat.';

  @override
  String get chooseTypeOrganization => 'Organization';

  @override
  String get chooseTypeOrganizationBody =>
      'Register a clinic, university or company.';

  @override
  String get chooseTypeCounselor => 'Counselor';

  @override
  String get chooseTypeCounselorBody =>
      'Join MyKaunsel as a registered counselor.';

  @override
  String get chooseTypeClosed => 'Closed for now';

  @override
  String get signupLinkTitle => 'Sign up on a computer';

  @override
  String get signupLinkOrganizationBody =>
      'Organizations register on a computer. Enter your email and we will send you the link.';

  @override
  String get signupLinkCounselorBody =>
      'Counselors sign up on a computer, with their documents. Enter your email and we will send you the link.';

  @override
  String get signupLinkSend => 'Send me the link';

  @override
  String get signupLinkSending => 'Sending…';

  @override
  String get signupLinkSentTitle => 'Check your email';

  @override
  String get signupLinkSentBody =>
      'If that address can receive email, the link is on its way. Open it on a computer to continue.';

  @override
  String get signupLinkDone => 'Done';

  @override
  String get accountNotifications => 'Notifications';

  @override
  String get notificationsTitle => 'Notifications';

  @override
  String get notificationsIntro =>
      'MyKaunsel only sends email. There are no push notifications.';

  @override
  String get notificationBookingsTitle => 'Bookings';

  @override
  String get notificationBookingsBody =>
      'Confirmations, answers to your time requests and changes to your sessions.';

  @override
  String get notificationsSaveFailed =>
      'We couldn\'t save that. Please try again.';

  @override
  String get accountPrivacy => 'Data and privacy';

  @override
  String get privacyTitle => 'Data and privacy';

  @override
  String get privacyHeading => 'How your information is handled';

  @override
  String get privacyChatKept =>
      'Your chat conversations are deleted after 12 months.';

  @override
  String get privacyChatPrivate =>
      'Counselors and organizations can\'t read your chat.';

  @override
  String get privacyPdpa =>
      'MyKaunsel handles your personal data under Malaysia\'s Personal Data Protection Act (PDPA).';

  @override
  String get privacyPolicyButton => 'Privacy Policy';

  @override
  String get privacyOpenFailed => 'We couldn\'t open the Privacy Policy.';

  @override
  String get privacyCopyHeading => 'A copy of your data';

  @override
  String privacyCopyBody(String email) {
    return 'To ask for a copy of your data, write to $email.';
  }

  @override
  String privacyMailFailed(String email) {
    return 'We couldn\'t open your email app. Please write to $email.';
  }

  @override
  String get navChat => 'Chat';

  @override
  String get chatTitle => 'Chat';

  @override
  String get chatConsentTitle => 'Before you chat';

  @override
  String get chatConsentIntro =>
      'The MyKaunsel chat is an AI companion you can talk to about how you feel.';

  @override
  String get chatConsentPoint1 =>
      'It is an AI, not a counselor or a doctor. It can\'t diagnose or treat you.';

  @override
  String get chatConsentPoint2 =>
      'Your messages are processed by an AI service so it can reply. We don\'t send your name, email or account. Please don\'t type details that identify you.';

  @override
  String get chatConsentPoint3 =>
      'Conversations are kept for 12 months. Counselors and organizations can\'t read them.';

  @override
  String get chatConsentPoint4 =>
      'You can turn this off at any time in Account.';

  @override
  String get chatConsentAgree => 'I agree';

  @override
  String get chatConsentLater => 'Not now';

  @override
  String get chatConsentSaving => 'Saving…';

  @override
  String get chatConsentFailed =>
      'We couldn\'t save your choice. Please try again.';

  @override
  String chatGreeting(String name) {
    return 'Hi $name. How are you feeling today?';
  }

  @override
  String get chatGreetingBody =>
      'This is a space to talk. I\'m an AI companion, not a counselor, and I\'m here to listen.';

  @override
  String get chatInputHint => 'Write a message';

  @override
  String get chatSend => 'Send';

  @override
  String get chatDisclaimer => 'AI companion. Not a counselor or a doctor.';

  @override
  String get chatNew => 'New conversation';

  @override
  String get chatHistory => 'Chat history';

  @override
  String get chatHelp => 'Need help now?';

  @override
  String get chatThinking => 'Thinking…';

  @override
  String chatYouSaid(String text) {
    return 'You said: $text';
  }

  @override
  String chatCompanionSaid(String text) {
    return 'Companion said: $text';
  }

  @override
  String get chatNoticeNew =>
      'This is a new conversation, because the last one had ended.';

  @override
  String get chatUnavailable =>
      'I can\'t reply right now. Your message is saved. Please try again in a moment.';

  @override
  String get chatRetrying => 'Trying again…';

  @override
  String get chatSendAgain => 'Send again';

  @override
  String get chatSending => 'Sending…';

  @override
  String get chatErrServer =>
      'Something went wrong on our side. Your message may or may not have been sent. Try again: it will not be sent twice.';

  @override
  String chatErrWait(int seconds) {
    return 'Too many messages. Please wait $seconds seconds.';
  }

  @override
  String get chatErrDaily =>
      'You\'ve reached today\'s limit of messages. Please come back tomorrow.';

  @override
  String get chatErrFull => 'This conversation is full. Start a new one.';

  @override
  String get crisisTitle => 'You are not alone';

  @override
  String get crisisBody =>
      'If you are thinking about ending your life or hurting yourself, please reach out to someone now. You can call:';

  @override
  String crisisCall(String name, String number) {
    return 'Call $name $number';
  }

  @override
  String get crisisEmergency =>
      'If you are in danger right now, go to the nearest hospital emergency department.';

  @override
  String get crisisFindCounselor => 'Find a counselor';

  @override
  String get crisisContinue => 'Continue chatting';

  @override
  String crisisCallFailed(String number) {
    return 'We couldn\'t start the call. Please dial $number.';
  }

  @override
  String get helpSheetBody =>
      'You don\'t have to face this alone. These lines are there to listen.';

  @override
  String get chatSuggestTitle => 'Counselors who may be able to help';

  @override
  String get chatSuggestBody =>
      'Talking with a real counselor can help. These are people you can book.';

  @override
  String get chatHistoryTitle => 'Chat history';

  @override
  String get chatHistoryEmptyTitle => 'No conversations yet';

  @override
  String get chatHistoryEmptyBody => 'Your past conversations will show here.';

  @override
  String get chatGroupToday => 'Today';

  @override
  String get chatGroupWeek => 'This week';

  @override
  String get chatGroupEarlier => 'Earlier';

  @override
  String get chatConversationTitle => 'Conversation';

  @override
  String get chatReadOnly =>
      'This conversation is finished. You can read it here.';

  @override
  String get chatNotFound => 'We couldn\'t find this conversation.';

  @override
  String chatEmotionMostly(String emotion) {
    return 'Mostly felt: $emotion';
  }

  @override
  String get chatEmotionSadness => 'sadness';

  @override
  String get chatEmotionAnxiety => 'anxiety';

  @override
  String get chatEmotionStress => 'stress';

  @override
  String get chatEmotionAnger => 'anger';

  @override
  String get chatEmotionLoneliness => 'loneliness';

  @override
  String get chatEmotionFear => 'fear';

  @override
  String get chatEmotionGuilt => 'guilt';

  @override
  String get chatEmotionHope => 'hope';

  @override
  String get chatEmotionCalm => 'calm';

  @override
  String get chatEmotionJoy => 'joy';

  @override
  String get chatEmotionNeutral => 'neutral';

  @override
  String get accountAiChat => 'AI chat';

  @override
  String get accountAiChatOn => 'On: you can talk with the companion.';

  @override
  String get accountAiChatOff => 'Off: the chat is turned off.';

  @override
  String get accountAiOffTitle => 'Turn off AI chat?';

  @override
  String get accountAiOffBody =>
      'The chat will stop working until you turn it on again. Your past conversations stay in your history.';

  @override
  String get accountAiOffConfirm => 'Turn off';

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
  String get homeUpcomingTitle => 'Your next session';

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

  @override
  String get navFeed => 'Feed';

  @override
  String get feedTitle => 'Feed';

  @override
  String get feedNewPost => 'New post';

  @override
  String get feedEmptyTitle => 'No posts yet';

  @override
  String get feedEmptyBody => 'Be the first to share how you are doing today.';

  @override
  String get feedMutedPeople => 'Muted people';

  @override
  String get feedPostOptions => 'Post options';

  @override
  String get feedCommentOptions => 'Comment options';

  @override
  String get feedLike => 'Like';

  @override
  String get feedUnlike => 'Remove like';

  @override
  String feedLikesCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count likes',
      one: '1 like',
      zero: 'No likes',
    );
    return '$_temp0';
  }

  @override
  String feedCommentsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count comments',
      one: '1 comment',
      zero: 'No comments',
    );
    return '$_temp0';
  }

  @override
  String get feedCounselorBadge => 'Counselor';

  @override
  String get feedEdited => 'edited';

  @override
  String get feedJustNow => 'Just now';

  @override
  String feedMinutesAgo(int n) {
    return '$n min ago';
  }

  @override
  String feedHoursAgo(int n) {
    return '$n h ago';
  }

  @override
  String get feedOpenPost => 'Open post';

  @override
  String get feedNewPostTitle => 'New post';

  @override
  String get feedEditPostTitle => 'Edit post';

  @override
  String get feedPostHint => 'What\'s on your mind?';

  @override
  String get feedPostAction => 'Post';

  @override
  String get feedSaveAction => 'Save';

  @override
  String feedCharCount(int used, int max) {
    return '$used/$max';
  }

  @override
  String feedPostingAs(String name) {
    return 'Posting as $name. Everyone who uses MyKaunsel can see your name and this post.';
  }

  @override
  String get feedPostTitle => 'Post';

  @override
  String get feedCommentsHeader => 'Comments';

  @override
  String get feedNoComments => 'No comments yet. Be the first to reply.';

  @override
  String get feedCommentHint => 'Write a comment…';

  @override
  String get feedSendComment => 'Send comment';

  @override
  String get feedShowMoreComments => 'Show more comments';

  @override
  String get feedPostGoneTitle => 'This post is no longer available';

  @override
  String get feedPostGoneBody => 'The author may have deleted it.';

  @override
  String get feedMenuEdit => 'Edit post';

  @override
  String get feedMenuDelete => 'Delete post';

  @override
  String get feedMenuReport => 'Report post';

  @override
  String feedMenuMute(String name) {
    return 'Mute $name';
  }

  @override
  String get feedMenuDeleteComment => 'Delete comment';

  @override
  String get feedMenuReportComment => 'Report comment';

  @override
  String get feedDeleteTitle => 'Delete this post?';

  @override
  String get feedDeleteBody =>
      'It will be removed for everyone. This can\'t be undone.';

  @override
  String get feedDeleteConfirm => 'Delete';

  @override
  String get feedDeleteCommentTitle => 'Delete this comment?';

  @override
  String get feedDeleted => 'Deleted.';

  @override
  String get feedReportTitle => 'Why are you reporting this?';

  @override
  String get feedReportSpam => 'Spam';

  @override
  String get feedReportHarassment => 'Harassment or bullying';

  @override
  String get feedReportSelfHarm => 'Self-harm or someone in danger';

  @override
  String get feedReportInappropriate => 'Inappropriate content';

  @override
  String get feedReportOther => 'Something else';

  @override
  String get feedReported => 'Thank you. Our team will look at it.';

  @override
  String feedMuteTitle(String name) {
    return 'Mute $name?';
  }

  @override
  String get feedMuteBody =>
      'You will no longer see their posts or comments. They are not told. You can undo this in Muted people.';

  @override
  String get feedMuteConfirm => 'Mute';

  @override
  String feedMutedDone(String name) {
    return '$name is muted.';
  }

  @override
  String get feedMutedTitle => 'Muted people';

  @override
  String get feedMutedEmptyTitle => 'No one is muted';

  @override
  String get feedMutedEmptyBody => 'People you mute will appear here.';

  @override
  String get feedUnmute => 'Unmute';

  @override
  String feedUnmuteName(String name) {
    return 'Unmute $name';
  }

  @override
  String get feedDailyLimit =>
      'You have reached today\'s limit of posts. Please come back tomorrow.';

  @override
  String get feedGone => 'This is no longer available.';

  @override
  String get feedCrisisContinue => 'OK, continue';

  @override
  String get feedLoadMoreFailed => 'Could not load more.';

  @override
  String get onboardingSkip => 'Skip';

  @override
  String get onboardingNext => 'Next';

  @override
  String get onboardingStart => 'Get started';

  @override
  String onboardingStep(int current, int total) {
    return 'Step $current of $total';
  }

  @override
  String get onboardingTalkTitle => 'Talk it through';

  @override
  String get onboardingTalkBody =>
      'Chat any time with a supportive assistant. It listens and helps you think, and it is not a replacement for a counselor.';

  @override
  String get onboardingFindTitle => 'Find a counselor';

  @override
  String get onboardingFindBody =>
      'See verified counselors, pick a time that suits you and book a session online or in person.';

  @override
  String get onboardingFeedTitle => 'Feel less alone';

  @override
  String get onboardingFeedBody =>
      'Read and share short posts with others. You post under your own name, and you can report or mute anything you do not want to see.';

  @override
  String get orgTitle => 'Your organization';

  @override
  String get orgNoneIntro =>
      'If your organization gave you a reference code, enter it here. When your organization approves you, you can book its counselors. You can use MyKaunsel without one.';

  @override
  String get orgCodeLabel => 'Reference code';

  @override
  String get orgJoin => 'Send my request';

  @override
  String orgWaitingTitle(String name) {
    return 'Waiting for $name';
  }

  @override
  String orgWaitingBody(String name) {
    return '$name has to approve you before you can book its counselors. We will e-mail you when they answer.';
  }

  @override
  String get orgWithdraw => 'Withdraw my request';

  @override
  String get orgWithdrawTitle => 'Withdraw your request?';

  @override
  String get orgWithdrawBody =>
      'Nothing else changes. You can ask again with a code later.';

  @override
  String get orgWithdrawConfirm => 'Withdraw';

  @override
  String orgMemberTitle(String name) {
    return 'You belong to $name';
  }

  @override
  String orgMemberBody(String name) {
    return 'You can book the counselors of $name, as well as the public counselors.';
  }

  @override
  String get orgLeave => 'Leave organization';

  @override
  String orgLeaveTitle(String name) {
    return 'Leave $name?';
  }

  @override
  String orgLeaveBody(String name, int days) {
    return '$name will be told that you left. Your later sessions with its counselors will be cancelled. Sessions in the next $days days still go ahead.';
  }

  @override
  String get orgLeaveConfirm => 'Leave';

  @override
  String orgLeftTitle(String name) {
    return 'You left $name';
  }

  @override
  String orgLeftCancelled(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count later sessions were cancelled.',
      one: '1 later session was cancelled.',
    );
    return '$_temp0';
  }

  @override
  String orgLeftKept(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count sessions in the next few days still go ahead.',
      one: '1 session in the next few days still goes ahead.',
    );
    return '$_temp0';
  }

  @override
  String orgInvitedTitle(String name) {
    return '$name invited you';
  }

  @override
  String get orgInvitedBody =>
      'If you accept, you join at once and can book its counselors.';

  @override
  String get orgAccept => 'Accept';

  @override
  String get orgDecline => 'Decline';

  @override
  String get deleteAccountTitle => 'Delete account';

  @override
  String get deleteAccountWarning => 'This can not be undone.';

  @override
  String get deleteErasedTitle => 'What is erased';

  @override
  String get deleteErasedBody =>
      'Your name, e-mail address and photo, your chats with the assistant, your Feed posts and comments, and every sign-in of yours.';

  @override
  String get deleteEndedTitle => 'What ends';

  @override
  String get deleteEndedBody =>
      'All your later sessions are cancelled and the counselors are told. If you belong to an organization, you leave it and it is told your name.';

  @override
  String get deleteKeptTitle => 'What stays';

  @override
  String get deleteKeptBody =>
      'Records of past bookings stay for 90 days without your name. What a counselor or organization wrote about a session stays with them.';

  @override
  String get deleteTypeLabel => 'Type DELETE to confirm';

  @override
  String get deleteTypeHelp => 'In capital letters';

  @override
  String get deleteAccountButton => 'Delete my account';

  @override
  String get accountDeletedNote =>
      'Your account has been deleted. We sent an e-mail to confirm.';
}
