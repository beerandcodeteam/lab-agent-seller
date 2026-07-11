<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('company can register', function () {
    Livewire::test(Register::class)
        ->set('company_name', 'Acme Ltda.')
        ->set('your_name', 'Maria Silva')
        ->set('email', 'maria@acme.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'maria@acme.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Acme Ltda.')
        ->and(Hash::check('password123', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('registration rejects duplicate email', function () {
    User::factory()->create(['email' => 'taken@acme.com']);

    Livewire::test(Register::class)
        ->set('company_name', 'Acme Ltda.')
        ->set('your_name', 'Maria Silva')
        ->set('email', 'taken@acme.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors(['email' => 'unique']);

    expect(User::where('email', 'taken@acme.com')->count())->toBe(1);
    $this->assertGuest();
});

test('registration enforces min password length', function () {
    Livewire::test(Register::class)
        ->set('company_name', 'Acme Ltda.')
        ->set('your_name', 'Maria Silva')
        ->set('email', 'maria@acme.com')
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('register')
        ->assertHasErrors(['password']);

    expect(User::where('email', 'maria@acme.com')->exists())->toBeFalse();
    $this->assertGuest();
});
