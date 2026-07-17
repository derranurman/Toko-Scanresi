@props(['active' => false, 'href'])

<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'px-3 py-2 rounded-md text-sm font-medium '.
        ($active ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:text-indigo-700 hover:bg-gray-50')]) }}>
    {{ $slot }}
</a>
