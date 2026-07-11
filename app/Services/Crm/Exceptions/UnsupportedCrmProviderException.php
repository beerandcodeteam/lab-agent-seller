<?php

namespace App\Services\Crm\Exceptions;

use RuntimeException;

/**
 * Thrown when no CRM driver is registered for a given provider slug.
 */
class UnsupportedCrmProviderException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("Nenhum driver de CRM registrado para o provedor \"{$slug}\".");
    }
}
