<?php

use App\Livewire\Agent\Settings;
use App\Models\User;
use Livewire\Livewire;

// ── UI-01 / UI-02: salvar, reexibir e isolar por empresa ─────────────────────

test('company_saves_and_reloads_guardrail_settings', function () {
    $company = User::factory()->create();

    Livewire::actingAs($company)
        ->test(Settings::class)
        ->set('guardrail_topic_alignments', 'dúvidas sobre pedidos e entregas')
        ->set('guardrail_restrictions', 'nunca prometer descontos')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    $company->refresh();

    expect($company->guardrail_topic_alignments)->toBe('dúvidas sobre pedidos e entregas');
    expect($company->guardrail_restrictions)->toBe('nunca prometer descontos');

    // Reexibição preenchida no próximo acesso.
    Livewire::actingAs($company)
        ->test(Settings::class)
        ->assertSet('guardrail_topic_alignments', 'dúvidas sobre pedidos e entregas')
        ->assertSet('guardrail_restrictions', 'nunca prometer descontos');
});

test('settings_are_isolated_between_companies', function () {
    $companyA = User::factory()->create([
        'guardrail_topic_alignments' => 'assuntos da empresa A',
        'guardrail_restrictions' => 'restrições da empresa A',
    ]);
    $companyB = User::factory()->create();

    // Empresa B não vê os valores da empresa A.
    Livewire::actingAs($companyB)
        ->test(Settings::class)
        ->assertSet('guardrail_topic_alignments', null)
        ->assertSet('guardrail_restrictions', null)
        ->set('guardrail_topic_alignments', 'assuntos da empresa B')
        ->call('save')
        ->assertHasNoErrors();

    // Salvar como empresa B não afeta a empresa A.
    $companyA->refresh();
    $companyB->refresh();

    expect($companyA->guardrail_topic_alignments)->toBe('assuntos da empresa A');
    expect($companyA->guardrail_restrictions)->toBe('restrições da empresa A');
    expect($companyB->guardrail_topic_alignments)->toBe('assuntos da empresa B');
});

// ── UI-03: campos vazios salvam sem erro ─────────────────────────────────────

test('saving_with_both_fields_empty_produces_no_validation_error', function () {
    $company = User::factory()->create();

    Livewire::actingAs($company)
        ->test(Settings::class)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    $company->refresh();

    expect($company->guardrail_topic_alignments)->toBeNull();
    expect($company->guardrail_restrictions)->toBeNull();
});

// ── Dashboard embute o componente para a empresa autenticada ─────────────────

test('dashboard_renders_agent_settings_component', function () {
    $company = User::factory()->create();

    $this->actingAs($company)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeLivewire(Settings::class)
        ->assertSee('Alinhamentos de assunto')
        ->assertSee('Restrições específicas da empresa');
});
