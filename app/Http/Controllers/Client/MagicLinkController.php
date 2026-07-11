<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MagicLink;
use App\Services\ClientAccess\MagicLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class MagicLinkController extends Controller
{
    /**
     * Authenticate a final client from a magic link.
     *
     * A valid, unused, non-expired link logs the client in (creating the
     * `clients` record on first access), marks the link as used, then routes to
     * the chat (single company match) or the company selection (multiple).
     * Expired or already-used links render the "link is no longer valid" screen.
     */
    public function verify(string $token, MagicLinkService $magicLinks): Response|RedirectResponse
    {
        $magicLink = MagicLink::where('token', hash('sha256', $token))->first();

        if ($magicLink === null || $magicLink->isExpired() || $magicLink->isUsed()) {
            return response()->view('client.invalid-link', status: 410);
        }

        $magicLink->forceFill(['used_at' => now()])->save();

        $client = Client::firstOrCreate(['email' => $magicLink->email]);

        Auth::guard('client')->login($client);

        $companies = $magicLinks->matchedCompanies($magicLink->email);

        if ($companies->count() === 1) {
            session(['selected_company_id' => $companies->first()->id]);

            return redirect()->route('client.chat');
        }

        return redirect()->route('client.company-selection');
    }
}
