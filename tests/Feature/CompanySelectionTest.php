<?php

use App\Livewire\Client\CompanySelection;
use App\Models\Client;
use Livewire\Livewire;

test('multiple_matches_list_all_matched_companies', function () {
    $email = 'multi@cliente.com';

    $first = companyMatchingEmail($email);
    $second = companyMatchingEmail($email);

    $client = Client::factory()->create(['email' => $email]);

    Livewire::actingAs($client, 'client')
        ->test(CompanySelection::class)
        ->assertSee($first->name)
        ->assertSee($second->name);
});

test('only_matched_companies_are_listed', function () {
    $email = 'ana@cliente.com';

    $matched = companyMatchingEmail($email);
    $other = companyMatchingEmail('outra@cliente.com');

    // Segunda empresa que casa, para que a tela não pule (2+ matches).
    companyMatchingEmail($email);

    $client = Client::factory()->create(['email' => $email]);

    Livewire::actingAs($client, 'client')
        ->test(CompanySelection::class)
        ->assertSee($matched->name)
        ->assertDontSee($other->name);
});

test('single_match_skips_selection', function () {
    $email = 'single@cliente.com';

    $company = companyMatchingEmail($email);

    $client = Client::factory()->create(['email' => $email]);

    $this->actingAs($client, 'client')
        ->get(route('client.company-selection'))
        ->assertRedirect(route('client.chat'));

    expect(session('selected_company_id'))->toBe($company->id);
});

test('selecting_a_company_sets_context_and_opens_chat', function () {
    $email = 'multi@cliente.com';

    $first = companyMatchingEmail($email);
    companyMatchingEmail($email);

    $client = Client::factory()->create(['email' => $email]);

    Livewire::actingAs($client, 'client')
        ->test(CompanySelection::class)
        ->call('select', $first->id)
        ->assertRedirect(route('client.chat'));

    expect(session('selected_company_id'))->toBe($first->id);
});

test('cannot_select_an_unmatched_company', function () {
    $email = 'multi@cliente.com';

    companyMatchingEmail($email);
    companyMatchingEmail($email);

    $unmatched = companyMatchingEmail('outra@cliente.com');

    $client = Client::factory()->create(['email' => $email]);

    Livewire::actingAs($client, 'client')
        ->test(CompanySelection::class)
        ->call('select', $unmatched->id)
        ->assertStatus(403);

    expect(session('selected_company_id'))->toBeNull();
});
