@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
])

@php
    $variants = [
        'primary' => 'bg-disbun-700 text-white shadow-sm hover:bg-disbun-800 focus-visible:ring-disbun-600',
        'accent' => 'bg-gradient-to-r from-disbun-amber to-disbun-amber-glow text-white shadow-sm hover:brightness-105 focus-visible:ring-disbun-amber',
        'secondary' => 'bg-white text-slate-700 border border-disbun-card-border shadow-sm hover:bg-slate-50 focus-visible:ring-slate-400',
        'danger' => 'bg-gradient-to-b from-red-500 to-red-600 text-white shadow-soft shadow-red-600/30 hover:brightness-105 hover:shadow-red-600/40 focus-visible:ring-red-500',
        'outline' => 'bg-transparent text-slate-600 border border-disbun-card-border hover:border-slate-300 hover:bg-slate-50 focus-visible:ring-slate-400',
    ];
    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
        'lg' => 'px-4 py-2.5 text-sm',
    ];
    $classes = "inline-flex items-center justify-center gap-2 rounded-2xl font-bold transition-all duration-200 ease-out active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none {$variants[$variant]} {$sizes[$size]}";
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
        @endif
        {{ $slot }}
    </button>
@endif