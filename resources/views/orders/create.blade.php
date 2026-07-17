@extends('layouts.app')
@section('title', 'Tambah Pesanan')

@section('content')
    <?php $header = 'Tambah Pesanan'; ?>
    <?php $subheader = 'Buat pesanan secara manual tanpa import PDF.'; ?>

    <div class="card max-w-4xl">
        <form method="POST" action="{{ route('orders.store') }}">
            @csrf
            @include('orders._form', ['order' => null, 'platforms' => $platforms, 'products' => $products])

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('orders.index') }}" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary">Simpan</button>
            </div>
        </form>
    </div>
@endsection
