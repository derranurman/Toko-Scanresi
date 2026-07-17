<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockReportController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $status = strtolower(trim((string) $request->query('status', '')));

        if (! in_array($status, ['low', 'ok', 'empty'], true)) {
            $status = '';
        }

        $variantStatusFilter = function ($query) use ($status) {
            if ($status === 'low') {
                $query->whereColumn('stock', '<=', 'min_stock');
            } elseif ($status === 'ok') {
                $query->whereColumn('stock', '>', 'min_stock');
            } elseif ($status === 'empty') {
                $query->where('stock', '<=', 0);
            }
        };

        $products = Product::with(['variants' => $variantStatusFilter])
            ->where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('type', 'like', "%{$q}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== '', function ($query) use ($variantStatusFilter) {
                $query->whereHas('variants', $variantStatusFilter);
            })
            ->orderBy('name')
            ->get();

        // Daftar tipe untuk dropdown filter (semua tipe yang ada di DB).
        $types = Product::where('is_active', true)
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return view('reports.stock', compact('products', 'q', 'type', 'status', 'types'));
    }

    public function export(Request $request): StreamedResponse
    {
        $type = trim((string) $request->query('type', ''));
        $status = strtolower(trim((string) $request->query('status', '')));

        if (! in_array($status, ['low', 'ok', 'empty'], true)) {
            $status = '';
        }

        $variantStatusFilter = function ($query) use ($status) {
            if ($status === 'low') {
                $query->whereColumn('stock', '<=', 'min_stock');
            } elseif ($status === 'ok') {
                $query->whereColumn('stock', '>', 'min_stock');
            } elseif ($status === 'empty') {
                $query->where('stock', '<=', 0);
            }
        };

        $products = Product::with(['variants' => $variantStatusFilter])
            ->where('is_active', true)
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($status !== '', function ($query) use ($variantStatusFilter) {
                $query->whereHas('variants', $variantStatusFilter);
            })
            ->orderBy('name')
            ->get();

        $filename = 'laporan-stok-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($products) {
            $handle = fopen('php://output', 'w');

            // BOM untuk Excel agar UTF-8 terbaca
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header
            fputcsv($handle, [
                'No',
                'Produk',
                'SKU Produk',
                'Tipe',
                'Variant',
                'SKU Variant',
                'Stok',
                'Min Stok',
                'Status',
                'Harga Beli',
                'Harga Reseller',
                'Harga Jual',
            ], ';');

            $no = 1;
            foreach ($products as $product) {
                foreach ($product->variants as $variant) {
                    $status = $variant->stock <= $variant->min_stock ? 'LOW STOCK' : 'OK';
                    fputcsv($handle, [
                        $no++,
                        $product->name,
                        $product->sku,
                        $product->type ?? '-',
                        $variant->name,
                        $variant->sku,
                        $variant->stock,
                        $variant->min_stock,
                        $status,
                        $product->purchase_price,
                        $product->reseller_price,
                        $product->selling_price,
                    ], ';');
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
