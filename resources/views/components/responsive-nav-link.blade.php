@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-blue-600 bg-blue-50 py-2 pe-4 ps-3 text-start text-base font-medium text-blue-700 transition'
            : 'block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
