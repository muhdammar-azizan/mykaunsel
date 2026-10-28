<?php

namespace App\Exceptions;

use InvalidArgumentException;

/** The date range chosen on the Analytics page cannot be used. The message is written for the admin who chose it. */
class InvalidAnalyticsRange extends InvalidArgumentException {}
