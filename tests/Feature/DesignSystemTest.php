<?php

use Illuminate\Support\Facades\Blade;

beforeEach(fn () => $this->withoutVite());

// ---------------------------------------------------------------------------
// Phase 3.1 — tokens e tema
// ---------------------------------------------------------------------------

it('registers the design tokens in the compiled theme', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    foreach ([
        '--color-bg', '--color-surface', '--color-ink', '--color-ink-2', '--color-ink-3',
        '--color-border', '--color-accent', '--color-accent-strong', '--color-accent-soft',
        '--color-success', '--color-success-soft', '--color-warn', '--color-warn-soft',
        '--color-danger', '--color-danger-soft', '--color-tool', '--color-tool-soft',
        '--radius-field', '--radius-card', '--shadow-sm', '--shadow-md', '--shadow-lg',
    ] as $token) {
        expect($css)->toContain($token);
    }

    expect($css)->toContain('Space Grotesk')
        ->toContain('IBM Plex Mono')
        ->toContain('.dark {');
});

// ---------------------------------------------------------------------------
// Phase 3.2 — x-button
// ---------------------------------------------------------------------------

it('renders the default button', function () {
    $html = Blade::render('<x-button>Conectar Pipedrive</x-button>');

    expect($html)->toContain('Conectar Pipedrive')
        ->toContain('bg-accent')
        ->toContain('hover:bg-accent-strong')
        ->toContain('type="button"');
});

it('renders every button variant', function (string $variant, string $marker) {
    $html = Blade::render('<x-button variant="'.$variant.'">Ação</x-button>');

    expect($html)->toContain($marker);
})->with([
    ['secondary', 'border-border'],
    ['ghost', 'bg-transparent'],
    ['danger', 'bg-danger'],
]);

it('shows a spinner and disables while loading', function () {
    $html = Blade::render('<x-button :loading="true">Validando</x-button>');

    expect($html)->toContain('animate-spin')
        ->toContain('aria-busy="true"')
        ->toContain('disabled');
});

it('renders a disabled button', function () {
    $html = Blade::render('<x-button :disabled="true">Ação</x-button>');

    expect($html)->toContain('disabled')
        ->toContain('disabled:cursor-not-allowed');
});

// ---------------------------------------------------------------------------
// Phase 3.2 — x-input
// ---------------------------------------------------------------------------

it('renders a default input with label', function () {
    $html = Blade::render('<x-input label="E-mail" name="email" value="ana@x.com" />');

    expect($html)->toContain('E-mail')
        ->toContain('name="email"')
        ->toContain('value="ana@x.com"')
        ->toContain('focus:border-accent');
});

it('renders the error state', function () {
    $html = Blade::render('<x-input label="Senha" type="password" error="Mínimo de 8 caracteres." />');

    expect($html)->toContain('border-danger')
        ->toContain('aria-invalid="true"')
        ->toContain('Mínimo de 8 caracteres.');
});

it('renders the disabled state', function () {
    $html = Blade::render('<x-input label="E-mail" :disabled="true" value="ana@x.com" />');

    expect($html)->toContain('disabled')
        ->toContain('disabled:bg-bg');
});

it('masks a sensitive field and never re-echoes its value', function () {
    $secret = 'super-secret-api-token-123';
    $html = Blade::render('<x-input label="API Token" name="api_token" :sensitive="true" value="'.$secret.'" />');

    expect($html)->not->toContain($secret)
        ->toContain('type="password"')
        ->toContain('font-mono')
        ->toContain('sensível');
});

it('renders a select field', function () {
    $html = Blade::render('<x-input label="Provider" :select="true" :options="[\'pipedrive\' => \'Pipedrive\']" />');

    expect($html)->toContain('<select')
        ->toContain('Pipedrive');
});

// ---------------------------------------------------------------------------
// Phase 3.2 — x-badge
// ---------------------------------------------------------------------------

it('renders every badge state with the right colour', function (string $state, string $marker) {
    $html = Blade::render('<x-badge state="'.$state.'" />');

    expect($html)->toContain('data-state="'.$state.'"')
        ->toContain($marker);
})->with([
    ['pending', 'bg-warn-soft'],
    ['syncing', 'bg-accent-soft'],
    ['completed', 'bg-success-soft'],
    ['failed', 'bg-danger-soft'],
    ['neutro', 'text-ink-2'],
    ['em-breve', 'border-dashed'],
]);

it('pulses the syncing badge dot', function () {
    $html = Blade::render('<x-badge state="syncing" />');

    expect($html)->toContain('animate-pulse');
});

// ---------------------------------------------------------------------------
// Phase 3.2 — x-alert
// ---------------------------------------------------------------------------

it('renders all four alert tones', function (string $type) {
    $html = Blade::render('<x-alert type="'.$type.'">Mensagem</x-alert>');

    expect($html)->toContain('data-type="'.$type.'"')
        ->toContain('role="alert"')
        ->toContain('Mensagem');
})->with(['info', 'success', 'warn', 'danger']);

// ---------------------------------------------------------------------------
// Phase 3.2 — x-card
// ---------------------------------------------------------------------------

it('renders a card with optional header and footer slots', function () {
    $html = Blade::render(<<<'BLADE'
        <x-card>
            <x-slot:header>Cabeçalho</x-slot:header>
            Corpo do card
            <x-slot:footer>Rodapé</x-slot:footer>
        </x-card>
    BLADE);

    expect($html)->toContain('Cabeçalho')
        ->toContain('Corpo do card')
        ->toContain('Rodapé')
        ->toContain('rounded-card');
});

it('renders a card body without slots', function () {
    $html = Blade::render('<x-card>Somente corpo</x-card>');

    expect($html)->toContain('Somente corpo')
        ->not->toContain('border-t');
});

// ---------------------------------------------------------------------------
// Phase 3.2 — x-empty-state
// ---------------------------------------------------------------------------

it('renders an empty state with icon, title, description and cta', function () {
    $html = Blade::render(<<<'BLADE'
        <x-empty-state icon="∅" title="Nada por aqui" description="Vazio esperado.">
            <x-slot:cta><x-button>Call to action</x-button></x-slot:cta>
        </x-empty-state>
    BLADE);

    expect($html)->toContain('∅')
        ->toContain('Nada por aqui')
        ->toContain('Vazio esperado.')
        ->toContain('Call to action');
});

// ---------------------------------------------------------------------------
// Phase 3.2 — x-agent-event
// ---------------------------------------------------------------------------

it('renders each MVP agent event type with fixed anatomy', function (string $type, string $glyphColor) {
    $html = Blade::render('<x-agent-event type="'.$type.'" timestamp="14:32:07" summary="resumo" :payload="[\'k\' => 1]" />');

    expect($html)->toContain('data-type="'.$type.'"')
        ->toContain($type)              // nome do evento em mono
        ->toContain('14:32:07')         // timestamp
        ->toContain('resumo')           // resumo humano
        ->toContain('<details')         // payload colapsável
        ->toContain('font-mono')
        ->toContain($glyphColor);
})->with([
    ['request.started', 'text-accent'],
    ['stream.delta', 'text-ink-2'],
    ['response.completed', 'text-success'],
    ['error', 'text-danger'],
]);

it('reserves the tool.* visual for the future without hardcoding layout', function () {
    $html = Blade::render('<x-agent-event type="tool.called" :future="true" summary="buscar_deals" />');

    expect($html)->toContain('tool.called')
        ->toContain('text-tool')
        ->toContain('FUTURO');
});

it('falls back to a neutral visual for unknown event types', function () {
    $html = Blade::render('<x-agent-event type="something.new" summary="x" />');

    expect($html)->toContain('data-type="something.new"')
        ->toContain('something.new');
});

it('collapses the json payload and pretty-prints it', function () {
    $html = Blade::render('<x-agent-event type="request.started" :payload="[\'model\' => \'gpt\', \'messages\' => 6]" :open="true" />');

    expect($html)->toContain('<details')
        ->toContain('open')
        ->toContain('&quot;model&quot;')
        ->toContain('gpt');
});

// ---------------------------------------------------------------------------
// Phase 3.3 — layouts
// ---------------------------------------------------------------------------

it('renders the authenticated panel topbar', function () {
    $html = Blade::render('<x-layouts.app company="Café Aurora" email="ana@cafeaurora.com.br">conteúdo</x-layouts.app>');

    expect($html)->toContain('agent<span class="text-accent">seller</span>')
        ->toContain('Café Aurora')
        ->toContain('ana@cafeaurora.com.br')
        ->toContain('Sair')
        ->toContain('conteúdo');
});

it('renders the minimalist public layout', function () {
    $html = Blade::render('<x-layouts.public>tela do cliente</x-layouts.public>');

    expect($html)->toContain('agent<span class="text-accent">seller</span>')
        ->toContain('tela do cliente')
        ->not->toContain('Sair');
});
