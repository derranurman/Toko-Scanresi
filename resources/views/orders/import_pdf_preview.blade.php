@extends('layouts.app')
@section('title', 'Pratinjau Import PDF')

@section('content')
    <?php $header = 'Pratinjau Import PDF'; ?>
    <?php $subheader = 'Cek hasil parse untuk tiap halaman. Centang yang ingin disimpan.'; ?>

    <div class="card mb-6">
        <div class="flex flex-wrap gap-4 items-center justify-between text-sm">
            <div>
                <div><span class="text-gray-500">File:</span> <b>{{ $draft->original_filename }}</b></div>
                <div><span class="text-gray-500">Halaman:</span> {{ $draft->total_pages }} · Diupload {{ $draft->created_at->diffForHumans() }}</div>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('orders.import.pdf.remap', $draft) }}"
                      title="Jalankan ulang resolver dengan mapping terkini, tanpa upload PDF lagi">
                    @csrf
                    <button class="btn-secondary" type="submit">Sinkronkan Mapping</button>
                </form>
                <form method="POST" action="{{ route('orders.import.pdf.discard', $draft) }}"
                      onsubmit="return confirm('Buang draft ini? Tidak akan disimpan sebagai pesanan.');">
                    @csrf
                    @method('DELETE')
                    <button class="btn-secondary" type="submit">Buang Draft</button>
                </form>
            </div>
        </div>
    </div>

<div x-data="quickMapping()" x-on:open-mapping.window="open($event.detail)">
    <form method="POST" action="{{ route('orders.import.pdf.commit', $draft) }}">
        @csrf

        <div class="card mb-4 flex flex-wrap items-center gap-3">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" id="select-all" checked class="rounded border-gray-300">
                Pilih semua
            </label>
            <span class="text-xs text-gray-500">
                Kuning = resi sudah ada (akan diupdate). Merah = ada item belum ke-mapping.
            </span>
            <div class="ml-auto">
                <button type="submit" class="btn-primary">Simpan Pesanan yang Dicentang</button>
            </div>
        </div>

        <div class="space-y-4">
            <?php foreach ($draft->parsed_orders as $idx => $entry): ?>
                <?php
                    $hasUnmatched = false;
                    foreach ($entry['items'] as $it) {
                        if (($it['source'] ?? null) === 'unmatched' || empty($it['variant_id'])) {
                            $hasUnmatched = true;
                            break;
                        }
                    }
                    $rowClass = $hasUnmatched
                        ? 'border-red-200 bg-red-50/40'
                        : ($entry['already_exists'] ? 'border-amber-200 bg-amber-50/40' : 'border-gray-200 bg-white');
                ?>
                <div class="rounded-xl border {{ $rowClass }} p-4">
                    <div class="flex flex-wrap items-start gap-3">
                        <label class="pt-1">
                            <input type="checkbox" name="selected[]" value="{{ $idx }}"
                                   class="item-check rounded border-gray-300 h-4 w-4"
                                   {{ $hasUnmatched ? '' : 'checked' }}>
                        </label>

                        <div class="flex-1 min-w-[280px]">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs text-gray-500">Hal. {{ $entry['page'] }}</span>
                                <span class="font-mono text-lg font-bold">{{ $entry['resi_number'] ?: '—' }}</span>
                                <?php $mp = $entry['marketplace'] ?? 'tiktok'; ?>
                                <?php $cr = $entry['courier'] ?? 'JNT'; ?>
                                <?php if ($mp === 'shopee'): ?>
                                    <span class="badge bg-orange-100 text-orange-700">Shopee / SPX</span>
                                <?php elseif ($mp === 'tokopedia'): ?>
                                    <?php if ($cr === 'JNT_CARGO'): ?>
                                        <span class="badge bg-emerald-100 text-emerald-700">Tokopedia / J&amp;T Cargo</span>
                                    <?php else: ?>
                                        <span class="badge bg-emerald-100 text-emerald-700">Tokopedia</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($cr === 'JNT_CARGO'): ?>
                                        <span class="badge bg-cyan-100 text-cyan-700">TikTok / J&amp;T Cargo</span>
                                    <?php else: ?>
                                        <span class="badge bg-indigo-100 text-indigo-700">TikTok / J&amp;T</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($entry['already_exists']): ?>
                                    <span class="badge bg-amber-100 text-amber-700">Sudah ada &middot; akan update</span>
                                <?php endif; ?>
                                <?php if ($entry['matched_keyword']): ?>
                                    <span class="badge bg-indigo-100 text-indigo-700">Combo: {{ $entry['matched_keyword'] }}</span>
                                <?php endif; ?>
                            </div>

                            {{-- Info mentah dari label PDF (bukan hasil resolusi) --}}
                            <dl class="mt-2 text-xs space-y-1">
                                <div class="flex">
                                    <dt class="w-28 text-gray-500 shrink-0">Pengirim</dt>
                                    <dd class="font-medium">{{ $entry['sender_name'] ?? '—' }}</dd>
                                </div>
                                <div class="flex">
                                    <dt class="w-28 text-gray-500 shrink-0">Resi</dt>
                                    <dd class="font-mono font-semibold">{{ $entry['resi_number'] ?: '—' }}</dd>
                                </div>
                                <div class="flex">
                                    <dt class="w-28 text-gray-500 shrink-0">Order ID</dt>
                                    <dd class="font-mono">{{ $entry['tiktok_order_id'] ?? '—' }}</dd>
                                </div>
                                <div class="flex items-start">
                                    <dt class="w-28 text-gray-500 shrink-0">Product Name</dt>
                                    <dd class="flex-1">
                                        <?php $pRows = $entry['product_rows'] ?? []; ?>
                                        <?php if (empty($pRows)): ?>
                                            <span class="text-gray-400">—</span>
                                        <?php else: ?>
                                            <ul class="space-y-0.5">
                                                <?php foreach ($pRows as $pr): ?>
                                                    <li>{{ $pr['product_name'] ?? '—' }}</li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <?php if (!empty($entry['barang_keyword'])): ?>
                                    <div class="flex">
                                        <dt class="w-28 text-gray-500 shrink-0">Jumlah &amp; Barang</dt>
                                        <?php
                                            $totalLabelQty = 0;
                                            foreach ($pRows as $pr) {
                                                $totalLabelQty += (int) ($pr['quantity'] ?? 0);
                                            }
                                            if ($totalLabelQty <= 0) $totalLabelQty = 1;
                                        ?>
                                        <dd><span class="font-medium">{{ $totalLabelQty }}pcs</span>, {{ $entry['barang_keyword'] }}</dd>
                                    </div>
                                <?php endif; ?>
                                <div class="flex items-start">
                                    <dt class="w-28 text-gray-500 shrink-0">SKU</dt>
                                    <dd class="flex-1 font-mono">
                                        <?php
                                            $skuParts = [];
                                            foreach ($pRows as $pr) {
                                                if (!empty($pr['sku'])) $skuParts[] = $pr['sku'];
                                            }
                                            $skuJoined = implode(' · ', array_unique($skuParts));
                                        ?>
                                        {{ $skuJoined !== '' ? $skuJoined : '—' }}
                                    </dd>
                                </div>
                                <div class="flex items-start">
                                    <dt class="w-28 text-gray-500 shrink-0">Variasi</dt>
                                    <dd class="flex-1">
                                        <?php
                                            $variasiParts = [];
                                            foreach ($pRows as $pr) {
                                                if (!empty($pr['seller_sku'])) $variasiParts[] = $pr['seller_sku'];
                                            }
                                            $variasiJoined = implode(' · ', array_unique($variasiParts));
                                        ?>
                                        {{ $variasiJoined !== '' ? $variasiJoined : '—' }}
                                    </dd>
                                </div>
                                <div class="flex items-start">
                                    <dt class="w-28 text-gray-500 shrink-0">Seller Note</dt>
                                    <dd class="flex-1">
                                        <?php if (!empty($entry['seller_note'])): ?>
                                            <span class="font-mono font-semibold text-indigo-600">{{ $entry['seller_note'] }}</span>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <?php if (!empty($entry['customer_message'])): ?>
                                    <div class="flex items-start">
                                        <dt class="w-28 text-gray-500 shrink-0">Customer Msg</dt>
                                        <dd class="flex-1">
                                            <span class="text-purple-700">{{ $entry['customer_message'] }}</span>
                                        </dd>
                                    </div>
                                <?php endif; ?>
                            </dl>

                            {{-- Penerima & alamat (secondary, lebih redup) --}}
                            <div class="mt-2 text-xs text-gray-600">
                                Penerima: <span class="font-medium">{{ $entry['buyer_name'] ?? '—' }}</span>
                                <?php if (!empty($entry['buyer_phone'])): ?>
                                    &middot; <span class="text-gray-500">{{ $entry['buyer_phone'] }}</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($entry['shipping_address'])): ?>
                                <div class="text-[11px] text-gray-500">{{ $entry['shipping_address'] }}</div>
                            <?php endif; ?>
                            <div class="text-[11px] text-gray-400 mt-1">
                                {{ $entry['courier'] }} &middot; {{ $entry['weight'] ?? '—' }} &middot; {{ $entry['order_date'] ?? '—' }}
                            </div>

                            <?php if (!empty($entry['raw_text'] ?? null) || empty($entry['items'])): ?>
                                <details class="mt-2">
                                    <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-700">Lihat Teks Mentah PDF (debug)</summary>
                                    <pre class="mt-1 text-[10px] bg-gray-50 border border-gray-200 rounded p-2 max-h-40 overflow-auto whitespace-pre-wrap font-mono text-gray-600">{{ $entry['raw_text'] ?? '(tidak tersimpan)' }}</pre>
                                </details>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1 min-w-[280px]">
                            <div class="text-xs font-semibold uppercase text-gray-500 mb-1">Item Setelah Resolusi</div>

                            <?php if (empty($entry['items'])): ?>
                                <div class="text-xs text-red-600">Tidak ada item terdeteksi.</div>
                            <?php else: ?>
                                <ul class="text-sm divide-y">
                                    <?php
                                        // Seller Note di-append ke baris produk PERTAMA saja (bukan
                                        // setiap item) supaya tidak terlihat duplikat & ringkas.
                                        $sellerNote = $entry['seller_note'] ?? null;
                                        $noteShown = false;
                                    ?>
                                    <?php foreach ($entry['items'] as $item): ?>
                                        <?php
                                            $source = $item['source'] ?? 'unmatched';
                                            $sourceBadge = match ($source) {
                                                'combo'       => ['combo', 'bg-indigo-100 text-indigo-700'],
                                                'sku'         => ['SKU match', 'bg-green-100 text-green-700'],
                                                'name'        => ['nama match', 'bg-sky-100 text-sky-700'],
                                                'seller_note' => ['seller note', 'bg-purple-100 text-purple-700'],
                                                default       => ['perlu mapping', 'bg-red-100 text-red-700'],
                                            };
                                            $appendNote = (! $noteShown && ! empty($sellerNote));
                                            if ($appendNote) {
                                                $noteShown = true;
                                            }
                                        ?>
                                        <li class="py-1 flex items-center gap-2 flex-wrap">
                                            <span class="badge bg-gray-100 text-gray-700">{{ $item['quantity'] }}&times;</span>
                                            <span class="flex-1">
                                                <span class="font-medium">{{ $item['product_name'] }}@if (!empty($item['variant_name'])) &mdash; {{ $item['variant_name'] }}@endif@if ($appendNote), <span class="text-purple-700 font-semibold">{{ $sellerNote }}</span>@endif</span>
                                                <?php if (!empty($item['sku'])): ?>
                                                    <span class="text-xs text-gray-400 font-mono ml-1">{{ $item['sku'] }}</span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['matched_keyword']) && $source !== 'combo'): ?>
                                                    <div class="text-[10px] text-gray-500 mt-0.5">{{ $item['matched_keyword'] }}</div>
                                                <?php endif; ?>
                                            </span>
                                            <span class="badge {{ $sourceBadge[1] }}">{{ $sourceBadge[0] }}</span>
                                            <?php if ($source === 'unmatched'): ?>
                                                <?php
                                                    // Bangun keyword PERSIS seperti yang ditampilkan
                                                    // di kolom "Item Setelah Resolusi", yaitu:
                                                    //   "{product_name} — {variant_name}"
                                                    // Format ini dipilih supaya:
                                                    //   1. User langsung tahu apa yang akan disimpan
                                                    //      tanpa harus ngedit field keyword.
                                                    //   2. Keyword cocok dengan combined label text
                                                    //      (barang_keyword + product_name + seller_sku
                                                    //      + sku) di resolver, jadi auto re-resolve
                                                    //      langsung apply.
                                                    //
                                                    // Seller note SENGAJA tidak ikut di keyword
                                                    // karena seller_note tidak masuk ke combined
                                                    // label text. Kalau user mau seller_note (mis.
                                                    // "t16 solder") trigger combo terpisah, bikin
                                                    // Combo Mapping dengan keyword "t16 solder" lewat
                                                    // menu Combo Mapping biasa.
                                                    $kwName = trim((string) ($item['product_name'] ?? ''));
                                                    $kwVariant = trim((string) ($item['variant_name'] ?? ''));
                                                    $defaultKw = $kwName;
                                                    if ($kwVariant !== '') {
                                                        $defaultKw = $kwName.' — '.$kwVariant;
                                                    }
                                                    if ($defaultKw === '') {
                                                        $defaultKw = (string) ($entry['barang_keyword'] ?? '');
                                                    }
                                                    $defaultDesc = $defaultKw;
                                                ?>
                                                <button type="button"
                                                        class="text-xs text-indigo-700 hover:text-indigo-900 underline decoration-dotted"
                                                        data-keyword="{{ $defaultKw }}"
                                                        data-description="{{ $defaultDesc }}"
                                                        @click="$dispatch('open-mapping', { keyword: $el.dataset.keyword, description: $el.dataset.description })">
                                                    Atur Mapping
                                                </button>
                                            <?php elseif ($source === 'combo' && ! empty($item['combo_mapping_id'])): ?>
                                                {{-- Tombol "Edit Mapping" untuk item yang sudah
                                                     ke-resolve via combo. Modal yang sama dipakai,
                                                     tapi pre-loaded dengan keyword/items existing
                                                     dari $mappingsById (di-injeksi di footer). --}}
                                                <button type="button"
                                                        class="text-xs text-indigo-700 hover:text-indigo-900 underline decoration-dotted"
                                                        data-mapping-id="{{ $item['combo_mapping_id'] }}"
                                                        @click="$dispatch('open-mapping', { mapping_id: Number($el.dataset.mappingId) })">
                                                    Edit Mapping
                                                </button>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if (!empty($entry['warnings'])): ?>
                                <div class="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                                    <ul class="list-disc list-inside">
                                        <?php foreach ($entry['warnings'] as $w): ?>
                                            <li>{{ $w }}</li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('combo_mappings.index') }}" class="btn-secondary">Buka Combo Mapping</a>
            <button type="submit" class="btn-primary">Simpan Pesanan yang Dicentang</button>
        </div>
    </form>

    {{-- Modal: Atur Mapping cepat. Submit ke endpoint quick_mapping yang sekaligus
         menjalankan ulang resolver pada draft, jadi item yang tadinya "perlu mapping"
         langsung ter-mapping tanpa upload PDF lagi. --}}
    <div x-show="visible"
         x-cloak
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="close()"
         style="display: none;">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
             @click.outside="close()">
            <form method="POST" :action="actionUrl" class="p-5 space-y-4" @submit="validateSubmit($event)">
                @csrf
                <input type="hidden" name="mapping_id" :value="mappingId ?? ''">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold" x-text="mappingId ? 'Edit Mapping' : 'Atur Mapping'"></h2>
                        <p class="text-xs text-gray-500">
                            Setelah disimpan, mapping akan langsung diterapkan ke pratinjau ini.
                            Tidak perlu upload PDF resi lagi.
                        </p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-lg leading-none"
                            @click="close()" aria-label="Tutup">&times;</button>
                </div>

                <div>
                    <label class="label">Keyword (harus unik)</label>
                    <input name="keyword" x-model="keyword" required
                           class="input font-mono"
                           placeholder="contoh: Stir Racing Mugen R13,5">
                    <p class="text-xs text-gray-500 mt-1">
                        Teks ini dicari (case-insensitive) di Barang / Seller SKU / Seller Note label PDF.
                    </p>
                </div>

                <div>
                    <label class="label">Deskripsi (opsional)</label>
                    <input name="description" x-model="description" class="input">
                </div>

                <div>
                    <label class="label">Pecah menjadi varian</label>
                    <div class="space-y-2">
                        <template x-for="(item, i) in items" :key="i">
                            <div class="flex gap-2 items-start">
                                <div class="relative flex-1"
                                     @click.outside="item.open = false"
                                     @keydown.escape.stop="item.open = false">
                                    <input type="text"
                                           class="input w-full"
                                           x-model="item.search"
                                           @focus="item.open = true"
                                           @input="item.open = true; item.variant_id = ''"
                                           @click="item.open = true"
                                           placeholder="Ketik nama / SKU varian, atau klik untuk pilih"
                                           autocomplete="off">
                                    <input type="hidden"
                                           :name="'items[' + i + '][variant_id]'"
                                           :value="item.variant_id">
                                    <div x-show="item.open"
                                         x-cloak
                                         x-transition.opacity
                                         class="absolute z-20 left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg"
                                         style="display: none;">
                                        <template x-for="v in filteredVariants(item.search)" :key="v.id">
                                            <button type="button"
                                                    class="w-full text-left px-3 py-1.5 text-sm hover:bg-indigo-50"
                                                    :class="{ 'bg-indigo-50 font-medium': String(item.variant_id) === String(v.id) }"
                                                    @click="pickVariant(item, v)"
                                                    x-text="v.label"></button>
                                        </template>
                                        <div x-show="filteredVariants(item.search).length === 0"
                                             class="px-3 py-2 text-xs text-gray-400">
                                            Tidak ada varian cocok.
                                        </div>
                                    </div>
                                </div>
                                <input type="number" min="1" :name="'items[' + i + '][quantity]'"
                                       x-model.number="item.quantity" required
                                       class="input w-20 text-center">
                                <button type="button" class="btn-secondary" @click="remove(i)" x-show="items.length > 1">−</button>
                            </div>
                        </template>
                    </div>
                    <button type="button" class="btn-secondary mt-2" @click="add()">+ Tambah Varian</button>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" class="btn-secondary" @click="close()">Batal</button>
                    <button type="submit" class="btn-primary"
                            x-text="mappingId ? 'Update & Terapkan' : 'Simpan & Terapkan'"></button>
                </div>
            </form>
        </div>
    </div>
</div>{{-- /x-data quickMapping --}}

    <script>
        document.getElementById('select-all').addEventListener('change', function (e) {
            document.querySelectorAll('.item-check').forEach(function (c) {
                c.checked = e.target.checked;
            });
        });

        function quickMapping() {
            return {
                visible: false,
                actionUrl: @json(route('orders.import.pdf.quick_mapping', $draft)),
                // ID mapping yang sedang di-edit. null = mode CREATE,
                // angka = mode UPDATE. Dikirim sebagai hidden input
                // 'mapping_id' di form.
                mappingId: null,
                keyword: '',
                description: '',
                // Daftar semua varian untuk combobox (cari + pilih manual).
                // Disuntik sekali di awal supaya tidak ada DOM <option> raksasa
                // berulang per row dan filter bisa dijalankan di client.
                variantsList: @json($variants->map(fn ($v) => [
                    'id'    => $v->id,
                    'label' => trim(($v->product?->name ?? '').' — '.$v->name).' ('.$v->sku.')',
                ])->values()),
                // Map combo_mapping_id → { id, keyword, description, items[] }
                // untuk mapping yang sedang dipakai oleh draft ini. Dipakai
                // mode EDIT supaya modal bisa pre-fill tanpa fetch tambahan.
                mappingsById: @json($mappingsById ?? new \stdClass()),
                items: [{ variant_id: '', quantity: 1, search: '', open: false }],
                open(data) {
                    const mid = data && data.mapping_id ? Number(data.mapping_id) : null;
                    const m = mid ? this.mappingsById[mid] : null;

                    if (m) {
                        // Mode EDIT: prefill dari mapping existing.
                        this.mappingId = mid;
                        this.keyword = m.keyword || '';
                        this.description = m.description || '';
                        this.items = (m.items || []).map(it => {
                            const v = this.variantsList.find(x => Number(x.id) === Number(it.variant_id));
                            return {
                                variant_id: it.variant_id,
                                quantity: it.quantity,
                                search: v ? v.label : '',
                                open: false,
                            };
                        });
                        if (this.items.length === 0) {
                            this.items = [{ variant_id: '', quantity: 1, search: '', open: false }];
                        }
                    } else {
                        // Mode CREATE: data dari tombol "Atur Mapping" (default
                        // keyword + description, items kosong).
                        this.mappingId = null;
                        this.keyword = (data && data.keyword) || '';
                        this.description = (data && data.description) || '';
                        this.items = [{ variant_id: '', quantity: 1, search: '', open: false }];
                    }
                    this.visible = true;
                },
                close() {
                    this.visible = false;
                },
                add() {
                    this.items.push({ variant_id: '', quantity: 1, search: '', open: false });
                },
                remove(i) {
                    this.items.splice(i, 1);
                },
                // Cari case-insensitive di label gabungan (produk + varian + SKU).
                // Dibatasi 100 hasil supaya rendering tetap ringan walaupun ada
                // ribuan varian.
                filteredVariants(q) {
                    const s = (q || '').toString().trim().toLowerCase();
                    const list = this.variantsList || [];
                    if (!s) {
                        return list.slice(0, 100);
                    }
                    const out = [];
                    for (let i = 0; i < list.length && out.length < 100; i++) {
                        if (list[i].label.toLowerCase().includes(s)) {
                            out.push(list[i]);
                        }
                    }
                    return out;
                },
                pickVariant(item, v) {
                    item.variant_id = v.id;
                    item.search = v.label;
                    item.open = false;
                },
                validateSubmit(e) {
                    // Hidden input variant_id tidak ikut HTML5 required-validation,
                    // jadi kita jaga di sini biar user dapat feedback langsung.
                    const missing = this.items.findIndex(it => !it.variant_id);
                    if (missing !== -1) {
                        e.preventDefault();
                        this.items[missing].open = true;
                        alert('Pilih varian dulu untuk semua baris sebelum menyimpan.');
                    }
                },
            };
        }
    </script>
@endsection
