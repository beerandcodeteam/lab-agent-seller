<div
    class="flex h-screen"
    x-data="{ activityOpen: true, mobileActivity: false }"
>
    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Cabeçalho --}}
        <header class="flex h-14 flex-none items-center justify-between border-b border-border bg-surface px-6">
            <div class="flex items-center gap-3">
                <span class="flex h-[34px] w-[34px] items-center justify-center rounded-[10px] bg-accent-soft font-sans text-[13px] font-semibold text-accent">
                    {{ $company->initials() }}
                </span>
                <div>
                    <div class="font-sans text-[14.5px] font-semibold text-ink">{{ $company->name }}</div>
                    <div class="flex items-center gap-1.5 font-mono text-[11px] text-ink-3">
                        <span class="h-1.5 w-1.5 rounded-full bg-success"></span>agent online
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                @if ($canSwitchCompany)
                    <a
                        href="{{ route('client.company-selection') }}"
                        wire:navigate
                        class="flex h-8 items-center rounded-lg px-3 font-sans text-[12.5px] font-medium text-ink-2 transition-colors hover:bg-bg hover:text-ink"
                    >
                        ⇄ Trocar empresa
                    </a>
                @endif

                <button
                    type="button"
                    x-on:click="activityOpen = !activityOpen"
                    class="hidden h-8 items-center rounded-lg border border-border bg-surface px-3 font-mono text-[12px] font-medium text-ink-2 transition-colors hover:bg-bg lg:flex"
                >
                    atividade <span x-text="activityOpen ? '◂' : '▸'"></span>
                </button>

                <button
                    type="button"
                    x-on:click="mobileActivity = true"
                    class="flex h-8 items-center rounded-lg border border-border bg-surface px-3 font-mono text-[12px] font-medium text-ink-2 transition-colors hover:bg-bg lg:hidden"
                >
                    atividade ▸
                </button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            {{-- Coluna do chat --}}
            <div class="flex min-w-0 flex-1 flex-col">
                {{-- O MutationObserver cobre novas mensagens e os deltas do wire:stream,
                     mantendo a rolagem sempre colada no fim da conversa. --}}
                <div
                    x-ref="thread"
                    x-init="
                        $el.scrollTop = $el.scrollHeight;
                        new MutationObserver(() => { $el.scrollTop = $el.scrollHeight })
                            .observe($el, { childList: true, subtree: true, characterData: true });
                    "
                    class="flex flex-1 flex-col gap-5 overflow-y-auto px-6 py-7 md:px-10"
                >
                    @foreach ($messages as $message)
                        @if ($message->role->slug === 'user')
                            <div
                                wire:key="message-{{ $message->id }}"
                                class="max-w-[420px] self-end rounded-[16px_16px_4px_16px] bg-ink px-4 py-3 font-sans text-[14px] leading-[1.6] text-surface"
                            >
                                {{ $message->content }}
                            </div>
                        @else
                            <div wire:key="message-{{ $message->id }}" class="flex max-w-[560px] gap-2.5 self-start">
                                <span class="mt-0.5 flex h-[26px] w-[26px] flex-none items-center justify-center rounded-lg bg-accent-soft font-sans text-[11px] font-semibold text-accent">AI</span>
                                <div class="chat-markdown font-sans text-[14px] leading-[1.7] text-ink">{!! Str::markdown($message->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</div>
                            </div>
                        @endif
                    @endforeach

                    {{-- Bolha da resposta em streaming (a mensagem final re-renderiza acima com markdown) --}}
                    @if ($streaming)
                        <div class="flex max-w-[560px] gap-2.5 self-start">
                            <span class="mt-0.5 flex h-[26px] w-[26px] flex-none items-center justify-center rounded-lg bg-accent-soft font-sans text-[11px] font-semibold text-accent">AI</span>
                            <div class="whitespace-pre-wrap font-sans text-[14px] leading-[1.7] text-ink">
                                <span wire:stream="agent-response"></span><span class="ml-0.5 inline-block h-[17px] w-[9px] translate-y-[3px] animate-pulse rounded-[2px] bg-accent"></span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Compositor --}}
                <div class="flex-none border-t border-border px-6 pb-5 pt-4 md:px-10">
                    @error('agent')
                        <div class="mb-3">
                            <x-alert type="danger">{{ $message }}</x-alert>
                        </div>
                    @enderror

                    <form wire:submit="sendMessage" class="flex items-center gap-2.5">
                        <input
                            type="text"
                            wire:model="body"
                            placeholder="Escreva sua mensagem…"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                            @disabled($streaming)
                            class="h-12 flex-1 rounded-[12px] border border-border bg-surface px-4 font-sans text-[14.5px] text-ink placeholder:text-ink-3 focus:border-accent focus:outline-none disabled:opacity-60"
                        >
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                            @disabled($streaming)
                            title="Enviar"
                            class="flex h-12 w-12 flex-none items-center justify-center rounded-[12px] bg-ink font-mono text-[16px] text-surface transition-colors hover:bg-ink-2 disabled:cursor-not-allowed disabled:bg-border disabled:text-ink-3"
                        >
                            ↑
                        </button>
                    </form>

                    @if ($streaming)
                        <div class="mt-2 flex items-center gap-1.5 font-mono text-[10.5px] text-ink-3">
                            <span class="h-2.5 w-2.5 animate-spin rounded-full border-2 border-border border-t-accent"></span>
                            o agent está escrevendo… <span wire:stream="agent-model" class="text-accent"></span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Painel de atividade (desktop) --}}
            <aside
                x-show="activityOpen"
                x-cloak
                class="hidden w-[380px] flex-none flex-col border-l border-border bg-bg lg:flex"
            >
                <x-chat.activity :activity="$activity" />
            </aside>
        </div>
    </div>

    {{-- Drawer de atividade (mobile) --}}
    <div x-show="mobileActivity" x-cloak class="fixed inset-0 z-40 lg:hidden">
        <div class="absolute inset-0 bg-ink/40" x-on:click="mobileActivity = false"></div>
        <aside class="absolute right-0 top-0 flex h-full w-[86%] max-w-[380px] flex-col border-l border-border bg-bg shadow-xl">
            <x-chat.activity :activity="$activity" stream-name="activity-live-mobile" />
        </aside>
    </div>
</div>
