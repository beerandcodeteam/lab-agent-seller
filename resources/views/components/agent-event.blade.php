@props([
    'type' => 'request.started',
    'timestamp' => null,
    'summary' => null,
    'payload' => null,
    'open' => false,
    'future' => false,
])

@php
    // Item polimórfico: mesma anatomia para todos os tipos, apenas o esquema de cor/ícone muda.
    // Nenhum layout é hardcoded por tipo — tipos desconhecidos caem no visual neutro.
    $schemes = [
        'request.started' => ['icon' => 'bg-accent-soft text-accent', 'glyph' => '→', 'row' => '', 'name' => 'text-ink'],
        'stream.start' => ['icon' => 'bg-accent-soft text-accent', 'glyph' => '▸', 'row' => '', 'name' => 'text-ink'],
        'reasoning' => ['icon' => 'bg-warn-soft text-warn', 'glyph' => '∴', 'row' => '', 'name' => 'text-ink'],
        'stream.delta' => ['icon' => 'bg-[#eef0f6] text-ink-2', 'glyph' => '≈', 'row' => '', 'name' => 'text-ink'],
        'response.completed' => ['icon' => 'bg-success-soft text-success', 'glyph' => '✓', 'row' => '', 'name' => 'text-ink'],
        'error' => ['icon' => 'bg-danger-soft text-danger', 'glyph' => '×', 'row' => 'bg-danger-soft/30', 'name' => 'text-danger'],
        'tool.called' => ['icon' => 'bg-tool-soft text-tool', 'glyph' => '⚙', 'row' => '', 'name' => 'text-ink'],
        'tool.result' => ['icon' => 'bg-tool-soft text-tool', 'glyph' => '⤷', 'row' => '', 'name' => 'text-ink'],
    ];

    $scheme = $schemes[$type] ?? ['icon' => 'bg-[#eef0f6] text-ink-2', 'glyph' => '•', 'row' => '', 'name' => 'text-ink'];

    if (is_null($payload)) {
        $payloadJson = null;
    } elseif (is_string($payload)) {
        $payloadJson = $payload;
    } else {
        $payloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
@endphp

<div {{ $attributes->class('relative flex gap-2.5 border-b border-border/70 px-[14px] py-[11px] '.$scheme['row']) }} data-type="{{ $type }}">
    @if ($future)
        <div class="absolute right-[14px] top-2 rounded font-mono text-[9px] font-semibold text-tool bg-tool-soft px-1.5 py-0.5">FUTURO</div>
    @endif

    <div class="flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-md font-mono text-xs font-semibold {{ $scheme['icon'] }}">{{ $scheme['glyph'] }}</div>

    <div class="min-w-0 flex-1">
        <div class="flex items-baseline justify-between gap-2">
            <span class="font-mono text-[11px] font-semibold {{ $scheme['name'] }}">{{ $type }}</span>
            <span class="font-mono text-[10px] text-ink-3">{{ $timestamp ?? '—' }}</span>
        </div>

        @if ($summary)
            <div class="mt-0.5 font-sans text-xs text-ink-2">{{ $summary }}</div>
        @endif

        @if (! is_null($payloadJson))
            <details class="mt-1.5" @if ($open) open @endif>
                <summary class="cursor-pointer font-mono text-[10.5px] font-medium text-ink-3">payload</summary>
                <pre class="mt-1.5 overflow-auto rounded-md border border-border bg-[#f2f4f9] px-2.5 py-2 font-mono text-[10.5px] leading-[1.6] text-ink-2 dark:bg-bg">{{ $payloadJson }}</pre>
            </details>
        @endif
    </div>
</div>
