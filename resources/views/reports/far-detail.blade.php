<x-public :title="$charity->name">
    <a href="{{ route('catalogue.far') }}" class="text-sm text-brand hover:underline">&larr; Back to FAR catalogue</a>
    <h1 class="mt-2 text-2xl font-bold text-brand">{{ $charity->name }}</h1>
    <p class="text-gray-600">Charity Commission ref: {{ $charity->cc_ref }}</p>

    <div class="mt-6 grid grid-cols-2 gap-4 sm:max-w-md">
        <div class="rounded border border-gray-200 bg-white p-4">
            <div class="text-xs uppercase text-gray-500">Q score</div>
            <div class="text-2xl font-semibold">{{ $charity->latest_q_score }}</div>
        </div>
        <div class="rounded border border-gray-200 bg-white p-4">
            <div class="text-xs uppercase text-gray-500">Stability</div>
            <div class="text-2xl font-semibold">{{ $charity->latest_stability }}</div>
        </div>
    </div>

    @if ($issue)
        <p class="mt-4 text-sm text-gray-600">Current issue: {{ $issue->version_label }}
            (published {{ $issue->published_at?->format('j M Y') }})</p>
    @endif

    <div class="mt-6 flex items-center gap-4">
        <span class="text-2xl font-bold">{{ \App\Support\Money::format($price) }}</span>
        @auth
            <button class="rounded bg-brand px-5 py-2 font-medium text-white" disabled title="Checkout arrives in M2">Buy now</button>
        @else
            <a href="{{ route('login') }}" class="rounded bg-brand px-5 py-2 font-medium text-white">Log in to buy</a>
        @endauth
    </div>

    @auth
        @if ($teaser)
            <p class="mt-4">
                <a href="{{ route('assets.download', $teaser) }}" class="text-sm text-brand hover:underline">View free sample</a>
            </p>
        @endif
    @endauth
</x-public>
