<?php

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        // Bust cache singleton AppSetting tiap kali ada update/delete
        // supaya nav, login, dan title langsung pakai nilai terbaru
        // tanpa restart server.
        AppSetting::saved(fn () => AppSetting::flushCache());
        AppSetting::deleted(fn () => AppSetting::flushCache());

        // Share $brand ke SEMUA view supaya nav & login & layout bisa
        // langsung print {{ $brand->app_name }} / $brand->logoUrl()
        // tanpa harus inject manual di tiap controller.
        View::composer('*', function ($view) {
            $view->with('brand', AppSetting::current());
        });
    }
}
