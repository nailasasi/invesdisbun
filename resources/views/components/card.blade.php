@props(['padding' => true])

<div {{ $attributes->merge(['class' => 'bg-white rounded-3xl shadow-bento border border-disbun-card-border overflow-hidden' . ($padding ? ' p-6' : '')]) }}>
    {{ $slot }}
</div>