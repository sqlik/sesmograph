@props(['for'])

@error($for)
    <p {{ $attributes->merge(['class' => 'mt-1.5 text-sm text-danger']) }}>{{ $message }}</p>
@enderror
