<div>
    <div class="mb-6 text-center">
        <h1 class="font-sans text-[19px] font-bold tracking-[-0.02em] text-ink">Com quem você quer conversar?</h1>
        <p class="mt-1 font-sans text-[13px] leading-[1.5] text-ink-3">
            Seu e-mail está cadastrado em mais de uma empresa. Cada conversa é separada.
        </p>
    </div>

    <div class="flex flex-col gap-3">
        @foreach ($companies as $company)
            @php($conversation = $conversations->get($company->id))
            <button
                type="button"
                wire:key="company-{{ $company->id }}"
                wire:click="select({{ $company->id }})"
                class="flex w-full items-center gap-3.5 rounded-card border border-border bg-surface p-4 text-left transition-colors hover:border-accent hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            >
                <span class="flex h-11 w-11 flex-none items-center justify-center rounded-field bg-accent-soft font-sans text-[15px] font-semibold text-accent">
                    {{ $company->initials() }}
                </span>
                <span class="flex-1">
                    <span class="block font-sans text-[14.5px] font-semibold text-ink">{{ $company->name }}</span>
                    <span class="mt-0.5 block font-sans text-[12px] text-ink-3">
                        @if ($conversation)
                            Conversa iniciada {{ $conversation->created_at->diffForHumans() }}
                        @else
                            Nenhuma conversa ainda
                        @endif
                    </span>
                </span>
                <span class="font-mono text-[15px] text-ink-3">→</span>
            </button>
        @endforeach
    </div>
</div>
