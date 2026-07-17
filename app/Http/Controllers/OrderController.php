<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PlatformDeduction;
use App\Models\Product;
use App\Models\Variant;
use App\Services\OrderMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(private OrderMetricsService $metrics)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $date = $request->query('date');

        $orders = Order::with(['items.variant.product', 'packedBy', 'platformDeduction'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('resi_number', 'like', "%{$q}%")
                        ->orWhere('tiktok_order_id', 'like', "%{$q}%")
                        ->orWhere('buyer_name', 'like', "%{$q}%")
                        ->orWhere('host_live', 'like', "%{$q}%")
                        ->orWhere('sender_name', 'like', "%{$q}%");
                });
            })
            ->when($status, fn ($qq) => $qq->where('status', $status))
            // Filter tanggal: cocokkan order_date (tanggal dari label, sering
            // null / beda hari) ATAU created_at (tanggal upload/input order).
            // Tanpa fallback created_at, order yang baru diupload hari ini tapi
            // order_date-nya null/beda tidak akan muncul saat difilter per tgl.
            ->when($date, fn ($qq) => $qq->where(function ($w) use ($date) {
                $w->whereDate('order_date', $date)
                    ->orWhereDate('created_at', $date);
            }))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Hitung metrik per order sekarang, kirim sebagai parallel array ke view.
        $metrics = [];
        foreach ($orders as $order) {
            $metrics[$order->id] = $this->metrics->compute($order);
        }

        $platforms = PlatformDeduction::where('is_active', true)
            ->orderBy('platform_name')
            ->get(['id', 'platform_name']);

        // Index awal untuk kolom "No" — mulai dari halaman saat ini
        $startNo = ($orders->currentPage() - 1) * $orders->perPage() + 1;

        return view('orders.index', compact(
            'orders',
            'metrics',
            'platforms',
            'startNo',
            'q',
            'status',
            'date',
        ));
    }

    public function show(Order $order): View
    {
        $order->load(['items.variant.product', 'packedBy', 'platformDeduction']);

        return view('orders.show', [
            'order' => $order,
            'metric' => $this->metrics->compute($order),
        ]);
    }

    /**
     * Export semua data tabel pesanan ke CSV (dibuka di Excel).
     * Mengikuti filter yang sama dengan index().
     */
    public function export(Request $request): StreamedResponse
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $date = $request->query('date');

        $orders = Order::with(['items.variant.product', 'packedBy', 'platformDeduction'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('resi_number', 'like', "%{$q}%")
                        ->orWhere('tiktok_order_id', 'like', "%{$q}%")
                        ->orWhere('buyer_name', 'like', "%{$q}%")
                        ->orWhere('host_live', 'like', "%{$q}%")
                        ->orWhere('sender_name', 'like', "%{$q}%");
                });
            })
            ->when($status, fn ($qq) => $qq->where('status', $status))
            // Filter tanggal: cocokkan order_date (tanggal dari label, sering
            // null / beda hari) ATAU created_at (tanggal upload/input order).
            // Tanpa fallback created_at, order yang baru diupload hari ini tapi
            // order_date-nya null/beda tidak akan muncul saat difilter per tgl.
            ->when($date, fn ($qq) => $qq->where(function ($w) use ($date) {
                $w->whereDate('order_date', $date)
                    ->orWhereDate('created_at', $date);
            }))
            ->latest('id')
            ->get();

        $filename = 'pesanan-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            // BOM agar Excel mengenali UTF-8 dengan benar.
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header — persis sama dengan kolom di halaman /orders.
            fputcsv($handle, [
                'No',
                'Tanggal',
                'Resi',
                'Order ID',
                'Host Live',
                'Platform',
                'Pengirim',
                'Pembeli',
                'No. HP',
                'Nama Barang',
                'SKU',
                'Harga Jual',
                'Total Jual',
                'Total Modal',
                'Total Reseller',
                'Ongkir Cargo',
                'Yield',
                'Label (Rp)',
                'Plastik/Dus',
                'Operasional',
                'ADM (%)',
                'ADM (Rp)',
                'Ongkir Free (%)',
                'Ongkir Free (Rp)',
                'Bulat Max 650Rb',
                'Biaya Layanan (Rp)',
                'Biaya Logistik (Rp)',
                'Pajak (%)',
                'Pajak (Rp)',
                'Profit Kotor',
                '% Profit Kotor',
                'Margin Bisnis',
                '% Margin Bisnis',
                'Margin Live',
                '% Margin Live',
                'Bersih Margin Live',
                'TOTAL POTONGAN APLIKASI',
                'Status',
            ], ';');

            $no = 1;
            // Format angka dengan koma sebagai pemisah desimal (tanpa pemisah
            // ribuan) supaya Excel locale Indonesia membacanya sebagai ANGKA,
            // bukan menafsirkan titik sebagai pemisah ribuan (mis. -3.66 → -3,66).
            $num = fn ($v) => number_format((float) $v, 2, ',', '');
            foreach ($orders as $order) {
                $m = $this->metrics->compute($order);

                $skus = $order->items->pluck('sku')->filter()->unique()->implode(', ');
                $names = $order->items
                    ->map(function ($it) {
                        // Master product name kalau ada, fallback ke snapshot di order_items.
                        $name = $it->variant?->product?->name ?? $it->product_name;
                        if ($it->variant?->name) {
                            $name = trim(($name ?? '—') . ' — ' . $it->variant->name);
                        }
                        return $name;
                    })
                    ->filter()
                    ->unique()
                    ->implode(', ');
                $hargaJual = (float) ($order->items->first()?->variant?->product?->selling_price ?? 0);

                fputcsv($handle, [
                    $no++,
                    $order->created_at ? $order->created_at->format('d/m/Y') : '—',
                    $order->resi_number,
                    $order->tiktok_order_id,
                    $order->host_live ?? '—',
                    $order->platformDeduction?->platform_name ?? '—',
                    $order->sender_name ?? '—',
                    $order->buyer_name ?? '—',
                    $order->buyer_phone ?? '—',
                    $names ?: '—',
                    $skus ?: '—',
                    $num($hargaJual),
                    $num($m['total_jual']),
                    $num($m['total_modal']),
                    $num($m['total_reseller']),
                    $num($m['ongkir_cargo']),
                    $num($m['yield_rp']),
                    $num($m['label']),
                    $num($m['plastik_dus']),
                    $num($m['operasional_rp']),
                    $num($m['adm_pct']),
                    $num($m['adm_rp']),
                    $num($m['ongkir_free_pct']),
                    $num($m['ongkir_free_rp']),
                    $num($m['bulat_max']),
                    $num($m['biaya_layanan']),
                    $num($m['biaya_logistik']),
                    $num($m['pajak_pct']),
                    $num($m['pajak_rp']),
                    $num($m['profit_kotor']),
                    $num($m['pct_profit_kotor']),
                    $num($m['margin_bisnis']),
                    $num($m['pct_margin_bisnis']),
                    $num($m['margin_live']),
                    $num($m['pct_margin_live']),
                    $num($m['bersih_margin_live']),
                    number_format((float) $m['total_potongan_aplikasi'], 0, ',', '.'),
                    ucfirst($order->status),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export daftar pesanan ke PDF (print-friendly view).
     *
     * Mengikuti pola yang sama dengan Laporan Packing: render Blade
     * print-friendly yang otomatis memanggil `window.print()` saat
     * dimuat, sehingga user bisa pilih "Save as PDF" sebagai
     * destination di dialog cetak browser tanpa perlu library
     * tambahan (mis. dompdf). Filter sama persis dengan index().
     */
    public function exportPdf(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $date = $request->query('date');

        $orders = Order::with(['items.variant.product', 'packedBy', 'platformDeduction'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('resi_number', 'like', "%{$q}%")
                        ->orWhere('tiktok_order_id', 'like', "%{$q}%")
                        ->orWhere('buyer_name', 'like', "%{$q}%")
                        ->orWhere('host_live', 'like', "%{$q}%")
                        ->orWhere('sender_name', 'like', "%{$q}%");
                });
            })
            ->when($status, fn ($qq) => $qq->where('status', $status))
            // Filter tanggal: cocokkan order_date (tanggal dari label, sering
            // null / beda hari) ATAU created_at (tanggal upload/input order).
            // Tanpa fallback created_at, order yang baru diupload hari ini tapi
            // order_date-nya null/beda tidak akan muncul saat difilter per tgl.
            ->when($date, fn ($qq) => $qq->where(function ($w) use ($date) {
                $w->whereDate('order_date', $date)
                    ->orWhereDate('created_at', $date);
            }))
            ->latest('id')
            ->get();

        $metrics = [];
        foreach ($orders as $order) {
            $metrics[$order->id] = $this->metrics->compute($order);
        }

        return view('orders.index_pdf', [
            'orders'  => $orders,
            'metrics' => $metrics,
            'q'       => $q,
            'status'  => $status,
            'date'    => $date,
        ]);
    }

    public function create(): View
    {
        $platforms = PlatformDeduction::where('is_active', true)
            ->orderBy('platform_name')
            ->get(['id', 'platform_name']);

        $products = Product::with('variants')->where('is_active', true)->get();

        return view('orders.create', compact('platforms', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateOrder($request);
        if (($data['total_potongan_aplikasi_override'] ?? null) === '') {
            $data['total_potongan_aplikasi_override'] = null;
        }

        $order = Order::create($data);

        // Save order items based on kelengkapan selection
        $this->saveOrderItems($request, $order);

        return redirect()->route('orders.index')
            ->with('success', "Pesanan {$order->resi_number} berhasil dibuat.");
    }

    public function edit(Order $order): View
    {
        $order->load('items.variant.product');

        $platforms = PlatformDeduction::where('is_active', true)
            ->orderBy('platform_name')
            ->get(['id', 'platform_name']);

        $products = Product::with('variants')->where('is_active', true)->get();

        return view('orders.edit', compact('order', 'platforms', 'products'));
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $this->validateOrder($request, $order);
        if (! $request->filled('total_potongan_aplikasi_override')) {
            $data['total_potongan_aplikasi_override'] = null;
        }

        $order->update($data);

        // Re-save order items based on kelengkapan selection
        $this->saveOrderItems($request, $order);

        return redirect()->route('orders.index')
            ->with('success', "Pesanan {$order->resi_number} berhasil diperbarui.");
    }

    public function destroy(Order $order): RedirectResponse
    {
        if ($order->status === Order::STATUS_PACKED) {
            return back()->with('error', 'Pesanan yang sudah di-packing tidak bisa dihapus.');
        }

        $order->delete();

        return redirect()->route('orders.index')->with('success', 'Pesanan dihapus.');
    }

    /**
     * Hapus banyak pesanan sekaligus berdasarkan ID yang dicentang
     * di halaman daftar Pesanan. Pesanan yang sudah di-packing
     * akan dilewati (tidak ikut dihapus) untuk menjaga integritas
     * data laporan packing.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders = Order::whereIn('id', $data['ids'])->get();

        $deleted = 0;
        $skipped = 0;
        foreach ($orders as $order) {
            if ($order->status === Order::STATUS_PACKED) {
                $skipped++;
                continue;
            }
            $order->delete();
            $deleted++;
        }

        $msg = "{$deleted} pesanan berhasil dihapus.";
        if ($skipped > 0) {
            $msg .= " {$skipped} pesanan dilewati karena sudah di-packing.";
        }

        return redirect()
            ->route('orders.index', $request->query())
            ->with($deleted > 0 ? 'success' : 'error', $msg);
    }

    /**
     * Update status pesanan secara inline (tanpa hapus).
     *
     * Side effect: ketika status berubah ke `return` atau `selesai_return`,
     * `returned_at` di-auto-set ke `now()` jika masih null. Ini supaya
     * pesanan yang di-set Return via dropdown inline (bukan via halaman
     * Kelola Return → markReturn) tetap muncul di Laporan Return — yang
     * memfilter berdasarkan `returned_at` per bulan.
     *
     * `returned_at` SENGAJA TIDAK di-clear ketika status pindah keluar
     * dari grup return — supaya histori tidak hilang. Untuk benar-benar
     * "membatalkan" return (clear `returned_at`), pakai endpoint
     * undoReturn atau Hapus di Laporan Return.
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
        ]);

        $update = ['status' => $data['status']];
        $isReturnGroup = in_array($data['status'], [
            Order::STATUS_RETURN,
            Order::STATUS_SELESAI_RETURN,
        ], true);
        if ($isReturnGroup && $order->returned_at === null) {
            $update['returned_at'] = now();
        }

        $order->update($update);

        return back()->with('success', "Status pesanan {$order->resi_number} diubah menjadi {$data['status']}.");
    }

    /**
     * Update inline Host Live, Platform, Pengirim, Pembeli, & No. HP
     * dari halaman Pesanan. Field bersifat opsional supaya satu form
     * bisa update kolom apa saja yang dia render.
     */
    public function updateMeta(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'host_live' => ['nullable', 'string', 'max:100'],
            'platform_deduction_id' => ['nullable', 'integer', 'exists:platform_deductions,id'],
            'sender_name' => ['nullable', 'string', 'max:150'],
            'buyer_name' => ['nullable', 'string', 'max:150'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
        ]);

        // Hanya update field yang BENAR-BENAR dikirim oleh form inline
        // (mis. form Pembeli cuma mengirim buyer_name). Pakai `has()` supaya
        // kolom lain tidak ke-overwrite jadi null gara-gara absen di request.
        $allowed = ['host_live', 'platform_deduction_id', 'sender_name', 'buyer_name', 'buyer_phone'];
        $data = [];
        foreach ($allowed as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                // String kosong → null supaya kolom yang nullable tetap konsisten.
                $data[$field] = ($value === '' ? null : $value);
            }
        }

        if (! empty($data)) {
            $order->update($data);
        }

        return back()->with('success', "Pesanan {$order->resi_number} diperbarui.");
    }

    /**
     * Update inline Total Potongan Aplikasi (override manual).
     * Kosongkan field untuk reset ke hitungan otomatis.
     */
    public function updatePotongan(Request $request, Order $order): RedirectResponse
    {
        // Normalisasi input: buang pemisah ribuan (titik) supaya nilai
        // seperti "79.000" terbaca sebagai 79000, bukan 79.
        $raw = $request->input('total_potongan_aplikasi_override');
        $normalized = is_string($raw) ? str_replace('.', '', trim($raw)) : $raw;
        $request->merge(['total_potongan_aplikasi_override' => $normalized]);

        $request->validate([
            'total_potongan_aplikasi_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $value = ($normalized === null || $normalized === '') ? null : (float) $normalized;

        $order->update(['total_potongan_aplikasi_override' => $value]);

        $msg = $value === null
            ? "Total Potongan Aplikasi {$order->resi_number} di-reset ke hitungan otomatis."
            : "Total Potongan Aplikasi {$order->resi_number} di-set ke Rp " . number_format($value, 0, ',', '.');

        return back()->with('success', $msg);
    }

    /**
     * Shared validation untuk create & update order.
     *
     * @return array<string, mixed>
     */
    private function validateOrder(Request $request, ?Order $existing = null): array
    {
        $resiRule = [
            'required', 'string', 'max:32',
            Rule::unique('orders', 'resi_number')->ignore($existing?->id),
        ];

        return $request->validate([
            'resi_number' => $resiRule,
            'tiktok_order_id' => ['nullable', 'string', 'max:64'],
            'courier' => ['nullable', 'string', 'max:20'],
            'buyer_name' => ['nullable', 'string', 'max:150'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'sender_name' => ['nullable', 'string', 'max:150'],
            'host_live' => ['nullable', 'string', 'max:100'],
            'platform_deduction_id' => ['nullable', 'integer', 'exists:platform_deductions,id'],
            'shipping_address' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(Order::STATUSES)],
            'order_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'total_potongan_aplikasi_override' => ['nullable', 'numeric', 'min:0'],
            'kelengkapan' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:variants,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * Simpan order items berdasarkan pilihan kelengkapan.
     *
     * Kelengkapan codes:
     *   1 = Stir Saja
     *   2 = Stir + Boskit
     *   3 = Boskit Saja
     *   4 = Spoiler
     *   5 = Klakson
     *   6 = Stir + Stir
     *   7 = Stir + Stir + Boskit
     *   8 = Stir + Boskit + Boskit
     */
    private function saveOrderItems(Request $request, Order $order): void
    {
        // Tag kelengkapan (opsional) — hanya penanda untuk laporan, tidak wajib.
        $kelengkapan = $request->input('kelengkapan');
        $kelengkapanTag = ($kelengkapan !== null && $kelengkapan !== '') ? (string) $kelengkapan : null;

        // =================================================================
        // PATH BARU: daftar barang bebas (bisa cari & pilih barang apa saja).
        // Form mengirim items[][variant_id] + items[][quantity]; tiap baris
        // punya qty sendiri. Menggantikan pemilihan berbasis "kelengkapan"
        // yang kaku (slot stir_1 / boskit_1 / dst.).
        // =================================================================
        $items = $request->input('items');
        if (is_array($items)) {
            $clean = [];
            foreach ($items as $row) {
                $variantId = is_array($row) ? ($row['variant_id'] ?? null) : null;
                if (! $variantId) {
                    continue;
                }
                $clean[] = [
                    'variant_id' => (int) $variantId,
                    'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                ];
            }

            if (! empty($clean)) {
                // Ganti total item lama (skenario update).
                $order->items()->delete();

                foreach ($clean as $row) {
                    $variant = Variant::with('product')->find($row['variant_id']);
                    if (! $variant) {
                        continue;
                    }

                    $qty = $row['quantity'];
                    $purchasePrice = (float) ($variant->product->purchase_price ?? 0);

                    $order->items()->create([
                        'variant_id' => $variant->id,
                        'product_name' => $variant->product->name,
                        'variant_name' => $variant->name,
                        'sku' => $variant->sku,
                        'kelengkapan' => $kelengkapanTag,
                        'harga_modal' => $purchasePrice * $qty,
                        'selling_price' => (float) ($variant->product->selling_price ?? 0),
                        'purchase_price' => $purchasePrice,
                        'reseller_price' => (float) ($variant->product->reseller_price ?? 0),
                        'quantity' => $qty,
                    ]);
                }

                return;
            }
        }

        // =================================================================
        // PATH LAMA (fallback): pemilihan berbasis kelengkapan. Dipertahankan
        // untuk backward-compat kalau ada form yang belum kirim items[].
        // =================================================================
        if (! $kelengkapanTag) {
            return;
        }
        $kelengkapan = $kelengkapanTag;

        // Mapping code -> array of request input field names (sinkron dengan form).
        $fieldMap = [
            '1' => ['variant_stir_1'],
            '2' => ['variant_stir_1', 'variant_boskit_1'],
            '3' => ['variant_boskit_1'],
            '4' => ['variant_spoiler'],
            '5' => ['variant_klakson'],
            '6' => ['variant_stir_1', 'variant_stir_2'],
            '7' => ['variant_stir_1', 'variant_stir_2', 'variant_boskit_1'],
            '8' => ['variant_stir_1', 'variant_boskit_1', 'variant_boskit_2'],
        ];

        if (! isset($fieldMap[$kelengkapan])) {
            return;
        }

        $qty = max(1, (int) $request->input('item_quantity', 1));

        // Ambil semua variant ID yang dibutuhkan dari request.
        $variantIds = [];
        foreach ($fieldMap[$kelengkapan] as $fieldName) {
            $id = $request->input($fieldName);
            if ($id) {
                $variantIds[] = $id;
            }
        }

        if (empty($variantIds)) {
            return;
        }

        // Remove existing items (untuk skenario update).
        $order->items()->delete();

        foreach ($variantIds as $variantId) {
            $variant = Variant::with('product')->find($variantId);
            if (! $variant) {
                continue;
            }

            $purchasePrice = (float) ($variant->product->purchase_price ?? 0);

            $order->items()->create([
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                // Simpan CODE agar bisa restore dropdown saat edit.
                'kelengkapan' => (string) $kelengkapan,
                'harga_modal' => $purchasePrice * $qty,
                // Snapshot harga satuan saat pesanan dibuat (lihat OrderMetricsService).
                'selling_price' => (float) ($variant->product->selling_price ?? 0),
                'purchase_price' => $purchasePrice,
                'reseller_price' => (float) ($variant->product->reseller_price ?? 0),
                'quantity' => $qty,
            ]);
        }
    }
}
