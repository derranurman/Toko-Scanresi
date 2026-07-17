<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderMetricsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReturnReportController extends Controller
{
    public function __construct(private OrderMetricsService $metrics)
    {
    }

    /**
     * Laporan Return.
     *
     * Tampilkan SEMUA pesanan yang punya `returned_at` (pernah di-return),
     * tidak peduli status sekarang. Ini supaya barang yang sudah diterima
     * kembali (status -> selesai_bulan_kemarin) TETAP tercatat di laporan.
     */
    public function index(Request $request): View
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);

        $orders = $this->buildQuery($month, $year)->get();

        $totalKerugian = 0;
        $totalJualHilang = 0;
        $metrics = [];
        foreach ($orders as $order) {
            $m = $this->metrics->compute($order);
            $metrics[$order->id] = $m;
            $totalKerugian += $m['total_modal'];
            $totalJualHilang += $m['total_jual'];
        }

        $returnCount = $orders->count();

        // Bulan sebelumnya untuk perbandingan.
        $prevDate = Carbon::create($year, $month, 1)->subMonth();
        $prevMonthCount = Order::whereNotNull('returned_at')
            ->whereYear('returned_at', $prevDate->year)
            ->whereMonth('returned_at', $prevDate->month)
            ->count();

        return view('reports.returns', compact(
            'orders',
            'metrics',
            'returnCount',
            'totalKerugian',
            'totalJualHilang',
            'prevMonthCount',
            'month',
            'year',
        ));
    }

    /**
     * Hapus permanen 1 baris dari Laporan Return.
     * Kita hanya menghapus penanda return-nya (set returned_at = null) dan
     * jika status masih "return", balikin ke pending. Pesanannya sendiri
     * tidak dihapus supaya tidak kehilangan histori.
     */
    public function destroy(Order $order): RedirectResponse
    {
        if ($order->returned_at === null) {
            return back()->with('error', 'Pesanan ini tidak ada di Laporan Return.');
        }

        $update = ['returned_at' => null];
        if ($order->status === Order::STATUS_RETURN) {
            $update['status'] = Order::STATUS_PENDING;
        }

        $order->update($update);

        return back()->with('success', "Pesanan {$order->resi_number} dihapus dari Laporan Return.");
    }

    /**
     * Export Laporan Return ke CSV (dibuka di Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);

        $orders = $this->buildQuery($month, $year)->get();

        $filename = sprintf('laporan-return-%04d-%02d.csv', $year, $month);

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            // BOM utk UTF-8 Excel.
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header — sama persis dengan kolom di tabel laporan return.
            fputcsv($handle, [
                'No',
                'Resi',
                'Tanggal Return',
                'Pembeli',
                'SKU',
                'Total Jual',
                'Total Modal',
                'Status Saat Ini',
                'Catatan',
            ], ';');

            $no = 1;
            foreach ($orders as $order) {
                $m = $this->metrics->compute($order);
                $skus = $order->items->pluck('sku')->filter()->unique()->implode(', ');

                fputcsv($handle, [
                    $no++,
                    $order->resi_number,
                    $order->returned_at?->format('d/m/Y H:i') ?? '-',
                    $order->buyer_name ?? '-',
                    $skus ?: '-',
                    $m['total_jual'],
                    $m['total_modal'],
                    \App\Models\Order::STATUS_LABELS[$order->status] ?? ucfirst($order->status),
                    $order->notes,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Query dasar yang dipakai oleh index() & export(): semua pesanan
     * yang `returned_at` ada di bulan/tahun yang dipilih.
     */
    private function buildQuery(int $month, int $year)
    {
        return Order::with(['items.variant.product', 'platformDeduction'])
            ->whereNotNull('returned_at')
            ->whereYear('returned_at', $year)
            ->whereMonth('returned_at', $month)
            ->orderByDesc('returned_at');
    }
}
