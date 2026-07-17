@props(['href'])
<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100']) }}>
    {{ $slot }}
</a>
