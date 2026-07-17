{{--
    Partial form untuk create/edit order.
    Expects: $order (null untuk create), $platforms (Collection), $products (Collection).
--}}
@php
    $o = $order ?? null;
    $statusOptions = [
        \App\Models\Order::STATUS_PENDING => 'Pending',
        \App\Models\Order::STATUS_PACKED => 'Packed',
        \App\Models\Order::STATUS_RETURN => 'Return',
        \App\Models\Order::STATUS_SELESAI_RETURN => 'Selesai Return',
        \App\Models\Order::STATUS_SELESAI_BULAN_KEMARIN => 'Selesai Bulan Kemarin',
    ];

    // Kelengkapan options: value => label
    $kelengkapanOptions = [
        '1' => '1 = Stir Saja',
        '2' => '2 = Stir + Boskit',
        '3' => '3 = Boskit Saja',
        '4' => '4 = Spoiler',
        '5' => '5 = Klakson',
        '6' => '6 = Stir + Stir',
        '7' => '7 = Stir + Stir + Boskit',
        '8' => '8 = Stir + Boskit + Boskit',
    ];

    // Mapping kelengkapan => field variant yang harus ditampilkan.
    // Harus sinkron dengan KELENGKAPAN_MAP di JavaScript & controller.
    $kelengkapanFieldMap = [
        '1' => ['stir_1'],
        '2' => ['stir_1', 'boskit_1'],
        '3' => ['boskit_1'],
        '4' => ['spoiler'],
        '5' => ['klakson'],
        '6' => ['stir_1', 'stir_2'],
        '7' => ['stir_1', 'stir_2', 'boskit_1'],
        '8' => ['stir_1', 'boskit_1', 'boskit_2'],
    ];

    // Koleksi variant per kategori.
    // Filter case-insensitive berdasarkan keyword di field "type" ATAU "name"
    // supaya fleksibel: produk dengan type "Stir Motor", "Stir Mobil", "Stir Universal"
    // atau nama "Stir Skeleton" semua masuk ke dropdown Stir.
    $matchByKeyword = function ($keywords) use ($products) {
        $keywords = (array) $keywords;
        return $products->filter(function ($p) use ($keywords) {
            $haystack = strtolower(($p->type ?? '').' '.($p->name ?? ''));
            foreach ($keywords as $kw) {
                if (str_contains($haystack, strtolower($kw))) {
                    return true;
                }
            }
            return false;
        })->values();
    };

    $stirProducts    = $matchByKeyword('stir');
    $boskitProducts  = $matchByKeyword(['boskit', 'bosskit']);
    $spoilerProducts = $matchByKeyword('spoiler');
    $klaksonProducts = $matchByKeyword('klakson');

    // Existing items for edit mode
    $existingItems = $o ? $o->items : collect();

    // Daftar semua varian (flat) untuk picker barang yang bisa dicari.
    $allVariants = $products->flatMap(function ($p) {
        return $p->variants->map(function ($v) use ($p) {
            return [
                'id' => $v->id,
                'label' => trim(($p->name ?? '') . ' - ' . ($v->name ?? ''), ' -'),
                'sku' => $v->sku,
                'selling' => (float) ($p->selling_price ?? 0),
                'purchase' => (float) ($p->purchase_price ?? 0),
            ];
        });
    })->values();

    // Item existing (mode edit) untuk pre-fill picker.
    $existingItemsData = $existingItems->map(fn ($it) => [
        'variant_id' => $it->variant_id,
        'quantity' => $it->quantity,
    ])->values();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="label">Nomor Resi <span class="text-red-500">*</span></label>
        <input type="text" name="resi_number" required maxlength="32"
               value="{{ old('resi_number', $o->resi_number ?? '') }}"
               class="input font-mono" placeholder="JX9374396076">
        @error('resi_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">Order ID</label>
        <input type="text" name="tiktok_order_id" maxlength="64"
               value="{{ old('tiktok_order_id', $o->tiktok_order_id ?? '') }}"
               class="input font-mono" placeholder="584005180715730252">
        @error('tiktok_order_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">Kurir</label>
        <input type="text" name="courier" maxlength="20"
               value="{{ old('courier', $o->courier ?? 'JNT') }}"
               class="input" placeholder="JNT">
        @error('courier') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">Status</label>
        <select name="status" class="input">
            @foreach ($statusOptions as $val => $label)
                <option value="{{ $val }}"
                    <?php if (old('status', $o->status ?? \App\Models\Order::STATUS_PENDING) === $val) echo 'selected'; ?>>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('status') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">Pengirim</label>
        <input type="text" name="sender_name" maxlength="150"
               value="{{ old('sender_name', $o->sender_name ?? '') }}"
               class="input" placeholder="ArrozaqAuto96">
        @error('sender_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">Host Live</label>
        <input type="text" name="host_live" maxlength="100"
               value="{{ old('host_live', $o->host_live ?? '') }}"
               class="input" placeholder="Host A">
        @error('host_live') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">Pembeli</label>
        <input type="text" name="buyer_name" maxlength="150"
               value="{{ old('buyer_name', $o->buyer_name ?? '') }}"
               class="input" placeholder="Nama pembeli">
        @error('buyer_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">No. HP</label>
        <input type="text" name="buyer_phone" maxlength="30"
               value="{{ old('buyer_phone', $o->buyer_phone ?? '') }}"
               class="input font-mono" placeholder="081234567890">
        @error('buyer_phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">Platform</label>
        <select name="platform_deduction_id" class="input">
            <option value="">— tidak dipilih —</option>
            @foreach ($platforms as $p)
                <option value="{{ $p->id }}"
                    <?php if ((int) old('platform_deduction_id', $o->platform_deduction_id ?? 0) === (int) $p->id) echo 'selected'; ?>>
                    {{ $p->platform_name }}
                </option>
            @endforeach
        </select>
        @error('platform_deduction_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label">Tanggal Pesanan</label>
        <input type="date" name="order_date"
               value="{{ old('order_date', $o?->order_date ? $o->order_date->format('Y-m-d') : '') }}"
               class="input">
        @error('order_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="label">Alamat Pengiriman</label>
        <textarea name="shipping_address" rows="2" class="input">{{ old('shipping_address', $o->shipping_address ?? '') }}</textarea>
        @error('shipping_address') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="label">Catatan (Seller Note)</label>
        <textarea name="notes" rows="2" class="input">{{ old('notes', $o->notes ?? '') }}</textarea>
        @error('notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="label">Total Potongan Aplikasi (Manual Override)</label>
        <input type="number" step="any" min="0" name="total_potongan_aplikasi_override"
               value="{{ old('total_potongan_aplikasi_override', $o?->total_potongan_aplikasi_override !== null ? rtrim(rtrim(number_format((float) $o->total_potongan_aplikasi_override, 2, '.', ''), '0'), '.') : '') }}"
               class="input font-mono" placeholder="Kosongkan untuk hitungan otomatis">
        <p class="text-xs text-gray-500 mt-1">
            Isi angka untuk override Total Potongan Aplikasi (contoh: <code>18900</code> untuk Rp 18.900).
            Kosongkan field ini supaya pakai hitungan otomatis = ADM + Bulat Max + Biaya Layanan + Biaya Logistik + Pajak.
        </p>
        @error('total_potongan_aplikasi_override') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
</div>


<style>[x-cloak]{display:none !important;}</style>
<div class="mt-6 border-t pt-4" x-data="orderItemsPicker()">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700">Pilihan Barang</h3>
        <span class="text-xs text-gray-500">Cari &amp; pilih barang apa saja (boleh lebih dari satu).</span>
    </div>

    <label class="label">Barang</label>
    <div class="space-y-2">
        <template x-for="(item, i) in items" :key="item._key">
            <div class="flex gap-2 items-start">
                <div class="flex-1 relative">
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
                                <span class="text-xs text-gray-400" x-show="v.sku" x-text="' - ' + v.sku"></span>
                            </button>
                        </template>
                        <div class="px-3 py-2 text-sm text-gray-400"
                             x-show="filtered(item.search).length === 0">
                            Tidak ada barang cocok.
                        </div>
                    </div>
                </div>

                <input type="number" min="1" :name="`items[${i}][quantity]`"
                       x-model.number="item.quantity"
                       class="input w-20 text-center" placeholder="Qty">

                <button type="button" class="btn-secondary" @click="remove(i)" x-show="items.length > 1">-</button>
            </div>
        </template>
    </div>
    <button type="button" class="btn-secondary mt-2" @click="add()">+ Tambah Barang</button>

    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="label">Total Harga Jual (Auto)</label>
            <input type="text" class="input bg-gray-100 font-mono" readonly
                   :value="formatRp(totalJual)" placeholder="Otomatis dihitung">
        </div>
        <div>
            <label class="label">Total Harga Modal (Auto)</label>
            <input type="text" class="input bg-gray-100 font-mono" readonly
                   :value="formatRp(totalModal)" placeholder="Otomatis dihitung">
        </div>
    </div>
</div>

<script>
    function orderItemsPicker() {
        return {
            variants: @json($allVariants),
            items: [],
            _k: 0,
            init() {
                const existing = @json($existingItemsData);
                if (existing.length) {
                    existing.forEach(e => this.addExisting(e.variant_id, e.quantity));
                } else {
                    this.add();
                }
            },
            add() {
                this.items.push({ _key: this._k++, variant_id: '', search: '', quantity: 1, open: false });
            },
            addExisting(variantId, qty) {
                const v = this.variants.find(x => x.id == variantId);
                this.items.push({
                    _key: this._k++,
                    variant_id: variantId || '',
                    search: v ? v.label : '',
                    quantity: qty || 1,
                    open: false,
                });
            },
            remove(i) {
                this.items.splice(i, 1);
                if (!this.items.length) this.add();
            },
            filtered(q) {
                q = (q || '').toLowerCase().trim();
                if (!q) return this.variants.slice(0, 50);
                return this.variants.filter(v =>
                    v.label.toLowerCase().includes(q) ||
                    (v.sku || '').toLowerCase().includes(q)
                ).slice(0, 50);
            },
            select(item, v) {
                item.variant_id = v.id;
                item.search = v.label;
                item.open = false;
            },
            get totalJual() {
                return this.items.reduce((s, it) => {
                    const v = this.variants.find(x => x.id == it.variant_id);
                    return s + (v ? v.selling * (parseInt(it.quantity) || 0) : 0);
                }, 0);
            },
            get totalModal() {
                return this.items.reduce((s, it) => {
                    const v = this.variants.find(x => x.id == it.variant_id);
                    return s + (v ? v.purchase * (parseInt(it.quantity) || 0) : 0);
                }, 0);
            },
            formatRp(n) { return n > 0 ? 'Rp ' + n.toLocaleString('id-ID') : ''; },
        };
    }
</script>
