<?php

namespace App\Providers;

use App\Models\Cuti;
use App\Policies\CutiPolicy; // <-- Pastikan import ini ada
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paksa Carbon menggunakan Bahasa Indonesia
        config(['app.locale' => 'id']);
        Carbon::setLocale('id');
        Gate::policy(Cuti::class, CutiPolicy::class);
    }
}
