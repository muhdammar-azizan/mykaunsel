<?php

namespace App\Exceptions;

use RuntimeException;

/** The invitation link cannot be used: unknown, expired, revoked, already used, replaced, or its sender is no longer an admin. The person is never told which. */
class InvitationUnavailable extends RuntimeException {}
