{{--
    Print-friendly view untuk Daftar Pesanan. Tidak meng-extend layout
    aplikasi (tanpa Tailwind / nav / sidebar) supaya output cetak bersih.
    Auto memanggil `window.print()` saat dimuat — user bisa pilih
    "Save as PDF" sebagai destination di dialog cetak browser untuk
    menghasilkan file PDF.

    Mengikuti pola yang sama dengan reports/packing_pdf.blade.php.
--}}
@php
    $fmt = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $statusLabel = [
        'pending'               => 'Pending',
        'packed'                => 'Packed',
        'return'                => 'Return',
        'selesai_return'        => 'Selesai Return',
        'selesai_bulan_kemarin' => 'Selesai Bln Kemarin',
    ];

    // Total agregat (footer ringkasan).
    $sumTotalJual = 0;
    $sumTotalModal = 0;
    $sumTotalPotongan = 0;
    $sumProfit = 0;
    $sumMarginLive = 0;
    $sumLabel = 0;
    foreach ($orders as $o) {
        $m = $metrics[$o->id] ?? [];
        $sumTotalJual     += (float) ($m['total_jual'] ?? 0);
        $sumTotalModal    += (float) ($m['total_modal'] ?? 0);
        $sumTotalPotongan += (float) ($m['total_potongan_aplikasi'] ?? 0);
        $sumProfit        += (float) ($m['profit_kotor'] ?? 0);
        $sumMarginLive    += (float) ($m['margin_live'] ?? 0);
        $sumLabel         += (float) ($m['label'] ?? 0);
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Pesanan — {{ now()->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #111;
            font-size: 9.5px;
        }
        body { padding: 18px; }
        h1 {
            margin: 0 0 4px;
            font-size: 16px;
            letter-spacing: -0.01em;
        }
        .meta {
            color: #555;
            font-size: 10px;
            margin-bottom: 4px;
        }
        .meta strong { color: #111; }
        .filters {
            margin-top: 6px;
            font-size: 10px;
            color: #444;
        }
        .filters span {
            display: inline-block;
            border: 1px solid #ccc;
            padding: 2px 6px;
            border-radius: 3px;
            margin-right: 4px;
            background: #f5f5f5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 8px;
            table-layout: fixed;
        }
        thead th {
            background: #f0f0f0;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 5px 6px;
            border-bottom: 1.5px solid #333;
            color: #222;
        }
        tbody td {
            padding: 5px 6px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: anywhere;
        }
        tfoot td {
            padding: 6px;
            border-top: 1.5px solid #333;
            background: #f0f0f0;
            font-weight: 600;
            font-size: 9.5px;
        }
        tbody tr:nth-child(even) td { background: #fafafa; }
        .right { text-align: right; }
        .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .mono { font-family: 'Consolas', 'Menlo', monospace; font-size: 9px; }
        .muted { color: #777; }
        .neg { color: #b91c1c; }
        .empty {
            text-align: center;
            padding: 16px;
            color: #888;
            font-style: italic;
        }
        .footer {
            margin-top: 12px;
            font-size: 9px;
            color: #888;
            text-align: right;
        }
        .toolbar {
            position: fixed;
            top: 12px;
            right: 12px;
            background: #111;
            color: #fff;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 11px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            z-index: 9999;
        }
        .toolbar button {
            background: #fff;
            color: #111;
            border: 0;
            border-radius: 4px;
            padding: 4px 10px;
            font-size: 11px;
            cursor: pointer;
            margin-left: 6px;
        }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
            tbody tr:nth-child(even) td { background: transparent; }
        }
        /* Landscape A4 supaya kolom lebih lega. */
        @page { size: A4 landscape; margin: 10mm 8mm; }
    </style>
</head>
<body>
    <div class="toolbar">
        Tip: pilih <strong>"Save as PDF"</strong> sebagai destination di dialog cetak.
        <button type="button" onclick="window.print()">Cetak ulang</button>
        <button type="button" onclick="window.close()">Tutup</button>
    </div>

    <h1>Daftar Pesanan</h1>
    <div class="meta">
        <strong>{{ $brand->app_name ?? config('app.name') }}</strong>
        &nbsp;&middot;&nbsp;
        Total: <strong>{{ number_format($orders->count()) }}</strong> pesanan
        &nbsp;&middot;&nbsp;
        Dicetak: {{ now()->format('d M Y H:i') }}
    </div>
    @if ($q || $status || $date)
        <div class="filters">
            @if ($q)<span>Cari: "{{ $q }}"</span>@endif
            @if ($status)<span>Status: {{ $statusLabel[$status] ?? $status }}</span>@endif
            @if ($date)<span>Tanggal: {{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</span>@endif
        </div>
    @endif

    <table>
        <colgroup>
            <col style="width: 22px;">   {{-- No --}}
            <col style="width: 80px;">   {{-- Resi --}}
            <col style="width: 60px;">   {{-- Tanggal --}}
            <col style="width: 70px;">   {{-- Host Live --}}
            <col style="width: 70px;">   {{-- Platform --}}
            <col style="width: 80px;">   {{-- Pengirim --}}
            <col style="width: 80px;">   {{-- Pembeli --}}
            <col>                        {{-- Item / SKU --}}
            <col style="width: 55px;">   {{-- Label --}}
            <col style="width: 60px;">   {{-- Total Jual --}}
            <col style="width: 60px;">   {{-- Total Modal --}}
            <col style="width: 60px;">   {{-- Potongan Aplikasi --}}
            <col style="width: 60px;">   {{-- Profit Kotor --}}
            <col style="width: 60px;">   {{-- Margin Live --}}
            <col style="width: 60px;">   {{-- Status --}}
        </colgroup>
        <thead>
            <tr>
                <th class="right">No</th>
                <th>Resi / Order ID</th>
                <th>Tanggal</th>
                <th>Host Live</th>
                <th>Platform</th>
                <th>Pengirim</th>
                <th>Pembeli</th>
                <th>Nama Barang / SKU</th>
                <th class="right">Label</th>
                <th class="right">Total Jual</th>
                <th class="right">Total Modal</th>
                <th class="right">Potongan Apl.</th>
                <th class="right">Profit Kotor</th>
                <th class="right">Margin Live</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @forelse ($orders as $order)
                @php
                    $m = $metrics[$order->id] ?? [];
                    $names = $order->items
                        ->map(function ($it) {
                            $name = $it->variant?->product?->name ?? $it->product_name;
                            if ($it->variant?->name) {
                                $name = trim(($name ?? '—') . ' — ' . $it->variant->name);
                            }
                            return $name;
                        })
                        ->filter()->unique()->implode(', ');
                    $skus = $order->items->pluck('sku')->filter()->unique()->implode(', ');
                @endphp
                <tr>
                    <td class="right num">{{ $no++ }}</td>
                    <td>
                        <div class="mono">{{ $order->resi_number }}</div>
                        @if ($order->tiktok_order_id)
                            <div class="mono muted">{{ $order->tiktok_order_id }}</div>
                        @endif
                    </td>
                    <td class="num">
                        {{ $order->created_at ? $order->created_at->format('d M Y') : '—' }}
                    </td>
                    <td>{{ $order->host_live ?? '—' }}</td>
                    <td>{{ $order->platformDeduction?->platform_name ?? '—' }}</td>
                    <td>{{ $order->sender_name ?? '—' }}</td>
                    <td>
                        {{ $order->buyer_name ?? '—' }}
                        @if ($order->buyer_phone)
                            <div class="muted mono">{{ $order->buyer_phone }}</div>
                        @endif
                    </td>
                    <td>
                        {{ $names ?: '—' }}
                        @if ($skus)
                            <div class="muted mono">{{ $skus }}</div>
                        @endif
                    </td>
                    <td class="right num">{{ $fmt($m['label'] ?? 0) }}</td>
                    <td class="right num">{{ $fmt($m['total_jual'] ?? 0) }}</td>
                    <td class="right num">{{ $fmt($m['total_modal'] ?? 0) }}</td>
                    <td class="right num">{{ $fmt($m['total_potongan_aplikasi'] ?? 0) }}</td>
                    <td class="right num {{ ($m['profit_kotor'] ?? 0) < 0 ? 'neg' : '' }}">
                        {{ $fmt($m['profit_kotor'] ?? 0) }}
                    </td>
                    <td class="right num {{ ($m['margin_live'] ?? 0) < 0 ? 'neg' : '' }}">
                        {{ $fmt($m['margin_live'] ?? 0) }}
                    </td>
                    <td>{{ $statusLabel[$order->status] ?? ucfirst($order->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="15" class="empty">Tidak ada pesanan pada filter ini.</td></tr>
            @endforelse
        </tbody>
        @if ($orders->count() > 0)
            <tfoot>
                <tr>
                    <td colspan="8" class="right">TOTAL ({{ number_format($orders->count()) }} pesanan)</td>
                    <td class="right num">{{ $fmt($sumLabel) }}</td>
                    <td class="right num">{{ $fmt($sumTotalJual) }}</td>
                    <td class="right num">{{ $fmt($sumTotalModal) }}</td>
                    <td class="right num">{{ $fmt($sumTotalPotongan) }}</td>
                    <td class="right num {{ $sumProfit < 0 ? 'neg' : '' }}">{{ $fmt($sumProfit) }}</td>
                    <td class="right num {{ $sumMarginLive < 0 ? 'neg' : '' }}">{{ $fmt($sumMarginLive) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        Dihasilkan otomatis oleh {{ $brand->app_name ?? config('app.name') }} —
        {{ now()->format('Y-m-d H:i:s') }}
    </div>

    <script>
        // Auto-trigger print dialog setelah halaman di-render. User tinggal
        // pilih "Save as PDF" sebagai destination untuk mendapat file PDF.
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 250);
        });
    </script>
</body>
</html>
