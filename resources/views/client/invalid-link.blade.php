<x-layouts.public title="Link inválido">
    <div class="mb-6 text-center">
        <h1 class="font-sans text-[19px] font-bold tracking-[-0.02em] text-ink">Este link não é mais válido</h1>
        <p class="mt-1 font-sans text-[13px] leading-[1.5] text-ink-3">
            Links de acesso expiram em 15 minutos e só podem ser usados uma vez.
        </p>
    </div>

    <x-card>
        <p class="mb-4 font-sans text-[13.5px] leading-[1.6] text-ink-2">
            Solicite um novo link para entrar no chat.
        </p>

        <a href="{{ route('client.access') }}" wire:navigate
            class="inline-flex h-[38px] w-full items-center justify-center gap-2 rounded-field border-0 bg-accent px-[18px] font-sans text-[13.5px] font-medium text-white transition-colors hover:bg-accent-strong">
            Solicitar novo link
        </a>
    </x-card>
</x-layouts.public>
