<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\Widgets;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
// --- TAMBAHKAN 2 IMPORT INI ---
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
// ------------------------------
use Filament\Http\Middleware\Authenticate; // <-- Dan ini
use Filament\Support\Facades\FilamentView;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()

            // === TAMBAHKAN BARIS INI ===
            ->brandName('E-Cuti TVRI Yogyakarta')
            // ->brandLogo(asset('images/logo-tvri.png')) 
            // ->brandLogoHeight('3rem') // Atur tinggi logo
            // ===========================
            
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                // Pages\Dashboard::class,
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            // 🔴 HAPUS ATAU GANTI BARIS INI:
            // PanelsRenderHook::FOOTER,
            
            // 🟢 GANTI MENJADI INI:
            PanelsRenderHook::CONTENT_END,
            
            // Konten tetap sama
            fn (): string => Blade::render("@include('filament.layout.footer')")
        );
    }
}