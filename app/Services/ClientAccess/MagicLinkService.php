<?php

namespace App\Services\ClientAccess;

use App\Mail\MagicLinkMail;
use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MagicLinkService
{
    /**
     * Minutes a freshly issued magic link stays valid.
     */
    public const int ExpiryMinutes = 15;

    /**
     * Companies (tenants) whose scanned CRM contains a person with this email.
     *
     * @return Collection<int, User>
     */
    public function matchedCompanies(string $email): Collection
    {
        $email = $this->normalize($email);

        return User::whereHas('crmConnection.crmPersons', function ($query) use ($email): void {
            $query->whereRaw('lower(email) = ?', [$email]);
        })->get();
    }

    /**
     * Issue and email a single-use magic link when the email matches at least
     * one company. No link is created or sent when there is no match; callers
     * must render an identical (enumeration-safe) response either way.
     */
    public function requestLink(string $email): void
    {
        $email = $this->normalize($email);

        if ($this->matchedCompanies($email)->isEmpty()) {
            return;
        }

        $plainToken = Str::random(64);

        MagicLink::create([
            'email' => $email,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(self::ExpiryMinutes),
        ]);

        Mail::to($email)->send(new MagicLinkMail($plainToken));
    }

    private function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
