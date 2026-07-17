<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PackingLog;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->isPacking()) {
            return redirect()->route('scan.index');
        }

        $stats = [
            'total_products' => Product::count(),
            'total_variants' => Variant::count(),
            'total_stock' => (int) Variant::sum('stock'),
            'pending_orders' => Order::where('status', Order::STATUS_PENDING)->count(),
            'packed_today' => Order::where('status', Order::STATUS_PACKED)
                ->whereDate('packed_at', today())
                ->count(),
            'low_stock' => Variant::whereColumn('stock', '<=', 'min_stock')->count(),
        ];

        $recent = PackingLog::with(['user', 'order'])
            ->latest('scanned_at')
            ->limit(10)
            ->get();

        $lowStock = Variant::with('product')
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit(10)
            ->get();

        return view('dashboard', compact('stats', 'recent', 'lowStock'));
    }
}
