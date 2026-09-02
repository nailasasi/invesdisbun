@props([
    'type' => 'success',
    'dismissible' => true,
])

@php
    $styles = [
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'error' => 'border-red-200 bg-red-50 text-red-800',
        'info' => 'border-blue-200 bg-blue-50 text-blue-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
    ];
    $icons = [
        'success' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'error' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
        'info' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    ];
@endphp

@if (session($type) || $slot->isNotEmpty() || ($type === 'error' && $errors->any()))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm shadow-sm {{ $styles[$type] }}">
        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$type] }}"/></svg>
        <div class="min-w-0 flex-1 leading-relaxed">
            @if ($slot->isNotEmpty())
                {{ $slot }}
            @elseif ($type === 'error' && $errors->any() && ! session('error'))
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            @else
                {{ session($type) }}
            @endif
        </div>
        @if ($dismissible)
            <button type="button" class="shrink-0 rounded-md p-1 opacity-70 transition hover:opacity-100" onclick="this.closest('div').remove()">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        @endif
    </div>
@endif
