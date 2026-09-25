@props(['disabled' => false])

@if (($attributes->get('type') ?? 'text') === 'password')
    {{-- Password fields get a show/hide toggle (eye icon) on the right --}}
    <div x-data="{ show: false }" style="position: relative;" class="{{ $attributes->get('class') }}">
        <input @disabled($disabled)
               {{ $attributes->except(['class', 'type'])->merge(['class' => 'border-gray-300 focus:border-brand focus:ring-brand rounded-md shadow-sm block w-full']) }}
               :type="show ? 'text' : 'password'"
               style="padding-right: 2.75rem;">
        <button type="button"
                x-on:click="show = !show"
                :aria-label="show ? 'Hide password' : 'Show password'"
                :title="show ? 'Hide password' : 'Show password'"
                tabindex="-1"
                style="position: absolute; top: 0; right: 0; bottom: 0; display: flex; align-items: center; padding: 0 0.75rem; background: none; border: 0; color: #64748b; cursor: pointer;">
            {{-- eye (password hidden) --}}
            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="width: 1.25rem; height: 1.25rem;" aria-hidden="true">
                <path d="M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12Z" />
                <circle cx="12" cy="12" r="3" />
            </svg>
            {{-- eye-off (password shown) --}}
            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="width: 1.25rem; height: 1.25rem; display: none;" aria-hidden="true">
                <path d="M3 3l18 18" />
                <path d="M10.6 5.3A10.9 10.9 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17.4 17.4 0 0 1-3.2 3.9" />
                <path d="M6.6 6.6C4 8.4 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.6 0 3-.4 4.2-1" />
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
            </svg>
        </button>
    </div>
@else
    <input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-brand focus:ring-brand rounded-md shadow-sm']) }}>
@endif
