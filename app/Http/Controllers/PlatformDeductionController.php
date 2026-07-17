<?php

namespace App\Http\Controllers;

use App\Models\PlatformDeduction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformDeductionController extends Controller
{
    public function index(): View
    {
        $deductions = PlatformDeduction::orderBy('platform_name')->get();

        return view('platform_deductions.index', compact('deductions'));
    }

    public function create(): View
    {
        return view('platform_deductions.form', [
            'deduction' => new PlatformDeduction(['is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['is_active'] = $request->boolean('is_active', true);

        PlatformDeduction::create($data);

        return redirect()->route('platform_deductions.index')->with('success', 'Potongan platform dibuat.');
    }

    public function edit(PlatformDeduction $platform_deduction): View
    {
        return view('platform_deductions.form', ['deduction' => $platform_deduction]);
    }

    public function update(Request $request, PlatformDeduction $platform_deduction): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['is_active'] = $request->boolean('is_active', false);

        $platform_deduction->update($data);

        return redirect()->route('platform_deductions.index')->with('success', 'Potongan platform diperbarui.');
    }

    public function destroy(PlatformDeduction $platform_deduction): RedirectResponse
    {
        $platform_deduction->delete();

        return back()->with('success', 'Potongan platform dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        // Tiap field nilai sekarang bisa diisi sebagai Rp ATAU persen, jadi
        // tidak ada lagi batas max:100. Tiap field punya flag *_is_percent
        // (0 = Rp, 1 = %) yang ikut divalidasi sebagai boolean.
        $rules = [
            'platform_name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];

        foreach (PlatformDeduction::FIELD_FLAGS as $valueCol => $flagCol) {
            $rules[$valueCol] = ['nullable', 'numeric', 'min:0'];
            $rules[$flagCol] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);

        // Normalisasi flag ke boolean tegas (select mengirim "0"/"1").
        foreach (PlatformDeduction::FIELD_FLAGS as $flagCol) {
            $data[$flagCol] = $request->boolean($flagCol);
        }

        return $data;
    }
}
