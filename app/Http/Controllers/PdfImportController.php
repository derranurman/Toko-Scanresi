<?php

namespace App\Http\Controllers;

use App\Models\ComboMapping;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PdfParseDraft;
use App\Models\Variant;
use App\Services\ParsedOrderResolver;
use App\Services\TiktokLabelParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PdfImportController extends Controller
{
    public function __construct(
        private TiktokLabelParser $parser,
        private ParsedOrderResolver $resolver,
    ) {
    }

    public function show(): View
    {
        $recentDrafts = PdfParseDraft::with('user')
            ->latest()
            ->limit(10)
            ->get();

        return view('orders.import_pdf', compact('recentDrafts'));
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $path = $request->file('file')->getRealPath();

        try {
            $rawPages = $this->parser->parseFile($path);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal membaca PDF: '.$e->getMessage());
        }

        if (empty($rawPages)) {
            return back()->with('error', 'Tidak ada resi yang terdeteksi di PDF. Pastikan format label TikTok Shop / Tokopedia + J&T Express / J&T Cargo / Shopee + SPX.');
        }

        // Resolve tiap halaman ke items dengan combo/sku match
        $enriched = [];
        foreach ($rawPages as $page) {
            $resolution = $this->resolver->resolve($page);
            $enriched[] = [
                'page' => $page['page'],
                'marketplace' => $page['marketplace'] ?? 'tiktok',
                'resi_number' => $page['resi_number'],
                'tiktok_order_id' => $page['tiktok_order_id'],
                'courier' => $page['courier'],
                'buyer_name' => $page['buyer_name'],
                'buyer_phone' => $page['buyer_phone'],
                'sender_name' => $page['sender_name'] ?? null,
                'shipping_address' => $page['shipping_address'],
                'weight' => $page['weight'],
                'order_date' => $page['order_date'],
                'barang_keyword' => $page['barang_keyword'],
                'seller_note' => $page['seller_note'],
                'customer_message' => $page['customer_message'] ?? null,
                'raw_text' => $page['raw_text'] ?? null,
                'product_rows' => $page['product_rows'] ?? [],
                'items' => $resolution['items'],
                'warnings' => $resolution['warnings'],
                'matched_keyword' => $resolution['matchedKeyword'],
                'already_exists' => Order::where('resi_number', $page['resi_number'])->exists(),
            ];
        }

        $draft = PdfParseDraft::create([
            'user_id' => $request->user()->id,
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'total_pages' => count($rawPages),
            'status' => PdfParseDraft::STATUS_DRAFT,
            'parsed_orders' => $enriched,
        ]);

        return redirect()->route('orders.import.pdf.preview', $draft)
            ->with('success', 'Berhasil parse '.count($rawPages).' halaman. Cek pratinjau di bawah.');
    }

    public function preview(PdfParseDraft $draft): View
    {
        abort_if($draft->status !== PdfParseDraft::STATUS_DRAFT, 404, 'Draft ini sudah tidak aktif.');

        // Auto re-resolve setiap kali preview dibuka, supaya:
        //  - ComboMapping yang baru saja dibuat di tab lain langsung kepakai
        //  - draft lama (yang resolver-nya pakai versi kode lama) ke-refresh
        //    dengan logic matching yang sekarang
        // Operasi ini idempotent: kalau mapping & resolver tidak berubah,
        // hasilnya identik dengan parsed_orders yang sudah tersimpan.
        $this->reresolveDraft($draft);
        $draft->refresh();

        $variants = Variant::with('product')
            ->orderBy('product_id')
            ->orderBy('name')
            ->get();

        // Kumpulkan semua combo_mapping_id yang muncul di parsed_orders, lalu
        // load datanya supaya tombol "Edit Mapping" di pratinjau bisa
        // pre-fill modal tanpa fetch tambahan.
        $mappingIds = [];
        foreach ($draft->parsed_orders ?? [] as $entry) {
            foreach ($entry['items'] ?? [] as $item) {
                if (! empty($item['combo_mapping_id'])) {
                    $mappingIds[] = (int) $item['combo_mapping_id'];
                }
            }
        }
        $mappingIds = array_values(array_unique($mappingIds));

        $mappingsById = [];
        if (! empty($mappingIds)) {
            ComboMapping::with('items')
                ->whereIn('id', $mappingIds)
                ->get()
                ->each(function (ComboMapping $m) use (&$mappingsById) {
                    $mappingsById[$m->id] = [
                        'id' => $m->id,
                        'keyword' => $m->keyword,
                        'description' => $m->description,
                        'items' => $m->items->map(fn ($it) => [
                            'variant_id' => (int) $it->variant_id,
                            'quantity' => (int) $it->quantity,
                        ])->values(),
                    ];
                });
        }

        return view('orders.import_pdf_preview', compact('draft', 'variants', 'mappingsById'));
    }

    /**
     * Re-run resolver pada draft yang sudah ada, supaya combo mapping baru
     * langsung kepakai TANPA harus upload ulang PDF.
     */
    public function remap(PdfParseDraft $draft): RedirectResponse
    {
        abort_if($draft->status !== PdfParseDraft::STATUS_DRAFT, 404);

        $this->reresolveDraft($draft);

        return redirect()->route('orders.import.pdf.preview', $draft)
            ->with('success', 'Mapping disinkronisasi ulang dari data PDF yang sudah ada.');
    }

    /**
     * Buat ATAU update combo mapping dari layar pratinjau LALU langsung
     * re-resolve draft, supaya pratinjau langsung menampilkan hasil terbaru.
     *
     * - mapping_id kosong → CREATE
     * - mapping_id terisi  → UPDATE (keyword/description di-overwrite, items
     *                        diganti total)
     */
    public function quickMapping(Request $request, PdfParseDraft $draft): RedirectResponse
    {
        abort_if($draft->status !== PdfParseDraft::STATUS_DRAFT, 404);

        $mappingIdInput = $request->input('mapping_id');
        $mappingId = $mappingIdInput !== null && $mappingIdInput !== ''
            ? (int) $mappingIdInput
            : null;

        $request->validate([
            'mapping_id' => ['nullable', 'integer', 'exists:combo_mappings,id'],
            'keyword' => [
                'required', 'string', 'min:6', 'max:150',
                Rule::unique('combo_mappings', 'keyword')->ignore($mappingId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'keyword.min' => 'Keyword minimal 6 karakter. Pakai nama produk yang spesifik (jangan cuma "Default" / nama warna), supaya tidak nyangkut ke order lain.',
        ]);

        DB::transaction(function () use ($request, $mappingId) {
            if ($mappingId) {
                $mapping = ComboMapping::findOrFail($mappingId);
                $mapping->update([
                    'keyword' => $request->input('keyword'),
                    'description' => $request->input('description'),
                ]);
                // Replace-all: lebih simpel & konsisten dengan controller
                // ComboMappingController::update().
                $mapping->items()->delete();
            } else {
                $mapping = ComboMapping::create([
                    'keyword' => $request->input('keyword'),
                    'description' => $request->input('description'),
                ]);
            }

            foreach ((array) $request->input('items', []) as $it) {
                if (empty($it['variant_id'])) {
                    continue;
                }
                $mapping->items()->create([
                    'variant_id' => (int) $it['variant_id'],
                    'quantity' => max(1, (int) ($it['quantity'] ?? 1)),
                ]);
            }
        });

        $this->reresolveDraft($draft);

        $verb = $mappingId ? 'diupdate' : 'dibuat';

        return redirect()->route('orders.import.pdf.preview', $draft)
            ->with('success', 'Mapping "'.$request->input('keyword').'" '.$verb.' & langsung diterapkan ke pratinjau.');
    }

    /**
     * Jalankan ulang ParsedOrderResolver pada parsed_orders yang sudah
     * tersimpan di draft, lalu update kembali kolom JSON-nya.
     */
    private function reresolveDraft(PdfParseDraft $draft): void
    {
        $entries = $draft->parsed_orders ?? [];
        if (empty($entries)) {
            return;
        }

        foreach ($entries as $idx => $entry) {
            $resolution = $this->resolver->resolve($entry);
            $entry['items'] = $resolution['items'];
            $entry['warnings'] = $resolution['warnings'];
            $entry['matched_keyword'] = $resolution['matchedKeyword'];
            $entries[$idx] = $entry;
        }

        $draft->update(['parsed_orders' => $entries]);
    }

    public function commit(Request $request, PdfParseDraft $draft): RedirectResponse
    {
        abort_if($draft->status !== PdfParseDraft::STATUS_DRAFT, 404);

        $selectedIndexes = (array) $request->input('selected', []);
        if (empty($selectedIndexes)) {
            return back()->with('error', 'Pilih minimal 1 pesanan untuk disimpan.');
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($draft, $selectedIndexes, &$created, &$updated, &$skipped, &$errors) {
            foreach ($draft->parsed_orders as $index => $entry) {
                if (! in_array((string) $index, array_map('strval', $selectedIndexes), true)) {
                    continue;
                }

                $resi = $entry['resi_number'];
                if (! $resi) {
                    $skipped++;
                    $errors[] = "Halaman {$entry['page']}: resi tidak terdeteksi.";
                    continue;
                }

                $existing = Order::where('resi_number', $resi)->first();
                if ($existing && $existing->status === Order::STATUS_PACKED) {
                    $skipped++;
                    $errors[] = "Resi {$resi}: sudah dipacking, dilewati.";
                    continue;
                }

                $orderData = [
                    'tiktok_order_id' => $entry['tiktok_order_id'],
                    'courier' => $entry['courier'] ?: 'JNT',
                    'buyer_name' => $entry['buyer_name'],
                    'buyer_phone' => $entry['buyer_phone'],
                    'sender_name' => $entry['sender_name'] ?? null,
                    'shipping_address' => $entry['shipping_address'],
                    'order_date' => $entry['order_date'] ?: now(),
                    'status' => Order::STATUS_PENDING,
                    'notes' => $entry['seller_note'],
                ];

                if ($existing) {
                    $existing->update($orderData);
                    $existing->items()->delete();
                    $order = $existing;
                    $updated++;
                } else {
                    $order = Order::create($orderData + ['resi_number' => $resi]);
                    $created++;
                }

                foreach ($entry['items'] as $item) {
                    $variant = $item['variant_id']
                        ? Variant::with('product')->find($item['variant_id'])
                        : null;
                    $product = $variant?->product;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'variant_id' => $item['variant_id'],
                        'product_name' => $item['product_name'] ?: '—',
                        'variant_name' => $item['variant_name'],
                        'sku' => $item['sku'],
                        // Snapshot harga satuan saat import (NULL kalau item belum
                        // ter-resolve ke master → metrik fallback ke harga saat ini).
                        'selling_price' => $product ? (float) $product->selling_price : null,
                        'purchase_price' => $product ? (float) $product->purchase_price : null,
                        'reseller_price' => $product ? (float) $product->reseller_price : null,
                        'quantity' => max(1, (int) $item['quantity']),
                    ]);
                }
            }

            $draft->update(['status' => PdfParseDraft::STATUS_COMMITTED]);
        });

        if (! empty($errors)) {
            session()->flash('import_errors', $errors);
        }

        return redirect()->route('orders.index')
            ->with('success', "Import PDF selesai: {$created} baru, {$updated} diperbarui, {$skipped} dilewati.");
    }

    public function discard(PdfParseDraft $draft): RedirectResponse
    {
        $draft->update(['status' => PdfParseDraft::STATUS_DISCARDED]);

        return redirect()->route('orders.import.pdf.show')->with('success', 'Draft dibuang.');
    }

    public function destroy(PdfParseDraft $draft): RedirectResponse
    {
        $draft->delete();

        return redirect()->route('orders.import.pdf.show')->with('success', 'Draft dihapus permanen.');
    }

    public function destroyAll(): RedirectResponse
    {
        $count = PdfParseDraft::count();
        PdfParseDraft::query()->delete();

        $message = $count > 0
            ? "Semua draft dihapus permanen ({$count} item)."
            : 'Tidak ada draft untuk dihapus.';

        return redirect()->route('orders.import.pdf.show')->with('success', $message);
    }
}
