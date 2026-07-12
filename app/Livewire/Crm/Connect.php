<?php

namespace App\Livewire\Crm;

use App\Jobs\ScanCrmConnection;
use App\Models\CrmConnection;
use App\Models\CrmProvider;
use App\Services\Crm\CrmDriverManager;
use App\Services\Crm\CrmTokenStatus;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * "Conectar Pipedrive" screen. Validates the API token against the provider and,
 * on success, persists an encrypted connection and queues the CRM scan.
 *
 * Three visually-distinct error states:
 *  - validation (empty token): inline field error, no banner   (design 4c)
 *  - 401 (invalid token): danger banner, field cleared          (design 4a)
 *  - network failure: amber banner, masked token preserved      (design 4b)
 */
#[Layout('components.layouts.app')]
class Connect extends Component
{
    public string $provider = 'pipedrive';

    #[Validate('required', message: 'Informe o token da API.', as: 'API Token')]
    public string $api_token = '';

    /** Banner tone for connection-level failures: 'danger' (401) or 'warn' (network). */
    public ?string $errorType = null;

    public ?string $errorMessage = null;

    public function connect(CrmDriverManager $drivers): void
    {
        $this->reset('errorType', 'errorMessage');

        // Empty token fails here — inline validation, before any API call (4c).
        $this->validate();

        $status = $drivers->driver($this->provider)->validateToken($this->api_token);

        match ($status) {
            CrmTokenStatus::Valid => $this->persistAndScan(),
            CrmTokenStatus::Invalid => $this->markInvalid(),
            CrmTokenStatus::Retryable => $this->markRetryable(),
        };
    }

    /**
     * Valid token → upsert the company's single connection (encrypted token),
     * queue the scan, and return to the panel. Token is never echoed back.
     */
    private function persistAndScan(): void
    {
        $provider = CrmProvider::where('slug', $this->provider)->firstOrFail();

        $connection = CrmConnection::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'crm_provider_id' => $provider->id,
                'api_token' => $this->api_token,
                'last_validated_at' => now(),
            ],
        );

        ScanCrmConnection::enqueue($connection);

        $this->reset('api_token');

        $this->redirectRoute('dashboard', navigate: true);
    }

    /**
     * 401 — token rejected. Clear the field (never re-echoed) and show a red banner.
     */
    private function markInvalid(): void
    {
        $this->reset('api_token');
        $this->errorType = 'danger';
        $this->errorMessage = 'Token inválido. A Pipedrive recusou a autenticação (401). Confira o token e tente de novo.';
    }

    /**
     * Network failure — token neither verified nor discarded. Keep it (masked)
     * server-side so the company can retry, and show an amber banner.
     */
    private function markRetryable(): void
    {
        $this->errorType = 'warn';
        $this->errorMessage = 'Não foi possível validar. Falha de rede ao falar com a Pipedrive — o token não foi verificado nem descartado.';
    }

    public function render(): View
    {
        return view('livewire.crm.connect');
    }
}
