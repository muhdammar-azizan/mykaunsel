import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/app_failure.dart';
import '../../../core/providers.dart';
import '../../bookings/presentation/my_bookings_controller.dart';
import '../../counselors/presentation/counselor_list_controller.dart';
import '../../requests/presentation/my_requests_controller.dart';
import '../domain/my_organization.dart';

class OrganizationState {
  const OrganizationState({this.loading = true, this.organization, this.invitations = const [], this.loadFailure, this.working = false, this.actionFailure, this.left});

  final bool loading;
  final MyOrganization? organization;
  final List<OrgInvitation> invitations;

  /// The page could not be filled (shown with "Try again").
  final AppFailure? loadFailure;

  /// A join or a leave is on its way: its button waits, so one tap is one request.
  final bool working;

  /// Why the last join or leave did not go through.
  final AppFailure? actionFailure;

  /// What leaving just did, shown once on the page that follows.
  final LeftOrganization? left;

  OrganizationState copyWith({
    bool? loading,
    MyOrganization? organization,
    bool clearOrganization = false,
    List<OrgInvitation>? invitations,
    AppFailure? loadFailure,
    bool clearLoadFailure = false,
    bool? working,
    AppFailure? actionFailure,
    bool clearActionFailure = false,
    LeftOrganization? left,
  }) =>
      OrganizationState(
        loading: loading ?? this.loading,
        organization: clearOrganization ? null : (organization ?? this.organization),
        invitations: invitations ?? this.invitations,
        loadFailure: clearLoadFailure ? null : (loadFailure ?? this.loadFailure),
        working: working ?? this.working,
        actionFailure: clearActionFailure ? null : (actionFailure ?? this.actionFailure),
        left: left ?? this.left,
      );
}

final organizationControllerProvider = NotifierProvider.autoDispose<OrganizationController, OrganizationState>(OrganizationController.new);

/// Account > Your organization. The server decides everything (one organization, the code, what leaving cancels); this only asks and shows.
class OrganizationController extends Notifier<OrganizationState> {
  @override
  OrganizationState build() {
    ref.watch(currentUserIdProvider);
    Future.microtask(load);

    return const OrganizationState();
  }

  Future<void> load() async {
    state = state.copyWith(loading: state.organization == null, clearLoadFailure: true);

    try {
      final overview = await ref.read(accountApiProvider).organization();

      if (!ref.mounted) return;

      state = overview.organization == null
          ? state.copyWith(loading: false, clearOrganization: true, invitations: overview.invitations)
          : state.copyWith(loading: false, organization: overview.organization, invitations: overview.invitations);
    } on AppFailure catch (failure) {
      if (ref.mounted) state = state.copyWith(loading: false, loadFailure: failure);
    }
  }

  /// Returns true when the request went through. A code typed with spaces or dashes is the same code; the server reads it.
  Future<bool> join(String code) async {
    if (state.working) return false;

    state = state.copyWith(working: true, clearActionFailure: true);

    try {
      final organization = await ref.read(accountApiProvider).joinOrganization(code.trim());

      if (!ref.mounted) return true;

      state = state.copyWith(working: false, organization: organization);

      return true;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = state.copyWith(working: false, actionFailure: failure);

      return false;
    }
  }

  /// Accepts an invitation. Returns true when it went through.
  Future<bool> accept(String id) async {
    if (state.working) return false;

    state = state.copyWith(working: true, clearActionFailure: true);

    try {
      final overview = await ref.read(accountApiProvider).acceptInvitation(id);

      if (!ref.mounted) return true;

      state = state.copyWith(working: false, organization: overview.organization, invitations: overview.invitations);

      return true;
    } on NotFoundFailure {
      // Cancelled or expired meanwhile: show what is really there.
      if (ref.mounted) {
        state = state.copyWith(working: false);
        await load();
      }

      return false;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = state.copyWith(working: false, actionFailure: failure);

      return false;
    }
  }

  Future<bool> decline(String id) async {
    if (state.working) return false;

    state = state.copyWith(working: true, clearActionFailure: true);

    try {
      final left = await ref.read(accountApiProvider).declineInvitation(id);

      if (!ref.mounted) return true;

      state = state.copyWith(working: false, invitations: left);

      return true;
    } on NotFoundFailure {
      if (ref.mounted) {
        state = state.copyWith(working: false);
        await load();
      }

      return false;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = state.copyWith(working: false, actionFailure: failure);

      return false;
    }
  }

  Future<bool> leave() async {
    if (state.working) return false;

    state = state.copyWith(working: true, clearActionFailure: true);

    try {
      final left = await ref.read(accountApiProvider).leaveOrganization();

      if (!ref.mounted) return true;

      state = state.copyWith(working: false, clearOrganization: true, left: left);
      _forgetWhatDependedOnIt();

      return true;
    } on NotFoundFailure {
      // Already gone (left on another phone, or removed meanwhile): the page simply shows how things are now.
      if (ref.mounted) {
        state = state.copyWith(working: false, clearOrganization: true);
        _forgetWhatDependedOnIt();
      }

      return true;
    } on AppFailure catch (failure) {
      if (ref.mounted) state = state.copyWith(working: false, actionFailure: failure);

      return false;
    }
  }

  /// Leaving cancels later sessions and takes the organization's counselors away, so those lists are asked for again.
  void _forgetWhatDependedOnIt() {
    ref.invalidate(myBookingsControllerProvider);
    ref.invalidate(myRequestsControllerProvider);
    ref.invalidate(counselorListControllerProvider);
  }
}
