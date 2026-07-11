<?php

use App\Models\Client;
use App\Models\MagicLink;

test('valid_link_authenticates_and_marks_used', function () {
    $this->freezeTime();

    companyMatchingEmail('ana@cliente.com');

    $plainToken = 'valid-plaintext-token';

    $link = MagicLink::factory()->create([
        'email' => 'ana@cliente.com',
        'token' => hash('sha256', $plainToken),
    ]);

    $this->get(route('client.magic-link.verify', ['token' => $plainToken]))
        ->assertRedirect(route('client.chat'));

    $this->assertAuthenticated('client');

    expect($link->fresh()->used_at)->not->toBeNull();
    expect(Client::where('email', 'ana@cliente.com')->exists())->toBeTrue();
});

test('expired_link_is_rejected', function () {
    companyMatchingEmail('ana@cliente.com');

    $plainToken = 'expired-plaintext-token';

    $link = MagicLink::factory()->expired()->create([
        'email' => 'ana@cliente.com',
        'token' => hash('sha256', $plainToken),
    ]);

    $this->get(route('client.magic-link.verify', ['token' => $plainToken]))
        ->assertStatus(410)
        ->assertSee('Este link não é mais válido');

    $this->assertGuest('client');
    expect($link->fresh()->used_at)->toBeNull();
});

test('used_link_cannot_be_reused', function () {
    companyMatchingEmail('ana@cliente.com');

    $plainToken = 'reuse-plaintext-token';

    MagicLink::factory()->create([
        'email' => 'ana@cliente.com',
        'token' => hash('sha256', $plainToken),
    ]);

    // Primeiro uso autentica e consome o link.
    $this->get(route('client.magic-link.verify', ['token' => $plainToken]))
        ->assertRedirect(route('client.chat'));

    // Segundo uso do mesmo link é rejeitado.
    $this->get(route('client.magic-link.verify', ['token' => $plainToken]))
        ->assertStatus(410)
        ->assertSee('Este link não é mais válido');
});

test('single_match_goes_to_chat_multiple_goes_to_selection', function () {
    // Um único match → chat direto.
    companyMatchingEmail('single@cliente.com');

    $singleToken = 'single-match-token';
    MagicLink::factory()->create([
        'email' => 'single@cliente.com',
        'token' => hash('sha256', $singleToken),
    ]);

    $this->get(route('client.magic-link.verify', ['token' => $singleToken]))
        ->assertRedirect(route('client.chat'));

    // Múltiplos matches → seleção de empresa.
    companyMatchingEmail('multi@cliente.com');
    companyMatchingEmail('multi@cliente.com');

    $multiToken = 'multi-match-token';
    MagicLink::factory()->create([
        'email' => 'multi@cliente.com',
        'token' => hash('sha256', $multiToken),
    ]);

    $this->get(route('client.magic-link.verify', ['token' => $multiToken]))
        ->assertRedirect(route('client.company-selection'));
});
