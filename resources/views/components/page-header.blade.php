@props(['title', 'subtitle' => null, 'actions' => null])

<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div>
        <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endif
</div>
