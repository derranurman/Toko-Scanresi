@extends('layouts.app')
@section('title', 'Import PDF Label')

@section('content')
    @php($header = 'Import Label PDF (TikTok Shop &amp; Shopee)')
    @php($subheader = 'Upload 1 file PDF berisi banyak halaman label. Sistem auto-deteksi marketplace (TikTok / Shopee) lalu ekstrak resi, alamat, dan produk.')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card lg:col-span-2">
            <h2 class="font-semibold mb-3">Upload PDF</h2>
            <form method="POST" action="{{ route('orders.import.pdf.upload') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="file" name="file" accept="application/pdf,.pdf" required
                       class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                @error('file') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="text-xs text-gray-500">
                    Maks 20 MB. Format yang didukung:
                    <span class="badge bg-indigo-50 text-indigo-700">TikTok Shop + J&T</span>
                    <span class="badge bg-orange-50 text-orange-700">Shopee + SPX</span>
                </p>
                <button class="btn-primary" type="submit">Upload & Parse</button>
            </form>
        </div>

        <div class="card">
            <h2 class="font-semibold mb-2">Tips</h2>
            <ul class="text-xs text-gray-600 space-y-2 list-disc list-inside">
                <li>Download label bulk dari Seller Center (TikTok / Shopee).</li>
                <li>Jangan scan fisik — gunakan file asli biar teks bisa dibaca.</li>
                <li>Kalau ada barang combo (misal <span class="font-mono">Stir+Bosskit</span>), buat Combo Mapping dulu.</li>
                <li>Resi yang sudah pernah di-packing akan otomatis dilewati.</li>
            </ul>
            <a href="{{ route('combo_mappings.index') }}" class="btn-secondary w-full mt-3 text-center">Kelola Combo Mapping</a>
        </div>
    </div>

    @if ($recentDrafts->isNotEmpty())
        <div class="card mt-6">
            <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                <h2 class="font-semibold">Draft Terbaru</h2>
                <form method="POST" action="{{ route('orders.import.pdf.destroy_all') }}" class="inline"
                      onsubmit="return confirm('Hapus SEMUA draft secara permanen? Data tidak bisa dikembalikan.');">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="px-3 py-1.5 rounded text-xs font-semibold bg-red-600 text-white hover:bg-red-700">
                        🗑 Hapus Semua
                    </button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 border-b">
                        <tr>
                            <th class="py-2">File</th>
                            <th class="py-2">User</th>
                            <th class="py-2">Halaman</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Waktu</th>
                            <th class="py-2 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($recentDrafts as $d)
                            <tr>
                                <td class="py-2">{{ $d->original_filename }}</td>
                                <td class="py-2">{{ $d->user?->name }}</td>
                                <td class="py-2">{{ $d->total_pages }}</td>
                                <td class="py-2">
                                    @if ($d->status === 'draft')
                                        <span class="badge bg-amber-100 text-amber-700">Draft</span>
                                    @elseif ($d->status === 'committed')
                                        <span class="badge bg-green-100 text-green-700">Tersimpan</span>
                                    @else
                                        <span class="badge bg-gray-100 text-gray-600">Dibuang</span>
                                    @endif
                                </td>
                                <td class="py-2 text-xs">{{ $d->created_at->format('d M H:i') }}</td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    @if ($d->status === 'draft')
                                        <a href="{{ route('orders.import.pdf.preview', $d) }}" class="text-indigo-600 hover:underline mr-2">Lanjutkan</a>
                                    @endif
                                    <form method="POST" action="{{ route('orders.import.pdf.destroy', $d) }}" class="inline"
                                          onsubmit="return confirm('Hapus draft {{ $d->original_filename }} secara permanen? Data tidak bisa dikembalikan.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
