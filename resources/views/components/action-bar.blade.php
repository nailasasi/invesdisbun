@props([
    'border' => true,
])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 px-6 py-4 lg:flex-row lg:items-center lg:justify-between' . ($border ? ' border-b border-disbun-card-border' : '')]) }}>
    <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
        {{ $slot }}
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
            {{ $actions }}
        </div>
    @endif
</div>
