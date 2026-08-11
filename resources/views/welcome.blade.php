<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'QS Analysis') }}</title>
        <meta name="description" content="Access Q Score Analysis reports, public information reports and supporting materials.">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|albert-sans:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white font-sans text-slate-700 antialiased">
        <header class="border-b border-slate-200 bg-qsa-grey">
            <div class="mx-auto flex max-w-[1200px] items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="flex items-center">
                    <x-application-logo class="h-9 w-auto sm:h-10" />
                </a>

                <nav class="hidden items-center gap-7 text-[0.72rem] font-medium uppercase tracking-[0.22em] text-slate-700 lg:flex">
                    <a href="#hero" class="transition hover:text-brand">Home</a>
                    <a href="#q-scores" class="transition hover:text-brand">Q Scores</a>
                    <a href="#pir-service" class="transition hover:text-brand">PIR Service</a>
                    <a href="#psp-service" class="transition hover:text-brand">PSP Service</a>
                    <a href="#about" class="transition hover:text-brand">About Us</a>
                    <a href="#contact" class="transition hover:text-brand">Contact Us</a>
                </nav>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('catalogue.pir') }}" class="hidden rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:border-brand hover:text-brand sm:inline-flex">Portal</a>
                    @else
                        @if (config('app.allow_registration', true))
                            <a href="{{ route('register') }}" class="hidden rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:border-brand hover:text-brand sm:inline-flex">Register</a>
                        @endif
                    @endauth
                    @auth
                        <a href="{{ route('profile') }}" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 text-slate-600 transition hover:border-brand hover:text-brand" aria-label="Account">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="8" r="3.25" />
                                <path d="M5.5 19c1.8-3.2 4.3-4.8 6.5-4.8S16.5 15.8 18.5 19" />
                            </svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 text-slate-600 transition hover:border-brand hover:text-brand" aria-label="Log in">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="8" r="3.25" />
                                <path d="M5.5 19c1.8-3.2 4.3-4.8 6.5-4.8S16.5 15.8 18.5 19" />
                            </svg>
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <section id="hero" class="relative overflow-hidden">
                <img src="{{ asset('images/hero-network.jpg') }}" alt="Q Score Analysis" class="h-[300px] w-full object-cover sm:h-[360px] lg:h-[420px]">
                <div class="absolute inset-0 bg-gradient-to-r from-brand/80 via-brand/40 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0">
                    <div class="mx-auto max-w-[1200px] px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
                        <p class="font-heading text-3xl font-medium text-white sm:text-[2.6rem]">About us</p>
                        <p class="mt-2 text-sm font-semibold uppercase tracking-[0.28em] text-brand-light">The Q Score Analysis story</p>
                    </div>
                </div>
            </section>

            <section id="about" class="bg-white">
                <div class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl">
                        <h1 class="font-heading text-3xl font-normal text-slate-700 sm:text-[2rem]">About Q Score Analysis</h1>
                        <div class="mt-8 space-y-5 text-[0.8rem] leading-6 text-slate-700 sm:text-[0.85rem]">
                            <p>Q Score Analysis is a small analytics business focused on the UK charity sector. We built it to make financial performance easier to understand, compare and discuss.</p>
                            <p>Our aim is simple: turn charity accounts into something clearer and more practical for trustees, advisers and other stakeholders who need to see where resources are going and how effectively they are being used.</p>
                            <p>The portal lets users review Public Information Reports, compare Q scores and move from browsing to access in a straightforward, client-friendly flow.</p>
                            <p>We also support a wider service offering for professional users who need sector analysis, benchmarking and reporting tools presented in a consistent format.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="q-scores" class="bg-qsa-grey">
                <div class="mx-auto max-w-[1200px] px-4 py-14 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl">
                        <h2 class="font-heading text-2xl font-normal text-slate-700 sm:text-[1.7rem]">The Q score methodology</h2>
                        <div class="mt-6 grid gap-6 lg:grid-cols-[0.9fr_1.1fr] lg:items-start">
                            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <p class="text-sm text-slate-700">We analyse charity data across a set of financial dimensions and combine the results into a single score designed to help users understand relative effectiveness.</p>
                            </div>
                            <div class="space-y-4 text-[0.8rem] leading-6 text-slate-700 sm:text-[0.85rem]">
                                <p>The result is a concise view of performance that is easier to compare across organisations than raw accounts alone.</p>
                                <p>That makes it suitable for quick review, deeper analysis and client conversations where clarity matters more than data volume.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="pir-service" class="bg-white">
                <div class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl">
                        <h2 class="font-heading text-2xl font-normal text-slate-700 sm:text-[1.7rem]">Public Information Report (PIR) Service</h2>
                        <div class="mt-6 space-y-5 text-[0.8rem] leading-6 text-slate-700 sm:text-[0.85rem]">
                            <p>Our PIR service presents structured report access for users who want a detailed view of a charity’s latest published analysis.</p>
                            <p>It is designed as a practical browsing and purchasing journey, so a client can move from a summary view into the full report and download area without friction.</p>
                        </div>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ route('catalogue.pir') }}" class="rounded-sm bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-brand">PIR Service</a>
                            @auth
                                <a href="{{ route('my-reports') }}" class="rounded-sm border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:border-brand hover:text-brand">My reports</a>
                            @endauth
                        </div>
                    </div>
                </div>
            </section>

            <section id="psp-service" class="bg-qsa-grey">
                <div class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl">
                        <h2 class="font-heading text-2xl font-normal text-slate-700 sm:text-[1.7rem]">Professional Service Provider (PSP) Service</h2>
                        <p class="mt-6 text-[0.8rem] leading-6 text-slate-700 sm:text-[0.85rem]">For advisers and sector specialists, the portal supports a more tailored service experience built around comparison, analysis and report access.</p>
                    </div>
                </div>
            </section>

            <section id="contact" class="bg-qsa-grey">
                <div class="mx-auto max-w-[1200px] px-4 py-12 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl">
                        <h2 class="font-heading text-2xl font-normal text-slate-700 sm:text-[1.7rem]">Contact Us</h2>
                        <p class="mt-4 text-[0.8rem] leading-6 text-slate-700 sm:text-[0.85rem]">If you have further questions about our services, please contact us via our contact form, which can be found by clicking the button below.</p>
                        <div class="mt-6">
                            <a href="#" class="inline-flex rounded-sm bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-brand">Contact us</a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-[1200px] px-4 py-8 text-center text-[0.7rem] text-slate-500 sm:px-6 lg:px-8">
                © Q Score Analysis Ltd 2026 | Privacy Policy | Website: AC/ NP
            </div>
        </footer>
    </body>
</html>
