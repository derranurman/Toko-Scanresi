@extends('layouts.app')
@section('title', $mapping->exists ? 'Edit Combo Mapping' : 'Combo Mapping Baru')

@section('content')
    @php($header = $mapping->exists ? 'Edit Combo Mapping' : 'Combo Mapping Baru')

    @php
        // Daftar varian (flat) untuk picker yang bisa dicari.
        $comboVariantsData = $variants->map(fn ($v) => [
            'id' => $v->id,
            'label' => trim(($v->product->name ?? '') . ' - ' . ($v->name ?? ''), ' -'),
            'sku' => $v->sku,
        ])->values();
    @endphp

    <div class="card max-w-3xl" x-data="comboForm({{ json_encode(
            $mapping->exists
                ? $mapping->items->map(fn ($i) => ['variant_id' => $i->variant_id, 'quantity' => $i->quantity])->values()
                : [['variant_id' => '', 'quantity' => 1]]
        ) }})">
        <form method="POST" action="{{ $mapping->exists ? route('combo_mappings.update', $mapping) : route('combo_mappings.store') }}" class="space-y-4">
            @csrf
            @if ($mapping->exists) @method('PUT') @endif

            <div>
                <label class="label">Keyword (harus unik)</label>
                <input name="keyword" value="{{ old('keyword', $mapping->keyword) }}" required class="input font-mono"
                       placeholder="contoh: Stir+Bosskit">
                <p class="text-xs text-gray-500 mt-1">Teks ini akan dicari (case-insensitive) di dalam teks "Barang : …" pada label.</p>
                @error('keyword')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label">Deskripsi (opsional)</label>
                <input name="description" value="{{ old('description', $mapping->description) }}" class="input" placeholder="Bundle paket stir + boskit untuk promo">
            </div>

            <div>
                <label class="label">Varian yang dikurangi</label>
                <style>[x-cloak]{display:none !important;}</style>
                <div class="space-y-2">
                    <template x-for="(item, i) in items" :key="i">
                        <div class="flex gap-2 items-start">
                            <div class="flex-1 relative">
                                <input type="hidden" :name="'items[' + i + '][variant_id]'" :value="item.variant_id">
                                <input type="text" class="input"
                                       :class="{ 'border-red-400': item.search && !item.variant_id }"
                                       placeholder="Cari varian / SKU..."
                                       x-model="item.search"
                                       @focus="item.open = true"
                                       @input="item.open = true; item.variant_id = ''"
                                       @click.away="item.open = false">
                                <div class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-auto"
                                     x-show="item.open" x-cloak>
                                    <template x-for="v in filtered(item.search)" :key="v.id">
                                        <button type="button"
                                                class="block w-full text-left px-3 py-2 text-sm hover:bg-indigo-50"
                                                @click="select(item, v)">
                                            <span x-text="v.label"></span>
                                            <span class="text-xs text-gray-400" x-show="v.sku" x-text="' - ' + v.sku"></span>
                                        </button>
                                    </template>
                                    <div class="px-3 py-2 text-sm text-gray-400" x-show="filtered(item.search).length === 0">Tidak ada varian cocok.</div>
                                </div>
                            </div>
                            <input type="number" min="1" :name="'items[' + i + '][quantity]'" x-model.number="item.quantity" required class="input w-20 text-center" placeholder="Qty">
                            <button type="button" class="btn-secondary" @click="remove(i)" x-show="items.length > 1">-</button>
                        </div>
                    </template>
                </div>
                <button type="button" class="btn-secondary mt-2" @click="add()">+ Tambah Varian</button>
                @error('items')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-2 pt-3 border-t">
                <button class="btn-primary" type="submit">Simpan</button>
                <a href="{{ route('combo_mappings.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    <script>
        function comboForm(initial) {
            return {
                variants: @json($comboVariantsData),
                items: [],
                init() {
                    const src = (initial && initial.length) ? initial : [{ variant_id: '', quantity: 1 }];
                    src.forEach(it => {
                        const v = this.variants.find(x => x.id == it.variant_id);
                        this.items.push({
                            variant_id: it.variant_id || '',
                            quantity: it.quantity || 1,
                            search: v ? v.label : '',
                            open: false,
                        });
                    });
                },
                add() { this.items.push({ variant_id: '', quantity: 1, search: '', open: false }); },
                remove(i) { this.items.splice(i, 1); if (!this.items.length) this.add(); },
                filtered(q) {
                    q = (q || '').toLowerCase().trim();
                    if (!q) return this.variants.slice(0, 50);
                    return this.variants.filter(v =>
                        v.label.toLowerCase().includes(q) || (v.sku || '').toLowerCase().includes(q)
                    ).slice(0, 50);
                },
                select(item, v) { item.variant_id = v.id; item.search = v.label; item.open = false; },
            };
        }
    </script>
@endsection
