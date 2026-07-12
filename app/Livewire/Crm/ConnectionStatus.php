<?php

namespace App\Livewire\Crm;

use App\Models\CrmConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * CRM connection block on the company panel. Shows the "connect" call-to-action
 * when there is no connection, or the connected-provider status (with disconnect
 * / update-token actions) when one exists.
 */
class ConnectionStatus extends Component
{
    public function disconnect(): void
    {
        $this->connection()?->delete();
    }

    /**
     * The authenticated company's CRM connection, if any.
     */
    public function connection(): ?CrmConnection
    {
        return $this->company()?->crmConnection()->with('crmProvider')->first();
    }

    /**
     * The authenticated company (tenant), if any.
     */
    private function company(): ?User
    {
        /** @var User|null $company */
        $company = auth()->user();

        return $company;
    }

    public function render(): View
    {
        return view('livewire.crm.connection-status', [
            'connection' => $this->connection(),
        ]);
    }
}
