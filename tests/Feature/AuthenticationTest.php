<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

test('company can login and logout', function () {
    $user = User::factory()->create([
        'email' => 'maria@acme.com',
        'password' => 'password123',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'maria@acme.com')
        ->set('password', 'password123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('login shows generic error on bad credentials', function () {
    User::factory()->create([
        'email' => 'maria@acme.com',
        'password' => 'password123',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'maria@acme.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee(__('auth.failed'));

    // Mensagem genérica: não revela o campo específico nem se o email existe.
    expect(__('auth.failed'))->toBe('These credentials do not match our records.');

    $this->assertGuest();
});

test('panel routes require authentication', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});
