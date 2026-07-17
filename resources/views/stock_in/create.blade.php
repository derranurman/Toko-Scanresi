@extends('layouts.app')
@section('title', 'Input Barang Masuk')

@section('content')
    @php($header = 'Input Barang Masuk')
    @php($subheader = 'Catat penerimaan stok dari supplier / restock. Bisa input banyak varian sekaligus.')

    <style>[x-cloak]{display:none !important;}</style>

    <div class="card max-w-4xl"
         x-data="stockInApp({{ $variants->toJson() }})">
        <form method="POST" action="{{ route('stock_in.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">Nomor Referensi (opsional)</label>
                    <input name="reference" value="{{ old('reference') }}" class="input" placeholder="Contoh: PO-2026-0012">
                    @error('reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Catatan (opsional)</label>
                    <input name="note" value="{{ old('note') }}" class="input" placeholder="Contoh: Restock dari PT Aksesoris">
                    @error('note')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="label">Daftar Barang Masuk</label>
                <p class="text-xs text-gray-500 mb-2">Cari barang / SKU lalu pilih dari daftar (boleh lebih dari satu varian).</p>
                <div class="space-y-2">
                    <template x-for="(item, i) in items" :key="item._key">
                        <div class="flex flex-wrap gap-2 items-start">
                            <div class="flex-1 min-w-[280px] relative">
                                <input type="hidden" :name="`items[${i}][variant_id]`" :value="item.variant_id">
                                <input type="text" class="input"
                                       :class="{ 'border-red-400': item.search && !item.variant_id }"
                                       placeholder="Cari barang / SKU..."
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
                                        </button>
                                    </template>
                                    <div class="px-3 py-2 text-sm text-gray-400"
                                         x-show="filtered(item.search).length === 0">
                                        Tidak ada barang cocok.
                                    </div>
                                </div>
                            </div>
                            <input type="number" min="1" step="1" :name="`items[${i}][quantity]`" x-model.number="item.quantity" required
                                   class="input w-24 text-center" placeholder="Qty">
                            <button type="button" class="btn-secondary" @click="remove(i)" x-show="items.length > 1">−</button>
                        </div>
                    </template>
                </div>
                <button type="button" class="btn-secondary mt-2" @click="add()">+ Tambah Baris</button>
                @error('items')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('items.*.variant_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                @error('items.*.quantity')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-lg bg-indigo-50 border border-indigo-200 px-3 py-2 text-sm text-indigo-800">
                Total akan ditambah:
                <span class="font-semibold" x-text="totalQty()"></span> pcs
                dalam <span class="font-semibold" x-text="items.filter(i => i.variant_id && i.quantity).length"></span> varian.
            </div>

            <div class="flex gap-2 pt-3 border-t">
                <button class="btn-primary" type="submit">Simpan Barang Masuk</button>
                <a href="{{ route('products.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    <script>
        function stockInApp(variants) {
            return {
                variants,
                items: [],
                _k: 0,
                init() {
                    this.add();
                },
                add() {
                    this.items.push({ _key: this._k++, variant_id: '', search: '', quantity: 1, open: false });
                },
                remove(i) {
                    this.items.splice(i, 1);
                    if (!this.items.length) this.add();
                },
                filtered(q) {
                    q = (q || '').toLowerCase().trim();
                    if (!q) return this.variants.slice(0, 50);
                    return this.variants.filter(v =>
                        (v.label || '').toLowerCase().includes(q) ||
                        (v.sku || '').toLowerCase().includes(q)
                    ).slice(0, 50);
                },
                select(item, v) {
                    item.variant_id = v.id;
                    item.search = v.label;
                    item.open = false;
                },
                totalQty() {
                    return this.items.reduce((sum, it) => sum + (parseInt(it.quantity) || 0), 0);
                },
            };
        }
    </script>
@endsection
