@props([
    'title' => null,
    'company' => null,
    'email' => null,
])

@php
    $user = auth()->user();
    $companyName = $company ?? $user?->name;
    $userEmail = $email ?? $user?->email;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}agentseller</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-bg font-sans text-ink">
    <header class="flex h-14 items-center justify-between border-b border-border bg-surface px-6">
        <div class="flex items-center gap-3.5">
            <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}" class="flex items-center gap-2.5">
                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-ink">
                    <span class="h-2 w-2 rounded-[2px] bg-accent"></span>
                </span>
                <span class="font-sans text-[15px] font-bold tracking-[-0.02em] text-ink">agent<span class="text-accent">seller</span></span>
            </a>

            @if ($companyName)
                <span class="h-5 w-px bg-border"></span>
                <span class="font-sans text-[13px] font-medium text-ink-2">{{ $companyName }}</span>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @if ($userEmail)
                <span class="font-mono text-[11.5px] text-ink-3">{{ $userEmail }}</span>
            @endif

            @if (Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-button type="submit" variant="secondary" class="h-8 px-3 text-[12.5px]">Sair</x-button>
                </form>
            @else
                <x-button variant="secondary" class="h-8 px-3 text-[12.5px]">Sair</x-button>
            @endif
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-6 py-8">
        {{ $slot }}
    </main>
</body>
</html>
