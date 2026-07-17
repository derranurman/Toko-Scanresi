@extends('layouts.app')
@section('title', 'Edit Produk')

@section('content')
    @php($header = 'Edit Produk: '.$product->name)

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card lg:col-span-1">
            <h2 class="font-semibold mb-3">Detail Produk</h2>
            <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf @method('PUT')

                <div>
                    <label class="label">Gambar</label>
                    @if ($product->imageUrl())
                        <div class="flex items-start gap-3 mb-2">
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}"
                                 class="h-24 w-24 rounded-lg object-cover border border-gray-200">
                            <label class="inline-flex items-center gap-2 text-xs text-red-600">
                                <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300">
                                Hapus gambar saat simpan
                            </label>
                        </div>
                    @endif
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                    <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengganti. Maks 2 MB.</p>
                    @error('image')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="label">Nama</label>
                    <input name="name" value="{{ old('name', $product->name) }}" required class="input">
                </div>
                <div>
                    <label class="label">SKU</label>
                    <input name="sku" value="{{ old('sku', $product->sku) }}" required class="input font-mono">
                </div>
                <div>
                    <label class="label">Jenis</label>
                    <input name="type" value="{{ old('type', $product->type) }}" class="input" placeholder="Contoh: Aksesoris Motor">
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="label">Harga Beli (Rp)</label>
                        <input type="text" inputmode="numeric" name="purchase_price" value="{{ old('purchase_price', (int) $product->purchase_price) }}" class="input money-input">
                    </div>
                    <div>
                        <label class="label">Harga Reseller (Rp)</label>
                        <input type="text" inputmode="numeric" name="reseller_price" value="{{ old('reseller_price', (int) $product->reseller_price) }}" class="input money-input">
                    </div>
                    <div>
                        <label class="label">Harga Jual (Rp)</label>
                        <input type="text" inputmode="numeric" name="selling_price" value="{{ old('selling_price', (int) $product->selling_price) }}" class="input money-input">
                    </div>
                </div>

                @php($profit = $product->grossProfit())
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-sm">
                    <span class="text-gray-500">Profit kotor saat ini:</span>
                    <span class="font-semibold ml-1 {{ $profit > 0 ? 'text-green-600' : ($profit < 0 ? 'text-red-600' : 'text-gray-700') }}">
                        Rp {{ number_format($profit, 0, ',', '.') }}
                    </span>
                </div>

                <div>
                    <label class="label">Deskripsi</label>
                    <textarea name="description" rows="3" class="input">{{ old('description', $product->description) }}</textarea>
                </div>

                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" {{ $product->is_active ? 'checked' : '' }} class="rounded border-gray-300">
                    Aktif
                </label>
                <div class="flex justify-between">
                    <button class="btn-primary">Simpan</button>
                </div>
            </form>

            <form method="POST" action="{{ route('products.destroy', $product) }}" class="mt-4 pt-4 border-t"
                  onsubmit="return confirm('Hapus produk ini? Semua varian juga akan terhapus.');">
                @csrf @method('DELETE')
                <button class="btn-danger w-full" type="submit">Hapus Produk</button>
            </form>
        </div>

        <div class="card lg:col-span-2">
            <h2 class="font-semibold mb-3">Varian</h2>

            <div class="overflow-x-auto mb-4">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 border-b">
                        <tr>
                            <th class="py-2">Nama</th>
                            <th class="py-2">SKU</th>
                            <th class="py-2">Stok</th>
                            <th class="py-2">Min</th>
                            <th class="py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($product->variants as $v)
                            <tr>
                                <form method="POST" action="{{ route('variants.update', $v) }}" class="contents">
                                    @csrf @method('PUT')
                                    <td class="py-2"><input name="name" value="{{ $v->name }}" class="input" required></td>
                                    <td class="py-2"><input name="sku" value="{{ $v->sku }}" class="input font-mono" required></td>
                                    <td class="py-2">
                                        <input name="stock" value="{{ $v->stock }}" type="number" min="0" required
                                               class="input w-24 {{ $v->isLowStock() ? 'border-red-500 text-red-600 font-semibold' : '' }}"
                                               title="Set stok ke nilai ini. Selisihnya akan dicatat di Riwayat Stok (audit trail).">
                                    </td>
                                    <td class="py-2"><input name="min_stock" value="{{ $v->min_stock }}" type="number" min="0" class="input w-20"></td>
                                    <td class="py-2">
                                        <button class="text-indigo-600 hover:underline text-sm" type="submit">Simpan</button>
                                </form>
                                        <form method="POST" action="{{ route('variants.destroy', $v) }}" class="inline ml-2"
                                              onsubmit="return confirm('Hapus varian ini?');">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:underline text-sm" type="submit">Hapus</button>
                                        </form>
                                    </td>
                            </tr>

                            <tr class="bg-gray-50">
                                <td colspan="5" class="px-3 py-2">
                                    <form method="POST" action="{{ route('variants.adjust', $v) }}" class="flex flex-wrap items-center gap-2 text-sm">
                                        @csrf
                                        <span class="text-gray-500">Sesuaikan stok:</span>
                                        <input name="qty" type="number" placeholder="+10 / -5" class="input w-24" required>
                                        <input name="note" placeholder="Catatan (opsional)" class="input flex-1 min-w-[180px]">
                                        <button class="btn-secondary">Simpan Adj.</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-gray-500">Belum ada varian.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t pt-4">
                <h3 class="font-semibold text-sm mb-2">Tambah Varian</h3>
                <form method="POST" action="{{ route('variants.store', $product) }}" class="grid grid-cols-1 md:grid-cols-4 gap-2 items-end">
                    @csrf
                    <div>
                        <label class="label">Nama</label>
                        <input name="name" required class="input" placeholder="Merah">
                    </div>
                    <div>
                        <label class="label">SKU</label>
                        <input name="sku" required class="input font-mono" placeholder="{{ $product->sku }}-RED">
                    </div>
                    <div>
                        <label class="label">Stok awal</label>
                        <input name="stock" type="number" min="0" value="0" required class="input">
                    </div>
                    <div class="flex gap-2">
                        <div class="flex-1">
                            <label class="label">Min</label>
                            <input name="min_stock" type="number" min="0" value="0" required class="input">
                        </div>
                        <button class="btn-primary h-[42px]" type="submit">+</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('products._money_format')
@endsection
