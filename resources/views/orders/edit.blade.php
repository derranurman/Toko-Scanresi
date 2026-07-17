@extends('layouts.app')
@section('title', 'Edit Pesanan')

@section('content')
    <?php $header = 'Edit Pesanan'; ?>
    <?php $subheader = 'Ubah status, data pembeli, no. HP, dan detail lain dari pesanan ini.'; ?>

    <div class="card max-w-4xl">
        <form method="POST" action="{{ route('orders.update', $order) }}">
            @csrf
            @method('PUT')
            @include('orders._form', ['order' => $order, 'platforms' => $platforms, 'products' => $products])

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('orders.index') }}" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
            </div>
        </form>

        {{-- Form hapus dibuat TERPISAH dari form edit. <form> bersarang tidak
             valid di HTML dan bisa membuat tombol "Simpan" malah men-trigger
             aksi DELETE (pesanan terhapus saat menyimpan). --}}
        <div class="mt-4 pt-4 border-t flex justify-start">
            <form method="POST" action="{{ route('orders.destroy', $order) }}"
                  onsubmit="return confirm('Hapus pesanan {{ $order->resi_number }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-secondary text-red-600 hover:bg-red-50">Hapus Pesanan</button>
            </form>
        </div>
    </div>
@endsection
