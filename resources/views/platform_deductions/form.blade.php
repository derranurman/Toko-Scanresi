@extends('layouts.app')
@section('title', $deduction->exists ? 'Edit Potongan' : 'Tambah Potongan')

@section('content')
    <?php $header = $deduction->exists ? 'Edit Potongan: '.$deduction->platform_name : 'Tambah Potongan Platform'; ?>

    <?php
        // value_column => [label, flag_column, default_is_percent]
        $fields = [
            'adm_percent' => ['ADM', 'adm_is_percent', true],
            'cashback_percent' => ['CB/BP', 'cashback_is_percent', true],
            'free_shipping_percent' => ['Ongkir Free', 'free_shipping_is_percent', true],
            'yield_percent' => ['Yield', 'yield_is_percent', true],
            'operational_percent' => ['Operasional', 'operational_is_percent', true],
            'tax_percent' => ['Pajak', 'tax_is_percent', true],
            'dynamic_commission_percent' => ['Komisi Dinamis', 'dynamic_commission_is_percent', true],
            'platform_commission_percent' => ['Biaya Komisi Platform', 'platform_commission_is_percent', true],
            'shipping_cargo_amount' => ['Ongkir Cargo', 'shipping_cargo_is_percent', false],
            'label_amount' => ['Label', 'label_is_percent', false],
            'packaging_amount' => ['Plastik/Lakban/Dus', 'packaging_is_percent', false],
            'service_fee_amount' => ['Biaya Layanan', 'service_fee_is_percent', false],
            'logistics_amount' => ['Biaya Logistik', 'logistics_is_percent', false],
        ];
    ?>

    <div class="card max-w-4xl">
        <form method="POST"
              action="{{ $deduction->exists ? route('platform_deductions.update', $deduction) : route('platform_deductions.store') }}"
              class="space-y-5">
            @csrf
            @if ($deduction->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="label">Nama Platform</label>
                    <input name="platform_name" value="{{ old('platform_name', $deduction->platform_name) }}"
                           required class="input" placeholder="Contoh: TikTok Ranco">
                    @error('platform_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1"
                               @checked(old('is_active', $deduction->is_active ?? true))
                               class="rounded border-gray-300">
                        Aktif
                    </label>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-700 mb-2 pb-1 border-b">Potongan &amp; Biaya</h3>
                <p class="text-xs text-gray-500 mb-3">Tiap field bisa diisi sebagai <b>%</b> (dihitung dari Total Jual) atau <b>Rp</b> (nominal tetap). Pilih satuannya di dropdown sebelah kiri tiap input.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach ($fields as $valueCol => $meta)
                        <?php $label = $meta[0]; ?>
                        <?php $flagCol = $meta[1]; ?>
                        <?php $defaultIsPercent = $meta[2]; ?>
                        <?php $flagVal = old($flagCol, $deduction->{$flagCol} ?? $defaultIsPercent); ?>
                        <?php
                            // Tampilkan nilai tersimpan tanpa trailing zero (mis. 10.7000 -> 10.7,
                            // 10000.00 -> 10000). old() dipakai saat validasi gagal.
                            $rawVal = old($valueCol, $deduction->{$valueCol});
                            $displayVal = ($rawVal === null || $rawVal === '')
                                ? ''
                                : rtrim(rtrim(number_format((float) $rawVal, 4, '.', ''), '0'), '.');
                        ?>
                        <div>
                            <label class="label">{{ $label }}</label>
                            <div class="flex gap-2">
                                <select name="{{ $flagCol }}" class="input" style="max-width: 5.5rem;">
                                    <option value="0" @selected(! $flagVal)>Rp</option>
                                    <option value="1" @selected($flagVal)>%</option>
                                </select>
                                <input type="number" step="any" min="0"
                                       name="{{ $valueCol }}"
                                       value="{{ $displayVal }}"
                                       class="input text-right flex-1" placeholder="0">
                            </div>
                            @error($valueCol)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            @error($flagCol)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2 pt-3 border-t">
                <button class="btn-primary" type="submit">Simpan</button>
                <a href="{{ route('platform_deductions.index') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>
@endsection
