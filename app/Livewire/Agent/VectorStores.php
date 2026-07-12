<?php

namespace App\Livewire\Agent;

use App\Models\User;
use App\Models\VectorStore;
use App\Models\VectorStoreFile;
use App\Services\Ai\Exceptions\VectorStoreOperationException;
use App\Services\Ai\VectorStoreService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Company knowledge-bases management screen. Thin component: it validates,
 * authorizes and delegates every OpenAI/persistence operation to the
 * VectorStoreService (fat service), mirroring how Connect delegates to the
 * CrmDriverManager. All reads/writes are scoped to the authenticated company
 * (tenant) and every store/file action re-checks ownership (abort 403).
 *
 * OpenAI failures surface as a friendly PT-BR banner without technical detail;
 * the store list stays consistent with the persisted state.
 */
#[Layout('components.layouts.app')]
class VectorStores extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:255', as: 'nome', message: ['name.required' => 'Informe o nome da base de conhecimento.'])]
    public string $name = '';

    #[Validate('required|string', as: 'descrição', message: ['description.required' => 'Informe a descrição da base de conhecimento.'])]
    public string $description = '';

    /** The store currently being edited, or null when creating a new one. */
    public ?int $editingStoreId = null;

    /**
     * The pending upload. The extension list is the OpenAI File Search set of
     * indexable document types (RNF-03) and 524288 KB is the 512 MB cap.
     */
    #[Validate(
        'required|file|extensions:c,cpp,cs,css,doc,docx,go,html,java,js,json,md,pdf,php,pptx,py,rb,sh,tex,ts,txt|max:524288',
        message: [
            'upload.required' => 'Selecione um arquivo para enviar.',
            'upload.file' => 'Envie um arquivo válido.',
            'upload.extensions' => 'Tipo de arquivo não suportado pelo File Search da OpenAI.',
            'upload.max' => 'O arquivo excede o limite de 512 MB.',
        ],
        as: 'arquivo',
    )]
    public mixed $upload = null;

    /** Friendly PT-BR banner for OpenAI failures (never a stack trace). */
    public ?string $errorMessage = null;

    /**
     * Create a new store or persist an edit to an existing one, delegating to
     * the service. On an OpenAI failure the PT-BR message is shown as a banner
     * and no partial state is persisted.
     */
    public function save(VectorStoreService $service): void
    {
        $this->reset('errorMessage');
        $this->validateOnly('name');
        $this->validateOnly('description');

        try {
            if ($this->editingStoreId !== null) {
                $service->rename($this->editingStore(), $this->name, $this->description);
            } else {
                $service->createForCompany($this->company(), $this->name, $this->description);
            }
        } catch (VectorStoreOperationException $exception) {
            $this->errorMessage = $exception->getMessage();

            return;
        }

        $this->reset('name', 'description', 'editingStoreId');
    }

    /**
     * Load a store the company owns into the form for editing.
     */
    public function editStore(int $storeId): void
    {
        $store = $this->authorizedStore($storeId);

        $this->reset('errorMessage');
        $this->editingStoreId = $store->id;
        $this->name = $store->name;
        $this->description = $store->description;
    }

    /**
     * Abandon an in-progress edit and clear the form.
     */
    public function cancelEdit(): void
    {
        $this->reset('name', 'description', 'editingStoreId', 'errorMessage');
    }

    /**
     * Delete a store the company owns (remote files + store, then local rows),
     * delegating the ordered/idempotent teardown to the service.
     */
    public function deleteStore(int $storeId, VectorStoreService $service): void
    {
        $store = $this->authorizedStore($storeId);

        $this->reset('errorMessage');

        try {
            $service->deleteStore($store);
        } catch (VectorStoreOperationException $exception) {
            $this->errorMessage = $exception->getMessage();
        }

        if ($this->editingStoreId === $storeId) {
            $this->reset('name', 'description', 'editingStoreId');
        }
    }

    /**
     * Upload a validated file to a store the company owns, delegating the
     * OpenAI upload + persistence to the service.
     */
    public function uploadFile(int $storeId, VectorStoreService $service): void
    {
        $store = $this->authorizedStore($storeId);

        $this->reset('errorMessage');
        $this->validateOnly('upload');

        try {
            $service->addFile($store, $this->upload);
        } catch (VectorStoreOperationException $exception) {
            $this->errorMessage = $exception->getMessage();

            return;
        }

        $this->reset('upload');
    }

    /**
     * Remove a file from a store the company owns, delegating the remote
     * deletion + local cleanup to the service.
     */
    public function removeFile(int $fileId, VectorStoreService $service): void
    {
        /** @var VectorStoreFile $file */
        $file = VectorStoreFile::with('vectorStore')->findOrFail($fileId);
        $this->authorizeStore($file->vectorStore);

        $this->reset('errorMessage');

        try {
            $service->removeFile($file);
        } catch (VectorStoreOperationException $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    /**
     * Resolve a store by id and assert the authenticated company owns it.
     */
    private function authorizedStore(int $storeId): VectorStore
    {
        $store = VectorStore::findOrFail($storeId);
        $this->authorizeStore($store);

        return $store;
    }

    /**
     * Guard: only the owning company may operate on a store (RNF-01).
     */
    private function authorizeStore(VectorStore $store): void
    {
        abort_unless($store->user_id === auth()->id(), 403);
    }

    /**
     * The store currently being edited, re-authorized against the owner.
     */
    private function editingStore(): VectorStore
    {
        return $this->authorizedStore((int) $this->editingStoreId);
    }

    /**
     * The authenticated company (tenant).
     */
    private function company(): User
    {
        /** @var User $company */
        $company = auth()->user();

        return $company;
    }

    public function render(VectorStoreService $service): View
    {
        $stores = $this->company()
            ->vectorStores()
            ->with('files')
            ->latest('id')
            ->get();

        [$states, $polling] = $this->resolveStates($stores, $service);

        return view('livewire.agent.vector-stores', [
            'stores' => $stores,
            'states' => $states,
            'polling' => $polling,
        ]);
    }

    /**
     * Derive the aggregate indexing state of each store that has files, so the
     * badge reflects `indexingState` (RF-07). Polling stays on while any store
     * is not yet ready. A remote failure during polling degrades to
     * "processing" without breaking the page (banner is reserved for actions).
     *
     * @param  Collection<int, VectorStore>  $stores
     * @return array{0: array<int, array{state: string, completed: int, pending: int, failed: int, ready: bool}>, 1: bool}
     */
    private function resolveStates(Collection $stores, VectorStoreService $service): array
    {
        $states = [];
        $polling = false;

        foreach ($stores as $store) {
            if ($store->files->isEmpty()) {
                continue;
            }

            try {
                $state = $service->indexingState($store);
            } catch (\Throwable) {
                $state = [
                    'state' => VectorStoreService::StateProcessing,
                    'completed' => 0,
                    'pending' => $store->files->count(),
                    'failed' => 0,
                    'ready' => false,
                ];
            }

            $states[$store->id] = $state;

            if ($state['state'] !== VectorStoreService::StateReady) {
                $polling = true;
            }
        }

        return [$states, $polling];
    }
}
