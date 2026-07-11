<div>
    @if ($connection)
        {{-- Tela 05 — CRM conectado --}}
        <x-card>
            <x-slot:header>
                <span class="flex items-center gap-2.5 font-sans text-[13.5px] font-semibold text-ink">
                    <span class="flex h-[18px] w-[18px] items-center justify-center rounded-[5px] bg-ink font-mono text-[10px] font-semibold text-white">P</span>
                    CRM conectado
                </span>
                <x-badge state="completed">conectado</x-badge>
            </x-slot:header>

            <div class="flex flex-col gap-1">
                <div class="font-sans text-[13px] text-ink-2">
                    Provedor:
                    <span class="font-mono text-[12px] font-medium text-ink">{{ $connection->crmProvider->name }}</span>
                </div>
                <div class="font-sans text-[13px] text-ink-3">
                    Conectado em
                    <span class="font-mono text-[12px] text-ink-2">{{ $connection->created_at?->format('d/m/Y') }}</span>
                    @if ($connection->last_validated_at)
                        · última validação
                        <span class="font-mono text-[12px] text-ink-2">{{ $connection->last_validated_at->format('d/m/Y H:i') }}</span>
                    @endif
                </div>
            </div>

            <x-slot:footer>
                <div class="flex items-center gap-2">
                    <a href="{{ route('crm.connect') }}" wire:navigate>
                        <x-button variant="secondary" class="h-8 px-3 text-[12.5px]">Atualizar token</x-button>
                    </a>
                    <x-button
                        variant="danger"
                        class="h-8 px-3 text-[12.5px]"
                        wire:click="disconnect"
                        wire:confirm="Desconectar o CRM? Você precisará informar o token novamente para reconectar."
                    >Desconectar</x-button>
                </div>
            </x-slot:footer>
        </x-card>
    @else
        {{-- Tela 03 — CRM não conectado --}}
        <x-card>
            <div class="flex flex-col items-center py-6 text-center">
                <div class="mb-2 font-sans text-[16px] font-semibold text-ink">Conecte seu CRM para começar</div>
                <p class="mb-5 max-w-[340px] font-sans text-[13px] leading-[1.6] text-ink-3">
                    O agente responde seus clientes com base nos dados do seu CRM. Sem conexão, não há o que conversar.
                </p>
                <a href="{{ route('crm.connect') }}" wire:navigate>
                    <x-button>Conectar Pipedrive</x-button>
                </a>
                <div class="mt-4 font-mono text-[11px] text-ink-3">leva ~1 minuto · só o API token</div>
            </div>
        </x-card>
    @endif
</div>
