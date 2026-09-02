@props([
    'title' => null,
    'description' => null,
    'footer' => null,
])

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    @if ($title)
        <div class="border-b border-slate-100 px-6 py-4">
            <h3 class="text-base font-semibold text-slate-800">{{ $title }}</h3>
            @if ($description)
                <p class="mt-0.5 text-sm text-slate-500">{{ $description }}</p>
            @endif
        </div>
    @endif

    <div class="p-6">
        {{ $slot }}
    </div>

    @if ($footer)
        <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4">
            {{ $footer }}
        </div>
    @endif
</div>
