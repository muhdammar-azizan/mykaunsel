<?php

namespace App\Services\Chat;

use RuntimeException;

/** The model could not answer. The chat turns this into a friendly message; the reason is for the server's log only, never for the person. */
class AssistantUnavailable extends RuntimeException {}
