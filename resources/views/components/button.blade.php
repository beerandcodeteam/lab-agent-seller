@props([
    'variant' => 'default',
    'type' => 'button',
    'loading' => false,
    'disabled' => false,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 h-[38px] px-[18px] rounded-field font-sans text-[13.5px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed';

    $variants = [
        'default' => 'border-0 bg-accent text-white hover:bg-accent-strong disabled:bg-border disabled:text-ink-3 disabled:hover:bg-border',
        'secondary' => 'border border-border bg-surface text-ink hover:border-ink-3 hover:bg-bg disabled:bg-bg disabled:text-ink-3 disabled:border-border disabled:hover:bg-bg',
        'ghost' => 'border-0 bg-transparent text-ink-2 hover:bg-accent-soft hover:text-ink disabled:text-ink-3 disabled:hover:bg-transparent',
        'danger' => 'border-0 bg-danger text-white hover:brightness-90 disabled:bg-border disabled:text-ink-3 disabled:hover:brightness-100',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['default']);
    $isDisabled = $disabled || $loading;
@endphp

<button
    type="{{ $type }}"
    {{ $isDisabled ? 'disabled' : '' }}
    @if ($loading) aria-busy="true" @endif
    {{ $attributes->class($classes) }}
>
    @if ($loading)
        <span
            role="status"
            aria-label="Carregando"
            class="h-[13px] w-[13px] shrink-0 rounded-full border-2 border-white/35 border-t-white animate-spin"
        ></span>
    @endif
    {{ $slot }}
</button>
