<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PlatformDeduction;

/**
 * Hitung semua metrik ekonomi untuk 1 pesanan.
 *
 * RUMUS (sesuai spesifikasi user, Mei 2026):
 *
 *   Total Jual     = Σ(Harga Jual × Qty)
 *   Total Modal    = Σ(Harga Beli × Qty)
 *   Total Reseller = Σ(Harga Reseller × Qty)
 *
 *   Harga Jual/Beli/Reseller diambil dari SNAPSHOT di order_items (harga
 *   saat pesanan dibuat) bila tersedia; kalau tidak (pesanan lama),
 *   fallback ke harga master Product saat ini.
 *
 *   // --- Potongan: tiap field bisa berupa PERSEN (%) atau NOMINAL (Rp) ---
 *   Tiap field di PlatformDeduction punya flag *_is_percent.
 *     - Jika is_percent = true  → nilai dihitung = base × nilai / 100
 *     - Jika is_percent = false → nilai dihitung = nilai (nominal Rp tetap)
 *
 *   Base per field:
 *     - ADM, Pajak, Bulat Max 650Rb → dasar di-cap maksimal 650.000
 *     - Field lain → dasar Total Jual penuh
 *
 *   // --- Agregat potongan ---
 *   Total Potongan Aplikasi = ADM + Bulat Max 650Rb + Biaya Layanan + Biaya Logistik + Pajak
 *
 *   // --- Margin ---
 *   Margin Live     = Total Jual - Total Reseller
 *   Profit Kotor    = Total Jual - Total Modal
 *
 *   Bersih Margin Live = Margin Live - Total Potongan Aplikasi
 *
 *   Margin Bisnis   = Total Reseller
 *                   - Total Modal
 *                   - Operasional
 *                   - Plastik/Dus
 *                   - Label (Rp)
 *                   - Ongkir Cargo
 *                   - Yield
 *
 * Semua return value disimpan di array dengan key stabil supaya
 * blade tinggal print (tidak ada logic di view).
 */
class OrderMetricsService
{
    /** Dasar perhitungan ADM/Pajak/Bulat Max di-cap maksimal ini (aturan TikTok). */
    public const BULAT_MAX = 650_000.0;

    /**
     * @return array<string, float|string|null>
     */
    public function compute(Order $order): array
    {
        $items = $order->items()->with('variant.product')->get();

        $totalJual = 0.0;
        $totalModal = 0.0;
        $totalReseller = 0.0;
        $totalQty = 0;

        // Buat "item satuan" jadi total terkumpul.
        //
        // Sumber harga (per item):
        //   1. Snapshot harga satuan di order_items (selling/purchase/reseller_price)
        //      jika ada → harga "beku" saat pesanan dibuat. Ini bikin metrik
        //      pesanan historis stabil meski harga master Product diubah.
        //   2. Fallback ke harga master Product saat ini untuk pesanan lama
        //      yang belum punya snapshot (NULL).
        foreach ($items as $item) {
            $qty = (int) $item->quantity;
            $totalQty += $qty;

            if ($item->hasPriceSnapshot()) {
                $totalJual += (float) $item->selling_price * $qty;
                $totalModal += (float) $item->purchase_price * $qty;
                $totalReseller += (float) $item->reseller_price * $qty;
                continue;
            }

            $product = $item->variant?->product;
            if ($product) {
                $totalJual += (float) $product->selling_price * $qty;
                $totalModal += (float) $product->purchase_price * $qty;
                $totalReseller += (float) $product->reseller_price * $qty;
            }
        }

        $deduction = $order->platformDeduction;

        // Dasar yang di-cap 650k: dipakai untuk ADM, Pajak, dan Bulat Max 650Rb.
        $capBase = min($totalJual, self::BULAT_MAX);

        // Helper: ubah "nilai mentah" jadi Rupiah dengan menghormati flag is_percent.
        //   - is_percent true  → base × nilai / 100
        //   - is_percent false → nilai (nominal Rp tetap)
        $resolve = function (float $value, bool $isPercent, float $base): float {
            return $isPercent ? $base * $value / 100 : $value;
        };

        $admRp = 0.0;
        $cbBpRp = 0.0;
        $ongkirFreeRp = 0.0;
        $yieldRp = 0.0;
        $operasionalRp = 0.0;
        $pajakRp = 0.0;
        $bulatMax = 0.0;
        $ongkirCargo = 0.0;
        $label = 0.0;
        $plastik = 0.0;
        $biayaLayanan = 0.0;
        $biayaLogistik = 0.0;

        // Nilai persen yang ditampilkan (hanya berarti kalau field dalam mode %).
        $admPct = 0.0;
        $cbBpPct = 0.0;
        $ongkirFreePct = 0.0;
        $yieldPct = 0.0;
        $operasionalPct = 0.0;
        $pajakPct = 0.0;

        if ($deduction instanceof PlatformDeduction) {
            // Tampilkan angka persen hanya bila field memang dalam mode %.
            $admPct = $deduction->adm_is_percent ? (float) $deduction->adm_percent : 0.0;
            $cbBpPct = $deduction->cashback_is_percent ? (float) $deduction->cashback_percent : 0.0;
            $ongkirFreePct = $deduction->free_shipping_is_percent ? (float) $deduction->free_shipping_percent : 0.0;
            $yieldPct = $deduction->yield_is_percent ? (float) $deduction->yield_percent : 0.0;
            $operasionalPct = $deduction->operational_is_percent ? (float) $deduction->operational_percent : 0.0;
            $pajakPct = $deduction->tax_is_percent ? (float) $deduction->tax_percent : 0.0;

            // Potongan dengan dasar di-cap 650k (ADM, Pajak, Bulat Max).
            $admRp = $resolve((float) $deduction->adm_percent, (bool) $deduction->adm_is_percent, $capBase);
            $pajakRp = $resolve((float) $deduction->tax_percent, (bool) $deduction->tax_is_percent, $capBase);
            $bulatMax = $resolve((float) $deduction->free_shipping_percent, (bool) $deduction->free_shipping_is_percent, $capBase);

            // Potongan dengan dasar Total Jual penuh.
            $cbBpRp = $resolve((float) $deduction->cashback_percent, (bool) $deduction->cashback_is_percent, $totalJual);
            $ongkirFreeRp = $resolve((float) $deduction->free_shipping_percent, (bool) $deduction->free_shipping_is_percent, $totalJual);
            $yieldRp = $resolve((float) $deduction->yield_percent, (bool) $deduction->yield_is_percent, $totalJual);
            $operasionalRp = $resolve((float) $deduction->operational_percent, (bool) $deduction->operational_is_percent, $totalJual);

            // Field yang dulunya selalu nominal Rp, sekarang bisa % juga.
            $ongkirCargo = $resolve((float) $deduction->shipping_cargo_amount, (bool) $deduction->shipping_cargo_is_percent, $totalJual);
            $label = $resolve((float) $deduction->label_amount, (bool) $deduction->label_is_percent, $totalJual);
            $plastik = $resolve((float) $deduction->packaging_amount, (bool) $deduction->packaging_is_percent, $totalJual);
            $biayaLayanan = $resolve((float) $deduction->service_fee_amount, (bool) $deduction->service_fee_is_percent, $totalJual);
            $biayaLogistik = $resolve((float) $deduction->logistics_amount, (bool) $deduction->logistics_is_percent, $totalJual);
        }

        // Total Potongan Aplikasi = ADM + Bulat Max 650Rb + Biaya Layanan + Biaya Logistik + Pajak
        // Jika user mengisi override manual di order, pakai nilai itu.
        $totalPotonganAplikasiAuto = $admRp + $bulatMax + $biayaLayanan + $biayaLogistik + $pajakRp;
        $totalPotonganAplikasi = $order->total_potongan_aplikasi_override !== null
            ? (float) $order->total_potongan_aplikasi_override
            : $totalPotonganAplikasiAuto;

        // Margin Live = Total Jual - Total Reseller
        $marginLive = $totalJual - $totalReseller;
        $pctMarginLive = $totalJual > 0 ? ($marginLive / $totalJual) * 100 : 0;

        // Profit Kotor = Total Jual - Total Modal
        $profitKotor = $totalJual - $totalModal;
        $pctProfitKotor = $totalJual > 0 ? ($profitKotor / $totalJual) * 100 : 0;

        // Bersih Margin Live = Margin Live - Total Potongan Aplikasi
        $bersihMarginLive = $marginLive - $totalPotonganAplikasi;

        // Margin Bisnis = Total Reseller - Total Modal - Operasional
        //   - Plastik/Dus - Label - Ongkir Cargo - Yield
        $marginBisnis = $totalReseller
            - $totalModal
            - $operasionalRp
            - $plastik
            - $label
            - $ongkirCargo
            - $yieldRp;
        $pctMarginBisnis = $totalJual > 0 ? ($marginBisnis / $totalJual) * 100 : 0;

        return [
            'total_qty' => $totalQty,
            'total_jual' => $totalJual,
            'total_modal' => $totalModal,
            'total_reseller' => $totalReseller,
            'ongkir_cargo' => $ongkirCargo,
            'yield_rp' => $yieldRp,
            'label' => $label,
            'plastik_dus' => $plastik,
            'operasional_rp' => $operasionalRp,
            'adm_pct' => $admPct,
            'adm_rp' => $admRp,
            'ongkir_free_pct' => $ongkirFreePct,
            'ongkir_free_rp' => $ongkirFreeRp,
            'bulat_max' => $bulatMax,
            'biaya_layanan' => $biayaLayanan,
            'biaya_logistik' => $biayaLogistik,
            'pajak_pct' => $pajakPct,
            'pajak_rp' => $pajakRp,
            'cb_bp_pct' => $cbBpPct,
            'cb_bp_rp' => $cbBpRp,
            'profit_kotor' => $profitKotor,
            'pct_profit_kotor' => $pctProfitKotor,
            'margin_bisnis' => $marginBisnis,
            'pct_margin_bisnis' => $pctMarginBisnis,
            'margin_live' => $marginLive,
            'pct_margin_live' => $pctMarginLive,
            'bersih_margin_live' => $bersihMarginLive,
            'total_potongan_aplikasi' => $totalPotonganAplikasi,
            'total_potongan_aplikasi_auto' => $totalPotonganAplikasiAuto,
            'total_potongan_aplikasi_overridden' => $order->total_potongan_aplikasi_override !== null,
        ];
    }
}
