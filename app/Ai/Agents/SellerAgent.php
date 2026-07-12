<?php

namespace App\Ai\Agents;

use App\Models\Conversation;
use App\Models\Message as MessageModel;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * The final-client facing sales agent. In the MVP it is driven solely by the
 * global system prompt fixed in code below — it has no CRM data, only the
 * provider's web search tool for public information. Conversation context is
 * fed from the local `messages` table so the agent can answer with awareness
 * of what was said before in this client+company chat.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-5.6-terra')]
class SellerAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * The global system prompt shared by every company's agent (fixed in code).
     */
    public const string SystemPrompt = <<<'PROMPT'
        Você é o agente de vendas virtual da empresa, conversando diretamente com um cliente final pelo chat.

        Diretrizes:
        - Responda sempre em português do Brasil, com tom cordial, claro e objetivo.
        - Seja prestativo e ajude o cliente a tirar dúvidas sobre a relação comercial dele com a empresa.
        - Você não tem acesso aos dados do CRM: responda com base no que o cliente disser na conversa.
        - Você pode buscar na web informações públicas (endereços, notícias, dados de mercado) quando isso ajudar a responder.
        - Se não souber ou não tiver a informação, diga isso com transparência e oriente o cliente a falar com um atendente humano.
        - Nunca invente dados de negócios, valores, prazos ou etapas que não tenham sido informados na conversa.
        PROMPT;

    /**
     * @param  Conversation  $conversation  The client+company chat this agent speaks in.
     * @param  int|null  $historyBeforeMessageId  When set, only messages older than this
     *                                            id are loaded as context, so the current
     *                                            (already persisted) user turn is not
     *                                            duplicated alongside the live prompt.
     */
    public function __construct(
        public Conversation $conversation,
        public ?int $historyBeforeMessageId = null,
    ) {}

    /**
     * The global system prompt fixed in code (same for every company in the MVP).
     */
    public function instructions(): string
    {
        return self::SystemPrompt;
    }

    /**
     * Provider-side tools available to the agent (web search only, capped so a
     * single reply never fans out into many searches).
     *
     * @return iterable<int, object>
     */
    public function tools(): iterable
    {
        return [
            new WebSearch(maxSearches: 3),
        ];
    }

    /**
     * Prior conversation turns, in chronological order, as agent context.
     * Blocked turns (and their redirect replies) never reach this agent.
     *
     * @return array<int, Message>
     */
    public function messages(): iterable
    {
        return $this->conversation->messages()
            ->with('role')
            ->whereNull('blocked_at')
            ->when(
                $this->historyBeforeMessageId,
                fn ($query) => $query->where('id', '<', $this->historyBeforeMessageId),
            )
            ->orderBy('id')
            ->get()
            ->map(fn (MessageModel $message): Message => new Message($message->role->slug, $message->content))
            ->all();
    }
}
