@extends('layouts.app')
@section('title', 'Pesanan')

@section('content')
    <?php $header = 'Pesanan'; ?>

    <div class="card">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <p class="text-xs text-gray-500">
                Tabel bergerak horizontal. Host Live &amp; Platform bisa diedit langsung
                (perubahan platform akan otomatis menghitung ulang ADM, Ongkir, Pajak, dll).
            </p>
            <div class="flex gap-2">
                <a href="{{ route('orders.create') }}" class="btn-primary">+ Tambah Pesanan</a>
                <a href="{{ route('orders.info_rumus') }}" class="btn-secondary" title="Info rumus perhitungan">ⓘ Info Rumus</a>
                <a href="{{ route('orders.export', request()->query()) }}" class="btn-secondary">⬇ Export Excel</a>
                <a href="{{ route('orders.export_pdf', request()->query()) }}" target="_blank" rel="noopener" class="btn-secondary">⬇ Export PDF</a>
            </div>
        </div>

        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-4">
            <input name="q" value="{{ $q }}" placeholder="Cari resi / order id / pembeli / host…" class="input">
            <select name="status" class="input">
                <option value="">Semua status</option>
                <option value="pending"               <?php if ($status === 'pending') echo 'selected'; ?>>Pending</option>
                <option value="packed"                <?php if ($status === 'packed') echo 'selected'; ?>>Packed</option>
                <option value="return"                <?php if ($status === 'return') echo 'selected'; ?>>Return</option>
                <option value="selesai_return"        <?php if ($status === 'selesai_return') echo 'selected'; ?>>Selesai Return</option>
                <option value="selesai_bulan_kemarin" <?php if ($status === 'selesai_bulan_kemarin') echo 'selected'; ?>>Selesai Bulan Kemarin</option>
            </select>
            <input type="date" name="date" value="{{ $date }}" class="input">
            <div class="flex gap-2">
                <button class="btn-primary flex-1" type="submit">Filter</button>
                <a href="{{ route('orders.index') }}" class="btn-secondary">Reset</a>
            </div>
        </form>

        {{-- Form khusus untuk bulk delete. Ditaruh di luar <table> supaya
             tidak bertabrakan dengan form-form inline (host_live, platform,
             status, dll.) yang ada di tiap baris. Checkbox di tabel
             "tergabung" ke form ini lewat atribut form="bulk-delete-form". --}}
        <form id="bulk-delete-form"
              method="POST"
              action="{{ route('orders.bulk_destroy', request()->query()) }}"
              onsubmit="return confirmBulkDelete(this);"
              class="mb-3">
            @csrf
            @method('DELETE')
            <div class="flex items-center gap-2 flex-wrap">
                <button type="submit"
                        id="bulk-delete-btn"
                        class="px-3 py-1.5 rounded text-xs font-semibold bg-red-600 text-white hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
                        disabled>
                    🗑 Hapus Terpilih (<span id="bulk-delete-count">0</span>)
                </button>
                <span class="text-xs text-gray-500">
                    Centang baris yang ingin dihapus. Pesanan berstatus
                    <span class="font-semibold">packed</span> akan dilewati otomatis.
                </span>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="text-xs whitespace-nowrap border-collapse">
                <thead class="text-left uppercase text-gray-500 border-b bg-gray-50">
                    <tr>
                        <th class="px-2 py-2">
                            <input type="checkbox" id="bulk-select-all"
                                   title="Pilih semua di halaman ini"
                                   class="rounded border-gray-300">
                        </th>
                        <th class="px-2 py-2">No</th>
                        <th class="px-2 py-2">Tanggal</th>
                        <th class="px-2 py-2">Resi</th>
                        <th class="px-2 py-2">Host Live</th>
                        <th class="px-2 py-2">Platform</th>
                        <th class="px-2 py-2">Pengirim</th>
                        <th class="px-2 py-2">Pembeli</th>
                        <th class="px-2 py-2">No. HP</th>
                        <th class="px-2 py-2">Nama Barang</th>
                        <th class="px-2 py-2">SKU</th>
                        <th class="px-2 py-2 text-right">Harga Jual</th>
                        <th class="px-2 py-2 text-right">Total Jual</th>
                        <th class="px-2 py-2 text-right">Total Modal</th>
                        <th class="px-2 py-2 text-right">Total Reseller</th>
                        <th class="px-2 py-2 text-right">Ongkir Cargo</th>
                        <th class="px-2 py-2 text-right">Yield</th>
                        <th class="px-2 py-2 text-right">Label (Rp)</th>
                        <th class="px-2 py-2 text-right">Plastik/Dus</th>
                        <th class="px-2 py-2 text-right">Operasional</th>
                        <th class="px-2 py-2 text-right">ADM</th>
                        <th class="px-2 py-2 text-right">Ongkir Free</th>
                        <th class="px-2 py-2 text-right">Bulat Max 650Rb</th>
                        <th class="px-2 py-2 text-right">Biaya Layanan (Rp)</th>
                        <th class="px-2 py-2 text-right">Biaya Logistik (Rp)</th>
                        <th class="px-2 py-2 text-right">Pajak</th>
                        <th class="px-2 py-2 text-right">Profit Kotor</th>
                        <th class="px-2 py-2 text-right">% Profit Kotor</th>
                        <th class="px-2 py-2 text-right">Margin Bisnis</th>
                        <th class="px-2 py-2 text-right">% Margin Bisnis</th>
                        <th class="px-2 py-2 text-right">Margin Live</th>
                        <th class="px-2 py-2 text-right">% Margin Live</th>
                        <th class="px-2 py-2 text-right">Bersih Margin Live</th>
                        <th class="px-2 py-2 text-right">TOTAL POTONGAN APLIKASI</th>
                        <th class="px-2 py-2">Status</th>
                        <th class="px-2 py-2 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if ($orders->isEmpty()): ?>
                        <tr>
                            <td colspan="36" class="py-6 text-center text-gray-500">
                                Belum ada pesanan.
                                <a href="{{ route('orders.import.pdf.show') }}" class="text-indigo-600 hover:underline">Import PDF &rarr;</a>
                                <span class="text-gray-400">·</span>
                                <a href="{{ route('orders.create') }}" class="text-indigo-600 hover:underline">Tambah manual &rarr;</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $iOrder => $order): ?>
                            <?php
                                $m = $metrics[$order->id];

                                // Ringkas: gabung Nama Barang & SKU semua item, harga jual ambil item pertama.
                                $skuList = [];
                                $nameList = [];
                                $firstSellingPrice = null;
                                foreach ($order->items as $it) {
                                    if ($it->sku) {
                                        $skuList[] = $it->sku;
                                    }
                                    // Prioritas: nama master product, fallback ke product_name yang tersimpan
                                    // di order_items (kalau master sudah dihapus).
                                    $itName = $it->variant?->product?->name ?? $it->product_name;
                                    if ($it->variant?->name) {
                                        $itName = trim(($itName ?? '—') . ' — ' . $it->variant->name);
                                    }
                                    if ($itName) {
                                        $nameList[] = $itName;
                                    }
                                    if ($firstSellingPrice === null) {
                                        $firstSellingPrice = (float) ($it->variant?->product?->selling_price ?? 0);
                                    }
                                }
                                $skuDisplay = implode(', ', array_unique($skuList));
                                $namaDisplay = implode(', ', array_unique($nameList));
                                $no = $startNo + $iOrder;

                                $fmt = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
                                $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . '%';

                                $profitClass = $m['profit_kotor'] >= 0 ? 'text-green-600' : 'text-red-600 font-bold';
                                $marginBisnisClass = $m['margin_bisnis'] >= 0 ? 'text-green-600' : 'text-red-600 font-bold';
                                $marginLiveClass = $m['margin_live'] >= 0 ? 'text-green-600' : 'text-red-600 font-bold';
                                $bersihMarginLiveClass = $m['bersih_margin_live'] >= 0 ? 'text-green-600' : 'text-red-600 font-bold';

                                // Helper: kalau nilai negatif, tampilkan merah + bold
                                // (request user: "kolom yg isi nominal nya -minus semua kasih
                                // tampilan warna merah yg tebal").
                                $negCls = fn ($v) => ((float) $v) < 0 ? 'text-red-600 font-bold' : '';
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-2 py-2">
                                    <input type="checkbox"
                                           form="bulk-delete-form"
                                           name="ids[]"
                                           value="{{ $order->id }}"
                                           class="bulk-select-row rounded border-gray-300"
                                           data-status="{{ $order->status }}"
                                           data-resi="{{ $order->resi_number }}"
                                           @if ($order->status === 'packed') disabled title="Pesanan sudah di-packing, tidak bisa dihapus" @endif>
                                </td>
                                <td class="px-2 py-2">{{ $no }}</td>

                                <td class="px-2 py-2 whitespace-nowrap">{{ $order->created_at ? $order->created_at->format('d M Y') : '—' }}</td>

                                <td class="px-2 py-2 font-mono">
                                    <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:underline">{{ $order->resi_number }}</a>
                                    <div class="text-gray-400 normal-case">{{ $order->tiktok_order_id }}</div>
                                </td>

                                {{-- Host Live & Platform: inline form --}}
                                <td class="px-2 py-2">
                                    <form method="POST" action="{{ route('orders.update_meta', $order) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="host_live" value="{{ $order->host_live }}"
                                               placeholder="—" class="input text-xs py-1 w-28">
                                        <input type="hidden" name="platform_deduction_id" value="{{ $order->platform_deduction_id }}">
                                        <button type="submit" class="text-indigo-600 hover:underline text-xs">OK</button>
                                    </form>
                                </td>

                                <td class="px-2 py-2">
                                    <form method="POST" action="{{ route('orders.update_meta', $order) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="hidden" name="host_live" value="{{ $order->host_live }}">
                                        <select name="platform_deduction_id" onchange="this.form.submit()" class="input text-xs py-1 w-36">
                                            <option value="">— pilih —</option>
                                            <?php foreach ($platforms as $p): ?>
                                                <option value="{{ $p->id }}" <?php if ((int) $order->platform_deduction_id === (int) $p->id) echo 'selected'; ?>>
                                                    {{ $p->platform_name }}
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>

                                <td class="px-2 py-2">
                                    <form method="POST" action="{{ route('orders.update_meta', $order) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="sender_name" value="{{ $order->sender_name }}"
                                               placeholder="—" class="input text-xs py-1 w-32">
                                        <button type="submit" class="text-indigo-600 hover:underline text-xs">OK</button>
                                    </form>
                                </td>
                                <td class="px-2 py-2">
                                    <form method="POST" action="{{ route('orders.update_meta', $order) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="buyer_name" value="{{ $order->buyer_name }}"
                                               placeholder="—" class="input text-xs py-1 w-32">
                                        <button type="submit" class="text-indigo-600 hover:underline text-xs">OK</button>
                                    </form>
                                </td>
                                <td class="px-2 py-2">
                                    <form method="POST" action="{{ route('orders.update_meta', $order) }}" class="inline-flex items-center gap-1">
                                        @csrf
                                        <input type="text" name="buyer_phone" value="{{ $order->buyer_phone }}"
                                               placeholder="—" class="input text-xs py-1 w-28 font-mono">
                                        <button type="submit" class="text-indigo-600 hover:underline text-xs">OK</button>
                                    </form>
                                </td>
                                <td class="px-2 py-2">
                                    <div class="max-w-[260px] truncate" title="{{ $namaDisplay }}">{{ $namaDisplay ?: '—' }}</div>
                                </td>
                                <td class="px-2 py-2 font-mono">{{ $skuDisplay ?: '—' }}</td>

                                <td class="px-2 py-2 text-right font-mono {{ $negCls($firstSellingPrice ?? 0) }}">{{ $fmt($firstSellingPrice ?? 0) }}</td>
                                <td class="px-2 py-2 text-right font-mono font-semibold {{ $negCls($m['total_jual']) }}">{{ $fmt($m['total_jual']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['total_modal']) }}">{{ $fmt($m['total_modal']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['total_reseller']) }}">{{ $fmt($m['total_reseller']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['ongkir_cargo']) }}">{{ $fmt($m['ongkir_cargo']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['yield_rp']) }}">{{ $fmt($m['yield_rp']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['label']) }}">{{ $fmt($m['label']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['plastik_dus']) }}">{{ $fmt($m['plastik_dus']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['operasional_rp']) }}">{{ $fmt($m['operasional_rp']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['adm_rp']) }}">{{ $fmt($m['adm_rp']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['ongkir_free_rp']) }}">{{ $fmt($m['ongkir_free_rp']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['bulat_max']) }}">{{ $fmt($m['bulat_max']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['biaya_layanan']) }}">{{ $fmt($m['biaya_layanan']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['biaya_logistik']) }}">{{ $fmt($m['biaya_logistik']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $negCls($m['pajak_rp']) }}">{{ $fmt($m['pajak_rp']) }}</td>
                                <td class="px-2 py-2 text-right font-mono font-semibold {{ $profitClass }}">{{ $fmt($m['profit_kotor']) }}</td>
                                <td class="px-2 py-2 text-right {{ $profitClass }}">{{ $pct($m['pct_profit_kotor']) }}</td>
                                <td class="px-2 py-2 text-right font-mono font-semibold {{ $marginBisnisClass }}">{{ $fmt($m['margin_bisnis']) }}</td>
                                <td class="px-2 py-2 text-right {{ $marginBisnisClass }}">{{ $pct($m['pct_margin_bisnis']) }}</td>
                                <td class="px-2 py-2 text-right font-mono font-semibold {{ $marginLiveClass }}">{{ $fmt($m['margin_live']) }}</td>
                                <td class="px-2 py-2 text-right {{ $marginLiveClass }}">{{ $pct($m['pct_margin_live']) }}</td>
                                <td class="px-2 py-2 text-right font-mono {{ $bersihMarginLiveClass }}">{{ $fmt($m['bersih_margin_live']) }}</td>
                                <td class="px-2 py-2 text-right">
                                    <form method="POST" action="{{ route('orders.update_potongan', $order) }}" class="inline-flex items-center gap-1 justify-end">
                                        @csrf
                                        <input type="text" inputmode="numeric"
                                               name="total_potongan_aplikasi_override"
                                               value="{{ $order->total_potongan_aplikasi_override !== null ? number_format((float) $order->total_potongan_aplikasi_override, 0, ',', '.') : '' }}"
                                               placeholder="{{ number_format((float) $m['total_potongan_aplikasi'], 0, ',', '.') }}"
                                               title="Kosongkan untuk pakai hitungan otomatis ({{ $fmt($m['total_potongan_aplikasi']) }})"
                                               class="input text-xs py-1 w-28 text-right font-mono font-semibold {{ $order->total_potongan_aplikasi_override !== null ? 'text-red-600 bg-red-50' : 'text-red-600' }}">
                                        <button type="submit" class="text-indigo-600 hover:underline text-xs">OK</button>
                                    </form>
                                    @if ($order->total_potongan_aplikasi_override !== null)
                                        <div class="text-[10px] text-amber-600 mt-1">manual override</div>
                                    @endif
                                </td>

                                <td class="px-2 py-2">
                                    <form method="POST" action="{{ route('orders.update_status', $order) }}" class="inline">
                                        @csrf
                                        <select name="status" onchange="this.form.submit()"
                                                class="text-xs rounded border px-2 py-1 font-semibold
                                                    {{ $order->status === 'pending' ? 'bg-amber-100 text-amber-700 border-amber-300' : '' }}
                                                    {{ $order->status === 'packed' ? 'bg-green-100 text-green-700 border-green-300' : '' }}
                                                    {{ $order->status === 'return' ? 'bg-red-100 text-red-700 border-red-300' : '' }}
                                                    {{ $order->status === 'selesai_return' ? 'bg-purple-100 text-purple-700 border-purple-300' : '' }}
                                                    {{ $order->status === 'selesai_bulan_kemarin' ? 'bg-blue-100 text-blue-700 border-blue-300' : '' }}">
                                            <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                            <option value="packed" {{ $order->status === 'packed' ? 'selected' : '' }}>Packed</option>
                                            <option value="return" {{ $order->status === 'return' ? 'selected' : '' }}>Return</option>
                                            <option value="selesai_return" {{ $order->status === 'selesai_return' ? 'selected' : '' }}>Selesai Return</option>
                                            <option value="selesai_bulan_kemarin" {{ $order->status === 'selesai_bulan_kemarin' ? 'selected' : '' }}>Selesai Bulan Kemarin</option>
                                        </select>
                                    </form>
                                </td>

                                <td class="px-2 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:underline">Detail</a>
                                    <span class="text-gray-300">·</span>
                                    <a href="{{ route('orders.edit', $order) }}" class="text-sky-600 hover:underline">Edit</a>
                                    <span class="text-gray-300">·</span>
                                    <form method="POST" action="{{ route('orders.destroy', $order) }}" class="inline"
                                          onsubmit="return confirm('Hapus pesanan {{ $order->resi_number }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    </div>

    {{-- Behaviour untuk bulk delete: select-all, counter, konfirmasi. --}}
    <script>
        (function () {
            const form = document.getElementById('bulk-delete-form');
            const selectAll = document.getElementById('bulk-select-all');
            const btn = document.getElementById('bulk-delete-btn');
            const counter = document.getElementById('bulk-delete-count');
            if (!form || !btn || !counter) return;

            const rowCheckboxes = () => Array.from(document.querySelectorAll('.bulk-select-row:not(:disabled)'));

            function refresh() {
                const checked = rowCheckboxes().filter(c => c.checked);
                counter.textContent = checked.length;
                btn.disabled = checked.length === 0;

                if (selectAll) {
                    const all = rowCheckboxes();
                    selectAll.checked = all.length > 0 && checked.length === all.length;
                    selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    rowCheckboxes().forEach(c => { c.checked = selectAll.checked; });
                    refresh();
                });
            }

            document.addEventListener('change', function (e) {
                if (e.target && e.target.classList && e.target.classList.contains('bulk-select-row')) {
                    refresh();
                }
            });

            window.confirmBulkDelete = function (formEl) {
                const checked = rowCheckboxes().filter(c => c.checked);
                if (checked.length === 0) {
                    alert('Pilih minimal satu pesanan untuk dihapus.');
                    return false;
                }
                return confirm('Hapus ' + checked.length + ' pesanan terpilih? Tindakan ini tidak bisa dibatalkan.');
            };

            refresh();
        })();
    </script>
@endsection
