<?php

namespace App\Exceptions;

use RuntimeException;

/** The password typed on the invitation page is not the account's password. Counted, so the page cannot be used to guess passwords. */
class InvitationPasswordRejected extends RuntimeException {}
