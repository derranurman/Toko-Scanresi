@extends('layouts.app')
@section('title', 'Laporan Live')

@section('content')
    <?php $header = 'Laporan Live'; ?>
    <?php $subheader = 'Rekap penjualan per Host Live berdasarkan periode.'; ?>

    @php
        $fmt = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $cls = fn ($v) => $v >= 0 ? 'text-green-600' : 'text-red-600 font-semibold';
    @endphp

    <div class="card">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <form method="GET" class="flex gap-2 flex-wrap">
                <select name="month" class="input w-36">
                    @for ($mo = 1; $mo <= 12; $mo++)
                        <option value="{{ $mo }}" {{ $month == $mo ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create()->month($mo)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
                <select name="year" class="input w-24">
                    @for ($y = now()->year; $y >= now()->year - 2; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
                <select name="host" class="input w-48">
                    <option value="">— Semua Host —</option>
                    @foreach ($hosts as $h)
                        <option value="{{ $h }}" {{ $host === $h ? 'selected' : '' }}>{{ $h }}</option>
                    @endforeach
                </select>
                <input type="date" name="start_date" value="{{ $startDate }}" class="input w-40" title="Dari Tanggal">
                <input type="date" name="end_date" value="{{ $endDate }}" class="input w-40" title="Sampai Tanggal">
                <button class="btn-primary" type="submit">Filter</button>
                <a href="{{ route('reports.live') }}" class="btn-secondary">Reset</a>
            </form>
            <a href="{{ route('reports.live.export', ['month' => $month, 'year' => $year, 'host' => $host, 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn-primary">
                ⬇ Export Excel (CSV)
            </a>
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div class="bg-indigo-50 rounded-lg p-3 text-center">
                <div class="text-xs text-indigo-600 font-medium">Jumlah Host</div>
                <div class="text-lg font-bold text-indigo-800">{{ $totals['hosts'] }}</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-3 text-center">
                <div class="text-xs text-blue-600 font-medium">Total Pesanan</div>
                <div class="text-lg font-bold text-blue-800">{{ $totals['orders'] }}</div>
            </div>
            <div class="bg-green-50 rounded-lg p-3 text-center">
                <div class="text-xs text-green-600 font-medium">Total Jual</div>
                <div class="text-lg font-bold text-green-800">{{ $fmt($totals['total_jual']) }}</div>
            </div>
            <div class="bg-sky-50 rounded-lg p-3 text-center">
                <div class="text-xs text-sky-600 font-medium">Total Bersih Margin Live</div>
                <div class="text-lg font-bold {{ $cls($totals['bersih_margin_live']) }}">{{ $fmt($totals['bersih_margin_live']) }}</div>
            </div>
            <div class="bg-amber-50 rounded-lg p-3 text-center">
                <div class="text-xs text-amber-600 font-medium">Total Margin Bisnis</div>
                <div class="text-lg font-bold {{ $cls($totals['margin_bisnis']) }}">{{ $fmt($totals['margin_bisnis']) }}</div>
            </div>
        </div>

        {{-- Peringkat Host Live --}}
        @if ($ranking->count() > 0)
            <div class="mb-6" x-data="hostRanking({{ $ranking->toJson() }})">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                    <div class="text-sm font-semibold text-gray-700">🏆 Peringkat Host Live</div>
                    <div class="text-xs text-gray-500">
                        Urut berdasarkan <span class="font-medium" x-text="labels[sortKey]"></span> terbanyak. Klik judul kolom untuk mengubah.
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="text-xs whitespace-nowrap border-collapse w-full">
                        <thead class="text-left uppercase text-gray-500 border-b bg-gray-50">
                            <tr>
                                <th class="px-2 py-2">Peringkat</th>
                                <th class="px-2 py-2">Host Live</th>
                                <th class="px-2 py-2 text-right cursor-pointer select-none hover:text-indigo-600"
                                    :class="{ 'text-indigo-600': sortKey === 'orders' }" @click="sortBy('orders')">
                                    Jumlah Pesanan <span x-show="sortKey === 'orders'">▼</span>
                                </th>
                                <th class="px-2 py-2 text-right cursor-pointer select-none hover:text-indigo-600"
                                    :class="{ 'text-indigo-600': sortKey === 'total_jual' }" @click="sortBy('total_jual')">
                                    Total Jual <span x-show="sortKey === 'total_jual'">▼</span>
                                </th>
                                <th class="px-2 py-2 text-right cursor-pointer select-none hover:text-indigo-600"
                                    :class="{ 'text-indigo-600': sortKey === 'bersih_margin_live' }" @click="sortBy('bersih_margin_live')">
                                    Bersih Margin Live <span x-show="sortKey === 'bersih_margin_live'">▼</span>
                                </th>
                                <th class="px-2 py-2 text-right cursor-pointer select-none hover:text-indigo-600"
                                    :class="{ 'text-indigo-600': sortKey === 'margin_bisnis' }" @click="sortBy('margin_bisnis')">
                                    Margin Bisnis <span x-show="sortKey === 'margin_bisnis'">▼</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <template x-for="(r, i) in sorted" :key="r.host">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-2 py-2 font-semibold">
                                        <span x-text="i === 0 ? '🥇' : (i === 1 ? '🥈' : (i === 2 ? '🥉' : ''))"></span>
                                        <span x-text="'#' + (i + 1)"></span>
                                    </td>
                                    <td class="px-2 py-2 font-medium" x-text="r.host"></td>
                                    <td class="px-2 py-2 text-right font-mono" x-text="r.orders"></td>
                                    <td class="px-2 py-2 text-right font-mono" x-text="fmt(r.total_jual)"></td>
                                    <td class="px-2 py-2 text-right font-mono" :class="cls(r.bersih_margin_live)" x-text="fmt(r.bersih_margin_live)"></td>
                                    <td class="px-2 py-2 text-right font-mono" :class="cls(r.margin_bisnis)" x-text="fmt(r.margin_bisnis)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                function hostRanking(rows) {
                    return {
                        rows: (rows || []).map(r => ({
                            host: r.host,
                            orders: Number(r.orders) || 0,
                            total_jual: Number(r.total_jual) || 0,
                            bersih_margin_live: Number(r.bersih_margin_live) || 0,
                            margin_bisnis: Number(r.margin_bisnis) || 0,
                        })),
                        sortKey: 'bersih_margin_live',
                        labels: {
                            orders: 'Jumlah Pesanan',
                            total_jual: 'Total Jual',
                            bersih_margin_live: 'Bersih Margin Live',
                            margin_bisnis: 'Margin Bisnis',
                        },
                        sortBy(key) { this.sortKey = key; },
                        get sorted() {
                            const k = this.sortKey;
                            return [...this.rows].sort((a, b) => b[k] - a[k]);
                        },
                        fmt(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 }); },
                        cls(v) { return v >= 0 ? 'text-green-600' : 'text-red-600 font-semibold'; },
                    };
                }
            </script>
        @endif

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="text-xs whitespace-nowrap border-collapse w-full">
                <thead class="text-left uppercase text-gray-500 border-b bg-gray-50">
                    <tr>
                        <th class="px-2 py-2">No</th>
                        <th class="px-2 py-2">Tanggal</th>
                        <th class="px-2 py-2">Host Live</th>
                        <th class="px-2 py-2">Platform</th>
                        <th class="px-2 py-2">Resi</th>
                        <th class="px-2 py-2">Nama Barang</th>
                        <th class="px-2 py-2 text-right">Total Jual</th>
                        <th class="px-2 py-2 text-right">Total Modal</th>
                        <th class="px-2 py-2 text-right">Profit Kotor</th>
                        <th class="px-2 py-2 text-right">Margin Live</th>
                        <th class="px-2 py-2 text-right">Bersih Margin Live</th>
                        <th class="px-2 py-2 text-right">Margin Bisnis</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($orders as $order)
                        @php
                            $m = $metrics[$order->id];
                            $names = $order->items
                                ->map(function ($it) {
                                    $name = $it->variant?->product?->name ?? $it->product_name;
                                    if ($it->variant?->name) {
                                        $name = trim(($name ?? '—') . ' — ' . $it->variant->name);
                                    }
                                    return $name;
                                })
                                ->filter()->unique()->implode(', ');
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-2 py-2">{{ ($orders->firstItem() ?? 1) + $loop->index }}</td>
                            <td class="px-2 py-2">{{ optional($order->order_date)->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-2 py-2 font-medium">{{ $order->host_live }}</td>
                            <td class="px-2 py-2">{{ $order->platformDeduction?->platform_name ?? '—' }}</td>
                            <td class="px-2 py-2 font-mono">{{ $order->resi_number }}</td>
                            <td class="px-2 py-2 max-w-xs truncate" title="{{ $names }}">{{ $names ?: '—' }}</td>
                            <td class="px-2 py-2 text-right font-mono">{{ $fmt($m['total_jual']) }}</td>
                            <td class="px-2 py-2 text-right font-mono">{{ $fmt($m['total_modal']) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $cls($m['profit_kotor']) }}">{{ $fmt($m['profit_kotor']) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $cls($m['margin_live']) }}">{{ $fmt($m['margin_live']) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $cls($m['bersih_margin_live']) }}">{{ $fmt($m['bersih_margin_live']) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $cls($m['margin_bisnis']) }}">{{ $fmt($m['margin_bisnis']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="py-6 text-center text-gray-500">Tidak ada pesanan dengan Host Live di periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($orders->count() > 0)
                    <tfoot class="border-t-2 bg-gray-50 font-semibold">
                        <tr>
                            <td class="px-2 py-2" colspan="6">TOTAL</td>
                            <td class="px-2 py-2 text-right font-mono">{{ $fmt($totals['total_jual']) }}</td>
                            <td class="px-2 py-2 text-right font-mono">{{ $fmt($totals['total_modal']) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $cls($totals['profit_kotor']) }}">{{ $fmt($totals['profit_kotor']) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $cls($totals['margin_live']) }}">{{ $fmt($totals['margin_live']) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $cls($totals['bersih_margin_live']) }}">{{ $fmt($totals['bersih_margin_live']) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $cls($totals['margin_bisnis']) }}">{{ $fmt($totals['margin_bisnis']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    </div>
@endsection
