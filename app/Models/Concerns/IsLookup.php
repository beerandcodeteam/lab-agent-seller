<?php

namespace App\Models\Concerns;

/**
 * Shared behaviour for lookup tables keyed by a unique `slug`.
 */
trait IsLookup
{
    /**
     * Resolve a lookup row by its slug for use in jobs and services.
     */
    public static function slug(string $slug): ?static
    {
        return static::query()->where('slug', $slug)->first();
    }
}
