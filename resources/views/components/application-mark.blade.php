{{-- resources/views/components/application-mark.blade.php --}}
<img
    src="{{ asset('images/cropped-amdlogoblu.jpeg') }}"
    alt="{{ config('app.name') }}"
    {{ $attributes->merge([
        'class' => 'h-10 w-auto'
    ]) }}
/>