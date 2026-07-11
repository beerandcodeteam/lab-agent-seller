<?php

namespace App\Livewire\Client;

use App\Services\ClientAccess\MagicLinkService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.public')]
class Access extends Component
{
    public string $email = '';

    /**
     * Whether the confirmation screen ("Verifique seu e-mail") is showing.
     */
    public bool $sent = false;

    /**
     * Match the email against every company's scanned CRM and, when there is at
     * least one match, issue a single-use magic link. The response is always the
     * same confirmation, so it never reveals whether (or in how many companies)
     * the email exists.
     */
    public function sendLink(MagicLinkService $magicLinks): void
    {
        $this->validate([
            'email' => 'required|string|email',
        ]);

        $magicLinks->requestLink($this->email);

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.client.access');
    }
}
