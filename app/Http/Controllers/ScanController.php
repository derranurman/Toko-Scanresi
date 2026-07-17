<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PackingLog;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function __construct(private StockService $stock)
    {
    }

    public function index(): View
    {
        $pendingCount = Order::where('status', Order::STATUS_PENDING)->count();
        $mePackedToday = PackingLog::where('user_id', auth()->id())
            ->whereDate('scanned_at', today())
            ->count();

        return view('scan.index', compact('pendingCount', 'mePackedToday'));
    }

    /**
     * Lookup order by resi_number. Returns order + items as JSON.
     */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resi_number' => ['required', 'string', 'max:100'],
        ]);

        $resi = trim($data['resi_number']);

        $order = Order::with(['items.variant.product', 'packedBy'])
            ->where('resi_number', $resi)
            ->first();

        if (! $order) {
            return response()->json([
                'ok' => false,
                'code' => 'not_found',
                'message' => "Resi {$resi} tidak ditemukan. Pastikan sudah di-import dari TikTok Shop.",
            ], 404);
        }

        if ($order->status === Order::STATUS_PACKED) {
            return response()->json([
                'ok' => false,
                'code' => 'already_packed',
                'message' => "Resi {$resi} sudah di-packing oleh {$order->packedBy?->name} pada {$order->packed_at?->format('d M Y H:i')}.",
                'order' => $this->serialize($order),
            ], 409);
        }

        if ($order->status === Order::STATUS_RETURN) {
            return response()->json([
                'ok' => false,
                'code' => 'return',
                'message' => "Pesanan ini berstatus Return dan tidak bisa di-packing.",
            ], 409);
        }

        // Cek stok mencukupi
        $issues = [];
        foreach ($order->items as $item) {
            if (! $item->variant) {
                $issues[] = "SKU {$item->sku} tidak terdaftar di master produk.";
                continue;
            }
            if ($item->variant->stock < $item->quantity) {
                $issues[] = "Stok {$item->product_name} ({$item->variant_name}) tidak cukup: tersedia {$item->variant->stock}, dibutuhkan {$item->quantity}.";
            }
        }

        return response()->json([
            'ok' => true,
            'order' => $this->serialize($order),
            'warnings' => $issues,
        ]);
    }

    /**
     * Konfirmasi packed: kurangi stok, set status packed, catat log.
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resi_number' => ['required', 'string', 'max:100'],
        ]);

        $resi = trim($data['resi_number']);
        $user = $request->user();

        try {
            $result = DB::transaction(function () use ($resi, $user) {
                $order = Order::with('items.variant')
                    ->where('resi_number', $resi)
                    ->lockForUpdate()
                    ->first();

                if (! $order) {
                    return ['ok' => false, 'status' => 404, 'code' => 'not_found', 'message' => 'Resi tidak ditemukan.'];
                }
                if ($order->status === Order::STATUS_PACKED) {
                    return ['ok' => false, 'status' => 409, 'code' => 'already_packed', 'message' => 'Resi sudah dipacking.'];
                }
                if ($order->status === Order::STATUS_RETURN) {
                    return ['ok' => false, 'status' => 409, 'code' => 'return', 'message' => 'Pesanan berstatus Return.'];
                }

                // Validasi stok
                foreach ($order->items as $item) {
                    if (! $item->variant) {
                        return ['ok' => false, 'status' => 422, 'code' => 'sku_missing',
                                'message' => "SKU {$item->sku} tidak terdaftar. Tambahkan di master produk terlebih dahulu."];
                    }
                    if ($item->variant->stock < $item->quantity) {
                        return ['ok' => false, 'status' => 422, 'code' => 'insufficient_stock',
                                'message' => "Stok {$item->product_name} ({$item->variant_name}) tidak cukup."];
                    }
                }

                // Kurangi stok
                $totalItems = 0;
                foreach ($order->items as $item) {
                    $this->stock->adjust(
                        $item->variant,
                        -$item->quantity,
                        StockMovement::TYPE_OUT,
                        $user->id,
                        $order->id,
                        'Scan resi '.$order->resi_number,
                    );
                    $totalItems += $item->quantity;
                }

                $order->update([
                    'status' => Order::STATUS_PACKED,
                    'packed_at' => now(),
                    'packed_by_user_id' => $user->id,
                ]);

                PackingLog::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'resi_number' => $order->resi_number,
                    'total_items' => $totalItems,
                    'distinct_skus' => $order->items->count(),
                    'scanned_at' => now(),
                ]);

                return [
                    'ok' => true,
                    'message' => "Berhasil! Stok sudah dikurangi. ({$totalItems} pcs)",
                    'order' => $this->serialize($order->fresh(['items.variant.product', 'packedBy'])),
                ];
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Terjadi kesalahan saat memproses: '.$e->getMessage(),
            ], 500);
        }

        if (! $result['ok']) {
            return response()->json([
                'ok' => false,
                'code' => $result['code'] ?? 'error',
                'message' => $result['message'],
            ], $result['status'] ?? 400);
        }

        return response()->json($result);
    }

    private function serialize(Order $order): array
    {
        return [
            'id' => $order->id,
            'resi_number' => $order->resi_number,
            'tiktok_order_id' => $order->tiktok_order_id,
            'status' => $order->status,
            'buyer_name' => $order->buyer_name,
            'buyer_phone' => $order->buyer_phone,
            'shipping_address' => $order->shipping_address,
            'order_date' => $order->order_date?->format('d M Y H:i'),
            'packed_at' => $order->packed_at?->format('d M Y H:i'),
            'packed_by' => $order->packedBy?->name,
            'items' => $order->items->map(fn ($it) => [
                'product_name' => $it->product_name,
                'variant_name' => $it->variant_name,
                'sku' => $it->sku,
                'quantity' => $it->quantity,
                'stock' => $it->variant?->stock,
                'has_variant' => (bool) $it->variant,
            ])->values(),
        ];
    }
}
