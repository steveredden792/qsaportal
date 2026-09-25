@props(['title' => 'QS Analysis', 'subtitle' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ config('app.name', 'QS Analysis') }}</title>
    <meta name="description" content="Browse and access Q Score Analysis reports and public information reports.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|albert-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-white text-slate-700 antialiased">
    <x-site-header />

    {{-- Reduced-height hero band, shared across every page that uses this wrapper --}}
    <section class="relative flex min-h-[190px] items-center overflow-hidden bg-brand sm:min-h-[220px]"
        style="background-image: linear-gradient(90deg, rgba(0,40,66,0.8) 0%, rgba(0,40,66,0.4) 100%), url('{{ asset('images/hero-network.jpg') }}'); background-size: cover; background-position: center;">
        <div class="mx-auto w-full max-w-[1200px] px-4 py-8 sm:px-6 lg:px-8">
            <p class="font-heading text-3xl font-semibold text-white sm:text-4xl">{{ $title }}</p>
            @if ($subtitle)
                <p class="mt-2 text-sm font-semibold uppercase tracking-[0.18em] text-brand-light">{{ $subtitle }}</p>
            @endif
        </div>
    </section>

    <main class="mx-auto flex-1 w-full max-w-[1200px] px-4 py-8 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-[1200px] px-4 py-8 text-center text-[0.7rem] text-slate-500 sm:px-6 lg:px-8">
            © Q Score Analysis Ltd 2026 | Privacy Policy | Website: AC/ NP
        </div>
    </footer>
</body>
</html>
