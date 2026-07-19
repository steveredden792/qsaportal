@props(['title' => 'QS Analysis'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <header class="bg-white border-b border-gray-200 text-gray-900">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
            <a href="{{ url('/') }}" class="text-lg font-semibold">QS Analysis</a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('catalogue.pir') }}" class="hover:underline">PIR</a>
                @auth
                    <livewire:basket-badge />
                    <a href="{{ route('dashboard') }}" class="hover:underline">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hover:underline">Log in</a>
                    <a href="{{ route('register') }}" class="rounded bg-brand px-3 py-1 font-medium text-white">Register</a>
                @endauth
            </nav>
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-8">
        {{ $slot }}
    </main>
</body>
</html>
