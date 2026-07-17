{{--
    Print-friendly view untuk Laporan Packing. Tidak meng-extend layout
    aplikasi (tanpa Tailwind / nav / sidebar) supaya output cetak bersih.
    Auto memanggil `window.print()` saat dimuat — user bisa pilih
    "Save as PDF" sebagai destination di dialog cetak browser untuk
    menghasilkan file PDF.
--}}
@php
    $appName = $brand->app_name ?? config('app.name');
    $logoUrl = isset($brand) && method_exists($brand, 'logoUrl') ? $brand->logoUrl() : null;

    // Total agregat untuk kartu statistik.
    $totalOrders   = $summary->sum('total_orders');
    $totalItems    = $summary->sum('total_items');
    $totalDistinct = $summary->sum('total_distinct');
    $totalUsers    = $summary->count();
    $totalScans    = $logs->count();
    $days          = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Packing — {{ $from->format('d M Y') }} s/d {{ $to->format('d M Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            color: #1e293b;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body { padding: 22px 26px 60px; }

        /* ===== Header ===== */
        .header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 18px;
            border-radius: 12px;
            background: linear-gradient(120deg, #4f46e5 0%, #6366f1 55%, #7c3aed 100%);
            color: #fff;
        }
        .brand-logo {
            width: 46px; height: 46px;
            border-radius: 10px;
            background: rgba(255,255,255,0.18);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; font-weight: 700;
            overflow: hidden; flex-shrink: 0;
        }
        .brand-logo img { width: 100%; height: 100%; object-fit: cover; }
        .header .title { flex: 1; }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }
        .header .sub {
            margin-top: 2px;
            font-size: 11px;
            color: rgba(255,255,255,0.85);
        }
        .header .printed {
            text-align: right;
            font-size: 10px;
            color: rgba(255,255,255,0.85);
            line-height: 1.5;
        }
        .header .printed b { color: #fff; }

        /* ===== Filter chips ===== */
        .filters {
            margin: 12px 0 4px;
            font-size: 10px;
        }
        .filters .lbl { color: #64748b; margin-right: 4px; }
        .filters span.tag {
            display: inline-block;
            border: 1px solid #c7d2fe;
            padding: 2px 8px;
            border-radius: 999px;
            margin-right: 5px;
            background: #eef2ff;
            color: #4338ca;
            font-weight: 600;
        }

        /* ===== Stat cards ===== */
        .stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin: 10px -8px 4px;
        }
        .stats td {
            width: 20%;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            background: #f8fafc;
            vertical-align: top;
        }
        .stats .k {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 600;
        }
        .stats .v {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
            line-height: 1.1;
        }
        .stats .v.accent { color: #4f46e5; }
        .stats .u { font-size: 9px; color: #94a3b8; }

        /* ===== Section heading ===== */
        h2 {
            margin: 22px 0 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #4f46e5;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        h2::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
        h2 .count {
            font-size: 9px;
            background: #eef2ff;
            color: #4338ca;
            border-radius: 999px;
            padding: 1px 8px;
            letter-spacing: 0;
        }

        /* ===== Tables ===== */
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        }
        table.data thead th {
            background: #1e293b;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 7px 9px;
            color: #f1f5f9;
            font-weight: 600;
        }
        table.data tbody td {
            padding: 6px 9px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
        }
        table.data tbody tr:nth-child(even) td { background: #f8fafc; }
        table.data tbody tr:last-child td { border-bottom: 0; }
        table.data tfoot td {
            padding: 7px 9px;
            background: #eef2ff;
            font-weight: 700;
            color: #312e81;
            border-top: 2px solid #c7d2fe;
        }
        .right { text-align: right; }
        .num { font-variant-numeric: tabular-nums; }
        .mono { font-family: 'Consolas', 'Menlo', monospace; font-size: 10px; }
        .muted { color: #94a3b8; }
        .strong { font-weight: 700; color: #0f172a; }
        .rank {
            display: inline-block;
            width: 16px; height: 16px;
            line-height: 16px;
            text-align: center;
            border-radius: 50%;
            background: #e2e8f0;
            color: #475569;
            font-size: 9px;
            font-weight: 700;
            margin-right: 6px;
        }
        .rank.top { background: #4f46e5; color: #fff; }

        .chip {
            display: inline-block;
            border-radius: 999px;
            padding: 1px 7px;
            margin: 0 3px 2px 0;
            font-size: 9px;
            white-space: nowrap;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
        }
        .item-list { margin: 0; padding-left: 0; list-style: none; font-size: 10px; }
        .item-list li { margin: 1px 0; padding-left: 12px; position: relative; }
        .item-list li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #818cf8;
        }
        .qty-pill {
            display: inline-block;
            min-width: 26px;
            text-align: center;
            background: #eef2ff;
            color: #4338ca;
            border-radius: 6px;
            padding: 1px 6px;
            font-weight: 700;
        }
        .empty {
            text-align: center;
            padding: 18px;
            color: #94a3b8;
            font-style: italic;
        }

        .footer {
            position: fixed;
            bottom: 10px; left: 26px; right: 26px;
            font-size: 8.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            display: flex;
            justify-content: space-between;
        }

        /* ===== Toolbar (layar saja) ===== */
        .toolbar {
            position: fixed;
            top: 12px; right: 12px;
            background: #0f172a;
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 11px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            z-index: 50;
        }
        .toolbar button {
            background: #fff;
            color: #0f172a;
            border: 0;
            border-radius: 5px;
            padding: 5px 11px;
            font-size: 11px;
            cursor: pointer;
            margin-left: 6px;
            font-weight: 600;
        }
        .toolbar button.primary { background: #6366f1; color: #fff; }

        @media print {
            .toolbar { display: none; }
            body { padding: 0 0 50px; }
            .header { border-radius: 0; }
            thead { display: table-header-group; }
            tfoot { display: table-row-group; }
            tr { page-break-inside: avoid; }
        }
        @page { margin: 12mm 10mm 16mm; }
    </style>
</head>
<body>
    <div class="toolbar">
        Pilih <strong>"Save as PDF"</strong> di dialog cetak
        <button type="button" class="primary" onclick="window.print()">Cetak / PDF</button>
        <button type="button" onclick="window.close()">Tutup</button>
    </div>

    {{-- ===== Header ===== --}}
    <div class="header">
        <div class="brand-logo">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="logo">
            @else
                {{ mb_strtoupper(mb_substr($appName, 0, 1)) }}
            @endif
        </div>
        <div class="title">
            <h1>Laporan Packing</h1>
            <div class="sub">{{ $appName }} · Periode {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} ({{ $days }} hari)</div>
        </div>
        <div class="printed">
            Dicetak<br><b>{{ now()->format('d M Y') }}</b><br>{{ now()->format('H:i') }} WIB
        </div>
    </div>

    @if ($userName || $type)
        <div class="filters">
            <span class="lbl">Filter aktif:</span>
            @if ($userName)<span class="tag">👤 {{ $userName }}</span>@endif
            @if ($type)<span class="tag">🏷 {{ $type }}</span>@endif
        </div>
    @endif

    {{-- ===== Stat cards ===== --}}
    <table class="stats">
        <tr>
            <td>
                <div class="k">Total Pesanan</div>
                <div class="v">{{ number_format($totalOrders) }}</div>
                <div class="u">resi ter-packing</div>
            </td>
            <td>
                <div class="k">Total Item</div>
                <div class="v accent">{{ number_format($totalItems) }}</div>
                <div class="u">pcs keluar</div>
            </td>
            <td>
                <div class="k">Total SKU</div>
                <div class="v">{{ number_format($totalDistinct) }}</div>
                <div class="u">varian unik</div>
            </td>
            <td>
                <div class="k">User Aktif</div>
                <div class="v">{{ number_format($totalUsers) }}</div>
                <div class="u">petugas packing</div>
            </td>
            <td>
                <div class="k">Total Scan</div>
                <div class="v">{{ number_format($totalScans) }}</div>
                <div class="u">baris detail</div>
            </td>
        </tr>
    </table>

    {{-- ===== Ringkasan per User ===== --}}
    <h2>Ringkasan per User <span class="count">{{ number_format($totalUsers) }} user</span></h2>
    <table class="data">
        <thead>
            <tr>
                <th>User</th>
                <th class="right" style="width: 90px;">Pesanan</th>
                <th class="right" style="width: 90px;">Item (pcs)</th>
                <th class="right" style="width: 70px;">SKU</th>
                <th style="width: 26%;">Rincian per Jenis</th>
                <th style="width: 26%;">Rincian Pengirim &amp; Layanan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($summary as $i => $row)
                <tr>
                    <td>
                        <span class="rank {{ $i === 0 ? 'top' : '' }}">{{ $i + 1 }}</span>
                        <span class="strong">{{ $row->user?->name ?? '—' }}</span>
                    </td>
                    <td class="right num">{{ number_format($row->total_orders) }}</td>
                    <td class="right num strong">{{ number_format($row->total_items) }}</td>
                    <td class="right num">{{ number_format($row->total_distinct) }}</td>
                    <td>
                        @php($bd = $typeBreakdown[$row->user?->id] ?? [])
                        @if (count($bd))
                            @foreach ($bd as $jenis => $qty)
                                <span class="chip">{{ $jenis }} · <b>{{ $qty }}</b></span>
                            @endforeach
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>
                        @php($sb = $senderBreakdown[$row->user?->id] ?? [])
                        @if (count($sb))
                            @foreach ($sb as $pengirim => $qty)
                                <span class="chip">{{ $pengirim }} · <b>{{ $qty }}</b></span>
                            @endforeach
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Tidak ada aktivitas packing pada rentang ini.</td></tr>
            @endforelse
        </tbody>
        @if ($summary->count())
            <tfoot>
                <tr>
                    <td>TOTAL</td>
                    <td class="right num">{{ number_format($totalOrders) }}</td>
                    <td class="right num">{{ number_format($totalItems) }}</td>
                    <td class="right num">{{ number_format($totalDistinct) }}</td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- ===== Detail Scan ===== --}}
    <h2>Detail Scan <span class="count">{{ number_format($totalScans) }} scan</span></h2>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 30px;" class="right">#</th>
                <th style="width: 95px;">Waktu</th>
                <th style="width: 100px;">User</th>
                <th style="width: 110px;">Resi</th>
                <th style="width: 90px;">Pengirim</th>
                <th>Item (Kelengkapan)</th>
                <th class="right" style="width: 42px;">Qty</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $idx => $log)
                <tr>
                    <td class="right muted num">{{ $idx + 1 }}</td>
                    <td class="mono">{{ $log->scanned_at->format('d/m/y H:i') }}</td>
                    <td>{{ $log->user?->name ?? '—' }}</td>
                    <td class="mono strong">{{ $log->resi_number }}</td>
                    <td>{{ $log->order?->sender_name ?? '—' }}</td>
                    <td>
                        @if ($log->order && $log->order->items->count())
                            <ul class="item-list">
                                @foreach ($log->order->items as $item)
                                    <li>
                                        <b>{{ $item->quantity }}×</b>
                                        {{ $item->product_name ?? '—' }}
                                        @if ($item->variant_name)— {{ $item->variant_name }}@endif
                                        @if ($item->sku)<span class="muted mono">[{{ $item->sku }}]</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="right"><span class="qty-pill num">{{ $log->total_items }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Tidak ada data scan pada rentang ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <span>{{ $appName }} — Laporan Packing</span>
        <span>Dihasilkan otomatis {{ now()->format('d M Y H:i') }}</span>
    </div>

    <script>
        // Auto-trigger print dialog setelah halaman + gambar di-render. User
        // tinggal pilih "Save as PDF" sebagai destination untuk file PDF.
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 350);
        });
    </script>
</body>
</html>
