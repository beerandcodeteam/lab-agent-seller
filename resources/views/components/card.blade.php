@props([
    'title' => null,
    'meta' => null,
])

<div {{ $attributes->class('bg-surface border border-border rounded-card shadow-sm overflow-hidden') }}>
    @isset($header)
        <div class="flex items-center justify-between border-b border-border/70 px-[18px] py-[14px]">
            {{ $header }}
        </div>
    @elseif ($title)
        <div class="flex items-center justify-between border-b border-border/70 px-[18px] py-[14px]">
            <span class="font-sans text-[13.5px] font-semibold text-ink">{{ $title }}</span>
            @if ($meta)
                <span class="font-mono text-[10.5px] font-medium text-ink-3">{{ $meta }}</span>
            @endif
        </div>
    @endisset

    <div class="px-[18px] py-[18px] font-sans text-[13px] leading-[1.6] text-ink-2">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="flex items-center justify-end border-t border-border/70 px-[18px] py-3">
            {{ $footer }}
        </div>
    @endisset
</div>
