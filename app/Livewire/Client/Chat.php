<?php

namespace App\Livewire\Client;

use App\Ai\Agents\GuardrailAgent;
use App\Ai\Agents\OutputGuardrailAgent;
use App\Ai\Agents\SellerAgent;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageRole;
use App\Models\User;
use App\Services\ClientAccess\MagicLinkService;
use ArrayAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
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
    /**
     * The safe redirect reply shown to the client when the guardrail blocks a
     * message (or fails closed). Fixed in code, PT-BR, and deliberately silent
     * about the detected category and the guardrail's internal instructions.
     */
    public const string GuardrailRedirectMessage = 'Não consigo ajudar com esse assunto por aqui. Posso te ajudar com dúvidas sobre sua relação comercial com a empresa — é só perguntar.';

    /**
     * The safe reply shown to the client when the OUTPUT guardrail retracts an
     * ungrounded (hallucinated) SellerAgent reply. Fixed in code, PT-BR, and
     * deliberately silent about which business fact was fabricated.
     */
    public const string OutputGuardrailFallbackMessage = 'Não consegui confirmar essa informação com segurança agora. Para não te passar um dado incorreto, prefiro te encaminhar a um atendente humano — quer que eu faça isso?';

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

        if (! $userMessage || $groupIndex === null) {
            $this->streaming = false;
            $this->pendingMessageId = null;

            return;
        }

        // The guardrail verdict comes first: no SellerAgent invocation may
        // happen before it, and block/error never reach the SellerAgent.
        [$verdict, $category] = $this->guardrailVerdict($userMessage);

        $this->recordGuardrailVerdict($groupIndex, $userMessage, $verdict, $category);

        if ($verdict !== 'allow') {
            $this->blockPendingMessage($groupIndex, $userMessage);

            return;
        }

        try {
            $agent = new SellerAgent($this->conversation, $userMessage->id);

            $text = '';

            // Tool/provider-tool results gathered on this turn — the primary
            // grounding source handed to the output guardrail after the stream.
            /** @var list<array{name: string, result: string}> $toolEvidence */
            $toolEvidence = [];

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

                    if ($streamEvent->successful) {
                        $toolEvidence[] = [
                            'name' => $streamEvent->toolResult->name,
                            'result' => $this->stringifyEvidence($streamEvent->toolResult->result),
                        ];
                    }

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

                    if ($streamEvent->status === 'completed' && filled($streamEvent->data)) {
                        $toolEvidence[] = [
                            'name' => $streamEvent->type,
                            'result' => $this->stringifyEvidence($streamEvent->data),
                        ];
                    }

                    continue;
                }

                if ($streamEvent instanceof ErrorEvent) {
                    throw new \RuntimeException($streamEvent->message);
                }
            }

            $fullText = $stream->text ?? $text;

            $assistantMessage = $this->conversation->messages()->create([
                'message_role_id' => $this->roleId('assistant'),
                'content' => $fullText,
            ]);

            $this->pushActivityEvent(
                $groupIndex,
                'stream.delta',
                mb_strlen($fullText).' caracteres agregados',
            );

            // Output guardrail: with the reply persisted, verify it is grounded
            // in the tool results, playbook and history the SellerAgent had. An
            // `ungrounded` verdict retracts the reply in place (the client saw
            // the stream, but the final rendered turn becomes the safe
            // fallback); a checker `error` fails OPEN — the reply is kept so a
            // transient failure never retracts a legitimate answer.
            [$outputVerdict, $unsupportedClaims] = $this->outputGuardrailVerdict(
                $fullText,
                $toolEvidence,
                $assistantMessage->id,
            );

            $this->recordOutputVerdict($groupIndex, $assistantMessage, $outputVerdict, $unsupportedClaims);

            if ($outputVerdict === 'ungrounded') {
                $this->retractUngroundedReply($assistantMessage);
            } else {
                $this->pushActivityEvent(
                    $groupIndex,
                    'response.completed',
                    'resposta concluída',
                    ['characters' => mb_strlen($fullText)],
                );
            }

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

    /**
     * Classify the pending user message with the guardrail, exactly once and
     * without streaming, BEFORE any SellerAgent invocation. Returns the
     * [verdict, category] pair where verdict ∈ allow|block|error: a provider
     * failure or a structured output outside the CT-01 contract collapses
     * into the fail-closed `error` verdict (RF-08) — never into an allow.
     *
     * @return array{0: string, 1: string|null}
     */
    private function guardrailVerdict(Message $userMessage): array
    {
        try {
            $response = (new GuardrailAgent($this->conversation, $userMessage->id))
                ->prompt($userMessage->content);

            if (! $response instanceof ArrayAccess) {
                return ['error', null];
            }

            $verdict = $response['verdict'] ?? null;
            $category = $response['category'] ?? null;

            if ($verdict === 'allow') {
                return ['allow', null];
            }

            if ($verdict === 'block' && in_array($category, GuardrailAgent::Categories, true)) {
                return ['block', $category];
            }

            return ['error', null];
        } catch (Throwable) {
            return ['error', null];
        }
    }

    /**
     * Terminal path for block and error verdicts: the SellerAgent is never
     * invoked, the client's turn is marked as blocked, and the fixed safe
     * redirect is persisted as the assistant turn — itself marked as blocked
     * so neither ever re-enters the SellerAgent history (RF-03, RF-08).
     */
    private function blockPendingMessage(int $groupIndex, Message $userMessage): void
    {
        $userMessage->update(['blocked_at' => now()]);

        $this->conversation->messages()->create([
            'message_role_id' => $this->roleId('assistant'),
            'content' => self::GuardrailRedirectMessage,
            'blocked_at' => now(),
        ]);

        $this->activity[$groupIndex]['status'] = 'completed';
        $this->streaming = false;
        $this->pendingMessageId = null;
    }

    /**
     * Single observability point for every guardrail outcome (allow, block,
     * error): one structured log entry (CT-03) and one `guardrail.verdict`
     * activity event (CT-02) per processed message. The raw message content
     * is never included in the logged context (RNF-03).
     */
    private function recordGuardrailVerdict(int $groupIndex, Message $userMessage, string $verdict, ?string $category): void
    {
        Log::info('guardrail.verdict', [
            'conversation_id' => $this->conversation->id,
            'message_id' => $userMessage->id,
            'verdict' => $verdict,
            'category' => $category,
        ]);

        $summary = match ($verdict) {
            'allow' => 'guardrail liberou a mensagem',
            'block' => 'guardrail bloqueou a mensagem',
            default => 'guardrail falhou — mensagem bloqueada por segurança',
        };

        $this->pushActivityEvent($groupIndex, 'guardrail.verdict', $summary, [
            'verdict' => $verdict,
            'category' => $category,
        ]);
    }

    /**
     * Classify a completed SellerAgent reply with the output guardrail, exactly
     * once and without streaming. Returns [verdict, unsupportedClaims] where
     * verdict ∈ grounded|ungrounded|error. Unlike the input guardrail this fails
     * OPEN: a provider failure or an out-of-contract structured output collapses
     * to `error`, which keeps the reply — retracting every legitimate answer on
     * a transient checker failure would be worse than the residual risk. An
     * empty reply is trivially grounded (nothing to verify).
     *
     * @param  list<array{name: string, result: string}>  $toolEvidence
     * @return array{0: string, 1: list<string>}
     */
    private function outputGuardrailVerdict(string $reply, array $toolEvidence, int $replyMessageId): array
    {
        if (trim($reply) === '') {
            return ['grounded', []];
        }

        try {
            $response = (new OutputGuardrailAgent($this->conversation, $reply, $toolEvidence, $replyMessageId))
                ->prompt('Verifique a resposta do agente conforme as instruções e emita o veredito.');

            if (! $response instanceof ArrayAccess) {
                return ['error', []];
            }

            $verdict = $response['verdict'] ?? null;
            $claims = $response['unsupported_claims'] ?? [];

            if ($verdict === 'grounded') {
                return ['grounded', []];
            }

            if ($verdict === 'ungrounded') {
                return ['ungrounded', is_array($claims) ? array_values(array_map('strval', $claims)) : []];
            }

            return ['error', []];
        } catch (Throwable) {
            return ['error', []];
        }
    }

    /**
     * Retract an ungrounded reply: overwrite its persisted content with the
     * fixed safe fallback and mark it blocked. Marking it blocked keeps the
     * hallucinated turn (and its retraction) out of the SellerAgent history so
     * it can never re-enter context on a later turn. The re-render replaces the
     * streamed bubble with the fallback (the streamed text is never persisted).
     */
    private function retractUngroundedReply(Message $assistantMessage): void
    {
        $assistantMessage->update([
            'content' => self::OutputGuardrailFallbackMessage,
            'blocked_at' => now(),
        ]);
    }

    /**
     * Single observability point for every output-guardrail outcome (grounded,
     * ungrounded, error): one structured log entry and one `guardrail.output`
     * activity event per verified reply. The raw reply content is never logged
     * (RNF-03) — only the verdict and the count of unsupported claims.
     *
     * @param  list<string>  $unsupportedClaims
     */
    private function recordOutputVerdict(int $groupIndex, Message $assistantMessage, string $verdict, array $unsupportedClaims): void
    {
        Log::info('guardrail.output_verdict', [
            'conversation_id' => $this->conversation->id,
            'message_id' => $assistantMessage->id,
            'verdict' => $verdict,
            'unsupported_claims_count' => count($unsupportedClaims),
        ]);

        $summary = match ($verdict) {
            'grounded' => 'guardrail de saída liberou a resposta',
            'ungrounded' => 'guardrail de saída retraiu a resposta — sem fundamento',
            default => 'guardrail de saída falhou — resposta mantida (fail-open)',
        };

        $this->pushActivityEvent($groupIndex, 'guardrail.output', $summary, [
            'verdict' => $verdict,
            'unsupported_claims' => $unsupportedClaims,
        ]);
    }

    /**
     * Normalise a tool/provider-tool result into a string for the grounding
     * prompt: strings pass through, structured payloads are JSON-encoded.
     */
    private function stringifyEvidence(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
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
