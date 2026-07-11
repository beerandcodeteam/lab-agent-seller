<?php

namespace App\Services\Crm\Contracts;

use App\Services\Crm\CrmTokenStatus;
use App\Services\Crm\Exceptions\EmptyTokenException;

/**
 * Provider-agnostic contract for a CRM integration driver.
 */
interface CrmDriver
{
    /**
     * The provider slug this driver serves (matches crm_providers.slug).
     */
    public function slug(): string;

    /**
     * Validate an API token against the provider.
     *
     * @throws EmptyTokenException when the token is blank (no API call is made)
     */
    public function validateToken(string $token): CrmTokenStatus;
}
