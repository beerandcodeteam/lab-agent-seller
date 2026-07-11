<div>
    @if ($sent)
        {{-- Confirmação idêntica com ou sem match: nunca revela em quais/quantas empresas o email existe. --}}
        <div class="mb-6 text-center">
            <h1 class="font-sans text-[19px] font-bold tracking-[-0.02em] text-ink">Verifique seu e-mail</h1>
            <p class="mt-1 font-sans text-[13px] leading-[1.5] text-ink-3">
                Se o seu e-mail estiver cadastrado, enviamos um link de acesso válido por 15 minutos.
            </p>
        </div>

        <x-card>
            <p class="font-sans text-[13.5px] leading-[1.6] text-ink-2">
                Abra o e-mail e clique no link para entrar no chat. O link expira em 15 minutos e só
                pode ser usado uma vez.
            </p>
        </x-card>

        <p class="mt-5 text-center font-sans text-[13px] text-ink-3">
            Não recebeu?
            <button type="button" wire:click="$set('sent', false)" class="font-medium text-accent hover:text-accent-strong">Tentar outro e-mail</button>
        </p>
    @else
        <div class="mb-6 text-center">
            <h1 class="font-sans text-[19px] font-bold tracking-[-0.02em] text-ink">Acessar o chat</h1>
            <p class="mt-1 font-sans text-[13px] text-ink-3">Informe seu e-mail para receber um link de acesso.</p>
        </div>

        <x-card>
            <form wire:submit="sendLink" class="flex flex-col gap-4">
                <x-input
                    label="E-mail"
                    type="email"
                    wire:model="email"
                    name="email"
                    placeholder="voce@email.com"
                    :error="$errors->first('email')"
                    autofocus
                />

                <x-button type="submit" class="mt-1 w-full" wire:loading.attr="disabled" wire:target="sendLink">
                    <span wire:loading.remove wire:target="sendLink">Enviar link de acesso</span>
                    <span wire:loading wire:target="sendLink">Enviando…</span>
                </x-button>
            </form>
        </x-card>
    @endif
</div>
