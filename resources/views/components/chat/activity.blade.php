@props([
    'activity' => [],
    'streamName' => 'activity-live',
])

@php
    // Rótulo do grupo por status: em execução, concluído (hora) ou falhou.
    $groupLabel = fn (array $group): string => match ($group['status']) {
        'running' => 'EM ANDAMENTO',
        'error' => 'FALHOU',
        default => $group['label'],
    };
@endphp

<div class="flex h-full flex-col">
    <div class="flex flex-none items-center justify-between border-b border-border px-[18px] py-3.5">
        <div class="flex items-center gap-2">
            <span class="font-sans text-[13px] font-semibold text-ink">Atividade do agent</span>
        </div>
        <span class="font-mono text-[10px] text-ink-3">eventos efêmeros</span>
    </div>

    <div class="flex-none border-b border-border px-[18px] py-2 font-mono text-[10px] text-ink-3">
        eventos em tempo real · somem ao recarregar a página
    </div>

    <div class="flex-1 overflow-y-auto">
        @forelse ($activity as $group)
            <div class="px-[18px] pb-1 pt-2 font-mono text-[10px] font-semibold uppercase tracking-[0.06em] text-ink-3">
                RESPOSTA #{{ $group['n'] }} — {{ $groupLabel($group) }}
            </div>

            @foreach ($group['events'] as $activityEvent)
                <x-agent-event
                    :type="$activityEvent['type']"
                    :timestamp="$activityEvent['timestamp']"
                    :summary="$activityEvent['summary']"
                    :payload="$activityEvent['payload']"
                />
            @endforeach

            {{-- Eventos chegam aqui em tempo real durante o stream; a
                 re-renderização final os substitui pela lista persistida acima. --}}
            @if ($group['status'] === 'running')
                <div wire:stream="{{ $streamName }}"></div>
            @endif
        @empty
            <div class="px-[18px] py-6 font-sans text-[12px] text-ink-3">
                Envie uma mensagem para ver a atividade do agente aqui.
            </div>
        @endforelse
    </div>

    <div class="flex-none border-t border-border px-[18px] py-2.5 font-mono text-[10px] leading-[1.6] text-ink-3">
        didático: cada resposta vira uma sequência request → deltas → completed | error
    </div>
</div>
