<?php

namespace App\Http\Controllers;

use App\Models\ComboMapping;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ComboMappingController extends Controller
{
    public function index(): View
    {
        $mappings = ComboMapping::with('items.variant.product')
            ->orderBy('keyword')
            ->paginate(20);

        return view('combo_mappings.index', compact('mappings'));
    }

    public function create(): View
    {
        $variants = Variant::with('product')->orderBy('id')->get();

        return view('combo_mappings.form', [
            'mapping' => new ComboMapping(),
            'variants' => $variants,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validateRequest($request);

        DB::transaction(function () use ($request) {
            $mapping = ComboMapping::create([
                'keyword' => $request->input('keyword'),
                'description' => $request->input('description'),
            ]);

            $this->syncItems($mapping, (array) $request->input('items', []));
        });

        return redirect()->route('combo_mappings.index')->with('success', 'Combo mapping dibuat.');
    }

    public function edit(ComboMapping $combo_mapping): View
    {
        $combo_mapping->load('items');
        $variants = Variant::with('product')->orderBy('id')->get();

        return view('combo_mappings.form', [
            'mapping' => $combo_mapping,
            'variants' => $variants,
        ]);
    }

    public function update(Request $request, ComboMapping $combo_mapping): RedirectResponse
    {
        $this->validateRequest($request, $combo_mapping->id);

        DB::transaction(function () use ($request, $combo_mapping) {
            $combo_mapping->update([
                'keyword' => $request->input('keyword'),
                'description' => $request->input('description'),
            ]);

            $combo_mapping->items()->delete();
            $this->syncItems($combo_mapping, (array) $request->input('items', []));
        });

        return redirect()->route('combo_mappings.index')->with('success', 'Combo mapping diperbarui.');
    }

    public function destroy(ComboMapping $combo_mapping): RedirectResponse
    {
        $combo_mapping->delete();

        return back()->with('success', 'Combo mapping dihapus.');
    }

    private function validateRequest(Request $request, ?int $ignoreId = null): void
    {
        $request->validate([
            'keyword' => ['required', 'string', 'max:150',
                'unique:combo_mappings,keyword'.($ignoreId ? ",{$ignoreId}" : '')],
            'description' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }

    private function syncItems(ComboMapping $mapping, array $items): void
    {
        foreach ($items as $it) {
            if (empty($it['variant_id'])) {
                continue;
            }
            $mapping->items()->create([
                'variant_id' => (int) $it['variant_id'],
                'quantity' => max(1, (int) ($it['quantity'] ?? 1)),
            ]);
        }
    }
}
