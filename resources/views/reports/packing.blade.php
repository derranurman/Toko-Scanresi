@extends('layouts.app')
@section('title', 'Laporan Packing')

@section('content')
    @php($header = 'Laporan Packing')
    @php($subheader = 'Lihat aktivitas packing per user, lengkap dengan detail item & kelengkapan.')

    <div class="card">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-7 gap-2 items-end">
            <div>
                <label class="label">Dari</label>
                <input type="date" name="from" value="{{ $from }}" class="input">
            </div>
            <div>
                <label class="label">Sampai</label>
                <input type="date" name="to" value="{{ $to }}" class="input">
            </div>
            <div>
                <label class="label">User</label>
                <select name="user_id" class="input">
                    <option value="">Semua user</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected($userId == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Resi</label>
                <input type="text" name="resi" value="{{ $resi }}" placeholder="Cari nomor resi…" class="input">
            </div>
            <div>
                <label class="label">Jenis Barang</label>
                <select name="type" class="input">
                    <option value="">Semua jenis</option>
                    @foreach ($types as $t)
                        <option value="{{ $t }}" @selected($type === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button class="btn-primary flex-1" type="submit">Filter</button>
                <a href="{{ route('reports.packing') }}" class="btn-secondary">Reset</a>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('reports.packing.export', request()->query()) }}" class="btn-secondary flex-1 text-center">Export CSV</a>
                <a href="{{ route('reports.packing.export_pdf', request()->query()) }}" target="_blank" rel="noopener" class="btn-secondary flex-1 text-center">Export PDF</a>
            </div>
        </form>
        @if ($type)
            <p class="mt-3 text-xs text-gray-500">
                Filter aktif: jenis barang
                <span class="badge bg-indigo-100 text-indigo-700">{{ $type }}</span>.
                Ringkasan hanya menghitung item dengan jenis ini.
            </p>
        @endif

        @if (auth()->user()?->isAdmin())
            <div class="mt-4 pt-4 border-t flex items-center justify-between flex-wrap gap-2">
                <p class="text-xs text-gray-500">
                    Hapus riwayat scan sesuai <strong>rentang tanggal &amp; filter</strong> yang sedang aktif
                    (<span class="font-mono">{{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}</span>
                    – <span class="font-mono">{{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</span>).
                    Tidak mengubah status pesanan atau stok.
                </p>
                <form method="POST" action="{{ route('reports.packing.clear') }}"
                      onsubmit="return confirm('Hapus riwayat packing pada rentang {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}{{ $userId ? ' (user terpilih)' : '' }}{{ $type ? ' (jenis: '.$type.')' : '' }}?\n\nTindakan ini permanen.');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="from" value="{{ $from }}">
                    <input type="hidden" name="to" value="{{ $to }}">
                    <input type="hidden" name="user_id" value="{{ $userId }}">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="resi" value="{{ $resi }}">
                    <button type="submit" class="btn-danger">🗑 Hapus Sesuai Filter</button>
                </form>
            </div>
        @endif
    </div>

    <div class="card mt-6">
        <h2 class="font-semibold mb-3">Ringkasan per User</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 border-b">
                    <tr>
                        <th class="py-2">User</th>
                        <th class="py-2 text-right">Total Pesanan</th>
                        <th class="py-2 text-right">Total Item (pcs)</th>
                        <th class="py-2 text-right">Total SKU</th>
                        <th class="py-2 text-right">Rincian per Jenis</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($summary as $row)
                        <tr>
                            <td class="py-2 font-medium">{{ $row->user?->name ?? '—' }}</td>
                            <td class="py-2 text-right">{{ number_format($row->total_orders) }}</td>
                            <td class="py-2 text-right font-semibold">{{ number_format($row->total_items) }}</td>
                            <td class="py-2 text-right">{{ number_format($row->total_distinct) }}</td>
                            <td class="py-2">
                                @php($bd = $typeBreakdown[$row->user?->id] ?? [])
                                @if (count($bd))
                                    <div class="flex flex-wrap gap-1 justify-end">
                                        @foreach ($bd as $jenis => $qty)
                                            <span class="badge bg-indigo-50 text-indigo-700">{{ $jenis }} {{ number_format($qty) }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">Belum ada aktivitas packing pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-6">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <h2 class="font-semibold">Detail Scan</h2>
            @if (auth()->user()?->isAdmin())
                <button type="submit" form="packing-delete-selected"
                        class="btn-danger text-xs"
                        onclick="return confirm('Hapus riwayat packing yang dipilih?\n\nTindakan ini permanen.');">
                    🗑 Hapus Terpilih
                </button>
            @endif
        </div>

        @if (auth()->user()?->isAdmin())
            <form id="packing-delete-selected" method="POST" action="{{ route('reports.packing.destroy_selected') }}">
                @csrf
                @method('DELETE')
                {{-- Bawa filter aktif supaya setelah hapus tetap di tampilan yang sama. --}}
                <input type="hidden" name="from" value="{{ $from }}">
                <input type="hidden" name="to" value="{{ $to }}">
                <input type="hidden" name="user_id" value="{{ $userId }}">
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="resi" value="{{ $resi }}">
            </form>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 border-b">
                    <tr>
                        @if (auth()->user()?->isAdmin())
                            <th class="py-2 w-8">
                                <input type="checkbox" id="packing-check-all" class="rounded border-gray-300" title="Pilih semua di halaman ini">
                            </th>
                        @endif
                        <th class="py-2">Waktu</th>
                        <th class="py-2">User</th>
                        <th class="py-2">Resi</th>
                        <th class="py-2">Pengirim</th>
                        <th class="py-2">Item (Kelengkapan)</th>
                        <th class="py-2 text-right">Qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($logs as $log)
                        <tr class="align-top">
                            @if (auth()->user()?->isAdmin())
                                <td class="py-3">
                                    <input type="checkbox" name="ids[]" value="{{ $log->id }}"
                                           form="packing-delete-selected"
                                           class="packing-check rounded border-gray-300">
                                </td>
                            @endif
                            <td class="py-3 text-xs">{{ $log->scanned_at->format('d M Y H:i') }}</td>
                            <td class="py-3 font-medium">{{ $log->user?->name }}</td>
                            <td class="py-3 font-mono text-xs">
                                <a class="text-indigo-600 hover:underline" href="{{ route('orders.show', $log->order_id) }}">
                                    {{ $log->resi_number }}
                                </a>
                            </td>
                            <td class="py-3 text-xs">{{ $log->order?->sender_name ?: '—' }}</td>
                            <td class="py-3">
                                <ul class="space-y-0.5">
                                    @foreach ($log->order?->items ?? [] as $item)
                                        <li class="flex items-center gap-2 text-xs">
                                            <span class="badge bg-gray-100 text-gray-700">{{ $item->quantity }}×</span>
                                            <span>{{ $item->product_name }} — {{ $item->variant_name ?? '—' }}</span>
                                            <span class="text-gray-400 font-mono">{{ $item->sku }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="py-3 text-right font-semibold">{{ $log->total_items }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()?->isAdmin() ? 7 : 6 }}" class="py-6 text-center text-gray-500">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </div>

    @if (auth()->user()?->isAdmin())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var all = document.getElementById('packing-check-all');
                var boxes = document.querySelectorAll('.packing-check');
                if (all) {
                    all.addEventListener('change', function () {
                        boxes.forEach(function (b) { b.checked = all.checked; });
                    });
                }
            });
        </script>
    @endif
@endsection
