<?php

use App\Http\Controllers\AppSettingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ComboMappingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveReportController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderImportController;
use App\Http\Controllers\OrderReportController;
use App\Http\Controllers\PackingReportController;
use App\Http\Controllers\PdfImportController;
use App\Http\Controllers\PlatformDeductionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\ReturnReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VariantController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Authenticated
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Packing (admin+packing)
    Route::middleware('role:admin,packing')->group(function () {
        Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
        Route::post('/scan/lookup', [ScanController::class, 'lookup'])->name('scan.lookup');
        Route::post('/scan/confirm', [ScanController::class, 'confirm'])->name('scan.confirm');

        // Laporan packing — bisa diakses oleh user packing juga (bukan admin only)
        Route::get('reports/packing', [PackingReportController::class, 'index'])->name('reports.packing');
        Route::get('reports/packing/export', [PackingReportController::class, 'export'])->name('reports.packing.export');
        Route::get('reports/packing/export-pdf', [PackingReportController::class, 'exportPdf'])->name('reports.packing.export_pdf');

        // Hapus riwayat packing — destruktif, admin only.
        Route::delete('reports/packing/clear', [PackingReportController::class, 'destroyByFilter'])
            ->middleware('role:admin')
            ->name('reports.packing.clear');
        Route::delete('reports/packing/selected', [PackingReportController::class, 'destroySelected'])
            ->middleware('role:admin')
            ->name('reports.packing.destroy_selected');
    });

    // Admin only
    Route::middleware('role:admin')->group(function () {
        // Products & Variants
        Route::resource('products', ProductController::class);
        Route::post('products/{product}/variants', [VariantController::class, 'store'])->name('variants.store');
        Route::put('variants/{variant}', [VariantController::class, 'update'])->name('variants.update');
        Route::delete('variants/{variant}', [VariantController::class, 'destroy'])->name('variants.destroy');
        Route::post('variants/{variant}/adjust', [VariantController::class, 'adjust'])->name('variants.adjust');

        // Input Barang Masuk (bulk stock-in)
        Route::get('stock-in', [StockInController::class, 'create'])->name('stock_in.create');
        Route::post('stock-in', [StockInController::class, 'store'])->name('stock_in.store');

        // Kelola Potongan Platform
        Route::resource('platform-deductions', PlatformDeductionController::class)
            ->parameters(['platform-deductions' => 'platform_deduction'])
            ->names('platform_deductions')
            ->except(['show']);

        // Orders
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/export', [OrderController::class, 'export'])->name('orders.export');
        Route::get('orders/export-pdf', [OrderController::class, 'exportPdf'])->name('orders.export_pdf');
        Route::delete('orders/bulk-destroy', [OrderController::class, 'bulkDestroy'])->name('orders.bulk_destroy');
        Route::view('orders/info-rumus', 'orders.info_rumus')->name('orders.info_rumus');
        Route::get('orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
        Route::put('orders/{order}', [OrderController::class, 'update'])->name('orders.update');
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::post('orders/{order}/meta', [OrderController::class, 'updateMeta'])->name('orders.update_meta');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update_status');
        Route::post('orders/{order}/potongan', [OrderController::class, 'updatePotongan'])->name('orders.update_potongan');

        // Import
        Route::get('orders-import', [OrderImportController::class, 'show'])->name('orders.import.show');
        Route::post('orders-import', [OrderImportController::class, 'import'])->name('orders.import');
        Route::get('orders-import/template', [OrderImportController::class, 'template'])->name('orders.import.template');

        // Import PDF label
        Route::get('orders-import-pdf', [PdfImportController::class, 'show'])->name('orders.import.pdf.show');
        Route::post('orders-import-pdf', [PdfImportController::class, 'upload'])->name('orders.import.pdf.upload');
        Route::delete('orders-import-pdf-all', [PdfImportController::class, 'destroyAll'])->name('orders.import.pdf.destroy_all');
        Route::get('orders-import-pdf/{draft}', [PdfImportController::class, 'preview'])->name('orders.import.pdf.preview');
        Route::post('orders-import-pdf/{draft}/commit', [PdfImportController::class, 'commit'])->name('orders.import.pdf.commit');
        Route::post('orders-import-pdf/{draft}/remap', [PdfImportController::class, 'remap'])->name('orders.import.pdf.remap');
        Route::post('orders-import-pdf/{draft}/quick-mapping', [PdfImportController::class, 'quickMapping'])->name('orders.import.pdf.quick_mapping');
        Route::delete('orders-import-pdf/{draft}', [PdfImportController::class, 'discard'])->name('orders.import.pdf.discard');
        Route::delete('orders-import-pdf/{draft}/destroy', [PdfImportController::class, 'destroy'])->name('orders.import.pdf.destroy');

        // Combo Mapping
        Route::resource('combo-mappings', ComboMappingController::class)
            ->parameters(['combo-mappings' => 'combo_mapping'])
            ->names('combo_mappings')
            ->except(['show']);

        // Reports
        Route::get('reports/products', [ProductReportController::class, 'index'])->name('reports.products');
        Route::delete('reports/products/clear', [ProductReportController::class, 'destroyByFilter'])->name('reports.products.clear');
        Route::delete('reports/products/selected', [ProductReportController::class, 'destroySelected'])->name('reports.products.destroy_selected');
        Route::get('reports/stock', [StockReportController::class, 'index'])->name('reports.stock');
        Route::get('reports/stock/export', [StockReportController::class, 'export'])->name('reports.stock.export');
        Route::get('reports/orders', [OrderReportController::class, 'index'])->name('reports.orders');
        Route::get('reports/returns', [ReturnReportController::class, 'index'])->name('reports.returns');
        Route::get('reports/returns/export', [ReturnReportController::class, 'export'])->name('reports.returns.export');
        Route::delete('reports/returns/{order}', [ReturnReportController::class, 'destroy'])->name('reports.returns.destroy');

        // Laporan Live — rekap penjualan per Host Live.
        Route::get('reports/live', [LiveReportController::class, 'index'])->name('reports.live');
        Route::get('reports/live/export', [LiveReportController::class, 'export'])->name('reports.live.export');

        // Return Management
        Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
        Route::post('returns/mark', [ReturnController::class, 'markReturn'])->name('returns.mark');
        Route::post('returns/manual', [ReturnController::class, 'storeManual'])->name('returns.manual');
        Route::post('returns/{order}/undo', [ReturnController::class, 'undoReturn'])->name('returns.undo');
        Route::post('returns/{order}/receive', [ReturnController::class, 'receiveItems'])->name('returns.receive');

        // Users
        Route::resource('users', UserController::class)->except(['show']);

        // Pengaturan aplikasi (nama & logo branding) — singleton.
        Route::get('settings', [AppSettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [AppSettingController::class, 'update'])->name('settings.update');
    });
});
