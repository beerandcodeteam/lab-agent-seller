@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'value' => '',
    'placeholder' => '',
    'hint' => null,
    'error' => null,
    'disabled' => false,
    'sensitive' => false,
    'select' => false,
    'options' => [],
])

@php
    $inputId = $attributes->get('id') ?? $name ?? 'field-'.\Illuminate\Support\Str::random(6);
    $hasError = filled($error);

    $fieldBase = 'w-full box-border h-10 px-3 rounded-field bg-surface font-sans text-sm text-ink transition-colors focus:outline-none focus:ring-[3px] focus:ring-accent-soft focus:border-accent disabled:bg-bg disabled:text-ink-3';
    $borderClass = $hasError ? 'border border-danger focus:border-danger' : 'border border-border';
    // Campo sensível: fonte mono, espaçamento de máscara — e nunca reexibe o valor.
    $sensitiveClass = $sensitive ? 'font-mono tracking-[2px]' : '';
    $fieldClasses = trim("$fieldBase $borderClass $sensitiveClass");
@endphp

<div>
    @if ($label)
        <label
            for="{{ $inputId }}"
            class="mb-1.5 flex items-baseline justify-between font-sans text-xs font-medium text-ink-2"
        >
            <span>{{ $label }}</span>
            @if ($sensitive)
                <span class="font-mono text-[10px] font-medium text-warn">sensível</span>
            @endif
        </label>
    @endif

    @if ($select)
        <select
            id="{{ $inputId }}"
            @if ($name) name="{{ $name }}" @endif
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->except(['id'])->class($fieldClasses) }}
        >
            {{ $slot }}
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($value === $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        </select>
    @else
        <input
            id="{{ $inputId }}"
            type="{{ $sensitive ? 'password' : $type }}"
            @if ($name) name="{{ $name }}" @endif
            {{-- Campo sensível nunca tem o valor reemitido para o cliente. --}}
            @unless ($sensitive) value="{{ $value }}" @endunless
            placeholder="{{ $sensitive && blank($placeholder) ? '••••••••••••••••' : $placeholder }}"
            {{ $disabled ? 'disabled' : '' }}
            @if ($hasError) aria-invalid="true" @endif
            {{ $attributes->except(['id'])->class($fieldClasses) }}
        >
    @endif

    @if ($hasError)
        <p class="mt-1.5 font-sans text-[11.5px] text-danger">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 font-sans text-[11.5px] text-ink-3">{{ $hint }}</p>
    @endif
</div>
