<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use App\Services\OrderMetricsService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function __construct(
        private OrderMetricsService $metrics,
        private StockService $stockService,
    ) {
    }

    /**
     * Kelola Return — tampilkan pesanan yang statusnya "return"
     * (yaitu yang masih perlu ditangani / belum diterima kembali).
     */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $orders = Order::with(['items.variant.product', 'platformDeduction'])
            ->where('status', Order::STATUS_RETURN)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('resi_number', 'like', "%{$q}%")
                        ->orWhere('buyer_name', 'like', "%{$q}%")
                        ->orWhere('tiktok_order_id', 'like', "%{$q}%");
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $metrics = [];
        foreach ($orders as $order) {
            $metrics[$order->id] = $this->metrics->compute($order);
        }

        // Produk aktif untuk dropdown "Tambah Return Manual".
        $products = Product::with('variants')->where('is_active', true)->orderBy('name')->get();

        return view('returns.index', compact('orders', 'metrics', 'q', 'products'));
    }

    /**
     * Tandai pesanan sebagai return dari halaman Kelola Return.
     */
    public function markReturn(Request $request): RedirectResponse
    {
        $request->validate([
            'resi_number' => ['required', 'string'],
        ]);

        $order = Order::where('resi_number', $request->input('resi_number'))->first();

        if (! $order) {
            return back()->with('error', 'Pesanan dengan resi tersebut tidak ditemukan.');
        }

        $order->update([
            'status' => Order::STATUS_RETURN,
            // returned_at = penanda permanen pesanan pernah di-return.
            // Hanya di-set jika belum pernah ada (supaya tanggal awal tetap).
            'returned_at' => $order->returned_at ?? now(),
            'notes' => trim($order->notes . "\n[RETURN] " . ($request->input('reason') ?? 'Tanpa alasan') . ' — ' . now()->format('d/m/Y H:i')),
        ]);

        return back()->with('success', "Pesanan {$order->resi_number} ditandai sebagai Return.");
    }

    /**
     * Tambah Return Manual.
     *
     * Buat data return baru dari nol untuk barang yang BELUM tercatat
     * sebagai pesanan (mis. retur fisik yang tidak punya order asal).
     * Menghasilkan Order baru berstatus "return" + 1 OrderItem dengan
     * snapshot harga, supaya langsung muncul di Kelola Return dan ikut
     * terhitung di Laporan Return.
     */
    public function storeManual(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'resi_number' => ['nullable', 'string', 'max:32', Rule::unique('orders', 'resi_number')],
            'buyer_name' => ['nullable', 'string', 'max:150'],
            'variant_id' => ['required', 'integer', 'exists:variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [], [
            'variant_id' => 'produk/varian',
            'quantity' => 'jumlah',
        ]);

        $variant = Variant::with('product')->find($data['variant_id']);
        if (! $variant || ! $variant->product) {
            return back()->with('error', 'Varian produk tidak valid.')->withInput();
        }

        $qty = (int) $data['quantity'];
        $product = $variant->product;

        // Resi otomatis kalau dikosongkan. Format: RTN-YYYYMMDD-HHMMSS.
        $resi = trim((string) ($data['resi_number'] ?? '')) ?: 'RTN-' . now()->format('Ymd-His');

        $reason = trim((string) ($data['reason'] ?? '')) ?: 'Tanpa alasan';

        $order = Order::create([
            'resi_number' => $resi,
            'buyer_name' => $data['buyer_name'] ?? null,
            'status' => Order::STATUS_RETURN,
            'order_date' => now(),
            'returned_at' => now(),
            'notes' => '[RETURN MANUAL] ' . $reason . ' — ' . now()->format('d/m/Y H:i'),
        ]);

        $order->items()->create([
            'variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => $variant->name,
            'sku' => $variant->sku,
            'harga_modal' => (float) $product->purchase_price * $qty,
            // Snapshot harga satuan saat return dibuat.
            'selling_price' => (float) $product->selling_price,
            'purchase_price' => (float) $product->purchase_price,
            'reseller_price' => (float) $product->reseller_price,
            'quantity' => $qty,
        ]);

        return back()->with('success', "Return manual {$resi} ({$product->name} × {$qty}) berhasil ditambahkan.");
    }

    /**
     * Batal return: kembalikan pesanan ke status pending dan
     * hapus penanda returned_at (return jadi tidak pernah terjadi).
     */
    public function undoReturn(Order $order): RedirectResponse
    {
        if ($order->status !== Order::STATUS_RETURN) {
            return back()->with('error', 'Pesanan ini tidak dalam status Return.');
        }

        $order->update([
            'status' => Order::STATUS_PENDING,
            'returned_at' => null,
        ]);

        return back()->with('success', "Pesanan {$order->resi_number} dikembalikan ke Pending.");
    }

    /**
     * Tandai bahwa barang return SUDAH DITERIMA kembali oleh toko.
     * Otomatis menambah stok untuk setiap item, lalu set status jadi
     * "selesai_return". `returned_at` SENGAJA TIDAK DIHAPUS supaya
     * pesanan tetap muncul di Laporan Return sebagai histori.
     *
     * Catatan: status "selesai_return" terpisah dari
     * "selesai_bulan_kemarin" (untuk pesanan lintas bulan yang sudah
     * tutup buku) supaya histori return bisa dibedakan dengan jelas
     * dari pesanan biasa yang sudah selesai.
     */
    public function receiveItems(Request $request, Order $order): RedirectResponse
    {
        if ($order->status !== Order::STATUS_RETURN) {
            return back()->with('error', 'Pesanan ini tidak dalam status Return.');
        }

        $order->load('items.variant');

        $totalRestocked = 0;
        $skipped = [];

        foreach ($order->items as $item) {
            if (! $item->variant) {
                $skipped[] = $item->sku ?? $item->product_name;
                continue;
            }

            $this->stockService->adjust(
                variant: $item->variant,
                qty: (int) $item->quantity,
                type: \App\Models\StockMovement::TYPE_IN,
                userId: auth()->id(),
                orderId: $order->id,
                reference: "Return diterima — Resi {$order->resi_number}",
            );

            $totalRestocked += (int) $item->quantity;
        }

        // Pindahkan status, TAPI biarkan returned_at agar tetap tercatat
        // di Laporan Return sebagai histori barang yang pernah di-return.
        //
        // Safety net: jika `returned_at` null (mis. order ini tadinya
        // di-set status='return' via dropdown inline tanpa lewat markReturn),
        // set sekarang supaya tetap muncul di Laporan Return.
        $order->update([
            'status' => Order::STATUS_SELESAI_RETURN,
            'returned_at' => $order->returned_at ?? now(),
            'notes' => trim($order->notes . "\n[BARANG DITERIMA] " . now()->format('d/m/Y H:i') . " — {$totalRestocked} unit dikembalikan ke stok"),
        ]);

        $msg = "Barang return resi {$order->resi_number} diterima. {$totalRestocked} unit dikembalikan ke stok.";
        if (! empty($skipped)) {
            $msg .= ' (Item tanpa variant terkait dilewati: ' . implode(', ', $skipped) . ')';
        }

        return redirect()->route('returns.index')->with('success', $msg);
    }
}
