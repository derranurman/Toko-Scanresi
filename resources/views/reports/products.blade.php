@extends('layouts.app')
@section('title', 'Laporan Produk')

@section('content')
    <?php $header = 'Laporan Produk'; ?>
    <?php $subheader = 'Pergerakan stok (barang masuk, keluar, penyesuaian) per produk.'; ?>

    <div class="card">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <div>
                <label class="label">Dari</label>
                <input type="date" name="from" value="{{ $from }}" class="input">
            </div>
            <div>
                <label class="label">Sampai</label>
                <input type="date" name="to" value="{{ $to }}" class="input">
            </div>
            <div>
                <label class="label">Produk</label>
                <select name="product_id" class="input">
                    <option value="">Semua produk</option>
                    <?php foreach ($products as $p): ?>
                        <option value="{{ $p->id }}" <?php if ((int) $productId === (int) $p->id) echo 'selected'; ?>>{{ $p->name }}</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="label">Tipe</label>
                <select name="type" class="input">
                    <option value="">Semua tipe</option>
                    <option value="in" <?php if ($type === 'in') echo 'selected'; ?>>Barang Masuk</option>
                    <option value="out" <?php if ($type === 'out') echo 'selected'; ?>>Barang Keluar</option>
                    <option value="adjustment" <?php if ($type === 'adjustment') echo 'selected'; ?>>Penyesuaian</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button class="btn-primary flex-1" type="submit">Filter</button>
                <a href="{{ route('reports.products') }}" class="btn-secondary">Reset</a>
            </div>
        </form>

        @if (auth()->user()?->isAdmin())
            <div class="mt-4 pt-4 border-t flex items-center justify-between flex-wrap gap-2">
                <p class="text-xs text-gray-500">
                    Hapus riwayat pergerakan stok sesuai <strong>rentang tanggal &amp; filter</strong> yang sedang aktif
                    (<span class="font-mono">{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}</span>
                    – <span class="font-mono">{{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</span>).
                    Hanya membersihkan catatan riwayat; stok produk saat ini tidak dihitung ulang.
                </p>
                <form method="POST" action="{{ route('reports.products.clear') }}"
                      onsubmit="return confirm('Hapus riwayat pergerakan stok pada rentang {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}{{ $productId ? ' (produk terpilih)' : '' }}{{ $type ? ' (tipe: '.$type.')' : '' }}?\n\nTindakan ini permanen.');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="to" value="{{ $to }}">
                    <input type="hidden" name="product_id" value="{{ $productId }}">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <button type="submit" class="btn-danger">🗑 Hapus Sesuai Filter</button>
                </form>
            </div>
        @endif
    </div>

    <div class="card mt-6">
        <h2 class="font-semibold mb-3">Ringkasan per Produk</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 border-b">
                    <tr>
                        <th class="py-2">Produk</th>
                        <th class="py-2 text-right">Masuk (pcs)</th>
                        <th class="py-2 text-right">Keluar (pcs)</th>
                        <th class="py-2 text-right">Penyesuaian (pcs)</th>
                        <th class="py-2 text-right">Selisih Bersih</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($summaryTable)): ?>
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-500">
                                Tidak ada pergerakan stok pada rentang ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($summaryTable as $row): ?>
                            <?php
                                $netClass = $row['net'] >= 0 ? 'text-green-600' : 'text-red-600';
                                $netPrefix = $row['net'] >= 0 ? '+' : '';
                                $adjPrefix = $row['adjustment'] >= 0 ? '+' : '';
                            ?>
                            <tr>
                                <td class="py-3 font-medium">{{ $row['product_name'] }}</td>
                                <td class="py-3 text-right font-semibold text-green-600">+{{ number_format($row['in']) }}</td>
                                <td class="py-3 text-right font-semibold text-red-600">-{{ number_format($row['out_abs']) }}</td>
                                <td class="py-3 text-right text-gray-700">{{ $adjPrefix }}{{ number_format($row['adjustment']) }}</td>
                                <td class="py-3 text-right font-semibold {{ $netClass }}">
                                    {{ $netPrefix }}{{ number_format($row['net']) }}
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-6">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <h2 class="font-semibold">Riwayat Pergerakan</h2>
            @if (auth()->user()?->isAdmin())
                <button type="submit" form="product-delete-selected"
                        class="btn-danger text-xs"
                        onclick="return confirm('Hapus riwayat pergerakan stok yang dipilih?\n\nTindakan ini permanen.');">
                    🗑 Hapus Terpilih
                </button>
            @endif
        </div>

        @if (auth()->user()?->isAdmin())
            <form id="product-delete-selected" method="POST" action="{{ route('reports.products.destroy_selected') }}">
                @csrf
                @method('DELETE')
                {{-- Bawa filter aktif supaya setelah hapus tetap di tampilan yang sama. --}}
                <input type="hidden" name="from" value="{{ $from }}">
                <input type="hidden" name="to" value="{{ $to }}">
                <input type="hidden" name="product_id" value="{{ $productId }}">
                <input type="hidden" name="type" value="{{ $type }}">
            </form>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 border-b">
                    <tr>
                        @if (auth()->user()?->isAdmin())
                            <th class="py-2 w-8">
                                <input type="checkbox" id="product-check-all" class="rounded border-gray-300" title="Pilih semua di halaman ini">
                            </th>
                        @endif
                        <th class="py-2">Waktu</th>
                        <th class="py-2">Resi</th>
                        <th class="py-2">Produk &mdash; Varian</th>
                        <th class="py-2 text-right">Qty</th>
                        <th class="py-2">Tipe</th>
                        <th class="py-2 text-right">Stok Setelah</th>
                        <th class="py-2">User</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if ($movements->isEmpty()): ?>
                        <tr>
                            <td colspan="{{ auth()->user()?->isAdmin() ? 8 : 7 }}" class="py-6 text-center text-gray-500">Tidak ada data.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $m): ?>
                            <?php
                                // Tipe: rename ke "Stok Masuk" / "Stok Keluar" /
                                // "Penyesuaian" supaya konsisten dengan istilah
                                // user. Badge color dipertahankan.
                                $mType = $m->type;
                                if ($mType === 'in') {
                                    $typeBadge = ['Stok Masuk', 'bg-green-100 text-green-700'];
                                } elseif ($mType === 'out') {
                                    $typeBadge = ['Stok Keluar', 'bg-red-100 text-red-700'];
                                } else {
                                    $typeBadge = ['Penyesuaian', 'bg-gray-100 text-gray-600'];
                                }
                                $qty = (int) $m->qty;
                                $qtyClass = $qty >= 0 ? 'text-green-600' : 'text-red-600';
                                $qtyPrefix = $qty >= 0 ? '+' : '';
                                $productName = $m->variant?->product?->name ?? '—';
                                $variantName = $m->variant?->name ?? '';
                                $variantSku = $m->variant?->sku ?? '';
                                $userName = $m->user?->name ?? '—';
                                // Resi: prefer dari order yang ter-link
                                // (movement dari Scan/Return), fallback ke
                                // string `reference` (Stock-In / Adjustment
                                // tidak punya order tapi punya catatan /
                                // nomor referensi manual).
                                $resi = $m->order?->resi_number ?: $m->reference;
                            ?>
                            <tr>
                                @if (auth()->user()?->isAdmin())
                                    <td class="py-2">
                                        <input type="checkbox" name="ids[]" value="{{ $m->id }}"
                                               form="product-delete-selected"
                                               class="product-check rounded border-gray-300">
                                    </td>
                                @endif
                                <td class="py-2 text-xs whitespace-nowrap">{{ $m->created_at->format('d M Y H:i') }}</td>
                                <td class="py-2 text-xs font-mono">{{ $resi ?: '—' }}</td>
                                <td class="py-2">
                                    <div class="font-medium">{{ $productName }}</div>
                                    <div class="text-xs text-gray-500">{{ $variantName }} <span class="font-mono">{{ $variantSku }}</span></div>
                                </td>
                                <td class="py-2 text-right font-semibold {{ $qtyClass }}">
                                    {{ $qtyPrefix }}{{ number_format($qty) }}
                                </td>
                                <td class="py-2">
                                    <span class="badge {{ $typeBadge[1] }}">{{ $typeBadge[0] }}</span>
                                </td>
                                <td class="py-2 text-right">{{ number_format((int) $m->stock_after) }}</td>
                                <td class="py-2 text-xs">{{ $userName }}</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $movements->links() }}</div>
    </div>

    @if (auth()->user()?->isAdmin())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var all = document.getElementById('product-check-all');
                var boxes = document.querySelectorAll('.product-check');
                if (all) {
                    all.addEventListener('change', function () {
                        boxes.forEach(function (b) { b.checked = all.checked; });
                    });
                }
            });
        </script>
    @endif
@endsection
