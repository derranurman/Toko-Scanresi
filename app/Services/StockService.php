<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Atomically adjust variant stock and record a movement.
     */
    public function adjust(
        Variant $variant,
        int $qty,
        string $type,
        ?int $userId = null,
        ?int $orderId = null,
        ?string $reference = null,
    ): Variant {
        return DB::transaction(function () use ($variant, $qty, $type, $userId, $orderId, $reference) {
            /** @var Variant $locked */
            $locked = Variant::whereKey($variant->id)->lockForUpdate()->first();
            $locked->stock += $qty;
            if ($locked->stock < 0) {
                $locked->stock = 0;
            }
            $locked->save();

            StockMovement::create([
                'variant_id' => $locked->id,
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => $type,
                'qty' => $qty,
                'stock_after' => $locked->stock,
                'reference' => $reference,
            ]);

            return $locked->fresh();
        });
    }
}
