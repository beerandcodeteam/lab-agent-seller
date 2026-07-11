<?php

use App\Livewire\Client\Access;
use App\Mail\MagicLinkMail;
use App\Models\MagicLink;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('no_crm_match_sends_no_link', function () {
    Mail::fake();

    Livewire::test(Access::class)
        ->set('email', 'stranger@nowhere.com')
        ->call('sendLink')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee('Verifique seu e-mail');

    expect(MagicLink::count())->toBe(0);
    Mail::assertNothingSent();
});

test('match_generates_link_expiring_in_15_min', function () {
    Mail::fake();
    $this->freezeTime();

    companyMatchingEmail('ana@cliente.com');

    Livewire::test(Access::class)
        ->set('email', 'ana@cliente.com')
        ->call('sendLink')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    $link = MagicLink::sole();

    expect($link->email)->toBe('ana@cliente.com');
    expect($link->used_at)->toBeNull();
    expect($link->expires_at->toDateTimeString())->toBe(now()->addMinutes(15)->toDateTimeString());

    Mail::assertSent(MagicLinkMail::class);
});

test('magic_link_token_is_hashed', function () {
    Mail::fake();

    companyMatchingEmail('ana@cliente.com');

    Livewire::test(Access::class)
        ->set('email', 'ana@cliente.com')
        ->call('sendLink');

    $link = MagicLink::sole();

    Mail::assertSent(MagicLinkMail::class, function (MagicLinkMail $mail) use ($link) {
        // O token que viaja no e-mail é o texto puro; no banco fica só o hash.
        expect($mail->token)->not->toBe($link->token);
        expect($link->token)->toBe(hash('sha256', $mail->token));

        return true;
    });

    expect($link->token)->toMatch('/^[a-f0-9]{64}$/');
});

test('access_response_is_enumeration_safe', function () {
    Mail::fake();

    $company = companyMatchingEmail('ana@cliente.com');

    // Mesma tela de confirmação com match (email existe numa empresa)...
    Livewire::test(Access::class)
        ->set('email', 'ana@cliente.com')
        ->call('sendLink')
        ->assertSet('sent', true)
        ->assertSee('Verifique seu e-mail')
        ->assertSee('enviamos um link de acesso válido por 15 minutos')
        // ...sem revelar em qual/quantas empresas o email existe.
        ->assertDontSee($company->name);

    // ...e sem match, a resposta é a mesma confirmação.
    Livewire::test(Access::class)
        ->set('email', 'stranger@nowhere.com')
        ->call('sendLink')
        ->assertSet('sent', true)
        ->assertSee('Verifique seu e-mail')
        ->assertSee('enviamos um link de acesso válido por 15 minutos');
});
