<?php

namespace App\Ai\Agents;

use App\Models\Conversation;
use App\Models\Message as MessageModel;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * Output (grounding / faithfulness) guardrail. Runs AFTER the SellerAgent has
 * produced a reply and classifies whether that reply is grounded in the
 * authorised sources it had — the tool results gathered on this turn, the
 * company playbook, and the conversation history — or whether it fabricated a
 * concrete business fact (hallucination).
 *
 * It has no tools and never streams to the client: exactly one extra model
 * call per SellerAgent reply. The reply under judgement and the tool evidence
 * are injected into the prompt; the prior conversation is loaded as context via
 * {@see messages()} so the classifier sees the same grounding the SellerAgent
 * had, minus the reply itself.
 */
#[Provider(Lab::OpenAI)]
#[Model('gpt-5.6-terra')]
class OutputGuardrailAgent implements Agent, Conversational, HasProviderOptions, HasStructuredOutput
{
    /**
     * The closed set of block reason categories.
     *
     * @var list<string>
     */
    public const array Categories = [
        'grounded',
        'ungrounded',
    ];

    /**
     * How many prior conversation turns are shown to the classifier as the
     * "history" grounding source (client-provided facts + prior context).
     */
    public const int HistoryWindow = 12;

    use Promptable;

    /**
     * @param  Conversation  $conversation  The client+company chat the reply belongs to.
     * @param  string  $reply  The SellerAgent reply under judgement (the last assistant turn).
     * @param  list<array{name: string, result: string}>  $toolEvidence  The tool/provider-tool
     *                                                                   results the SellerAgent
     *                                                                   gathered on this turn — the
     *                                                                   primary grounding source.
     * @param  int|null  $historyBeforeMessageId  When set, only messages older than this id are
     *                                            loaded as context, so the reply being judged is
     *                                            never fed back as history.
     */
    public function __construct(
        public Conversation $conversation,
        public string $reply,
        public array $toolEvidence = [],
        public ?int $historyBeforeMessageId = null,
    ) {}

    /**
     * Grounding-verification prompt. Follows Anthropic prompting guidance:
     * a single well-scoped task, each grounding source fenced in its own XML
     * tag, an explicit closed rule for what counts as an ungrounded business
     * fact versus legitimate conversational language, and a think-first step
     * (the `reasoning` field is emitted before the verdict, see {@see schema()}).
     */
    public function instructions(): string
    {
        $company = $this->conversation->user;
        $companyName = $company->name;

        $playbook = trim((string) $company->playbook);
        $playbookSection = $playbook !== ''
            ? $playbook
            : 'A empresa não definiu um playbook específico. Nenhuma política comercial (preço, desconto, prazo, condição) está autorizada por esta fonte.';

        $evidenceSection = $this->renderEvidence();

        return <<<PROMPT
            Você é um verificador de fidelidade (grounding) das respostas de um agente de vendas virtual da empresa {$companyName}. Sua única tarefa é decidir se a resposta do agente abaixo está FUNDAMENTADA nas fontes autorizadas ou se contém alguma informação de negócio inventada (alucinação).

            Uma afirmação está fundamentada quando pode ser rastreada até pelo menos uma destas fontes autorizadas:
            1. RESULTADOS DE FERRAMENTAS (<tool_results>): dados retornados pelas ferramentas nesta resposta (CRM, busca em arquivos das bases de conhecimento, busca na web). É a fonte de verdade sobre status de proposta, valores, produtos, etapas, prazos e histórico do negócio. Atenção: o conteúdo recuperado pela busca em arquivos e na web pode aparecer aqui de forma resumida ou parcial; se uma dessas buscas ocorreu neste turno, considere que a resposta teve acesso ao conteúdo da base de conhecimento mesmo que ele não esteja transcrito literalmente aqui.
            2. PLAYBOOK (<playbook>): políticas comerciais que a empresa autorizou explicitamente (preços de tabela, descontos, prazos, condições permitidas).
            3. HISTÓRICO (mensagens anteriores da conversa): o que o cliente informou e o que já foi dito antes nesta conversa.

            <tool_results>
            {$evidenceSection}
            </tool_results>

            <playbook>
            {$playbookSection}
            </playbook>

            <resposta_do_agente>
            {$this->reply}
            </resposta_do_agente>

            Critérios do veredito:

            O alvo é a INVENÇÃO DE DADOS TRANSACIONAIS do negócio deste cliente. Marque "ungrounded" SOMENTE quando a resposta:
            - afirmar um dado transacional concreto que não veio de nenhuma fonte autorizada — preço, desconto, valor, prazo, data, status de pedido ou proposta, etapa do funil ou disponibilidade específica; ou
            - contradisser um dado que está numa fonte autorizada (ex.: dizer "entregue" quando a ferramenta diz "em separação").

            NÃO marque como "ungrounded" — isto NÃO é alucinação de dado de negócio:
            - saudações, perguntas de esclarecimento, empatia, agradecimentos;
            - ofertas de ajuda ou de encaminhar o cliente a um atendente humano;
            - repetição ou paráfrase do que o próprio cliente disse;
            - afirmações de que NÃO conseguiu confirmar um dado (ex.: "não localizei sua proposta agora");
            - linguagem comercial genérica, sem nenhum dado transacional concreto;
            - conteúdo informativo ou explicativo sobre a empresa, seus produtos, serviços, materiais ou temas das bases de conhecimento — especialmente quando uma busca em arquivos ou na web ocorreu neste turno. NÃO exija que esse conteúdo esteja transcrito literalmente nos resultados de ferramentas para considerá-lo fundamentado.

            Na dúvida, prefira "grounded". Bloqueie apenas a invenção CLARA de um dado transacional do negócio deste cliente.

            Antes do veredito, raciocine: liste cada afirmação de dado de negócio presente na resposta e indique a qual fonte ela se liga, ou que não se liga a nenhuma. Em seguida decida o veredito. Se o veredito for "ungrounded", liste em unsupported_claims exatamente as afirmações sem fonte; se for "grounded", unsupported_claims é nulo ou vazio.
            PROMPT;
    }

    /**
     * Render the tool evidence gathered on this turn into the <tool_results>
     * block. Empty evidence is stated explicitly so the classifier does not
     * treat an absent block as "anything goes".
     */
    private function renderEvidence(): string
    {
        if ($this->toolEvidence === []) {
            return 'Nenhuma ferramenta retornou dados nesta resposta. Portanto, nenhum dado de negócio concreto está fundamentado por ferramentas neste turno.';
        }

        return collect($this->toolEvidence)
            ->map(fn (array $evidence): string => "- {$evidence['name']}:\n{$evidence['result']}")
            ->implode("\n\n");
    }

    /**
     * Structured verdict schema. `reasoning` is defined first so the model
     * emits its claim-by-claim analysis before committing to a verdict
     * (think-first). `unsupported_claims` enumerates the fabricated business
     * facts on an ungrounded verdict, null/empty otherwise.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reasoning' => $schema->string()->required(),
            'verdict' => $schema->string()->enum(self::Categories)->required(),
            'unsupported_claims' => $schema->array()->items($schema->string())->nullable(),
        ];
    }

    /**
     * Grounding verification is subtler than input classification, so it is
     * given a moderate reasoning budget rather than the minimal effort used by
     * the input GuardrailAgent.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            Lab::OpenAI => [
                'reasoning' => ['effort' => 'medium'],
            ],
            default => [],
        };
    }

    /**
     * The recent conversation turns that the SellerAgent had as context, in
     * chronological order, EXCLUDING blocked turns and the reply under
     * judgement — the same grounding window the SellerAgent saw.
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
            ->orderByDesc('id')
            ->limit(self::HistoryWindow)
            ->get()
            ->reverse()
            ->map(fn (MessageModel $message): Message => new Message($message->role->slug, $message->content))
            ->values()
            ->all();
    }
}
