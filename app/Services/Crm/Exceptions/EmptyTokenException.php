<?php

namespace App\Services\Crm\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when token validation is attempted with a blank token, before any
 * network call is made to the CRM provider.
 */
class EmptyTokenException extends InvalidArgumentException
{
    public function __construct(string $message = 'O token está vazio; validação abortada antes de chamar a API.')
    {
        parent::__construct($message);
    }
}
