@extends('layouts.app')
@section('title', 'Laporan Stok')

@section('content')
    <?php $header = 'Laporan Stok'; ?>
    <?php $subheader = 'Rekap stok semua produk & variant. Bisa di-export ke Excel (CSV).'; ?>

    <div class="card">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <form method="GET" class="flex gap-2 flex-wrap">
                <input name="q" value="{{ $q }}" placeholder="Cari produk / SKU…" class="input w-56">
                <select name="type" class="input w-48">
                    <option value="">— Semua Tipe —</option>
                    @foreach ($types as $t)
                        <option value="{{ $t }}" {{ $type === $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
                <select name="status" class="input w-40">
                    <option value="">— Semua Status —</option>
                    <option value="low" {{ $status === 'low' ? 'selected' : '' }}>LOW</option>
                    <option value="ok" {{ $status === 'ok' ? 'selected' : '' }}>OK</option>
                    <option value="empty" {{ $status === 'empty' ? 'selected' : '' }}>STOK 0 / HABIS</option>
                </select>
                <button class="btn-primary" type="submit">Filter</button>
                @if ($q || $type || $status)
                    <a href="{{ route('reports.stock') }}" class="btn-secondary">Reset</a>
                @endif
            </form>
            <a href="{{ route('reports.stock.export', ['type' => $type, 'status' => $status]) }}" class="btn-primary">
                ⬇ Export Excel (CSV)
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="text-xs whitespace-nowrap border-collapse w-full">
                <thead class="text-left uppercase text-gray-500 border-b bg-gray-50">
                    <tr>
                        <th class="px-2 py-2">No</th>
                        <th class="px-2 py-2">Gambar</th>
                        <th class="px-2 py-2">Produk</th>
                        <th class="px-2 py-2">SKU</th>
                        <th class="px-2 py-2">Tipe</th>
                        <th class="px-2 py-2">Variant</th>
                        <th class="px-2 py-2">SKU Variant</th>
                        <th class="px-2 py-2 text-right">Stok</th>
                        <th class="px-2 py-2 text-right">Min</th>
                        <th class="px-2 py-2">Status</th>
                        <th class="px-2 py-2 text-right">Harga Beli</th>
                        <th class="px-2 py-2 text-right">Harga Reseller</th>
                        <th class="px-2 py-2 text-right">Harga Jual</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @php $no = 1; @endphp
                    @forelse ($products as $product)
                        @foreach ($product->variants as $variant)
                            @php
                                $lowStock = $variant->stock <= $variant->min_stock;
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $lowStock ? 'bg-red-50' : '' }}">
                                <td class="px-2 py-2">{{ $no++ }}</td>
                                <td class="px-2 py-2">
                                    @if ($product->imageUrl())
                                        <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}"
                                             data-lightbox-src="{{ $product->imageUrl() }}" data-lightbox-alt="{{ $product->name }}"
                                             class="h-10 w-10 rounded object-cover border border-gray-200 cursor-zoom-in hover:opacity-80 transition">
                                    @else
                                        <div class="h-10 w-10 rounded bg-gray-100 border border-gray-200 grid place-items-center text-gray-300">&mdash;</div>
                                    @endif
                                </td>
                                <td class="px-2 py-2 font-medium">{{ $product->name }}</td>
                                <td class="px-2 py-2 font-mono">{{ $product->sku }}</td>
                                <td class="px-2 py-2">{{ $product->type ?? '-' }}</td>
                                <td class="px-2 py-2">{{ $variant->name }}</td>
                                <td class="px-2 py-2 font-mono">{{ $variant->sku }}</td>
                                <td class="px-2 py-2 text-right font-mono font-semibold {{ $lowStock ? 'text-red-600' : 'text-green-600' }}">
                                    {{ $variant->stock }}
                                </td>
                                <td class="px-2 py-2 text-right font-mono">{{ $variant->min_stock }}</td>
                                <td class="px-2 py-2">
                                    @if ($lowStock)
                                        <span class="badge bg-red-100 text-red-700">LOW</span>
                                    @else
                                        <span class="badge bg-green-100 text-green-700">OK</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right font-mono">Rp {{ number_format($product->purchase_price, 0, ',', '.') }}</td>
                                <td class="px-2 py-2 text-right font-mono">Rp {{ number_format($product->reseller_price, 0, ',', '.') }}</td>
                                <td class="px-2 py-2 text-right font-mono">Rp {{ number_format($product->selling_price, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="13" class="py-6 text-center text-gray-500">Tidak ada data produk.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-xs text-gray-500">
            Total: {{ $products->sum(fn($p) => $p->variants->count()) }} variant dari {{ $products->count() }} produk.
            Total stok: {{ $products->sum(fn($p) => $p->variants->sum('stock')) }} unit.
        </div>
    </div>
    @include('partials.lightbox')
@endsection
