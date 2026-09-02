@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
])

@php
    $variants = [
        'primary' => 'bg-gradient-to-b from-emerald-500 to-emerald-600 text-white shadow-soft shadow-emerald-600/30 hover:brightness-105 hover:shadow-emerald-600/40 focus-visible:ring-emerald-500',
        'secondary' => 'bg-white text-slate-700 border border-slate-200 shadow-soft hover:bg-slate-50 focus-visible:ring-slate-400',
        'danger' => 'bg-gradient-to-b from-red-500 to-red-600 text-white shadow-soft shadow-red-600/30 hover:brightness-105 hover:shadow-red-600/40 focus-visible:ring-red-500',
        'outline' => 'bg-transparent text-slate-600 border border-slate-200 hover:border-slate-300 hover:bg-slate-50 focus-visible:ring-slate-400',
    ];
    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
        'lg' => 'px-5 py-2.5 text-base',
    ];
    $classes = "inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-all duration-200 ease-out active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none {$variants[$variant]} {$sizes[$size]}";
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