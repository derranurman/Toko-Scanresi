@extends('layouts.app')
@section('title', 'Import Pesanan')

@section('content')
    @php($header = 'Import Pesanan dari TikTok Shop')
    @php($subheader = 'Export pesanan dari Seller Center, sesuaikan kolom, lalu upload CSV di sini.')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card lg:col-span-2">
            <h2 class="font-semibold mb-3">Upload CSV</h2>
            <form method="POST" action="{{ route('orders.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="file" name="file" accept=".csv,text/csv" required
                       class="block w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white hover:file:bg-indigo-700">
                @error('file') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <button class="btn-primary" type="submit">Upload & Import</button>
            </form>

            @if (session('import_errors'))
                <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
                    <div class="font-semibold mb-1">Catatan import:</div>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach (session('import_errors') as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="card">
            <h2 class="font-semibold mb-2">Format CSV</h2>
            <p class="text-sm text-gray-600 mb-3">
                Satu baris = satu item. Baris dengan <span class="font-mono">resi_number</span> sama digabung jadi satu pesanan.
            </p>
            <div class="text-xs bg-gray-50 rounded-lg p-3 font-mono leading-relaxed overflow-x-auto">
                tiktok_order_id, <b>resi_number</b>, courier, buyer_name, buyer_phone,<br>
                shipping_address, order_date, product_name, variant_name, sku, <b>quantity</b>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                Kolom wajib: <b>resi_number</b>, <b>quantity</b>.<br>
                SKU disesuaikan dengan master produk — kalau tidak ketemu, item tetap masuk tapi stok tidak akan terkurangi saat scan.
            </p>
            <a href="{{ route('orders.import.template') }}" class="btn-secondary mt-3 w-full">Download Template CSV</a>
        </div>
    </div>
@endsection
