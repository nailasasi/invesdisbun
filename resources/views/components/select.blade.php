@props([
    'label' => null,
    'name',
    'id' => null,
    'required' => false,
    'options' => [],
    'placeholder' => '-- Pilih --',
    'help' => null,
])

@php
    $fieldId = $id ?? $name;
    $hasError = $errors->has($name);
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-slate-700">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        {!! $attributes->merge([
            'class' => 'block w-full rounded-xl border bg-white px-3.5 py-2 text-sm text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0 transition ' .
                ($hasError
                    ? 'border-red-400 focus:border-red-500 focus:ring-red-400'
                    : 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-400')
        ]) !!}
        @if ($required) required @endif
    >
        <option value="" disabled {{ old($name) === null && !isset($selected) ? 'selected' : '' }}>{{ $placeholder }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected($attributes->has('value') ? (string)$attributes->get('value') === (string)$value : (string)old($name) === (string)$value)>
                {{ $text }}
            </option>
        @endforeach
    </select>

    @if ($hasError)
        <p class="text-xs font-medium text-red-600">{{ $errors->first($name) }}</p>
    @endif

    @if ($help)
        <p class="text-xs text-slate-400">{{ $help }}</p>
    @endif
</div>
