@php
    // Mesmos tokens visuais do x-input, adaptados para textarea (multiline).
    $textareaClasses = 'w-full box-border min-h-[96px] px-3 py-2.5 rounded-field bg-surface font-sans text-sm text-ink transition-colors focus:outline-none focus:ring-[3px] focus:ring-accent-soft focus:border-accent border border-border resize-y';
@endphp

<x-card title="Configuração do agente" meta="guardrail">
    <form wire:submit="save" class="flex flex-col gap-5">
        @if ($saved)
            <x-alert type="success">Configuração salva.</x-alert>
        @endif

        <div>
            <label for="guardrail_topic_alignments" class="mb-1.5 block font-sans text-xs font-medium text-ink-2">
                Alinhamentos de assunto
            </label>
            <textarea
                id="guardrail_topic_alignments"
                wire:model="guardrail_topic_alignments"
                placeholder="Ex.: dúvidas sobre pedidos, produtos e entregas da empresa"
                class="{{ $textareaClasses }}"
            ></textarea>
            <p class="mt-1.5 font-sans text-[11.5px] text-ink-3">
                Assuntos que o agente pode tratar. Vazio: nenhuma mensagem é bloqueada por fugir do assunto.
            </p>
        </div>

        <div>
            <label for="guardrail_restrictions" class="mb-1.5 block font-sans text-xs font-medium text-ink-2">
                Restrições específicas da empresa
            </label>
            <textarea
                id="guardrail_restrictions"
                wire:model="guardrail_restrictions"
                placeholder="Ex.: nunca falar de concorrentes ou prometer descontos"
                class="{{ $textareaClasses }}"
            ></textarea>
            <p class="mt-1.5 font-sans text-[11.5px] text-ink-3">
                Regras próprias da empresa que o agente não pode violar. Vazio: nenhuma restrição extra é aplicada.
            </p>
        </div>

        <div class="flex justify-end">
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Salvar configuração</span>
                <span wire:loading wire:target="save">Salvando…</span>
            </x-button>
        </div>
    </form>
</x-card>
