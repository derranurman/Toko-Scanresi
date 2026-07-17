<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ProductReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to, $productId, $type] = $this->filters($request);

        // Ringkasan per produk: total masuk / keluar / penyesuaian dalam rentang
        $summaryRows = StockMovement::query()
            ->selectRaw('variants.product_id, stock_movements.type, SUM(stock_movements.qty) as total_qty')
            ->join('variants', 'variants.id', '=', 'stock_movements.variant_id')
            ->whereBetween('stock_movements.created_at', [$from, $to])
            ->when($productId, fn ($q) => $q->where('variants.product_id', $productId))
            ->groupBy('variants.product_id', 'stock_movements.type')
            ->get();

        // Bentuk ulang jadi array per product_id dengan field siap tampil.
        $summary = [];
        foreach ($summaryRows as $row) {
            $pid = (int) $row->product_id;
            if (! isset($summary[$pid])) {
                $summary[$pid] = [
                    'in' => 0,
                    'out' => 0,
                    'adjustment' => 0,
                ];
            }
            $summary[$pid][$row->type] = (int) $row->total_qty;
        }

        // Sekalian hitung absolut & net supaya blade bersih (tidak ada logic).
        $productsMap = Product::whereIn('id', array_keys($summary))->get()->keyBy('id');
        $summaryTable = [];
        foreach ($summary as $pid => $row) {
            $in = (int) $row['in'];
            $out = (int) $row['out'];
            $adj = (int) $row['adjustment'];
            $outAbs = abs($out);
            $net = $in + $adj - $outAbs;

            $summaryTable[] = [
                'product_name' => $productsMap[$pid]->name ?? '—',
                'in' => $in,
                'out_abs' => $outAbs,
                'adjustment' => $adj,
                'net' => $net,
            ];
        }

        // Riwayat detail (paginate)
        // Eager-load `order` supaya kolom "Resi" di Riwayat Pergerakan
        // bisa diambil dari order.resi_number tanpa N+1.
        $movements = StockMovement::query()
            ->with(['variant.product', 'user', 'order'])
            ->whereBetween('stock_movements.created_at', [$from, $to])
            ->when($productId, fn ($q) => $q->whereHas('variant', fn ($sub) => $sub->where('product_id', $productId)))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $products = Product::orderBy('name')->get(['id', 'name']);

        return view('reports.products', [
            'summaryTable' => $summaryTable,
            'movements' => $movements,
            'products' => $products,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'productId' => $productId,
            'type' => (string) $type,
        ]);
    }

    /**
     * Hapus riwayat pergerakan stok SESUAI FILTER aktif (rentang tanggal +
     * opsional produk/tipe). Admin-only (dijaga di route). Ini hanya
     * membersihkan CATATAN riwayat pergerakan; stok produk saat ini tidak
     * dihitung ulang.
     */
    public function destroyByFilter(Request $request): RedirectResponse
    {
        [$from, $to, $productId, $type] = $this->filters($request);

        $query = StockMovement::query()
            ->whereBetween('stock_movements.created_at', [$from, $to])
            ->when($productId, fn ($q) => $q->whereHas('variant', fn ($sub) => $sub->where('product_id', $productId)))
            ->when($type, fn ($q) => $q->where('type', $type));

        $count = (clone $query)->count();
        $query->delete();

        return redirect()
            ->route('reports.products', $request->only(['from', 'to', 'product_id', 'type']))
            ->with('success', "{$count} riwayat pergerakan stok dihapus (rentang {$from->format('d/m/Y')} – {$to->format('d/m/Y')}).");
    }

    /**
     * Hapus riwayat pergerakan stok yang DIPILIH MANUAL (centang baris di
     * tabel Riwayat Pergerakan). Admin-only (dijaga di route).
     */
    public function destroySelected(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:stock_movements,id'],
        ]);

        $count = StockMovement::whereIn('id', $data['ids'])->delete();

        return back()->with('success', "{$count} riwayat pergerakan stok terpilih dihapus.");
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: ?int, 3: ?string}
     */
    private function filters(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        $productId = $request->filled('product_id') ? (int) $request->query('product_id') : null;
        $type = $request->filled('type') ? (string) $request->query('type') : null;

        return [$from, $to, $productId, $type];
    }
}
