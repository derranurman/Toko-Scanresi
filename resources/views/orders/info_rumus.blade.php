@extends('layouts.app')
@section('title', 'Info Rumus Perhitungan')

@section('content')
    <?php $header = 'Info Rumus Perhitungan'; ?>
    <?php $subheader = 'Dokumentasi semua rumus yang dipakai di halaman Pesanan & laporan.'; ?>

    <div class="space-y-6 max-w-5xl">

        {{-- Navigasi balik --}}
        <div>
            <a href="{{ route('orders.index') }}" class="text-indigo-600 hover:underline text-sm">&larr; Kembali ke Pesanan</a>
        </div>

        {{-- ============= GROUP: TOTAL DASAR ============= --}}
        <div class="card">
            <h3 class="text-base font-semibold text-gray-900 mb-3">1. Total Dasar (per Pesanan)</h3>

            <div class="space-y-3">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Total Jual</div>
                    <div class="font-mono text-sm text-gray-900">Total Jual = Σ(Harga Jual produk × Quantity)</div>
                    <p class="text-xs text-gray-500 mt-1">
                        Jumlah seluruh harga jual setiap variant yang ada di pesanan dikalikan jumlahnya.
                    </p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Total Modal</div>
                    <div class="font-mono text-sm text-gray-900">Total Modal = Σ(Harga Beli produk × Quantity)</div>
                    <p class="text-xs text-gray-500 mt-1">
                        Jumlah seluruh harga beli (modal) setiap variant yang ada di pesanan.
                    </p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Total Reseller</div>
                    <div class="font-mono text-sm text-gray-900">Total Reseller = Σ(Harga Reseller produk × Quantity)</div>
                </div>
            </div>
        </div>

        {{-- ============= GROUP: POTONGAN PERSENTASE ============= --}}
        <div class="card">
            <h3 class="text-base font-semibold text-gray-900 mb-3">2. Potongan Persentase</h3>
            <p class="text-xs text-gray-600 mb-3">
                Aturan TikTok: ADM, Pajak, dan Bulat Max 650Rb pakai dasar perhitungan yang
                <strong>di-cap maksimal Rp 650.000</strong>. Sedangkan Ongkir Free, Yield, Operasional
                pakai dasar Total Jual penuh.
            </p>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                <div class="text-xs font-semibold text-blue-700 mb-1">Cap Base (dasar yang di-cap)</div>
                <div class="font-mono text-sm text-blue-900">capBase = min(Total Jual, 650.000)</div>
            </div>

            <div class="space-y-3">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">ADM (Rp)</div>
                    <div class="font-mono text-sm text-gray-900">ADM Rp = capBase × ADM%</div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Pajak (Rp)</div>
                    <div class="font-mono text-sm text-gray-900">Pajak Rp = capBase × Pajak%</div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Bulat Max 650Rb</div>
                    <div class="font-mono text-sm text-gray-900">Bulat Max 650Rb = capBase × Ongkir Free%</div>
                    <p class="text-xs text-gray-500 mt-1">Ongkir Free %, tapi dasarnya di-cap 650k.</p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Ongkir Free (Rp)</div>
                    <div class="font-mono text-sm text-gray-900">Ongkir Free Rp = Total Jual × Ongkir Free%</div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Yield (Rp)</div>
                    <div class="font-mono text-sm text-gray-900">Yield Rp = Total Jual × Yield%</div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Operasional (Rp)</div>
                    <div class="font-mono text-sm text-gray-900">Operasional Rp = Total Jual × Operasional%</div>
                </div>
            </div>
        </div>

        {{-- ============= GROUP: AGREGAT POTONGAN ============= --}}
        <div class="card">
            <h3 class="text-base font-semibold text-gray-900 mb-3">3. Agregat Potongan</h3>

            <div class="bg-amber-50 border border-amber-300 rounded-lg p-4">
                <div class="text-sm font-semibold text-amber-800 mb-2">TOTAL POTONGAN APLIKASI</div>
                <div class="font-mono text-sm text-amber-900">
                    Total Potongan Aplikasi = ADM + Bulat Max 650Rb + Biaya Layanan + Biaya Logistik + Pajak
                </div>
                <p class="text-xs text-amber-700 mt-2">
                    <strong>Catatan:</strong> Nilai ini bisa di-<em>override manual</em> dari kolom
                    <code>TOTAL POTONGAN APLIKASI</code> di tabel pesanan atau dari halaman Edit Pesanan.
                    Kalau ada override, nilai manual yang dipakai untuk menghitung Bersih Margin Live.
                    Kalau kosong, otomatis pakai rumus di atas.
                </p>
            </div>
        </div>

        {{-- ============= GROUP: MARGIN ============= --}}
        <div class="card">
            <h3 class="text-base font-semibold text-gray-900 mb-3">4. Margin & Profit</h3>

            <div class="space-y-3">
                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-green-800 mb-1">Margin Live</div>
                    <div class="font-mono text-sm text-green-900">Margin Live = Total Jual − Total Reseller</div>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-green-800 mb-1">% Margin Live</div>
                    <div class="font-mono text-sm text-green-900">% Margin Live = (Margin Live / Total Jual) × 100</div>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-green-800 mb-1">Profit Kotor</div>
                    <div class="font-mono text-sm text-green-900">Profit Kotor = Total Jual − Total Modal</div>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-green-800 mb-1">% Profit Kotor</div>
                    <div class="font-mono text-sm text-green-900">% Profit Kotor = (Profit Kotor / Total Jual) × 100</div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-blue-800 mb-1">Bersih Margin Live</div>
                    <div class="font-mono text-xs text-blue-900 leading-relaxed">
                        Bersih Margin Live = Margin Live − Total Potongan Aplikasi
                    </div>
                </div>

                <div class="bg-indigo-50 border border-indigo-300 rounded-lg p-4">
                    <div class="text-sm font-semibold text-indigo-800 mb-1">Margin Bisnis</div>
                    <div class="font-mono text-xs text-indigo-900 leading-relaxed">
                        Margin Bisnis = Total Reseller<br>
                        &nbsp;&nbsp;− Total Modal<br>
                        &nbsp;&nbsp;− Operasional<br>
                        &nbsp;&nbsp;− Plastik/Dus<br>
                        &nbsp;&nbsp;− Label (Rp)<br>
                        &nbsp;&nbsp;− Ongkir Cargo<br>
                        &nbsp;&nbsp;− Yield
                    </div>
                </div>

                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-indigo-800 mb-1">% Margin Bisnis</div>
                    <div class="font-mono text-sm text-indigo-900">% Margin Bisnis = (Margin Bisnis / Total Jual) × 100</div>
                </div>
            </div>
        </div>

        {{-- ============= GROUP: PILIHAN BARANG (Form Tambah Pesanan) ============= --}}
        <div class="card">
            <h3 class="text-base font-semibold text-gray-900 mb-3">5. Form Tambah Pesanan — Pilihan Kelengkapan</h3>

            <p class="text-sm text-gray-600 mb-3">Kode kelengkapan menentukan field variant yang harus diisi:</p>

            <div class="overflow-x-auto">
                <table class="text-xs border-collapse w-full">
                    <thead class="bg-gray-50 text-left uppercase text-gray-500">
                        <tr>
                            <th class="px-2 py-2">Kode</th>
                            <th class="px-2 py-2">Label</th>
                            <th class="px-2 py-2">Field yang Muncul</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr><td class="px-2 py-2 font-mono">1</td><td class="px-2 py-2">Stir Saja</td><td class="px-2 py-2 font-mono">Stir 1</td></tr>
                        <tr><td class="px-2 py-2 font-mono">2</td><td class="px-2 py-2">Stir + Boskit</td><td class="px-2 py-2 font-mono">Stir 1, Boskit 1</td></tr>
                        <tr><td class="px-2 py-2 font-mono">3</td><td class="px-2 py-2">Boskit Saja</td><td class="px-2 py-2 font-mono">Boskit 1</td></tr>
                        <tr><td class="px-2 py-2 font-mono">4</td><td class="px-2 py-2">Spoiler</td><td class="px-2 py-2 font-mono">Spoiler</td></tr>
                        <tr><td class="px-2 py-2 font-mono">5</td><td class="px-2 py-2">Klakson</td><td class="px-2 py-2 font-mono">Klakson</td></tr>
                        <tr><td class="px-2 py-2 font-mono">6</td><td class="px-2 py-2">Stir + Stir</td><td class="px-2 py-2 font-mono">Stir 1, Stir 2</td></tr>
                        <tr><td class="px-2 py-2 font-mono">7</td><td class="px-2 py-2">Stir + Stir + Boskit</td><td class="px-2 py-2 font-mono">Stir 1, Stir 2, Boskit 1</td></tr>
                        <tr><td class="px-2 py-2 font-mono">8</td><td class="px-2 py-2">Stir + Boskit + Boskit</td><td class="px-2 py-2 font-mono">Stir 1, Boskit 1, Boskit 2</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 space-y-3">
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Harga Modal (Auto)</div>
                    <div class="font-mono text-sm text-gray-900">
                        Harga Modal = Σ(Harga Beli variant yang dipilih) × Jumlah
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <div class="text-sm font-semibold text-gray-700 mb-1">Harga Jual (Auto)</div>
                    <div class="font-mono text-sm text-gray-900">
                        Harga Jual = Σ(Harga Jual variant yang dipilih) × Jumlah
                    </div>
                </div>
            </div>
        </div>

        {{-- ============= GROUP: STATUS ============= --}}
        <div class="card">
            <h3 class="text-base font-semibold text-gray-900 mb-3">6. Status Pesanan</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                <div class="flex items-center gap-2">
                    <span class="badge bg-amber-100 text-amber-700">Pending</span>
                    <span class="text-gray-600">Default. Belum dipacking.</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="badge bg-green-100 text-green-700">Packed</span>
                    <span class="text-gray-600">Sudah dipacking, siap kirim.</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="badge bg-blue-100 text-blue-700">Selesai Bulan Kemarin</span>
                    <span class="text-gray-600">Pesanan selesai bulan sebelumnya.</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="badge bg-red-100 text-red-700">Return</span>
                    <span class="text-gray-600">Pesanan dikembalikan pembeli.</span>
                </div>
            </div>

            <p class="text-xs text-gray-500 mt-3">
                Hanya 4 status pesanan yang valid: <strong>Pending</strong>, <strong>Packed</strong>,
                <strong>Selesai Bulan Kemarin</strong>, <strong>Return</strong>.
            </p>
        </div>

    </div>
@endsection
