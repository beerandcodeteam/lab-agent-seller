<div class="mx-auto max-w-[520px]">
    <div class="mb-6">
        <h1 class="font-sans text-[18px] font-bold tracking-[-0.02em] text-ink">Conectar CRM</h1>
        <p class="mt-1 font-sans text-[13px] leading-[1.5] text-ink-3">
            O token é usado para uma varredura de leitura. Nunca escrevemos no seu CRM.
        </p>
    </div>

    <x-card>
        {{-- Banner de erro em nível de conexão: danger (401) ou warn (rede). Nunca aparece para erro de validação. --}}
        @if ($errorMessage)
            <div class="mb-4">
                <x-alert :type="$errorType">{{ $errorMessage }}</x-alert>
            </div>
        @endif

        <form wire:submit="connect" class="flex flex-col gap-5">
            {{-- Seleção de provedor: Pipedrive selecionado; demais "em breve", desabilitados. --}}
            <div>
                <label class="mb-1.5 block font-sans text-xs font-medium text-ink-2">Provedor</label>
                <div class="overflow-hidden rounded-field border border-border">
                    <label class="flex h-[42px] cursor-pointer items-center justify-between bg-accent-soft/40 px-3">
                        <span class="flex items-center gap-2.5 font-sans text-sm font-medium text-ink">
                            <span class="flex h-[18px] w-[18px] items-center justify-center rounded-[5px] bg-ink font-mono text-[10px] font-semibold text-white">P</span>
                            Pipedrive
                        </span>
                        <span class="font-mono text-[11px] font-semibold text-accent">✓ selecionado</span>
                        <input type="radio" wire:model="provider" value="pipedrive" class="sr-only" checked>
                    </label>

                    <div class="flex h-[38px] items-center justify-between border-t border-border/70 px-3 text-ink-3">
                        <span class="font-sans text-[13.5px]">HubSpot</span>
                        <x-badge state="em-breve">em breve</x-badge>
                    </div>
                    <div class="flex h-[38px] items-center justify-between border-t border-border/70 px-3 text-ink-3">
                        <span class="font-sans text-[13.5px]">RD Station</span>
                        <x-badge state="em-breve">em breve</x-badge>
                    </div>
                </div>
            </div>

            {{-- Campo sensível: mascarado, nunca reexibido após salvar. Erro de validação inline (4c). --}}
            <x-input
                label="API Token"
                sensitive
                wire:model="api_token"
                name="api_token"
                placeholder="Cole o API token"
                hint="Encontre em Pipedrive → Configurações pessoais → API. O token nunca é exibido novamente após salvar."
                :error="$errors->first('api_token')"
            />

            <x-button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="connect">
                <span wire:loading.remove wire:target="connect">Validar e conectar</span>
                <span wire:loading wire:target="connect">Validando…</span>
            </x-button>
        </form>
    </x-card>

    <p class="mt-5 text-center font-sans text-[13px] text-ink-3">
        <a href="{{ route('dashboard') }}" wire:navigate class="font-medium text-accent hover:text-accent-strong">Voltar ao painel</a>
    </p>
</div>
