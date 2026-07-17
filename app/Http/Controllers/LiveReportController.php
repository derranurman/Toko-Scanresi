<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveReportController extends Controller
{
    public function __construct(private OrderMetricsService $metrics)
    {
    }

    /**
     * Laporan Live.
     *
     * Daftar pesanan yang punya Host Live dalam satu periode (bulan/tahun),
     * berdasarkan `order_date`. Bisa difilter per nama Host Live. Tiap baris
     * menampilkan metrik ekonomi pesanan dari OrderMetricsService.
     */
    public function index(Request $request): View
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $host = trim((string) $request->query('host', ''));
        $startDate = trim((string) $request->query('start_date', ''));
        $endDate = trim((string) $request->query('end_date', ''));

        // Semua data terfilter untuk menghitung ringkasan/total (tidak
        // terpengaruh pagination), lalu tabel dipaginate terpisah.
        $allOrders = $this->buildQuery($month, $year, $host, $startDate, $endDate)->get();

        $metrics = [];
        $totals = $this->emptyTotals();
        $hostSet = [];

        foreach ($allOrders as $order) {
            $m = $this->metrics->compute($order);
            $metrics[$order->id] = $m;
            $hostSet[$order->host_live] = true;

            $totals['orders']++;
            foreach (['total_jual', 'total_modal', 'profit_kotor', 'margin_live', 'bersih_margin_live', 'margin_bisnis'] as $k) {
                $totals[$k] += (float) $m[$k];
            }
        }
        $totals['hosts'] = count($hostSet);

        // Perangkingan tiap Host Live berdasarkan Bersih Margin Live (net).
        $ranking = [];
        foreach ($allOrders as $order) {
            $m = $metrics[$order->id];
            $h = $order->host_live;
            if (! isset($ranking[$h])) {
                $ranking[$h] = [
                    'host' => $h,
                    'orders' => 0,
                    'total_jual' => 0.0,
                    'bersih_margin_live' => 0.0,
                    'margin_bisnis' => 0.0,
                ];
            }
            $ranking[$h]['orders']++;
            $ranking[$h]['total_jual'] += (float) $m['total_jual'];
            $ranking[$h]['bersih_margin_live'] += (float) $m['bersih_margin_live'];
            $ranking[$h]['margin_bisnis'] += (float) $m['margin_bisnis'];
        }
        $ranking = collect($ranking)
            ->sortByDesc('bersih_margin_live')
            ->values();

        // Data tabel dipaginate (mempertahankan query string filter).
        $orders = $this->buildQuery($month, $year, $host, $startDate, $endDate)->paginate(25)->withQueryString();

        // Pastikan metrik tersedia untuk tiap baris pada halaman ini.
        foreach ($orders as $order) {
            if (! isset($metrics[$order->id])) {
                $metrics[$order->id] = $this->metrics->compute($order);
            }
        }

        // Daftar host untuk dropdown filter (semua host di periode ini,
        // terlepas dari filter yang sedang aktif).
        $hosts = $this->buildQuery($month, $year, '', $startDate, $endDate)
            ->reorder()
            ->distinct()
            ->orderBy('host_live')
            ->pluck('host_live')
            ->filter()
            ->values();

        return view('reports.live', compact('orders', 'metrics', 'totals', 'hosts', 'ranking', 'month', 'year', 'host', 'startDate', 'endDate'));
    }

    /**
     * Export Laporan Live ke CSV (dibuka di Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $host = trim((string) $request->query('host', ''));
        $startDate = trim((string) $request->query('start_date', ''));
        $endDate = trim((string) $request->query('end_date', ''));

        $orders = $this->buildQuery($month, $year, $host, $startDate, $endDate)->get();

        $filename = sprintf('laporan-live-%04d-%02d.csv', $year, $month);

        // Format angka dengan koma desimal (tanpa pemisah ribuan) supaya
        // Excel locale Indonesia membacanya sebagai ANGKA, bukan menafsirkan
        // titik sebagai pemisah ribuan.
        $num = fn ($v) => number_format((float) $v, 2, ',', '');

        return response()->streamDownload(function () use ($orders, $num) {
            $handle = fopen('php://output', 'w');

            // BOM utk UTF-8 Excel.
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'No',
                'Tanggal',
                'Host Live',
                'Platform',
                'Resi',
                'Nama Barang',
                'Total Jual',
                'Total Modal',
                'Profit Kotor',
                'Margin Live',
                'Bersih Margin Live',
                'Margin Bisnis',
            ], ';');

            $no = 1;
            foreach ($orders as $order) {
                $m = $this->metrics->compute($order);

                fputcsv($handle, [
                    $no++,
                    optional($order->order_date)->format('Y-m-d') ?? '-',
                    $order->host_live ?? '-',
                    $order->platformDeduction?->platform_name ?? '-',
                    $order->resi_number,
                    $this->itemNames($order) ?: '-',
                    $num($m['total_jual']),
                    $num($m['total_modal']),
                    $num($m['profit_kotor']),
                    $num($m['margin_live']),
                    $num($m['bersih_margin_live']),
                    $num($m['margin_bisnis']),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Query dasar: semua pesanan dengan host_live terisi pada bulan/tahun
     * yang dipilih (berdasarkan order_date), opsional difilter per host.
     */
    private function buildQuery(int $month, int $year, string $host, string $startDate = '', string $endDate = '')
    {
        $query = Order::with(['items.variant.product', 'platformDeduction'])
            ->whereNotNull('host_live')
            ->where('host_live', '!=', '');

        if ($startDate !== '' || $endDate !== '') {
            if ($startDate !== '') {
                $query->whereDate('order_date', '>=', $startDate);
            }
            if ($endDate !== '') {
                $query->whereDate('order_date', '<=', $endDate);
            }
        } else {
            $query->whereYear('order_date', $year)
                ->whereMonth('order_date', $month);
        }

        return $query
            ->when($host !== '', fn ($q) => $q->where('host_live', $host))
            ->orderBy('host_live')
            ->orderByDesc('id');
    }

    /**
     * Susun "Nama Barang" gabungan dari item pesanan (nama master kalau ada,
     * fallback ke snapshot di order_items, plus nama varian).
     */
    private function itemNames(Order $order): string
    {
        return $order->items
            ->map(function ($it) {
                $name = $it->variant?->product?->name ?? $it->product_name;
                if ($it->variant?->name) {
                    $name = trim(($name ?? '—') . ' — ' . $it->variant->name);
                }
                return $name;
            })
            ->filter()
            ->unique()
            ->implode(', ');
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyTotals(): array
    {
        return [
            'orders' => 0,
            'hosts' => 0,
            'total_jual' => 0.0,
            'total_modal' => 0.0,
            'profit_kotor' => 0.0,
            'margin_live' => 0.0,
            'bersih_margin_live' => 0.0,
            'margin_bisnis' => 0.0,
        ];
    }
}
