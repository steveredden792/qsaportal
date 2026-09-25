{{-- Shared site header: used by the home page, the public layout and the account (app) layout --}}
<header class="border-b border-slate-200 bg-qsa-grey">
    <div class="mx-auto flex max-w-[1200px] items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ url('/') }}" class="flex items-center">
            <x-application-logo class="h-9 w-auto sm:h-10" />
        </a>

        <nav class="hidden items-center gap-7 text-[0.72rem] font-medium uppercase tracking-[0.22em] text-slate-700 lg:flex">
            <a href="{{ url('/') }}" class="transition hover:text-brand">Home</a>
            <a href="{{ route('catalogue.pir') }}" class="transition hover:text-brand">PIR Database</a>
            <a href="#" class="transition hover:text-brand">FAR Database</a>
            <a href="{{ route('contact') }}" class="transition hover:text-brand">Contact Us</a>
            @auth
                <a href="{{ route('my-reports') }}" class="transition hover:text-brand">My Reports</a>
            @endauth
            <livewire:basket-badge />
        </nav>

        <div class="flex items-center gap-3">
            @auth
                <a href="{{ route('profile') }}" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 text-slate-600 transition hover:border-brand hover:text-brand" aria-label="Account" title="My account">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="8" r="3.25" />
                        <path d="M5.5 19c1.8-3.2 4.3-4.8 6.5-4.8S16.5 15.8 18.5 19" />
                    </svg>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:border-brand hover:text-brand">Log Out</button>
                </form>
            @else
                @if (config('app.allow_registration', true))
                    <a href="{{ route('register') }}" class="hidden rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:border-brand hover:text-brand sm:inline-flex">Register</a>
                @endif
                <a href="{{ route('login') }}" class="inline-flex rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:border-brand hover:text-brand">Log In</a>
            @endauth
        </div>
    </div>
</header>
