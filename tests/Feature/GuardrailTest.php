<?php

use App\Ai\Agents\GuardrailAgent;
use App\Ai\Agents\SellerAgent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;

/**
 * Create a conversation owned by a company with the given guardrail config.
 */
function conversationForCompany(?string $alignments = null, ?string $restrictions = null): Conversation
{
    $company = User::factory()->create([
        'guardrail_topic_alignments' => $alignments,
        'guardrail_restrictions' => $restrictions,
    ]);

    return Conversation::factory()->for($company)->create();
}

// ── T03: instructions (RF-05, RF-06, RNF-01) ─────────────────────────────────

test('guardrail_instructions_name_all_six_criteria', function () {
    $instructions = (new GuardrailAgent(conversationForCompany()))->instructions();

    foreach (GuardrailAgent::Categories as $category) {
        expect($instructions)->toContain($category);
    }

    // Distinção PII: dados de terceiros bloqueiam, dados PRÓPRIOS para compra são permitidos.
    expect($instructions)->toContain('TERCEIROS');
    expect($instructions)->toContain('PRÓPRIOS dados de contato');
    expect($instructions)->toContain('NÃO deve ser bloqueado');
});

test('company_config_is_injected_into_instructions', function () {
    $conversation = conversationForCompany(
        alignments: 'somente dúvidas sobre pedidos de vinho',
        restrictions: 'nunca mencionar concorrentes',
    );

    $instructions = (new GuardrailAgent($conversation))->instructions();

    expect($instructions)->toContain('somente dúvidas sobre pedidos de vinho');
    expect($instructions)->toContain('nunca mencionar concorrentes');
    expect($instructions)->not->toContain('Não aplicável');
});

test('empty_config_marks_conditional_categories_as_not_applicable', function () {
    $instructions = (new GuardrailAgent(conversationForCompany()))->instructions();

    expect($instructions)->toContain('não configurou alinhamentos de assunto. NUNCA use esta categoria');
    expect($instructions)->toContain('não configurou restrições específicas. NUNCA use esta categoria');
});

test('instructions_use_only_the_owning_company_config', function () {
    $conversationA = conversationForCompany(alignments: 'assuntos da empresa A');
    $conversationB = conversationForCompany(alignments: 'assuntos da empresa B');

    $instructionsA = (new GuardrailAgent($conversationA))->instructions();
    $instructionsB = (new GuardrailAgent($conversationB))->instructions();

    expect($instructionsA)->toContain('assuntos da empresa A');
    expect($instructionsA)->not->toContain('assuntos da empresa B');
    expect($instructionsB)->toContain('assuntos da empresa B');
    expect($instructionsB)->not->toContain('assuntos da empresa A');
});

// ── T03: schema (CT-01) e configuração do agente ─────────────────────────────

test('guardrail_schema_maps_verdict_contract', function () {
    $types = (new GuardrailAgent(conversationForCompany()))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($types))->toBe(['verdict', 'category']);

    $verdict = $types['verdict']->toArray();
    expect($verdict['type'])->toBe('string');
    expect($verdict['enum'])->toBe(['allow', 'block']);

    $category = $types['category']->toArray();
    expect($category['type'])->toBe(['string', 'null']);
    expect($category['enum'])->toBe([
        'prompt_injection',
        'jailbreak',
        'intent_change',
        'pii',
        'off_topic',
        'company_restriction',
    ]);
});

test('guardrail_has_no_tools_and_uses_low_reasoning_effort', function () {
    $agent = new GuardrailAgent(conversationForCompany());

    expect($agent)->not->toBeInstanceOf(HasTools::class);
    expect($agent->providerOptions(Lab::OpenAI))->toBe(['reasoning' => ['effort' => 'low']]);
});

// ── T03: janela de histórico (RF-01) ─────────────────────────────────────────

test('guardrail_history_is_capped_at_ten_and_keeps_blocked_turns', function () {
    $conversation = conversationForCompany();

    foreach (range(1, 12) as $index) {
        Message::factory()->fromUser()->for($conversation)->create(['content' => "mensagem {$index}"]);
    }

    $blocked = Message::factory()->fromUser()->blocked()->for($conversation)->create(['content' => 'turno bloqueado']);
    $current = Message::factory()->fromUser()->for($conversation)->create(['content' => 'turno atual']);

    $history = collect((new GuardrailAgent($conversation, $current->id))->messages());

    expect($history)->toHaveCount(10);

    // Ordem cronológica: da mensagem 4 até o turno bloqueado; o turno atual fica de fora.
    expect($history->first()->content)->toBe('mensagem 4');
    expect($history->last()->content)->toBe('turno bloqueado');
    expect($history->pluck('content'))->not->toContain('turno atual');
});

// ── T04: SellerAgent exclui turnos bloqueados (RF-03, RF-04) ─────────────────

test('seller_agent_history_excludes_blocked_turns', function () {
    $conversation = conversationForCompany();

    Message::factory()->fromUser()->for($conversation)->create(['content' => 'pergunta legítima']);
    Message::factory()->fromAssistant()->for($conversation)->create(['content' => 'resposta legítima']);
    Message::factory()->fromUser()->blocked()->for($conversation)->create(['content' => 'ataque bloqueado']);
    Message::factory()->fromAssistant()->blocked()->for($conversation)->create(['content' => 'redirecionamento seguro']);
    Message::factory()->fromUser()->for($conversation)->create(['content' => 'pergunta seguinte']);

    $history = collect((new SellerAgent($conversation))->messages());

    expect($history->pluck('content')->all())->toBe([
        'pergunta legítima',
        'resposta legítima',
        'pergunta seguinte',
    ]);
});
