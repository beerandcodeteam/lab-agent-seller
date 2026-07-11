<?php

namespace App\Livewire\Crm;

use App\Models\CrmConnection;
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
        return auth()->user()?->crmConnection()->with('crmProvider')->first();
    }

    public function render()
    {
        return view('livewire.crm.connection-status', [
            'connection' => $this->connection(),
        ]);
    }
}
