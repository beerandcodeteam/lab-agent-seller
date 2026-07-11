<?php

namespace App\Services\Crm\Exceptions;

use RuntimeException;

/**
 * Raised when a CRM provider's API fails mid-scan (e.g. a 429 while paginating).
 * The message is written to be human-readable and copyable in the panel.
 */
class CrmApiException extends RuntimeException {}
