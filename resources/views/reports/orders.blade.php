@extends('layouts.app')
@section('title', 'Laporan Pesanan')

@section('content')
    <?php $header = 'Laporan Pesanan'; ?>
    <?php $subheader = 'Ringkasan pesanan berdasarkan periode dan status.'; ?>

    <div class="card">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-2 mb-4">
            <input type="date" name="start_date" value="{{ $startDate }}" class="input" placeholder="Dari">
            <input type="date" name="end_date" value="{{ $endDate }}" class="input" placeholder="Sampai">
            <select name="status" class="input">
                <option value="">Semua status</option>
                <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="packed" {{ $status === 'packed' ? 'selected' : '' }}>Packed</option>
                <option value="return" {{ $status === 'return' ? 'selected' : '' }}>Return</option>
                <option value="selesai_return" {{ $status === 'selesai_return' ? 'selected' : '' }}>Selesai Return</option>
                <option value="selesai_bulan_kemarin" {{ $status === 'selesai_bulan_kemarin' ? 'selected' : '' }}>Selesai Bulan Kemarin</option>
            </select>
            <select name="platform" class="input">
                <option value="">Semua platform</option>
                @foreach ($platforms as $p)
                    <option value="{{ $p->id }}" {{ (string) $platform === (string) $p->id ? 'selected' : '' }}>{{ $p->platform_name }}</option>
                @endforeach
            </select>
            <button class="btn-primary" type="submit">Filter</button>
            <a href="{{ route('reports.orders') }}" class="btn-secondary text-center">Reset</a>
        </form>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div class="bg-blue-50 rounded-lg p-3 text-center">
                <div class="text-xs text-blue-600 font-medium">Total Pesanan</div>
                <div class="text-lg font-bold text-blue-800">{{ $orders->total() }}</div>
            </div>
            <div class="bg-green-50 rounded-lg p-3 text-center">
                <div class="text-xs text-green-600 font-medium">Total Jual</div>
                <div class="text-lg font-bold text-green-800">Rp {{ number_format($totalJual, 0, ',', '.') }}</div>
            </div>
            <div class="bg-amber-50 rounded-lg p-3 text-center">
                <div class="text-xs text-amber-600 font-medium">Total Modal</div>
                <div class="text-lg font-bold text-amber-800">Rp {{ number_format($totalModal, 0, ',', '.') }}</div>
            </div>
            <div class="bg-indigo-50 rounded-lg p-3 text-center">
                <div class="text-xs text-indigo-600 font-medium">Profit Kotor</div>
                <div class="text-lg font-bold {{ $totalProfit >= 0 ? 'text-indigo-800' : 'text-red-800' }}">Rp {{ number_format($totalProfit, 0, ',', '.') }}</div>
            </div>
            <div class="bg-red-50 rounded-lg p-3 text-center">
                <div class="text-xs text-red-600 font-medium">Return</div>
                <div class="text-lg font-bold text-red-800">{{ $statusCounts['return'] }}</div>
            </div>
        </div>

        {{-- Status breakdown --}}
        <div class="flex gap-3 text-xs mb-4 flex-wrap">
            <span class="badge bg-amber-100 text-amber-700">Pending: {{ $statusCounts['pending'] }}</span>
            <span class="badge bg-green-100 text-green-700">Packed: {{ $statusCounts['packed'] }}</span>
            <span class="badge bg-red-100 text-red-700">Return: {{ $statusCounts['return'] }}</span>
            <span class="badge bg-purple-100 text-purple-700">Selesai Return: {{ $statusCounts['selesai_return'] }}</span>
            <span class="badge bg-blue-100 text-blue-700">Selesai Bulan Kemarin: {{ $statusCounts['selesai_bulan_kemarin'] }}</span>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="text-xs whitespace-nowrap border-collapse w-full">
                <thead class="text-left uppercase text-gray-500 border-b bg-gray-50">
                    <tr>
                        <th class="px-2 py-2">No</th>
                        <th class="px-2 py-2">Resi</th>
                        <th class="px-2 py-2">Tanggal</th>
                        <th class="px-2 py-2">Pembeli</th>
                        <th class="px-2 py-2">Platform</th>
                        <th class="px-2 py-2 text-right">Total Jual</th>
                        <th class="px-2 py-2 text-right">Total Modal</th>
                        <th class="px-2 py-2 text-right">Profit</th>
                        <th class="px-2 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($orders as $order)
                        @php $m = $metrics[$order->id]; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-2">{{ ($orders->firstItem() ?? 1) + $loop->index }}</td>
                            <td class="px-2 py-2 font-mono">{{ $order->resi_number }}</td>
                            <td class="px-2 py-2">{{ $order->created_at?->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-2 py-2">{{ $order->buyer_name ?? '-' }}</td>
                            <td class="px-2 py-2">{{ $order->platformDeduction?->platform_name ?? '-' }}</td>
                            <td class="px-2 py-2 text-right font-mono">Rp {{ number_format($m['total_jual'], 0, ',', '.') }}</td>
                            <td class="px-2 py-2 text-right font-mono">Rp {{ number_format($m['total_modal'], 0, ',', '.') }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $m['profit_kotor'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                Rp {{ number_format($m['profit_kotor'], 0, ',', '.') }}
                            </td>
                            <td class="px-2 py-2">
                                <span class="badge
                                    {{ $order->status === 'pending' ? 'bg-amber-100 text-amber-700' : '' }}
                                    {{ $order->status === 'packed' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $order->status === 'return' ? 'bg-red-100 text-red-700' : '' }}
                                    {{ $order->status === 'selesai_return' ? 'bg-purple-100 text-purple-700' : '' }}
                                    {{ $order->status === 'selesai_bulan_kemarin' ? 'bg-blue-100 text-blue-700' : '' }}">
                                    {{ \App\Models\Order::STATUS_LABELS[$order->status] ?? ucfirst($order->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-6 text-center text-gray-500">Tidak ada data pesanan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    </div>
@endsection
