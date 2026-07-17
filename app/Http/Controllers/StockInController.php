<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\Variant;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman "Input Barang Masuk" — untuk mencatat penerimaan stok baru
 * dari supplier / restock. Satu submit bisa berisi banyak varian sekaligus.
 */
class StockInController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function create(): View
    {
        $variants = Variant::with('product')
            ->orderBy('id')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'label' => ($v->product?->name ?? '—').' — '.$v->name.' ('.$v->sku.') · stok: '.$v->stock,
                'sku' => $v->sku,
                'stock' => $v->stock,
            ]);

        return view('stock_in.create', compact('variants'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $totalItems = 0;
        $rowsApplied = 0;

        DB::transaction(function () use ($data, $request, &$totalItems, &$rowsApplied) {
            foreach ($data['items'] as $row) {
                $variant = Variant::whereKey($row['variant_id'])->first();
                if (! $variant) {
                    continue;
                }

                $qty = max(1, (int) $row['quantity']);

                $this->stock->adjust(
                    $variant,
                    $qty,
                    StockMovement::TYPE_IN,
                    $request->user()->id,
                    null,
                    trim(($data['reference'] ?? 'Barang masuk').($data['note'] ?? '' ? ' — '.$data['note'] : '')),
                );

                $totalItems += $qty;
                $rowsApplied++;
            }
        });

        return redirect()->route('products.index')
            ->with('success', "Barang masuk tercatat: {$rowsApplied} varian, total {$totalItems} pcs.");
    }
}
