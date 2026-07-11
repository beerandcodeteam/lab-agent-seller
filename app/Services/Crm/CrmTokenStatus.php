<?php

namespace App\Services\Crm;

/**
 * Outcome of validating a CRM API token against the provider.
 */
enum CrmTokenStatus
{
    /** Provider accepted the token. */
    case Valid;

    /** Provider rejected the token (e.g. 401). Token must be discarded. */
    case Invalid;

    /** Transient failure (network/timeout/5xx). Token was neither verified nor discarded. */
    case Retryable;
}
