<?php

namespace App\Services\Crm;

use App\Services\Crm\Contracts\CrmDriver;
use App\Services\Crm\Drivers\PipedriveDriver;
use App\Services\Crm\Exceptions\UnsupportedCrmProviderException;

/**
 * Resolves a CRM driver from a provider slug (crm_providers.slug).
 */
class CrmDriverManager
{
    /**
     * Map of provider slug → driver factory.
     *
     * @var array<string, callable(): CrmDriver>
     */
    private array $drivers = [];

    public function __construct()
    {
        $this->drivers['pipedrive'] = fn (): CrmDriver => new PipedriveDriver;
    }

    /**
     * @throws UnsupportedCrmProviderException when the slug has no driver
     */
    public function driver(string $slug): CrmDriver
    {
        if (! isset($this->drivers[$slug])) {
            throw new UnsupportedCrmProviderException($slug);
        }

        return ($this->drivers[$slug])();
    }
}
