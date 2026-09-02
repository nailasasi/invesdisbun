@props(['padding' => true])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200/80 bg-white shadow-soft' . ($padding ? ' p-6' : '')]) }}>
    {{ $slot }}
</div>