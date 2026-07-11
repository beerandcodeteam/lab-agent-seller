@props([
    'type' => 'info',
    'title' => null,
])

@php
    // Quatro tons; cada um com borda/fundo suaves e um glifo mono no ícone.
    $types = [
        'info' => ['wrap' => 'border-accent/40 bg-accent-soft/40', 'icon' => 'bg-accent-soft text-accent', 'glyph' => 'i'],
        'success' => ['wrap' => 'border-success/40 bg-success-soft/50', 'icon' => 'bg-success-soft text-success', 'glyph' => '✓'],
        'warn' => ['wrap' => 'border-warn/40 bg-warn-soft/50', 'icon' => 'bg-warn-soft text-warn', 'glyph' => '!'],
        'danger' => ['wrap' => 'border-danger/40 bg-danger-soft/50', 'icon' => 'bg-danger-soft text-danger', 'glyph' => '×'],
    ];

    $config = $types[$type] ?? $types['info'];
    $wrapClasses = 'flex gap-[11px] px-[15px] py-[13px] rounded-[10px] border '.$config['wrap'];
@endphp

<div role="alert" {{ $attributes->class($wrapClasses) }} data-type="{{ $type }}">
    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md font-mono text-xs font-semibold {{ $config['icon'] }}">{{ $config['glyph'] }}</span>
    <div class="font-sans text-[13px] leading-[1.5] text-ink">
        @if ($title)
            <div class="font-medium">{{ $title }}</div>
        @endif
        {{ $slot }}
    </div>
</div>
