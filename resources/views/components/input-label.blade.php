@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm text-amber-400/90 tracking-wide']) }}>
    {{ $value ?? $slot }}
</label>