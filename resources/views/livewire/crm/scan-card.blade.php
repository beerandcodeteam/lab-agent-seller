<div @if ($scanning) wire:poll.2s @endif>
    @if ($connection)
        {{-- Tela 05 — card de varredura, 4 estados da máquina --}}
        <x-card class="mb-6">
            @if ($state === 'syncing')
                {{-- Barra indeterminada no topo (5b). --}}
                <x-slot:header>
                    <div class="flex w-full flex-col gap-3">
                        <div class="h-[3px] w-full overflow-hidden rounded-full bg-border">
                            <div class="h-full w-1/3 animate-pulse rounded-full bg-accent"></div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-3">
                                <span class="font-sans text-[14px] font-semibold text-ink">Varredura do Pipedrive</span>
                                <x-badge state="syncing">syncing</x-badge>
                            </span>
                            <x-button variant="secondary" class="h-8 px-3 text-[12.5px]" disabled>Ressincronizar</x-button>
                        </div>
                    </div>
                </x-slot:header>
            @else
                <x-slot:header>
                    <span class="flex items-center gap-3">
                        <span class="font-sans text-[14px] font-semibold text-ink">Varredura do Pipedrive</span>
                        <x-badge :state="$state">{{ $state }}</x-badge>
                    </span>
                    <div class="flex items-center gap-3.5">
                        @if ($state === 'completed' && $scan?->finished_at)
                            <span class="font-mono text-[11px] text-ink-3">concluída em {{ $scan->finished_at->format('d/m/Y · H:i') }}</span>
                        @elseif ($state === 'failed' && $scan?->finished_at)
                            <span class="font-mono text-[11px] text-ink-3">interrompida em {{ $scan->finished_at->format('d/m/Y · H:i') }}</span>
                        @endif
                        <x-button
                            variant="secondary"
                            class="h-8 px-3 text-[12.5px]"
                            wire:click="rescan"
                            :disabled="$scanning"
                        >Ressincronizar</x-button>
                    </div>
                </x-slot:header>
            @endif

            @if ($state === 'pending')
                <p class="font-sans text-[13px] leading-[1.6] text-ink-2">
                    Na fila. A varredura começa automaticamente — nenhuma ação necessária.
                </p>
            @elseif ($state === 'syncing')
                <div class="flex items-center gap-2.5 font-sans text-[13px] leading-[1.6] text-ink-2">
                    <span class="h-[14px] w-[14px] shrink-0 animate-spin rounded-full border-2 border-border border-t-accent"></span>
                    Importando pipelines, campos, persons e deals. Esta tela atualiza sozinha.
                </div>
            @elseif ($state === 'failed')
                <div class="flex flex-col gap-3">
                    <div class="flex items-start gap-2">
                        <pre class="flex-1 select-all whitespace-pre-wrap rounded-field border border-border bg-bg px-3 py-2.5 font-mono text-[11px] leading-[1.6] text-danger">{{ $scan?->error_message }}</pre>
                    </div>
                    <p class="font-sans text-[12.5px] leading-[1.6] text-ink-3">
                        Os registros já importados nas páginas anteriores continuam no banco — a varredura não os apaga.
                    </p>
                </div>
            @endif

            {{-- Grid de contagens por entidade (5c / tela 05 grid). --}}
            @if ($state === 'completed' && $counts)
                <div class="mt-1 grid grid-cols-3 overflow-hidden rounded-field border border-border sm:grid-cols-6">
                    @foreach ([
                        ['value' => $counts['pipelines'], 'label' => 'pipelines'],
                        ['value' => $counts['stages'], 'label' => 'stages'],
                        ['value' => $counts['customFieldsPerson'], 'label' => 'c. fields person'],
                        ['value' => $counts['customFieldsDeal'], 'label' => 'c. fields deal'],
                        ['value' => $counts['persons'], 'label' => 'persons'],
                        ['value' => $counts['deals'], 'label' => 'deals'],
                    ] as $cell)
                        <div class="border-b border-r border-border px-4 py-3.5 last:border-r-0">
                            <div class="font-sans text-[22px] font-semibold text-ink">{{ $cell['value'] }}</div>
                            <div class="mt-0.5 font-mono text-[10.5px] leading-[1.4] text-ink-3">{{ $cell['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Tela 06 — volume de conversas/mensagens; zero é estado honesto. --}}
        <div class="grid max-w-[640px] grid-cols-2 gap-5">
            <x-card>
                <div class="font-sans text-[12px] font-medium text-ink-3">Conversas</div>
                <div class="mt-1.5 font-sans text-[34px] font-semibold text-ink">{{ $conversationsCount }}</div>
            </x-card>
            <x-card>
                <div class="font-sans text-[12px] font-medium text-ink-3">Mensagens</div>
                <div class="mt-1.5 font-sans text-[34px] font-semibold text-ink">{{ $messagesCount }}</div>
            </x-card>
        </div>

        <p class="mt-3 max-w-[640px] font-mono text-[11.5px] leading-[1.6] text-ink-3">
            @if ($conversationsCount === 0)
                Nenhuma conversa ainda. Os números aparecem assim que seus clientes começarem a conversar com o agente — nada a configurar aqui.
            @else
                O conteúdo das conversas é privado entre seus clientes e o agente. O painel mostra apenas volumes.
            @endif
        </p>
    @endif
</div>
