<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PlatformDeduction;
use App\Services\OrderMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderReportController extends Controller
{
    public function __construct(private OrderMetricsService $metrics)
    {
    }

    public function index(Request $request): View
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $status = $request->query('status');
        $platform = $request->query('platform');

        // Query dasar yang dipakai ulang untuk ringkasan (semua data) dan
        // untuk tabel (dipaginate). Filter platform berdasarkan
        // platform_deduction_id yang tampil di kolom Platform.
        $baseQuery = fn () => Order::with(['items.variant.product', 'platformDeduction'])
            ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($platform, fn ($q) => $q->where('platform_deduction_id', $platform))
            ->latest('id');

        // Ambil SEMUA data terfilter untuk menghitung ringkasan/metrik total
        // supaya angka ringkasan tetap mencakup seluruh periode, bukan hanya
        // halaman yang sedang tampil.
        $allOrders = $baseQuery()->get();

        $totalJual = 0;
        $totalModal = 0;
        $totalProfit = 0;
        $metrics = [];

        foreach ($allOrders as $order) {
            $m = $this->metrics->compute($order);
            $metrics[$order->id] = $m;
            $totalJual += $m['total_jual'];
            $totalModal += $m['total_modal'];
            $totalProfit += $m['profit_kotor'];
        }

        $statusCounts = [
            'pending' => $allOrders->where('status', Order::STATUS_PENDING)->count(),
            'packed' => $allOrders->where('status', Order::STATUS_PACKED)->count(),
            'selesai_bulan_kemarin' => $allOrders->where('status', Order::STATUS_SELESAI_BULAN_KEMARIN)->count(),
            'selesai_return' => $allOrders->where('status', Order::STATUS_SELESAI_RETURN)->count(),
            'return' => $allOrders->where('status', Order::STATUS_RETURN)->count(),
        ];

        // Data tabel dipaginate (mempertahankan query string filter).
        $orders = $baseQuery()->paginate(25)->withQueryString();

        // Pastikan metrik tersedia untuk tiap baris pada halaman ini.
        foreach ($orders as $order) {
            if (! isset($metrics[$order->id])) {
                $metrics[$order->id] = $this->metrics->compute($order);
            }
        }

        // Daftar platform untuk dropdown filter.
        $platforms = PlatformDeduction::orderBy('platform_name')->get(['id', 'platform_name']);

        return view('reports.orders', compact(
            'orders',
            'metrics',
            'totalJual',
            'totalModal',
            'totalProfit',
            'statusCounts',
            'startDate',
            'endDate',
            'status',
            'platform',
            'platforms',
        ));
    }
}
