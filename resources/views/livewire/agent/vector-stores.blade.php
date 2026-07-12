<div @if ($polling) wire:poll.3s @endif>
    <div class="mb-6">
        <h1 class="font-sans text-[20px] font-bold tracking-[-0.02em] text-ink">Bases de conhecimento</h1>
        <p class="mt-1 font-sans text-[13px] leading-[1.5] text-ink-3">
            Envie documentos da sua empresa para o agente consultar durante as conversas.
        </p>
    </div>

    {{-- Banner de erro amigável (UI-04): mensagem PT-BR do serviço, sem detalhe técnico. --}}
    @if ($errorMessage)
        <div class="mb-6 max-w-[720px]">
            <x-alert type="danger">{{ $errorMessage }}</x-alert>
        </div>
    @endif

    {{-- Formulário de criação/edição (UI-02). --}}
    <div class="mb-6 max-w-[720px]">
        <x-card :title="$editingStoreId ? 'Editar base de conhecimento' : 'Nova base de conhecimento'">
            <form wire:submit="save" class="flex flex-col gap-4">
                <x-input
                    label="Nome"
                    wire:model="name"
                    name="name"
                    placeholder="Ex.: Manual do produto"
                    :error="$errors->first('name')"
                />

                <div>
                    <label for="description" class="mb-1.5 block font-sans text-xs font-medium text-ink-2">Descrição</label>
                    <textarea
                        id="description"
                        wire:model="description"
                        placeholder="Ex.: Especificações técnicas e políticas de garantia dos produtos"
                        class="w-full box-border min-h-[80px] px-3 py-2.5 rounded-field bg-surface font-sans text-sm text-ink transition-colors focus:outline-none focus:ring-[3px] focus:ring-accent-soft focus:border-accent border border-border resize-y @error('description') border-danger @enderror"
                    ></textarea>
                    @error('description')
                        <p class="mt-1.5 font-sans text-[11.5px] text-danger">{{ $message }}</p>
                    @enderror
                    <p class="mt-1.5 font-sans text-[11.5px] text-ink-3">
                        O agente usa a descrição para saber o que cada base contém.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2.5">
                    @if ($editingStoreId)
                        <x-button type="button" variant="secondary" wire:click="cancelEdit">Cancelar</x-button>
                    @endif
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingStoreId ? 'Salvar alterações' : 'Criar base' }}</span>
                        <span wire:loading wire:target="save">Salvando…</span>
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>

    {{-- Lista escopada à empresa autenticada (RF-03 / UI-01). --}}
    @forelse ($stores as $store)
        @php
            $state = $states[$store->id] ?? null;
            $badge = match ($state['state'] ?? null) {
                \App\Services\Ai\VectorStoreService::StateReady => ['state' => 'completed', 'label' => 'pronto'],
                \App\Services\Ai\VectorStoreService::StateFailed => ['state' => 'failed', 'label' => ($state['failed'] ?? 0).' com falha'],
                \App\Services\Ai\VectorStoreService::StateProcessing => ['state' => 'syncing', 'label' => 'em processamento'],
                default => null,
            };
        @endphp

        <div class="mb-5 max-w-[720px]" wire:key="store-{{ $store->id }}">
            <x-card>
                <x-slot:header>
                    <span class="flex items-center gap-3">
                        <span class="font-sans text-[14px] font-semibold text-ink">{{ $store->name }}</span>
                        @if ($badge)
                            <x-badge :state="$badge['state']">{{ $badge['label'] }}</x-badge>
                        @endif
                    </span>
                    <div class="flex items-center gap-2">
                        <x-button
                            variant="secondary"
                            class="h-8 px-3 text-[12.5px]"
                            wire:click="editStore({{ $store->id }})"
                        >Editar</x-button>
                        <x-button
                            variant="danger"
                            class="h-8 px-3 text-[12.5px]"
                            wire:click="deleteStore({{ $store->id }})"
                            wire:confirm="Excluir esta base de conhecimento e todos os seus arquivos?"
                        >Excluir</x-button>
                    </div>
                </x-slot:header>

                <p class="mb-4 font-sans text-[13px] leading-[1.6] text-ink-2">{{ $store->description }}</p>

                {{-- Controle de upload de um arquivo por vez (UI-03). --}}
                <div class="mb-4 flex flex-col gap-2 rounded-field border border-dashed border-border bg-bg px-3.5 py-3">
                    <input
                        type="file"
                        wire:model="upload"
                        class="block w-full font-sans text-[12.5px] text-ink-2 file:mr-3 file:rounded-field file:border-0 file:bg-surface file:px-3 file:py-1.5 file:font-sans file:text-[12.5px] file:text-ink hover:file:bg-accent-soft"
                    >
                    @error('upload')
                        <p class="font-sans text-[11.5px] text-danger">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-end">
                        <x-button
                            class="h-8 px-3 text-[12.5px]"
                            wire:click="uploadFile({{ $store->id }})"
                            wire:target="upload,uploadFile({{ $store->id }})"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="uploadFile({{ $store->id }})">Enviar arquivo</span>
                            <span wire:loading wire:target="uploadFile({{ $store->id }})">Enviando…</span>
                        </x-button>
                    </div>
                    <p class="font-mono text-[10.5px] text-ink-3">
                        Tipos suportados pelo File Search da OpenAI · máx. 512 MB por arquivo.
                    </p>
                </div>

                {{-- Arquivos do store: nome + status herdado do agregado + remover (UI-03). --}}
                @if ($store->files->isNotEmpty())
                    <div class="overflow-hidden rounded-field border border-border">
                        @foreach ($store->files as $file)
                            <div class="flex items-center justify-between border-b border-border/70 px-3.5 py-2.5 last:border-b-0" wire:key="file-{{ $file->id }}">
                                <span class="flex items-center gap-2.5 truncate">
                                    <span class="truncate font-sans text-[13px] text-ink">{{ $file->filename }}</span>
                                    @if ($badge)
                                        <x-badge :state="$badge['state']">{{ $badge['label'] }}</x-badge>
                                    @endif
                                </span>
                                <x-button
                                    variant="ghost"
                                    class="h-8 px-3 text-[12.5px]"
                                    wire:click="removeFile({{ $file->id }})"
                                    wire:confirm="Remover este arquivo da base?"
                                >Remover</x-button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="font-sans text-[12.5px] text-ink-3">Nenhum arquivo enviado ainda.</p>
                @endif
            </x-card>
        </div>
    @empty
        <div class="max-w-[720px]">
            <x-card>
                <x-empty-state
                    title="Nenhuma base de conhecimento"
                    description="Crie uma base e envie documentos da sua empresa para o agente consultar durante as conversas."
                />
            </x-card>
        </div>
    @endforelse
</div>
