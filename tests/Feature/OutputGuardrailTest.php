<?php

use App\Ai\Agents\GuardrailAgent;
use App\Ai\Agents\OutputGuardrailAgent;
use App\Ai\Agents\SellerAgent;
use App\Livewire\Client\Chat;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The guardrail.output activity events pushed to the component's panel.
 */
function outputActivityEvents(Testable $component): array
{
    return collect($component->get('activity'))
        ->flatMap(fn (array $group) => $group['events'])
        ->where('type', 'guardrail.output')
        ->values()
        ->all();
}

/**
 * Drive the full chat flow for one client message with the three agents faked:
 * input guardrail allows, SellerAgent replies, output guardrail returns the
 * given verdict.
 *
 * @param  callable|array<int, mixed>  $outputVerdict
 */
function sendWithOutputVerdict(string $reply, callable|array $outputVerdict): Testable
{
    GuardrailAgent::fake([['verdict' => 'allow', 'category' => null]]);
    SellerAgent::fake([$reply]);
    OutputGuardrailAgent::fake($outputVerdict);

    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    return Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->set('body', 'qual o status do meu pedido?')
        ->call('sendMessage')
        ->call('generateResponse');
}

// ── Agent contract: schema, config, history window ───────────────────────────

test('output_guardrail_schema_puts_reasoning_before_verdict', function () {
    $conversation = Conversation::factory()->create();

    $types = (new OutputGuardrailAgent($conversation, 'resposta'))->schema(new JsonSchemaTypeFactory);

    // reasoning first forces the model to analyse claim-by-claim before deciding.
    expect(array_keys($types))->toBe(['reasoning', 'verdict', 'unsupported_claims']);

    expect($types['reasoning']->toArray()['type'])->toBe('string');

    $verdict = $types['verdict']->toArray();
    expect($verdict['type'])->toBe('string');
    expect($verdict['enum'])->toBe(['grounded', 'ungrounded']);
});

test('output_guardrail_has_no_tools_and_uses_medium_reasoning_effort', function () {
    $agent = new OutputGuardrailAgent(Conversation::factory()->create(), 'resposta');

    expect($agent)->not->toBeInstanceOf(HasTools::class);
    expect($agent->providerOptions(Lab::OpenAI))->toBe(['reasoning' => ['effort' => 'medium']]);
});

test('output_guardrail_instructions_inject_reply_playbook_and_evidence', function () {
    $company = User::factory()->create(['playbook' => 'Desconto máximo autorizado: 10%.']);
    $conversation = Conversation::factory()->for($company, 'user')->create();

    $agent = new OutputGuardrailAgent(
        $conversation,
        reply: 'Seu pedido está na etapa Proposta.',
        toolEvidence: [
            ['name' => 'GetDealDataTool', 'result' => '{"stage":"Proposta"}'],
        ],
    );

    $instructions = $agent->instructions();

    expect($instructions)
        ->toContain('Seu pedido está na etapa Proposta.')          // reply under judgement
        ->toContain('Desconto máximo autorizado: 10%.')             // playbook source
        ->toContain('GetDealDataTool')                              // tool evidence
        ->toContain('{"stage":"Proposta"}')
        ->toContain('ungrounded')                                   // closed verdict named
        ->toContain('encaminhar');                                  // human-handoff is not a hallucination
});

test('output_guardrail_states_absence_of_tool_evidence_explicitly', function () {
    $agent = new OutputGuardrailAgent(Conversation::factory()->create(), 'olá!', toolEvidence: []);

    expect($agent->instructions())->toContain('Nenhuma ferramenta retornou dados nesta resposta');
});

test('output_guardrail_history_excludes_reply_and_blocked_turns', function () {
    $conversation = Conversation::factory()->create();

    Message::factory()->fromUser()->for($conversation)->create(['content' => 'pergunta legítima']);
    Message::factory()->fromAssistant()->blocked()->for($conversation)->create(['content' => 'alucinação retraída']);
    $reply = Message::factory()->fromAssistant()->for($conversation)->create(['content' => 'resposta em julgamento']);

    $history = collect((new OutputGuardrailAgent($conversation, 'resposta em julgamento', historyBeforeMessageId: $reply->id))->messages());

    expect($history->pluck('content')->all())->toBe(['pergunta legítima']);
    expect($history->pluck('content'))
        ->not->toContain('resposta em julgamento')
        ->not->toContain('alucinação retraída');
});

// ── Orchestration in the chat (stream live + retract) ────────────────────────

test('grounded_reply_is_kept_and_verdict_recorded', function () {
    Log::spy();

    $component = sendWithOutputVerdict('Seu pedido foi entregue ontem.', [
        ['reasoning' => 'rastreado até a ferramenta', 'verdict' => 'grounded', 'unsupported_claims' => null],
    ]);

    OutputGuardrailAgent::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->agent->reply === 'Seu pedido foi entregue ontem.',
    );

    $messages = Conversation::sole()->messages()->with('role')->orderBy('id')->get();

    expect($messages)->toHaveCount(2);
    expect($messages[1]->content)->toBe('Seu pedido foi entregue ontem.');
    expect($messages[1]->blocked_at)->toBeNull();

    Log::shouldHaveReceived('info')
        ->with('guardrail.output_verdict', [
            'conversation_id' => $messages[1]->conversation_id,
            'message_id' => $messages[1]->id,
            'verdict' => 'grounded',
            'unsupported_claims_count' => 0,
        ])
        ->once();

    $events = outputActivityEvents($component);
    expect($events)->toHaveCount(1);
    expect($events[0]['payload'])->toBe(['verdict' => 'grounded', 'unsupported_claims' => []]);
});

test('ungrounded_reply_is_retracted_to_safe_fallback', function () {
    Log::spy();

    $hallucination = 'Consigo um desconto especial de 40% só para você hoje.';

    $component = sendWithOutputVerdict($hallucination, [
        [
            'reasoning' => 'desconto de 40% não aparece em nenhuma fonte',
            'verdict' => 'ungrounded',
            'unsupported_claims' => ['desconto especial de 40%'],
        ],
    ]);

    $messages = Conversation::sole()->messages()->with('role')->orderBy('id')->get();

    // The user turn stays intact (the question was legitimate); only the reply
    // is retracted to the fixed safe fallback and marked blocked.
    expect($messages)->toHaveCount(2);
    expect($messages[0]->role->slug)->toBe('user');
    expect($messages[0]->blocked_at)->toBeNull();

    expect($messages[1]->role->slug)->toBe('assistant');
    expect($messages[1]->content)->toBe(Chat::OutputGuardrailFallbackMessage);
    expect($messages[1]->blocked_at)->not->toBeNull();

    // The fixed fallback never leaks the fabricated fact.
    expect(Chat::OutputGuardrailFallbackMessage)->not->toContain('40%');

    $component->assertSet('streaming', false)->assertSet('pendingMessageId', null);

    Log::shouldHaveReceived('info')
        ->with('guardrail.output_verdict', [
            'conversation_id' => $messages[1]->conversation_id,
            'message_id' => $messages[1]->id,
            'verdict' => 'ungrounded',
            'unsupported_claims_count' => 1,
        ])
        ->once();

    // The raw hallucinated reply is never written to the log context.
    Log::shouldNotHaveReceived(
        'info',
        fn (...$arguments) => str_contains(json_encode($arguments), $hallucination),
    );

    $events = outputActivityEvents($component);
    expect($events)->toHaveCount(1);
    expect($events[0]['payload'])->toBe([
        'verdict' => 'ungrounded',
        'unsupported_claims' => ['desconto especial de 40%'],
    ]);
});

test('output_guardrail_failure_fails_open_and_keeps_the_reply', function () {
    Log::spy();

    $component = sendWithOutputVerdict(
        'Seu pedido está a caminho.',
        fn () => throw new RuntimeException('timeout do provider'),
    );

    // Fail-open: a checker error keeps the reply rather than retracting a
    // possibly-legitimate answer.
    $messages = Conversation::sole()->messages()->with('role')->orderBy('id')->get();

    expect($messages[1]->content)->toBe('Seu pedido está a caminho.');
    expect($messages[1]->blocked_at)->toBeNull();

    Log::shouldHaveReceived('info')
        ->with('guardrail.output_verdict', [
            'conversation_id' => $messages[1]->conversation_id,
            'message_id' => $messages[1]->id,
            'verdict' => 'error',
            'unsupported_claims_count' => 0,
        ])
        ->once();

    $events = outputActivityEvents($component);
    expect($events[0]['payload'])->toBe(['verdict' => 'error', 'unsupported_claims' => []]);
});

test('out_of_contract_output_verdict_fails_open', function () {
    $component = sendWithOutputVerdict('Resposta qualquer.', [
        ['reasoning' => 'x', 'verdict' => 'talvez', 'unsupported_claims' => null],
    ]);

    $messages = Conversation::sole()->messages()->with('role')->orderBy('id')->get();

    expect($messages[1]->content)->toBe('Resposta qualquer.');
    expect($messages[1]->blocked_at)->toBeNull();

    $events = outputActivityEvents($component);
    expect($events[0]['payload'])->toBe(['verdict' => 'error', 'unsupported_claims' => []]);
});

test('retracted_reply_is_excluded_from_seller_history_on_next_turn', function () {
    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    // Turn 1: SellerAgent hallucinates, output guardrail retracts it.
    GuardrailAgent::fake([['verdict' => 'allow', 'category' => null]]);
    SellerAgent::fake(['Desconto de 40% garantido.']);
    OutputGuardrailAgent::fake([
        ['reasoning' => 'sem fonte', 'verdict' => 'ungrounded', 'unsupported_claims' => ['desconto de 40%']],
    ]);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->set('body', 'tem desconto?')
        ->call('sendMessage')
        ->call('generateResponse');

    // Turn 2: a legitimate follow-up. The retracted hallucination and the
    // fallback must not re-enter the SellerAgent context.
    GuardrailAgent::fake([['verdict' => 'allow', 'category' => null]]);
    SellerAgent::fake(['Claro, posso verificar.']);
    OutputGuardrailAgent::fake([
        ['reasoning' => 'ok', 'verdict' => 'grounded', 'unsupported_claims' => null],
    ]);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->set('body', 'e o frete?')
        ->call('sendMessage')
        ->call('generateResponse');

    SellerAgent::assertPrompted(function (AgentPrompt $prompt) {
        $contents = collect($prompt->agent->messages())->pluck('content');

        return $prompt->prompt === 'e o frete?'
            && $contents->doesntContain('Desconto de 40% garantido.')
            && $contents->doesntContain(Chat::OutputGuardrailFallbackMessage);
    });
});
