@props([
    'title' => null,
])

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
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ url('/') }}" class="mb-8 flex items-center gap-2.5">
            <span class="flex h-7 w-7 items-center justify-center rounded-[7px] bg-ink">
                <span class="h-2.5 w-2.5 rounded-[2px] bg-accent"></span>
            </span>
            <span class="font-sans text-[17px] font-bold tracking-[-0.02em] text-ink">agent<span class="text-accent">seller</span></span>
        </a>

        <main class="w-full max-w-[380px]">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
