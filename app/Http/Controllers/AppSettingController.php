<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AppSettingController extends Controller
{
    public function edit(): View
    {
        $setting = $this->settingRow();

        return view('settings.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            // Suara packing: terima mp3/wav (+ beberapa mime umum), maks 2 MB.
            'sound_ok' => ['nullable', 'file', 'mimes:mp3,wav,mpga,wave,x-wav', 'max:2048'],
            'sound_dup' => ['nullable', 'file', 'mimes:mp3,wav,mpga,wave,x-wav', 'max:2048'],
            'sound_err' => ['nullable', 'file', 'mimes:mp3,wav,mpga,wave,x-wav', 'max:2048'],
            'remove_sound_ok' => ['nullable', 'boolean'],
            'remove_sound_dup' => ['nullable', 'boolean'],
            'remove_sound_err' => ['nullable', 'boolean'],
        ]);

        $setting = $this->settingRow();
        $shouldRemove = $request->boolean('remove_logo');

        $update = ['app_name' => $data['app_name']];

        if ($shouldRemove && $setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
            $update['logo_path'] = null;
        }

        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $update['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        // Suara packing (ok / dup / err): upload baru menimpa lama,
        // centang hapus → kembali ke suara default.
        $soundMap = [
            'ok'  => ['column' => 'sound_ok_path',  'input' => 'sound_ok',  'remove' => 'remove_sound_ok'],
            'dup' => ['column' => 'sound_dup_path', 'input' => 'sound_dup', 'remove' => 'remove_sound_dup'],
            'err' => ['column' => 'sound_err_path', 'input' => 'sound_err', 'remove' => 'remove_sound_err'],
        ];

        foreach ($soundMap as $cfg) {
            $column = $cfg['column'];

            if ($request->boolean($cfg['remove']) && $setting->{$column}) {
                Storage::disk('public')->delete($setting->{$column});
                $update[$column] = null;
            }

            if ($request->hasFile($cfg['input'])) {
                if ($setting->{$column}) {
                    Storage::disk('public')->delete($setting->{$column});
                }
                $update[$column] = $request->file($cfg['input'])->store('sounds', 'public');
            }
        }

        $setting->update($update);

        return redirect()->route('settings.edit')->with('success', 'Pengaturan disimpan.');
    }

    /**
     * Pastikan baris settings ada — kalau belum (mis. migrasi seed
     * belum jalan, atau row terhapus manual) bikin sekarang. Idempotent.
     */
    private function settingRow(): AppSetting
    {
        return AppSetting::firstOrCreate(
            [],
            ['app_name' => config('app.name', 'Scaner Toko'), 'logo_path' => null]
        );
    }
}
