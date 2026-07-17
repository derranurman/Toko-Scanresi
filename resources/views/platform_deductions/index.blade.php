@extends('layouts.app')
@section('title', 'Kelola Potongan')

@section('content')
    @php($header = 'Kelola Potongan Platform')
    @php($subheader = 'Biaya & persentase potongan per marketplace. Dipakai untuk hitung profit bersih per pesanan.')


    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <div class="text-sm text-gray-500">
                Total: <b>{{ $deductions->count() }}</b> platform terdaftar
            </div>
            <a href="{{ route('platform_deductions.create') }}" class="btn-primary">+ Tambah Platform</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="text-left text-xs uppercase text-gray-500 border-b">
                    <tr>
                        <th class="py-2 pr-3">No</th>
                        <th class="py-2 pr-3">Platform</th>
                        <th class="py-2 pr-3 text-right">ADM</th>
                        <th class="py-2 pr-3 text-right">CB/BP</th>
                        <th class="py-2 pr-3 text-right">Ongkir Free</th>
                        <th class="py-2 pr-3 text-right">Ongkir Cargo</th>
                        <th class="py-2 pr-3 text-right">Label</th>
                        <th class="py-2 pr-3 text-right">Yield</th>
                        <th class="py-2 pr-3 text-right">Plastik/Lakban/Dus</th>
                        <th class="py-2 pr-3 text-right">Operasional</th>
                        <th class="py-2 pr-3 text-right">Biaya Layanan</th>
                        <th class="py-2 pr-3 text-right">Biaya Logistik</th>
                        <th class="py-2 pr-3 text-right">Pajak</th>
                        <th class="py-2 pr-3 text-right">Komisi Dinamis</th>
                        <th class="py-2 pr-3 text-right">Biaya Komisi Platform</th>
                        <th class="py-2 pr-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($deductions as $i => $d)
                        <?php
                            // Format nilai sesuai flag: % (dari Total Jual) atau Rp nominal.
                            $fmtVal = fn ($value, $isPercent) => $isPercent
                                ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.').'%'
                                : 'Rp '.number_format((float) $value, 0, ',', '.');
                        ?>
                        <tr>
                            <td class="py-3 pr-3">{{ $i + 1 }}</td>
                            <td class="py-3 pr-3 font-medium">
                                {{ $d->platform_name }}
                                @if (! $d->is_active)
                                    <span class="badge bg-gray-100 text-gray-600 ml-1">Nonaktif</span>
                                @endif
                            </td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->adm_percent, $d->adm_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->cashback_percent, $d->cashback_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->free_shipping_percent, $d->free_shipping_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->shipping_cargo_amount, $d->shipping_cargo_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->label_amount, $d->label_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->yield_percent, $d->yield_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->packaging_amount, $d->packaging_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->operational_percent, $d->operational_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->service_fee_amount, $d->service_fee_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->logistics_amount, $d->logistics_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->tax_percent, $d->tax_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->dynamic_commission_percent, $d->dynamic_commission_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right font-mono text-xs">{{ $fmtVal($d->platform_commission_percent, $d->platform_commission_is_percent) }}</td>
                            <td class="py-3 pr-3 text-right">
                                <a href="{{ route('platform_deductions.edit', $d) }}" class="text-indigo-600 hover:underline">Edit</a>
                                <form method="POST" action="{{ route('platform_deductions.destroy', $d) }}" class="inline ml-2"
                                      onsubmit="return confirm('Hapus potongan platform {{ $d->platform_name }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="16" class="py-6 text-center text-gray-500">
                            Belum ada data. <a href="{{ route('platform_deductions.create') }}" class="text-indigo-600 hover:underline">Tambah sekarang →</a>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
