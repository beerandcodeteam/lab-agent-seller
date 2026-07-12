<?php

namespace App\Livewire\Client;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\User;
use App\Services\ClientAccess\MagicLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.public')]
class CompanySelection extends Component
{
    /**
     * Skip the screen when the client matches a single company (or none): a lone
     * match is auto-selected and routed straight to the chat, so the selection
     * only ever renders for 2+ matched companies.
     */
    public function mount(MagicLinkService $magicLinks): Redirector|RedirectResponse|null
    {
        $companies = $this->matchedCompanies($magicLinks);

        if ($companies->count() === 1) {
            session(['selected_company_id' => $companies->first()->id]);

            return redirect()->route('client.chat');
        }

        if ($companies->isEmpty()) {
            return redirect()->route('client.access');
        }

        return null;
    }

    /**
     * Select a company and open the chat. The company must be one the client's
     * email actually matches; any other id is rejected (a client may not enter a
     * company whose CRM does not contain them).
     */
    public function select(int $company, MagicLinkService $magicLinks): Redirector|RedirectResponse
    {
        abort_unless(
            $this->matchedCompanies($magicLinks)->contains('id', $company),
            403,
        );

        session(['selected_company_id' => $company]);

        return redirect()->route('client.chat');
    }

    public function render(MagicLinkService $magicLinks): View
    {
        $companies = $this->matchedCompanies($magicLinks);

        return view('livewire.client.company-selection', [
            'companies' => $companies,
            'conversations' => $this->conversationsByCompany($companies),
        ]);
    }

    /**
     * Companies whose scanned CRM contains the authenticated client's email.
     *
     * @return Collection<int, User>
     */
    private function matchedCompanies(MagicLinkService $magicLinks): Collection
    {
        return $magicLinks->matchedCompanies($this->client()->email);
    }

    /**
     * The client's existing conversation with each matched company, keyed by the
     * company id, so the list can show whether a chat has already started.
     *
     * @param  Collection<int, User>  $companies
     * @return \Illuminate\Support\Collection<int, Conversation>
     */
    private function conversationsByCompany(Collection $companies): \Illuminate\Support\Collection
    {
        return $this->client()
            ->conversations()
            ->whereIn('user_id', $companies->pluck('id'))
            ->get()
            ->keyBy('user_id');
    }

    private function client(): Client
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        return $client;
    }
}
