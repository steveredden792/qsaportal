<x-public :title="$market->name">
    <a href="{{ route('catalogue.pmr') }}" class="text-sm text-brand hover:underline">&larr; Back to PMR catalogue</a>
    <h1 class="mt-2 text-2xl font-bold text-brand">{{ $market->name }}</h1>
    <p class="text-gray-600">Provider Market Report</p>
    @if ($issue)
        <p class="mt-1 text-sm text-gray-600">Current version: {{ $issue->version_label }}</p>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2 sm:max-w-xl">
        @foreach ($tiers as $tier)
            <div class="rounded border border-gray-200 bg-white p-4">
                <div class="text-lg font-semibold">{{ $tier['name'] }}</div>
                <div class="my-2 text-2xl font-bold">{{ \App\Support\Money::format($tier['price']) }}</div>
                <p class="text-sm text-gray-600">{{ $tier['desc'] }}</p>
                @auth
                    <button class="mt-3 w-full rounded bg-brand px-3 py-2 text-sm font-medium text-white" disabled title="Checkout arrives in M2">Buy {{ $tier['name'] }}</button>
                @else
                    <a href="{{ route('login') }}" class="mt-3 block rounded bg-brand px-3 py-2 text-center text-sm font-medium text-white">Log in to buy</a>
                @endauth
            </div>
        @endforeach
    </div>

    @auth
        @if ($teaser)
            <p class="mt-4">
                <a href="{{ route('assets.download', $teaser) }}" class="text-sm text-brand hover:underline">View free sample</a>
            </p>
        @endif
    @endauth
</x-public>
