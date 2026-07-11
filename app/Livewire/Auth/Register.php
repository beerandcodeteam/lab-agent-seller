<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.public')]
class Register extends Component
{
    public string $company_name = '';

    public string $your_name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Register the company (tenant), authenticate it, and send it to the panel.
     */
    public function register(): void
    {
        $this->validate([
            'company_name' => 'required|string|max:255',
            'your_name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $this->company_name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        Auth::login($user);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
