@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
    @php($header = 'Pengaturan Aplikasi')
    @php($subheader = 'Atur nama & logo aplikasi, serta suara notifikasi packing.')

    <div class="card max-w-xl">
        <form method="POST"
              action="{{ route('settings.update') }}"
              enctype="multipart/form-data"
              class="space-y-5"
              x-data="{
                  preview: @js($setting->logoUrl()),
                  removed: false,
                  pickFile(event) {
                      const file = event.target.files[0];
                      if (!file) return;
                      this.removed = false;
                      this.preview = URL.createObjectURL(file);
                  },
                  clearFile() {
                      this.preview = null;
                      this.removed = true;
                      this.$refs.fileInput.value = '';
                  },
              }">
            @csrf
            @method('PUT')

            {{-- Logo --}}
            <div>
                <label class="label">Logo</label>
                <div class="flex items-center gap-4">
                    <div class="h-16 w-16 rounded-xl bg-indigo-600 text-white grid place-items-center overflow-hidden shrink-0">
                        <template x-if="preview">
                            <img :src="preview" alt="Logo" class="h-full w-full object-cover">
                        </template>
                        <template x-if="!preview">
                            <span class="text-2xl font-bold">{{ $setting->initial() }}</span>
                        </template>
                    </div>
                    <div class="flex-1 space-y-2">
                        <input type="file"
                               name="logo"
                               accept="image/*"
                               x-ref="fileInput"
                               @change="pickFile($event)"
                               class="block w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-500">JPG, PNG, GIF, atau WEBP. Maks 2 MB. Disarankan rasio 1:1 (kotak).</p>
                        @if ($setting->logo_path)
                            <label class="inline-flex items-center gap-2 text-xs text-red-600 cursor-pointer"
                                   x-show="preview || removed">
                                <input type="checkbox"
                                       name="remove_logo"
                                       value="1"
                                       x-model="removed"
                                       @change="if (removed) clearFile()"
                                       class="rounded border-red-300">
                                Hapus logo saat simpan (kembali ke huruf inisial)
                            </label>
                        @endif
                    </div>
                </div>
                @error('logo')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Nama --}}
            <div>
                <label class="label">Nama Aplikasi</label>
                <input name="app_name"
                       value="{{ old('app_name', $setting->app_name) }}"
                       maxlength="60"
                       required
                       class="input"
                       placeholder="Contoh: Toko Budi">
                <p class="text-xs text-gray-500 mt-1">Tampil di top-nav, halaman login, dan judul tab browser. Maks 60 karakter.</p>
                @error('app_name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Suara Notifikasi Packing --}}
            <div class="pt-4 border-t">
                <label class="label">Suara Notifikasi Packing</label>
                <p class="text-xs text-gray-500 mb-3">
                    Upload suara sendiri (MP3 atau WAV, maks 2 MB) untuk tiap status di
                    halaman Scan Packing. Kalau tidak diisi, dipakai suara default.
                </p>

                <div class="space-y-4">
                    @php($sounds = [
                        ['kind' => 'ok',  'input' => 'sound_ok',  'remove' => 'remove_sound_ok',  'label' => 'Sukses Packed', 'badge' => 'bg-green-100 text-green-700'],
                        ['kind' => 'dup', 'input' => 'sound_dup', 'remove' => 'remove_sound_dup', 'label' => 'Sudah Packed',  'badge' => 'bg-amber-100 text-amber-700'],
                        ['kind' => 'err', 'input' => 'sound_err', 'remove' => 'remove_sound_err', 'label' => 'Gagal',         'badge' => 'bg-red-100 text-red-700'],
                    ])

                    @foreach ($sounds as $s)
                        <div class="rounded-lg border border-gray-200 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="badge {{ $s['badge'] }}">{{ $s['label'] }}</span>
                                @if ($setting->customSoundUrl($s['kind']))
                                    <span class="text-[11px] text-gray-500">Suara custom aktif</span>
                                @else
                                    <span class="text-[11px] text-gray-400">Pakai suara default</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 mb-2">
                                <button type="button"
                                        class="btn-secondary text-xs shrink-0"
                                        onclick="(function(b){var a=new Audio(b.dataset.src);a.volume=1;a.play();})(this)"
                                        data-src="{{ $setting->soundUrl($s['kind']) }}">
                                    ▶ Tes suara
                                </button>
                                <input type="file"
                                       name="{{ $s['input'] }}"
                                       accept=".mp3,.wav,audio/mpeg,audio/wav,audio/x-wav"
                                       class="block w-full text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            </div>

                            @if ($setting->customSoundUrl($s['kind']))
                                <label class="inline-flex items-center gap-2 text-xs text-red-600 cursor-pointer">
                                    <input type="checkbox" name="{{ $s['remove'] }}" value="1" class="rounded border-red-300">
                                    Hapus suara custom (kembali ke default)
                                </label>
                            @endif

                            @error($s['input'])<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2 pt-2 border-t">
                <button class="btn-primary" type="submit">Simpan</button>
                <a href="{{ route('dashboard') }}" class="btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    {{-- Preview kecil supaya user tahu hasilnya. --}}
    <div class="mt-6">
        <div class="text-xs uppercase text-gray-500 mb-2">Preview</div>
        <div class="card max-w-xl flex items-center gap-3">
            @if ($setting->logoUrl())
                <div class="h-10 w-10 rounded-lg overflow-hidden">
                    <img src="{{ $setting->logoUrl() }}" alt="Logo" class="h-full w-full object-cover">
                </div>
            @else
                <div class="h-10 w-10 rounded-lg bg-indigo-600 text-white grid place-items-center font-bold">
                    {{ $setting->initial() }}
                </div>
            @endif
            <span class="font-semibold text-gray-900">{{ $setting->app_name }}</span>
        </div>
        <p class="text-xs text-gray-500 mt-2">Catatan: preview di atas dari nilai tersimpan; logo/nama yang baru kamu pilih baru akan muncul setelah klik <b>Simpan</b>.</p>
    </div>
@endsection
