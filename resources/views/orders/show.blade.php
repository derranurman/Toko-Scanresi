@extends('layouts.app')
@section('title', 'Detail Pesanan')

@section('content')
    @php($header = 'Pesanan ')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card lg:col-span-2">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <div class="text-xs uppercase text-gray-500">Nomor Resi</div>
                    <div class="text-xl font-mono font-bold">{{ $order->resi_number }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $order->courier }} · Order ID: {{ $order->tiktok_order_id ?? '—' }}</div>
                </div>
                <div>
                    @if ($order->status === \App\Models\Order::STATUS_PENDING)
                        <span class="badge bg-amber-100 text-amber-700">Pending</span>
                    @elseif ($order->status === \App\Models\Order::STATUS_PACKED)
                        <span class="badge bg-green-100 text-green-700">Packed</span>
                    @elseif ($order->status === \App\Models\Order::STATUS_RETURN)
                        <span class="badge bg-red-100 text-red-700">Return</span>
                    @elseif ($order->status === \App\Models\Order::STATUS_SELESAI_RETURN)
                        <span class="badge bg-purple-100 text-purple-700">Selesai Return</span>
                    @elseif ($order->status === \App\Models\Order::STATUS_SELESAI_BULAN_KEMARIN)
                        <span class="badge bg-blue-100 text-blue-700">Selesai Bulan Kemarin</span>
                    @else
                        <span class="badge bg-gray-100 text-gray-600">{{ ucfirst($order->status) }}</span>
                    @endif
                </div>
            </div>

            <h3 class="font-semibold mb-2">Item Pesanan</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 border-b">
                        <tr>
                            <th class="py-2">Produk</th>
                            <th class="py-2">Varian</th>
                            <th class="py-2">SKU</th>
                            <th class="py-2 text-right">Qty</th>
                            <th class="py-2 text-right">Stok Kini</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($order->items as $item)
                            <tr>
                                <td class="py-2">{{ $item->product_name }}</td>
                                <td class="py-2">{{ $item->variant_name ?? '—' }}</td>
                                <td class="py-2 font-mono text-xs">
                                    {{ $item->sku ?? '—' }}
                                    @unless ($item->variant)
                                        <span class="badge bg-red-100 text-red-700 ml-1">Tidak terdaftar</span>
                                    @endunless
                                </td>
                                <td class="py-2 text-right font-semibold">{{ $item->quantity }}</td>
                                <td class="py-2 text-right">{{ $item->variant?->stock ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h3 class="font-semibold mb-2">Pembeli</h3>
            <div class="text-sm space-y-1">
                <div><span class="text-gray-500">Nama:</span> {{ $order->buyer_name ?? '—' }}</div>
                <div><span class="text-gray-500">HP:</span> {{ $order->buyer_phone ?? '—' }}</div>
                <div><span class="text-gray-500">Alamat:</span><br> {{ $order->shipping_address ?? '—' }}</div>
                <div><span class="text-gray-500">Tgl Order:</span> {{ $order->order_date?->format('d M Y H:i') ?? '—' }}</div>
            </div>

            @if ($order->packed_at)
                <div class="mt-4 pt-3 border-t text-sm">
                    <div class="text-gray-500 text-xs uppercase mb-1">Packing</div>
                    <div><b>{{ $order->packedBy?->name }}</b></div>
                    <div class="text-gray-500">{{ $order->packed_at->format('d M Y H:i') }}</div>
                </div>
            @endif

            <div class="mt-4 pt-3 border-t flex gap-2">
                <a href="{{ route('orders.index') }}" class="btn-secondary flex-1 text-center">Kembali</a>
                @if ($order->status === 'pending')
                    <form method="POST" action="{{ route('orders.destroy', $order) }}"
                          onsubmit="return confirm('Hapus pesanan ini?');" class="flex-1">
                        @csrf @method('DELETE')
                        <button class="btn-danger w-full" type="submit">Hapus</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
