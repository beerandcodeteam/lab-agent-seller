<?php

use App\Ai\Agents\GuardrailAgent;
use App\Ai\Agents\SellerAgent;
use App\Livewire\Client\Chat;
use App\Livewire\Client\CompanySelection;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Providers\Tools\WebSearch;
use Livewire\Livewire;

/**
 * Authenticate a client whose email matches the given company and set that
 * company as the active chat context, returning the client.
 */
function clientInChatWith(User $company, string $email): Client
{
    $client = Client::factory()->create(['email' => $email]);

    session(['selected_company_id' => $company->id]);

    return $client;
}

// ── Phase 9.1: conversa persistida e resposta do agente ──────────────────────

test('client_message_and_agent_reply_are_persisted', function () {
    GuardrailAgent::fake([['verdict' => 'allow', 'category' => null]]);
    SellerAgent::fake(['Olá! Como posso ajudar?']);

    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->set('body', 'Oi, tudo bem?')
        ->call('sendMessage')
        ->call('generateResponse');

    $conversation = Conversation::firstWhere([
        'client_id' => $client->id,
        'user_id' => $company->id,
    ]);

    $messages = $conversation->messages()->with('role')->orderBy('id')->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->role->slug)->toBe('user');
    expect($messages[0]->content)->toBe('Oi, tudo bem?');
    expect($messages[1]->role->slug)->toBe('assistant');
    expect($messages[1]->content)->toBe('Olá! Como posso ajudar?');
});

test('agent_exposes_pipedrive_and_web_search_tools', function () {
    $tools = collect((new SellerAgent(new Conversation))->tools());

    expect($tools)->toHaveCount(9);

    $webSearch = $tools->first(fn ($tool) => $tool instanceof WebSearch);
    expect($webSearch)->not->toBeNull();
    expect($webSearch->maxSearches)->toBe(3);
});

test('agent_uses_global_system_prompt', function () {
    GuardrailAgent::fake([['verdict' => 'allow', 'category' => null]]);
    SellerAgent::fake(['resposta']);

    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->set('body', 'Qual o status?')
        ->call('sendMessage')
        ->call('generateResponse');

    SellerAgent::assertPrompted('Qual o status?');
    SellerAgent::assertPrompted(
        fn (AgentPrompt $prompt) => $prompt->agent->instructions() === strtr(SellerAgent::SystemPrompt, [
            '{company_name}' => $company->name,
            '{company_playbook}' => SellerAgent::DefaultPlaybook,
        ]),
    );
});

test('agent_renders_company_skills_into_prompt', function () {
    $conversation = Conversation::factory()->create();

    $agent = new SellerAgent($conversation, skills: 'Processo: qualificar, propor, fechar.');

    expect($agent->instructions())
        ->toContain('Processo: qualificar, propor, fechar.')
        ->toContain($conversation->user->name)
        ->not->toContain('{company_name}')
        ->not->toContain('{company_playbook}');
});

test('agent_uses_company_stored_playbook_when_no_skills_override', function () {
    $company = User::factory()->create(['playbook' => 'Playbook cadastrado no painel.']);
    $conversation = Conversation::factory()->for($company, 'user')->create();

    $agent = new SellerAgent($conversation);

    expect($agent->instructions())
        ->toContain('Playbook cadastrado no painel.')
        ->not->toContain(SellerAgent::DefaultPlaybook);
});

test('agent_without_skills_falls_back_to_default_playbook', function () {
    $conversation = Conversation::factory()->create();

    $agent = new SellerAgent($conversation);

    expect($agent->instructions())
        ->toContain(SellerAgent::DefaultPlaybook)
        ->toContain($conversation->user->name);
});

test('conversation_is_reused_per_client_company_pair', function () {
    GuardrailAgent::fake([['verdict' => 'allow', 'category' => null]]);
    SellerAgent::fake(['ok']);

    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    Livewire::actingAs($client, 'client')->test(Chat::class)->call('sendMessage');
    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->set('body', 'de novo')
        ->call('sendMessage')
        ->call('generateResponse');

    expect(Conversation::where('client_id', $client->id)->where('user_id', $company->id)->count())->toBe(1);
});

// ── Phase 9.1: retomar histórico ─────────────────────────────────────────────

test('history_reloads_in_order', function () {
    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    $conversation = Conversation::create(['client_id' => $client->id, 'user_id' => $company->id]);
    Message::factory()->fromUser()->for($conversation)->create(['content' => 'primeira do cliente']);
    Message::factory()->fromAssistant()->for($conversation)->create(['content' => 'primeira do agente']);
    Message::factory()->fromUser()->for($conversation)->create(['content' => 'segunda do cliente']);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->assertSeeInOrder(['primeira do cliente', 'primeira do agente', 'segunda do cliente']);
});

test('conversations_are_isolated_between_companies', function () {
    $email = 'multi@cliente.com';
    $first = companyMatchingEmail($email);
    $second = companyMatchingEmail($email);
    $client = Client::factory()->create(['email' => $email]);

    $conversationFirst = Conversation::create(['client_id' => $client->id, 'user_id' => $first->id]);
    Message::factory()->fromUser()->for($conversationFirst)->create(['content' => 'segredo da empresa A']);

    $conversationSecond = Conversation::create(['client_id' => $client->id, 'user_id' => $second->id]);
    Message::factory()->fromUser()->for($conversationSecond)->create(['content' => 'assunto da empresa B']);

    session(['selected_company_id' => $second->id]);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->assertSee('assunto da empresa B')
        ->assertDontSee('segredo da empresa A');
});

test('history_persists_across_sessions', function () {
    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = Client::factory()->create(['email' => $email]);

    $conversation = Conversation::create(['client_id' => $client->id, 'user_id' => $company->id]);
    Message::factory()->fromUser()->for($conversation)->create(['content' => 'mensagem antiga']);

    // Nova sessão (equivalente a um novo magic link): sessão limpa e re-autenticação.
    session()->flush();
    session(['selected_company_id' => $company->id]);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->assertSee('mensagem antiga');
});

// ── Phase 9.2: streaming ─────────────────────────────────────────────────────

test('streamed_response_is_persisted_on_completion', function () {
    GuardrailAgent::fake([['verdict' => 'allow', 'category' => null]]);
    SellerAgent::fake(['a resposta completa em streaming']);

    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->set('body', 'me conta')
        ->call('sendMessage')
        ->assertSet('streaming', true)
        ->call('generateResponse')
        ->assertSet('streaming', false);

    $conversation = Conversation::firstWhere(['client_id' => $client->id, 'user_id' => $company->id]);

    $assistant = $conversation->messages()->whereRelation('role', 'slug', 'assistant')->first();

    expect($assistant)->not->toBeNull();
    expect($assistant->content)->toBe('a resposta completa em streaming');
});

test('client_message_renders_before_agent_reply_streams', function () {
    SellerAgent::fake(['resposta']);

    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    // Após sendMessage (antes de generateResponse) a mensagem do cliente já
    // aparece e o compositor fica travado aguardando o stream.
    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->set('body', 'apareça já')
        ->call('sendMessage')
        ->assertSee('apareça já')
        ->assertSet('streaming', true)
        ->assertSet('body', '');
});

test('assistant_reply_is_rendered_as_markdown', function () {
    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    $conversation = Conversation::create(['client_id' => $client->id, 'user_id' => $company->id]);
    Message::factory()->fromAssistant()->for($conversation)->create([
        'content' => "Veja **importante**:\n\n- item um",
    ]);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->assertSeeHtml('<strong>importante</strong>')
        ->assertSeeHtml('<li>item um</li>');
});

// ── Phase 9.3: erro do provider e troca de empresa ───────────────────────────

test('provider_error_shows_friendly_message_and_preserves_client_message', function () {
    GuardrailAgent::fake([['verdict' => 'allow', 'category' => null]]);
    SellerAgent::fake(fn () => throw new RuntimeException('provider indisponível'));

    $email = 'ana@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->set('body', 'minha pergunta importante')
        ->call('sendMessage')
        ->call('generateResponse')
        ->assertHasErrors('agent');

    $conversation = Conversation::firstWhere(['client_id' => $client->id, 'user_id' => $company->id]);
    $messages = $conversation->messages()->with('role')->get();

    // A mensagem do cliente persiste e nenhuma resposta parcial corrompe o histórico.
    expect($messages)->toHaveCount(1);
    expect($messages[0]->role->slug)->toBe('user');
    expect($messages[0]->content)->toBe('minha pergunta importante');
});

test('switch_company_opens_other_conversation', function () {
    SellerAgent::fake(['ok']);

    $email = 'multi@cliente.com';
    $first = companyMatchingEmail($email);
    $second = companyMatchingEmail($email);
    $client = Client::factory()->create(['email' => $email]);

    // Abre a conversa da primeira empresa.
    session(['selected_company_id' => $first->id]);
    Livewire::actingAs($client, 'client')->test(Chat::class);

    // Troca de empresa a partir da seleção.
    Livewire::actingAs($client, 'client')
        ->test(CompanySelection::class)
        ->call('select', $second->id)
        ->assertRedirect(route('client.chat'));

    expect(session('selected_company_id'))->toBe($second->id);

    $component = Livewire::actingAs($client, 'client')->test(Chat::class);

    $conversationSecond = Conversation::firstWhere(['client_id' => $client->id, 'user_id' => $second->id]);

    expect($conversationSecond)->not->toBeNull();
    expect($component->instance()->conversation->user_id)->toBe($second->id);
    expect(Conversation::where('client_id', $client->id)->count())->toBe(2);
});

test('switch_hidden_for_single_match_client', function () {
    $email = 'single@cliente.com';
    $company = companyMatchingEmail($email);
    $client = clientInChatWith($company, $email);

    Livewire::actingAs($client, 'client')
        ->test(Chat::class)
        ->assertDontSee('Trocar empresa');
});
