<?php

namespace App\Http\Controllers;

use App\Models\PackingLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class PackingReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to, $userId, $type, $resi] = $this->filters($request);

        $logQuery = PackingLog::with(['user', 'order.items.variant.product'])
            ->whereBetween('scanned_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($resi, fn ($q) => $q->where('resi_number', 'like', '%'.$resi.'%'))
            ->when($type, fn ($q) => $q->whereHas(
                'order.items.variant.product',
                fn ($qq) => $qq->where('type', $type)
            ));

        // Detail scan (dipaginate). Filter type sudah di-apply di whereHas, jadi
        // hanya scan yang punya minimal 1 item dengan jenis tsb yang muncul.
        $logs = (clone $logQuery)
            ->latest('scanned_at')
            ->paginate(30)
            ->withQueryString();

        // Ringkasan per user. Kalau filter type aktif, totalnya dihitung dari
        // item yang COCOK saja (bukan dari kolom snapshot total_items log,
        // karena log tidak tahu jenis barang).
        if ($type) {
            $summary = $this->summaryByType((clone $logQuery)->get(), $type);
        } else {
            $summary = PackingLog::selectRaw('user_id, COUNT(*) as total_orders, SUM(total_items) as total_items, SUM(distinct_skus) as total_distinct')
                ->with('user:id,name')
                ->whereBetween('scanned_at', [$from, $to])
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->groupBy('user_id')
                ->orderByDesc('total_items')
                ->get();
        }

        // Rincian jumlah barang ter-scan keluar per JENIS barang, per user.
        // Mengikuti filter tanggal/user/jenis yang sama dengan ringkasan.
        $typeBreakdown = $this->breakdownByType((clone $logQuery)->get(), $type);

        $users = User::where('role', User::ROLE_PACKING)->orWhere('role', User::ROLE_ADMIN)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Daftar jenis barang unik dari master Product. Dipakai untuk dropdown
        // filter; cukup ambil yang non-empty supaya tidak ada pilihan kosong.
        $types = Product::whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type');

        return view('reports.packing', [
            'summary' => $summary,
            'typeBreakdown' => $typeBreakdown,
            'logs' => $logs,
            'users' => $users,
            'types' => $types,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'userId' => $userId,
            'type' => $type,
            'resi' => $resi,
        ]);
    }

    /**
     * Export CSV mengikuti tabel Detail Scan: 1 baris per scan (bukan per
     * item). Kolom Item (Kelengkapan) berisi semua item yang ke-pack pada
     * scan tersebut, dipisahkan newline supaya saat dibuka di Excel dengan
     * Wrap Text aktif tampilan-nya sama dengan layar.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to, $userId, $type, $resi] = $this->filters($request);

        $logs = PackingLog::with(['user', 'order.items.variant.product'])
            ->whereBetween('scanned_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($resi, fn ($q) => $q->where('resi_number', 'like', '%'.$resi.'%'))
            ->when($type, fn ($q) => $q->whereHas(
                'order.items.variant.product',
                fn ($qq) => $qq->where('type', $type)
            ))
            ->orderBy('scanned_at')
            ->get();

        $filename = "laporan_packing_{$from->format('Ymd')}_{$to->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');

            // BOM agar Excel paham UTF-8 (nama produk bisa pakai karakter
            // khusus seperti em-dash atau koma).
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'Waktu',
                'User',
                'Resi',
                'Pengirim',
                'Order ID',
                'Item (Kelengkapan)',
                'Qty',
            ], ';');

            foreach ($logs as $log) {
                $order = $log->order;
                $itemsText = '';
                $totalQty = (int) $log->total_items;

                if ($order) {
                    $lines = [];
                    foreach ($order->items as $item) {
                        $name = trim(($item->product_name ?? '—').' — '.($item->variant_name ?? '—'), ' —');
                        $sku = $item->sku ? " [{$item->sku}]" : '';
                        $lines[] = "{$item->quantity}× {$name}{$sku}";
                    }
                    $itemsText = implode("\n", $lines);
                }

                fputcsv($out, [
                    $log->scanned_at->format('Y-m-d H:i:s'),
                    $log->user?->name ?? '—',
                    $log->resi_number,
                    $order?->sender_name ?? '—',
                    $order->tiktok_order_id ?? '—',
                    $itemsText,
                    $totalQty,
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export PDF mengikuti tampilan layar (Ringkasan + Detail Scan).
     * Implementasi pakai print-friendly Blade view yang otomatis memanggil
     * `window.print()` saat dimuat, sehingga user bisa "Save as PDF" dari
     * dialog cetak browser tanpa perlu library tambahan.
     */
    public function exportPdf(Request $request): View
    {
        [$from, $to, $userId, $type, $resi] = $this->filters($request);

        $logQuery = PackingLog::with(['user', 'order.items.variant.product', 'order.platformDeduction'])
            ->whereBetween('scanned_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($resi, fn ($q) => $q->where('resi_number', 'like', '%'.$resi.'%'))
            ->when($type, fn ($q) => $q->whereHas(
                'order.items.variant.product',
                fn ($qq) => $qq->where('type', $type)
            ));

        $logs = (clone $logQuery)->orderBy('scanned_at')->get();

        if ($type) {
            $summary = $this->summaryByType((clone $logQuery)->get(), $type);
        } else {
            $summary = PackingLog::selectRaw('user_id, COUNT(*) as total_orders, SUM(total_items) as total_items, SUM(distinct_skus) as total_distinct')
                ->with('user:id,name')
                ->whereBetween('scanned_at', [$from, $to])
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->groupBy('user_id')
                ->orderByDesc('total_items')
                ->get();
        }

        $typeBreakdown = $this->breakdownByType((clone $logQuery)->get(), $type);
        $senderBreakdown = $this->breakdownBySender((clone $logQuery)->get());

        $userName = null;
        if ($userId) {
            $userName = User::whereKey($userId)->value('name');
        }

        return view('reports.packing_pdf', [
            'summary' => $summary,
            'typeBreakdown' => $typeBreakdown,
            'senderBreakdown' => $senderBreakdown,
            'logs' => $logs,
            'from' => $from,
            'to' => $to,
            'type' => $type,
            'userName' => $userName,
        ]);
    }

    /**
     * Bangun ringkasan per-user dari koleksi log yang sudah diambil.
     * Menghitung HANYA item dengan product.type yang dipilih.
     *
     * @param  \Illuminate\Support\Collection<int, PackingLog>  $logs
     */
    private function summaryByType($logs, string $type)
    {
        $byUser = [];
        foreach ($logs as $log) {
            $uid = $log->user_id;
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = [
                    'user' => $log->user,
                    'orders' => [],
                    'items' => 0,
                    'distinct' => [],
                ];
            }

            $matchedInThisOrder = false;
            foreach ($log->order?->items ?? [] as $item) {
                if (($item->variant?->product?->type ?? null) !== $type) {
                    continue;
                }
                $matchedInThisOrder = true;
                $byUser[$uid]['items'] += (int) $item->quantity;
                $byUser[$uid]['distinct'][$item->sku ?? 'item-'.$item->id] = true;
            }

            // Order baru di-count kalau memang ada item type yang cocok
            if ($matchedInThisOrder) {
                $byUser[$uid]['orders'][$log->order_id] = true;
            }
        }

        return collect($byUser)
            ->map(fn ($row) => (object) [
                'user' => $row['user'],
                'total_orders' => count($row['orders']),
                'total_items' => $row['items'],
                'total_distinct' => count($row['distinct']),
            ])
            ->sortByDesc('total_items')
            ->values();
    }

    /**
     * Rincian jumlah barang ter-scan keluar, dikelompokkan per JENIS barang
     * (Product.type) untuk tiap user. Hasil: [user_id => ['boskit' => 20, ...]]
     * terurut dari qty terbesar. Kalau filter $type aktif, hanya jenis itu yang
     * dihitung supaya konsisten dengan ringkasan.
     *
     * @param  \Illuminate\Support\Collection<int, PackingLog>  $logs
     * @return array<int, array<string, int>>
     */
    private function breakdownByType($logs, ?string $type): array
    {
        $byUser = [];

        foreach ($logs as $log) {
            $uid = $log->user_id;
            $byUser[$uid] ??= [];

            foreach ($log->order?->items ?? [] as $item) {
                $itemType = $item->variant?->product?->type;
                $itemType = ($itemType === null || $itemType === '')
                    ? 'Tanpa jenis'
                    : $itemType;

                if ($type !== null && $itemType !== $type) {
                    continue;
                }

                $byUser[$uid][$itemType] = ($byUser[$uid][$itemType] ?? 0) + (int) $item->quantity;
            }
        }

        foreach ($byUser as $uid => $types) {
            arsort($types);
            $byUser[$uid] = $types;
        }

        return $byUser;
    }

    /**
     * Rincian jumlah RESI/scan ter-packing, dikelompokkan per PENGIRIM +
     * LAYANAN PENGIRIMAN/platform (Order.sender_name + platformDeduction.
     * platform_name) untuk tiap user, misal "Ranco Autoshop · TikTok Ranco"
     * dan "Ranco Autoshop · Shopee". Dihitung PER SCAN (1 resi = 1), BUKAN
     * per jumlah item kelengkapan. Mengikuti filter yang sama dengan rincian
     * per jenis.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\PackingLog>  $logs
     * @return array<int, array<string, int>>
     */
    private function breakdownBySender($logs): array
    {
        $byUser = [];

        foreach ($logs as $log) {
            $uid = $log->user_id;
            $byUser[$uid] ??= [];

            $sender = $log->order?->sender_name;
            $sender = ($sender === null || $sender === '')
                ? 'Tanpa pengirim'
                : $sender;

            // Layanan pengiriman / platform (TikTok, Shopee, dll) yang ke-scan
            // pada order tsb. Diambil dari platform deduction yang terpasang.
            $platform = $log->order?->platformDeduction?->platform_name;
            $platform = ($platform === null || $platform === '')
                ? null
                : $platform;

            // Gabungkan pengirim + layanan pengiriman jadi satu label supaya
            // pengirim yang sama tapi beda platform tampil terpisah. Contoh:
            // "Ranco Autoshop · TikTok Ranco", "Ranco Autoshop · Shopee".
            $label = $platform !== null ? "{$sender} · {$platform}" : $sender;

            // Hitung PER SCAN (resi): satu scan = satu resi untuk pengirim +
            // layanan pengiriman tsb, berapa pun jumlah item kelengkapannya.
            $byUser[$uid][$label] = ($byUser[$uid][$label] ?? 0) + 1;
        }

        foreach ($byUser as $uid => $senders) {
            arsort($senders);
            $byUser[$uid] = $senders;
        }

        return $byUser;
    }

    /**
     * Hapus riwayat packing SESUAI FILTER aktif (rentang tanggal + opsional
     * user/jenis). Admin-only (dijaga di route). Tidak mengubah status
     * pesanan maupun stok — hanya membersihkan catatan riwayat scan.
     */
    public function destroyByFilter(Request $request): \Illuminate\Http\RedirectResponse
    {
        [$from, $to, $userId, $type, $resi] = $this->filters($request);

        $query = PackingLog::whereBetween('scanned_at', [$from, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($resi, fn ($q) => $q->where('resi_number', 'like', '%'.$resi.'%'))
            ->when($type, fn ($q) => $q->whereHas(
                'order.items.variant.product',
                fn ($qq) => $qq->where('type', $type)
            ));

        $count = (clone $query)->count();
        $query->delete();

        return redirect()
            ->route('reports.packing', $request->only(['from', 'to', 'user_id', 'type', 'resi']))
            ->with('success', "{$count} riwayat packing dihapus (rentang {$from->format('d/m/Y')} – {$to->format('d/m/Y')}).");
    }

    /**
     * Hapus riwayat packing yang DIPILIH MANUAL (centang baris di tabel).
     * Admin-only (dijaga di route).
     */
    public function destroySelected(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:packing_logs,id'],
        ]);

        $count = PackingLog::whereIn('id', $data['ids'])->delete();

        return back()->with('success', "{$count} riwayat packing terpilih dihapus.");
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: ?int, 3: ?string, 4: ?string}
     */
    private function filters(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        $userId = $request->filled('user_id') ? (int) $request->query('user_id') : null;
        $type = $request->filled('type') ? trim((string) $request->query('type')) : null;
        $resi = $request->filled('resi') ? trim((string) $request->query('resi')) : null;

        return [$from, $to, $userId, $type, $resi];
    }
}
