<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class OrderImportController extends Controller
{
    /**
     * Expected CSV columns (header row, case-insensitive; flexible):
     *  tiktok_order_id, resi_number, courier, buyer_name, buyer_phone,
     *  shipping_address, order_date, product_name, variant_name, sku, quantity
     *
     * Satu baris = satu item. Baris dengan resi_number yang sama akan
     * digabung menjadi satu Order dengan banyak OrderItem.
     */
    public function show(): View
    {
        return view('orders.import');
    }

    public function template(): StreamedResponse
    {
        $headers = [
            'tiktok_order_id', 'resi_number', 'courier', 'buyer_name', 'buyer_phone',
            'shipping_address', 'order_date', 'product_name', 'variant_name', 'sku', 'quantity',
        ];

        $rows = [
            ['TT123456', 'JP0000000100', 'JNT', 'Budi', '08123456789', 'Jakarta', '2026-05-09', 'Stir Skeleton', 'Merah', 'STIR-SKL-RED', '1'],
            ['TT123457', 'JP0000000101', 'JNT', 'Siti', '08123456781', 'Bandung', '2026-05-09', 'Stir Skeleton', 'Hitam', 'STIR-SKL-BLK', '1'],
            ['TT123457', 'JP0000000101', 'JNT', 'Siti', '08123456781', 'Bandung', '2026-05-09', 'Boskit Motor', 'Standar', 'BSK-MTR-STD', '1'],
        ];

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, $r);
            }
            fclose($out);
        }, 'template_import_tiktok.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            return back()->with('error', 'Gagal membuka file.');
        }

        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delim = str_contains($firstLine, ';') && ! str_contains($firstLine, ',') ? ';' : ',';

        $header = fgetcsv($handle, 0, $delim);
        if (! $header) {
            fclose($handle);
            return back()->with('error', 'File kosong atau tidak valid.');
        }

        $map = [];
        foreach ($header as $i => $h) {
            $map[strtolower(trim($h))] = $i;
        }

        $required = ['resi_number', 'quantity'];
        foreach ($required as $r) {
            if (! array_key_exists($r, $map)) {
                fclose($handle);
                return back()->with('error', "Kolom '{$r}' tidak ditemukan di CSV.");
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        // Kelompokkan row per resi number
        $buckets = [];
        $lineNo = 1;
        while (($row = fgetcsv($handle, 0, $delim)) !== false) {
            $lineNo++;
            if (count($row) === 1 && trim($row[0]) === '') {
                continue;
            }
            $val = function (string $key) use ($row, $map) {
                return isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : null;
            };

            $resi = $val('resi_number');
            if (! $resi) {
                $skipped++;
                $errors[] = "Baris {$lineNo}: resi_number kosong.";
                continue;
            }

            $buckets[$resi][] = [
                'tiktok_order_id' => $val('tiktok_order_id'),
                'resi_number' => $resi,
                'courier' => $val('courier') ?: 'JNT',
                'buyer_name' => $val('buyer_name'),
                'buyer_phone' => $val('buyer_phone'),
                'shipping_address' => $val('shipping_address'),
                'order_date' => $val('order_date'),
                'product_name' => $val('product_name'),
                'variant_name' => $val('variant_name'),
                'sku' => $val('sku'),
                'quantity' => (int) ($val('quantity') ?: 1),
                '_line' => $lineNo,
            ];
        }
        fclose($handle);

        DB::transaction(function () use ($buckets, &$created, &$updated, &$skipped, &$errors) {
            foreach ($buckets as $resi => $rows) {
                $first = $rows[0];

                $order = Order::where('resi_number', $resi)->first();

                if ($order && $order->status === Order::STATUS_PACKED) {
                    $skipped += count($rows);
                    $errors[] = "Resi {$resi}: sudah dipacking, dilewati.";
                    continue;
                }

                $orderData = [
                    'tiktok_order_id' => $first['tiktok_order_id'],
                    'courier' => $first['courier'],
                    'buyer_name' => $first['buyer_name'],
                    'buyer_phone' => $first['buyer_phone'],
                    'shipping_address' => $first['shipping_address'],
                    'order_date' => $first['order_date'] ?: now(),
                    'status' => Order::STATUS_PENDING,
                ];

                if ($order) {
                    $order->update($orderData);
                    $order->items()->delete();
                    $updated++;
                } else {
                    $order = Order::create($orderData + ['resi_number' => $resi]);
                    $created++;
                }

                foreach ($rows as $row) {
                    $variant = null;
                    if ($row['sku']) {
                        $variant = Variant::with('product')->where('sku', $row['sku'])->first();
                    }

                    $product = $variant?->product;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'variant_id' => $variant?->id,
                        'product_name' => $row['product_name'] ?: ($variant?->product?->name ?? '—'),
                        'variant_name' => $row['variant_name'] ?: $variant?->name,
                        'sku' => $row['sku'] ?: $variant?->sku,
                        // Snapshot harga satuan saat import (NULL kalau SKU tak cocok
                        // master → metrik fallback ke harga Product saat ini).
                        'selling_price' => $product ? (float) $product->selling_price : null,
                        'purchase_price' => $product ? (float) $product->purchase_price : null,
                        'reseller_price' => $product ? (float) $product->reseller_price : null,
                        'quantity' => max(1, (int) $row['quantity']),
                    ]);

                    if (! $variant) {
                        $errors[] = "Resi {$resi}: SKU '{$row['sku']}' tidak ditemukan di master produk.";
                    }
                }
            }
        });

        $msg = "Import selesai: {$created} pesanan baru, {$updated} diperbarui, {$skipped} dilewati.";
        if (! empty($errors)) {
            session()->flash('import_errors', $errors);
        }

        return back()->with('success', $msg);
    }
}
