@props([
    'fallback' => route('home'),
    'label' => 'Kembali',
])

<button type="button"
        onclick="if (window.history.length > 1 && document.referrer.includes(window.location.host)) { window.history.back(); } else { window.location.href = '{{ $fallback }}'; }"
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white hover:bg-gray-900 text-gray-800 hover:text-white border border-gray-200 shadow-xs text-xs font-black uppercase tracking-wider transition active:scale-95']) }}>
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
    </svg>
    <span>{{ $label }}</span>
</button>