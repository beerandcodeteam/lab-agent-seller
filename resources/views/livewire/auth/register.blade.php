<div>
    <div class="mb-6 text-center">
        <h1 class="font-sans text-[19px] font-bold tracking-[-0.02em] text-ink">Criar conta da empresa</h1>
        <p class="mt-1 font-sans text-[13px] text-ink-3">Cadastre sua empresa para conectar o CRM e ativar o agente.</p>
    </div>

    <x-card>
        <form wire:submit="register" class="flex flex-col gap-4">
            <x-input
                label="Nome da empresa"
                wire:model="company_name"
                name="company_name"
                placeholder="Acme Ltda."
                :error="$errors->first('company_name')"
                autofocus
            />

            <x-input
                label="Seu nome"
                wire:model="your_name"
                name="your_name"
                placeholder="Maria Silva"
                :error="$errors->first('your_name')"
            />

            <x-input
                label="Email"
                type="email"
                wire:model="email"
                name="email"
                placeholder="voce@empresa.com"
                :error="$errors->first('email')"
            />

            <x-input
                label="Senha"
                sensitive
                wire:model="password"
                name="password"
                hint="Mínimo de 8 caracteres."
                :error="$errors->first('password')"
            />

            <x-input
                label="Confirmar senha"
                sensitive
                wire:model="password_confirmation"
                name="password_confirmation"
                :error="$errors->first('password_confirmation')"
            />

            <x-button type="submit" class="mt-1 w-full">Criar conta</x-button>
        </form>
    </x-card>

    <p class="mt-5 text-center font-sans text-[13px] text-ink-3">
        Já tem conta?
        <a href="{{ route('login') }}" wire:navigate class="font-medium text-accent hover:text-accent-strong">Entrar</a>
    </p>
</div>
