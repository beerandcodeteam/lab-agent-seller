<div>
    <div class="mb-6 text-center">
        <h1 class="font-sans text-[19px] font-bold tracking-[-0.02em] text-ink">Entrar na sua conta</h1>
        <p class="mt-1 font-sans text-[13px] text-ink-3">Acesse o painel da sua empresa.</p>
    </div>

    <x-card>
        {{-- Erro genérico único: nunca revela qual campo falhou nem se o email existe. --}}
        @error('email')
            <div class="mb-4">
                <x-alert type="danger">{{ $message }}</x-alert>
            </div>
        @enderror

        <form wire:submit="login" class="flex flex-col gap-4">
            <x-input
                label="Email"
                type="email"
                wire:model="email"
                name="email"
                placeholder="voce@empresa.com"
                autofocus
            />

            <x-input
                label="Senha"
                sensitive
                wire:model="password"
                name="password"
            />

            <x-button type="submit" class="mt-1 w-full">Entrar</x-button>
        </form>
    </x-card>

    <p class="mt-5 text-center font-sans text-[13px] text-ink-3">
        Não tem conta?
        <a href="{{ route('register') }}" wire:navigate class="font-medium text-accent hover:text-accent-strong">Criar conta</a>
    </p>
</div>
