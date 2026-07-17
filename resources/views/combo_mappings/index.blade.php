@extends('layouts.app')
@section('title', 'Combo Mapping')

@section('content')
    @php($header = 'Combo Mapping')
    @php($subheader = 'Teks di label yang mewakili banyak produk dipecah otomatis saat import PDF.')

    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <div class="text-sm text-gray-500">
                Sistem akan mencari teks <b>keyword</b> di dalam label (mis. <span class="font-mono bg-gray-100 px-1 rounded">Stir+Bosskit</span>)
                dan memecahnya menjadi daftar varian di bawah.
            </div>
            <a href="{{ route('combo_mappings.create') }}" class="btn-primary">+ Mapping Baru</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 border-b">
                    <tr>
                        <th class="py-2">Keyword</th>
                        <th class="py-2">Pecah Menjadi</th>
                        <th class="py-2">Deskripsi</th>
                        <th class="py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($mappings as $mapping)
                        <tr class="align-top">
                            <td class="py-3 font-mono">{{ $mapping->keyword }}</td>
                            <td class="py-3">
                                <ul class="space-y-0.5">
                                    @foreach ($mapping->items as $item)
                                        <li class="flex items-center gap-2 text-xs">
                                            <span class="badge bg-gray-100 text-gray-700">{{ $item->quantity }}×</span>
                                            <span>
                                                {{ $item->variant?->product?->name }} — {{ $item->variant?->name }}
                                                <span class="text-gray-400 font-mono ml-1">{{ $item->variant?->sku }}</span>
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="py-3 text-xs text-gray-600">{{ $mapping->description }}</td>
                            <td class="py-3 text-right">
                                <a href="{{ route('combo_mappings.edit', $mapping) }}" class="text-indigo-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('combo_mappings.destroy', $mapping) }}"
                                      class="inline ml-2"
                                      onsubmit="return confirm('Hapus mapping ini?');">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-gray-500">
                                Belum ada mapping. Setelah upload PDF, kalau ada item yang "perlu mapping", buat di sini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $mappings->links() }}</div>
    </div>
@endsection
