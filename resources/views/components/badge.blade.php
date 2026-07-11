@props([
    'state' => 'neutro',
])

@php
    // Cada estado mapeia para uma cor do design; syncing pulsa; em-breve é tracejado sem dot.
    $states = [
        'pending' => ['wrap' => 'bg-warn-soft text-warn font-mono', 'dot' => 'bg-warn'],
        'syncing' => ['wrap' => 'bg-accent-soft text-accent font-mono', 'dot' => 'bg-accent animate-pulse'],
        'completed' => ['wrap' => 'bg-success-soft text-success font-mono', 'dot' => 'bg-success'],
        'failed' => ['wrap' => 'bg-danger-soft text-danger font-mono', 'dot' => 'bg-danger'],
        'neutro' => ['wrap' => 'bg-[#eef0f6] text-ink-2 font-mono', 'dot' => null],
        'em-breve' => ['wrap' => 'border border-dashed border-border text-ink-3 font-sans', 'dot' => null],
    ];

    $config = $states[$state] ?? $states['neutro'];
    $wrapClasses = 'inline-flex items-center gap-[7px] h-[26px] px-[11px] rounded-full text-[11.5px] font-medium '.$config['wrap'];
@endphp

<span {{ $attributes->class($wrapClasses) }} data-state="{{ $state }}">
    @if ($config['dot'])
        <span class="h-[7px] w-[7px] shrink-0 rounded-full {{ $config['dot'] }}"></span>
    @endif
    {{ $slot->isEmpty() ? $state : $slot }}
</span>
