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
        <x-site-header />

        <main>
            <section id="hero" class="relative overflow-hidden">
                <img src="{{ asset('images/hero-network.jpg') }}" alt="Q Score Analysis" class="h-[300px] w-full object-cover sm:h-[360px] lg:h-[420px]">
                <div class="absolute inset-0 bg-gradient-to-r from-brand/80 via-brand/40 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0">
                    <div class="mx-auto max-w-[1200px] px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
                        <p class="font-heading text-3xl font-medium text-white sm:text-[2.6rem]">Q Score Analysis Portal</p>
                        <p class="mt-2 text-sm font-semibold uppercase tracking-[0.28em] text-brand-light">Browse and Purchase our Reports</p>
                    </div>
                </div>
            </section>

            <section id="about" class="bg-white">
                <div class="mx-auto max-w-[1200px] px-4 py-16 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl">
                        <h1 class="font-heading text-3xl font-normal text-slate-700 sm:text-[2rem]">Welcome to the Q Score Analysis Portal</h1>
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
                            <a href="{{ route('catalogue.pir') }}" class="rounded-sm bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-brand">Browse our PIR Database</a>
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
                        <div class="mt-6 space-y-5 text-[0.8rem] leading-6 text-slate-700 sm:text-[0.85rem]">
                            <p>For advisers and sector specialists, the portal supports a more tailored service experience built around comparison, analysis and report access.</p>
                            <p>We have produced in depth Financial Analysis Reports (FAR) for each of the professional service sectors to show how each provider is performing.</p>
                        </div>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="#" class="rounded-sm bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-brand">View a Sample FAR</a>
                        </div>
                    </div>
                </div>
            </section>

            <section id="contact" class="bg-qsa-grey">
                <div class="mx-auto max-w-[1200px] px-4 py-12 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl">
                        <h2 class="font-heading text-2xl font-normal text-slate-700 sm:text-[1.7rem]">Contact Us</h2>
                        <p class="mt-4 text-[0.8rem] leading-6 text-slate-700 sm:text-[0.85rem]">If you have further questions about our services, please contact us via our contact form, which can be found by clicking the button below.</p>
                        <div class="mt-6">
                            <a href="{{ route('contact') }}" class="inline-flex rounded-sm bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-brand">Contact us</a>
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

        <a href="#" id="back-to-top" aria-label="Back to top" title="Back to top">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="18" height="18" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .53.22l6 6a.75.75 0 1 1-1.06 1.06L10.75 5.56V16.25a.75.75 0 0 1-1.5 0V5.56L4.53 10.28a.75.75 0 0 1-1.06-1.06l6-6A.75.75 0 0 1 10 3Z" clip-rule="evenodd"/></svg>
            <span>Top</span>
        </a>
        <style>
            #back-to-top {
                position: fixed;
                right: 0;
                bottom: 2.5rem;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0.25rem;
                padding: 0.6rem 0.55rem;
                border-radius: 0.25rem 0 0 0.25rem;
                background-color: #0f172a;
                color: #fff;
                font-size: 0.6rem;
                font-weight: 600;
                letter-spacing: 0.18em;
                text-transform: uppercase;
                text-decoration: none;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.25);
                opacity: 0;
                visibility: hidden;
                transform: translateX(0.5rem);
                transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s, background-color 0.2s ease;
                z-index: 50;
            }
            #back-to-top.is-visible { opacity: 1; visibility: visible; transform: translateX(0); }
            #back-to-top:hover { background-color: #00c7c3; }
        </style>
        <script>
            (function () {
                var btn = document.getElementById('back-to-top');
                if (!btn) return;
                var toggle = function () { btn.classList.toggle('is-visible', window.scrollY > 300); };
                window.addEventListener('scroll', toggle, { passive: true });
                toggle();
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            })();
        </script>
    </body>
</html>
