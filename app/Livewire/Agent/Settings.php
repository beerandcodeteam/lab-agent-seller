<?php

namespace App\Livewire\Agent;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Guardrail configuration section embedded in the company dashboard. The two
 * free-text fields drive the off_topic and company_restriction guardrail
 * criteria; both are optional — an empty field disables the matching filter.
 * Values are always read from and written to the authenticated company only.
 */
class Settings extends Component
{
    #[Validate(['nullable', 'string'])]
    public ?string $guardrail_topic_alignments = null;

    #[Validate(['nullable', 'string'])]
    public ?string $guardrail_restrictions = null;

    public bool $saved = false;

    public function mount(): void
    {
        $company = $this->company();

        $this->guardrail_topic_alignments = $company->guardrail_topic_alignments;
        $this->guardrail_restrictions = $company->guardrail_restrictions;
    }

    public function save(): void
    {
        $this->saved = false;

        $this->validate();

        $this->company()->update([
            'guardrail_topic_alignments' => $this->guardrail_topic_alignments,
            'guardrail_restrictions' => $this->guardrail_restrictions,
        ]);

        $this->saved = true;
    }

    /**
     * The authenticated company (tenant) whose settings are edited.
     */
    private function company(): User
    {
        /** @var User $company */
        $company = auth()->user();

        return $company;
    }

    public function render(): View
    {
        return view('livewire.agent.settings');
    }
}
