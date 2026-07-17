@extends('layouts.app')
@section('title', 'Produk Baru')

@section('content')
    @php($header = 'Produk Baru')

    <div class="card max-w-3xl">
        <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label class="label">Gambar Produk</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp"
                       class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                <p class="text-xs text-gray-500 mt-1">JPG/PNG/WEBP, maks 2 MB.</p>
                @error('image')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="label">Nama Produk</label>
                    <input name="name" value="{{ old('name') }}" required class="input">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">SKU</label>
                    <input name="sku" value="{{ old('sku') }}" required class="input font-mono">
                    @error('sku')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="label">Jenis</label>
                <input name="type" value="{{ old('type') }}" class="input" placeholder="Contoh: Aksesoris Motor, Spare Part">
                @error('type')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="label">Harga Beli (Rp)</label>
                    <input type="text" inputmode="numeric" name="purchase_price" value="{{ old('purchase_price', 0) }}" class="input money-input">
                    @error('purchase_price')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Harga Reseller (Rp)</label>
                    <input type="text" inputmode="numeric" name="reseller_price" value="{{ old('reseller_price', 0) }}" class="input money-input">
                    @error('reseller_price')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Harga Jual (Rp)</label>
                    <input type="text" inputmode="numeric" name="selling_price" value="{{ old('selling_price', 0) }}" class="input money-input">
                    @error('selling_price')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="label">Deskripsi</label>
                <textarea name="description" rows="3" class="input">{{ old('description') }}</textarea>
            </div>

            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300">
                Aktif
            </label>

            <div class="flex gap-2 pt-2 border-t">
                <button class="btn-primary" type="submit">Simpan & Tambah Varian</button>
                <a href="{{ route('products.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    @include('products._money_format')
@endsection
