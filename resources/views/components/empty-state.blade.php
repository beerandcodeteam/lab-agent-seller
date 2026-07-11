@props([
    'icon' => '∅',
    'title' => null,
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center gap-1.5 px-8 py-10 text-center') }}>
    <div class="mb-2 flex h-11 w-11 items-center justify-center rounded-[12px] border border-dashed border-border bg-[#f1f3f8] font-mono text-lg text-ink-3">
        {{ $icon }}
    </div>

    @if ($title)
        <div class="font-sans text-[15px] font-semibold text-ink">{{ $title }}</div>
    @endif

    @if ($description)
        <div class="max-w-[260px] font-sans text-[13px] leading-[1.55] text-ink-3">{{ $description }}</div>
    @endif

    @isset($cta)
        <div class="mt-2.5">{{ $cta }}</div>
    @endisset

    {{ $slot }}
</div>
