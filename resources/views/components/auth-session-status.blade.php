@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert-success font-medium']) }}>
        {{ $status }}
    </div>
@endif
