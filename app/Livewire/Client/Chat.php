<?php

namespace App\Livewire\Client;

use App\Ai\Agents\SellerAgent;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\MessageRole;
use App\Models\User;
use App\Services\ClientAccess\MagicLinkService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Laravel\Ai\Streaming\Events\Error as ErrorEvent;
use Laravel\Ai\Streaming\Events\ProviderToolEvent;
use Laravel\Ai\Streaming\Events\ReasoningEnd;
use Laravel\Ai\Streaming\Events\ReasoningStart;
use Laravel\Ai\Streaming\Events\StreamStart;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * The final client's chat with a company's sales agent. On mount it
 * materialises (or resumes) the single conversation for the client+company
 * pair, then persists every turn — the client message first (so it survives a
 * provider failure) and the streamed agent reply once it completes.
 *
 * The activity panel is populated with ephemeral, per-response events that live
 * only in component state: they are grouped by "RESPOSTA #N" and vanish on a
 * full page reload since they are never persisted.
 */
#[Layout('components.layouts.chat')]
class Chat extends Component
{
    public Conversation $conversation;

    /**
     * The draft message currently being typed.
     */
    public string $body = '';

    /**
     * Whether an agent response is currently streaming (locks the composer).
     */
    public bool $streaming = false;

    /**
     * Ephemeral activity groups, one per response, oldest first. Not persisted.
     *
     * @var array<int, array{n: int, status: string, label: string, events: array<int, array<string, mixed>>}>
     */
    public array $activity = [];

    /**
     * Number of responses requested this session (drives "RESPOSTA #N").
     */
    public int $responseCount = 0;

    /**
     * Id of the persisted user message awaiting an agent reply. Bridges the
     * two-step send flow: sendMessage() persists and renders the user turn,
     * then generateResponse() streams the reply for this message.
     */
    public ?int $pendingMessageId = null;

    /**
     * Resolve the selected company, guard the client's access to it, and
     * materialise/resume the conversation for the pair.
     */
    public function mount(MagicLinkService $magicLinks): ?RedirectResponse
    {
        $companyId = session('selected_company_id');

        $companies = $magicLinks->matchedCompanies($this->client()->email);

        // No company chosen yet, or the client no longer matches it: send them
        // back through selection (which itself auto-picks a lone match).
        if (! $companyId || ! $companies->contains('id', $companyId)) {
            return redirect()->route('client.company-selection');
        }

        $this->conversation = Conversation::firstOrCreate([
            'client_id' => $this->client()->id,
            'user_id' => $companyId,
        ]);

        return null;
    }

    /**
     * Persist the typed message and render it immediately, then kick off the
     * streamed agent reply in a follow-up request.
     *
     * The client message is persisted (and shown) before the AI call so a
     * provider failure never loses it and the user sees their own turn right
     * away instead of only after the stream finishes.
     */
    public function sendMessage(): void
    {
        $content = trim($this->body);

        if ($content === '' || $this->streaming) {
            return;
        }

        $this->body = '';
        $this->streaming = true;

        $userMessage = $this->conversation->messages()->create([
            'message_role_id' => $this->roleId('user'),
            'content' => $content,
        ]);
        $this->pendingMessageId = $userMessage->id;

        $this->responseCount++;
        $this->activity[] = [
            'n' => $this->responseCount,
            'status' => 'running',
            'label' => now()->format('H:i'),
            'events' => [
                $this->event('request.started', $this->conversation->messages()->count().' mensagens enviadas', [
                    'messages' => $this->conversation->messages()->count(),
                ]),
            ],
        ];

        $this->js('$wire.generateResponse()');
    }

    /**
     * Stream the agent reply for the pending user message and persist it once
     * the stream completes with its full text.
     */
    public function generateResponse(): void
    {
        if (! $this->pendingMessageId) {
            return;
        }

        $userMessage = $this->conversation->messages()->find($this->pendingMessageId);
        $groupIndex = array_key_last($this->activity);

        if (! $userMessage) {
            $this->streaming = false;
            $this->pendingMessageId = null;

            return;
        }

        try {
            $agent = new SellerAgent($this->conversation, $userMessage->id);

            $text = '';
            $stream = $agent->stream($userMessage->content);

            foreach ($stream as $streamEvent) {
                if ($streamEvent instanceof StreamStart) {
                    $this->pushActivityEvent(
                        $groupIndex,
                        'stream.start',
                        $streamEvent->model.' em execução',
                        ['provider' => $streamEvent->provider, 'model' => $streamEvent->model],
                    );
                    $this->stream(to: 'agent-model', content: $streamEvent->model);

                    continue;
                }

                if ($streamEvent instanceof TextDelta) {
                    $text .= $streamEvent->delta;
                    $this->stream(to: 'agent-response', content: $streamEvent->delta);

                    continue;
                }

                if ($streamEvent instanceof ReasoningStart) {
                    $this->pushActivityEvent($groupIndex, 'reasoning', 'modelo raciocinando…');

                    continue;
                }

                if ($streamEvent instanceof ReasoningEnd) {
                    $this->pushActivityEvent(
                        $groupIndex,
                        'reasoning',
                        'raciocínio concluído',
                        filled($streamEvent->summary) ? ['summary' => $streamEvent->summary] : null,
                    );

                    continue;
                }

                if ($streamEvent instanceof ToolCall) {
                    $this->pushActivityEvent(
                        $groupIndex,
                        'tool.called',
                        $streamEvent->toolCall->name,
                        ['arguments' => $streamEvent->toolCall->arguments],
                    );

                    continue;
                }

                if ($streamEvent instanceof ToolResult) {
                    $this->pushActivityEvent(
                        $groupIndex,
                        $streamEvent->successful ? 'tool.result' : 'error',
                        $streamEvent->toolResult->name.($streamEvent->successful ? '' : ' falhou'),
                        ['result' => $streamEvent->toolResult->result, 'error' => $streamEvent->error],
                    );

                    continue;
                }

                // Provider-side tools (e.g. web search) stream progress as
                // generic events: completed steps land as tool.result, the
                // rest as tool.called.
                if ($streamEvent instanceof ProviderToolEvent) {
                    $this->pushActivityEvent(
                        $groupIndex,
                        $streamEvent->status === 'completed' ? 'tool.result' : 'tool.called',
                        $streamEvent->type.' · '.$streamEvent->status,
                        $streamEvent->data ?: null,
                    );

                    continue;
                }

                if ($streamEvent instanceof ErrorEvent) {
                    throw new \RuntimeException($streamEvent->message);
                }
            }

            $fullText = $stream->text ?? $text;

            $this->conversation->messages()->create([
                'message_role_id' => $this->roleId('assistant'),
                'content' => $fullText,
            ]);

            $this->pushActivityEvent(
                $groupIndex,
                'stream.delta',
                mb_strlen($fullText).' caracteres agregados',
            );
            $this->pushActivityEvent(
                $groupIndex,
                'response.completed',
                'resposta concluída',
                ['characters' => mb_strlen($fullText)],
            );
            $this->activity[$groupIndex]['status'] = 'completed';
        } catch (Throwable $exception) {
            $this->pushActivityEvent(
                $groupIndex,
                'error',
                'Falha ao gerar a resposta do agente.',
                ['message' => $exception->getMessage()],
            );
            $this->activity[$groupIndex]['status'] = 'error';

            $this->addError(
                'agent',
                'O agente não conseguiu responder agora. Sua mensagem foi mantida — tente reenviar.',
            );
        } finally {
            $this->streaming = false;
            $this->pendingMessageId = null;
        }
    }

    public function render(): View
    {
        return view('livewire.client.chat', [
            'company' => $this->company(),
            'messages' => $this->conversation->messages()->with('role')->orderBy('id')->get(),
            'canSwitchCompany' => $this->canSwitchCompany(),
        ]);
    }

    /**
     * The company (tenant) this conversation belongs to.
     */
    private function company(): User
    {
        return $this->conversation->user;
    }

    /**
     * The "Trocar empresa" affordance is only offered when the client's email
     * matches more than one company.
     */
    private function canSwitchCompany(): bool
    {
        return app(MagicLinkService::class)
            ->matchedCompanies($this->client()->email)
            ->count() > 1;
    }

    private function client(): Client
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        return $client;
    }

    /**
     * Record an activity event and push it live to the panel while the
     * response is still streaming — component state alone only reaches the
     * browser when the request finishes, so each event is also streamed as
     * rendered HTML into the running group's wire:stream containers.
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function pushActivityEvent(int $groupIndex, string $type, string $summary, ?array $payload = null): void
    {
        $event = $this->event($type, $summary, $payload);
        $this->activity[$groupIndex]['events'][] = $event;

        $html = Blade::render(
            '<x-agent-event :type="$type" :timestamp="$timestamp" :summary="$summary" :payload="$payload" />',
            $event,
        );

        $this->stream(to: 'activity-live', content: $html);
        $this->stream(to: 'activity-live-mobile', content: $html);
    }

    /**
     * Build one ephemeral activity event for the panel.
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{type: string, timestamp: string, summary: string, payload: array<string, mixed>|null}
     */
    private function event(string $type, string $summary, ?array $payload = null): array
    {
        return [
            'type' => $type,
            'timestamp' => now()->format('H:i:s.v'),
            'summary' => $summary,
            'payload' => $payload,
        ];
    }

    /**
     * Resolve a message role id by slug, seeding the lookup if absent (mirrors
     * the message factory so the component works with or without seeded data).
     */
    private function roleId(string $slug): int
    {
        $names = ['user' => 'Cliente', 'assistant' => 'Agente'];

        return MessageRole::firstOrCreate(
            ['slug' => $slug],
            ['name' => $names[$slug] ?? ucfirst($slug)],
        )->id;
    }
}
