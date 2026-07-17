@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    @php($header = 'Dashboard')

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        @foreach ([
            ['label' => 'Produk', 'value' => $stats['total_products'], 'color' => 'indigo', 'href' => route('products.index')],
            ['label' => 'Varian', 'value' => $stats['total_variants'], 'color' => 'sky', 'href' => route('products.index')],
            ['label' => 'Total Stok', 'value' => $stats['total_stock'], 'color' => 'emerald', 'href' => route('reports.stock')],
            ['label' => 'Pesanan Pending', 'value' => $stats['pending_orders'], 'color' => 'amber', 'href' => route('orders.index', ['status' => 'pending'])],
            ['label' => 'Packed Hari Ini', 'value' => $stats['packed_today'], 'color' => 'teal', 'href' => route('reports.packing', ['from' => today()->toDateString(), 'to' => today()->toDateString()])],
            ['label' => 'Stok Menipis', 'value' => $stats['low_stock'], 'color' => 'red', 'href' => route('reports.stock', ['status' => 'low'])],
        ] as $stat)
            @php($cardHref = $stat['href'] ?? null)
            @if ($cardHref)
                <a href="{{ $cardHref }}"
                   class="card block transition hover:shadow-md hover:ring-1 hover:ring-gray-200 focus:outline-none focus:ring-2 focus:ring-{{ $stat['color'] }}-400"
                   title="Lihat detail {{ $stat['label'] }}">
                    <div class="text-xs font-medium text-gray-500 uppercase">{{ $stat['label'] }}</div>
                    <div class="mt-2 text-2xl font-bold text-{{ $stat['color'] }}-600">{{ number_format($stat['value']) }}</div>
                </a>
            @else
                <div class="card">
                    <div class="text-xs font-medium text-gray-500 uppercase">{{ $stat['label'] }}</div>
                    <div class="mt-2 text-2xl font-bold text-{{ $stat['color'] }}-600">{{ number_format($stat['value']) }}</div>
                </div>
            @endif
        @endforeach
    </div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card">
            <h2 class="font-semibold text-gray-900 mb-3">Scan Terbaru</h2>
            @if ($recent->isEmpty())
                <p class="text-sm text-gray-500">Belum ada aktivitas scan.</p>
            @else
                <div class="divide-y">
                    @foreach ($recent as $log)
                        <div class="py-2 flex items-center justify-between text-sm">
                            <div>
                                <div class="font-medium">{{ $log->resi_number }}</div>
                                <div class="text-xs text-gray-500">{{ $log->user?->name }} · {{ $log->scanned_at->format('d M Y H:i') }}</div>
                            </div>
                            <span class="badge bg-green-100 text-green-700">{{ $log->total_items }} pcs</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card">
            <h2 class="font-semibold text-gray-900 mb-3">Stok Menipis</h2>
            @if ($lowStock->isEmpty())
                <p class="text-sm text-gray-500">Semua stok aman.</p>
            @else
                <div class="divide-y">
                    @foreach ($lowStock as $v)
                        @if ($v->product)
                            <a href="{{ route('products.edit', $v->product) }}"
                               class="py-2 -mx-2 px-2 flex items-center justify-between text-sm rounded-md hover:bg-gray-50 transition-colors"
                               title="Klik untuk edit produk & sesuaikan stok">
                                <div class="min-w-0 pr-3">
                                    <div class="font-medium text-gray-900 truncate">{{ $v->product->name }} &mdash; {{ $v->name }}</div>
                                    <div class="text-xs text-gray-500 font-mono truncate">{{ $v->sku }}</div>
                                </div>
                                <span class="badge shrink-0 {{ $v->stock <= 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $v->stock }} / min {{ $v->min_stock }}
                                </span>
                            </a>
                        @else
                            <div class="py-2 flex items-center justify-between text-sm">
                                <div class="min-w-0 pr-3">
                                    <div class="font-medium text-gray-900 truncate">{{ $v->name }}</div>
                                    <div class="text-xs text-gray-500 font-mono truncate">{{ $v->sku }}</div>
                                </div>
                                <span class="badge shrink-0 {{ $v->stock <= 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $v->stock }} / min {{ $v->min_stock }}
                                </span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
