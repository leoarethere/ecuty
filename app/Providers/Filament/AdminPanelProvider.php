<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\Widgets;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Support\HtmlString;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Http\Middleware\Authenticate;
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

            // === KONFIGURASI LOGO & NAMA ===
            ->brandName('E-Cuti TVRI Yogyakarta') // Teks Alt jika gambar gagal muat
            ->brandLogo(fn () => new HtmlString('
                <div class="flex items-center gap-x-1">
                    <img 
                        src="'. asset('img/logodark.png') .'" 
                        alt="Logo" 
                        class="h-10" 
                        style="height: 3rem;"
                    >
                    <span class="font-bold text-2xl text-gray-950 dark:text-white">
                        Yogyakarta
                    </span>
                </div>
            '))
            ->favicon(asset('img/favicon.png'))
            // ===============================
            
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
        // 1. Hook untuk Footer Dashboard (Yang sudah Anda buat sebelumnya)
        FilamentView::registerRenderHook(
            PanelsRenderHook::CONTENT_END,
            fn (): string => Blade::render("@include('filament.layout.footer')")
        );

        // 2. === MODIFIKASI TAMPILAN LOGIN ===
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '
                <style>
                    /* Hanya terapkan di halaman login */
                    .fi-body.fi-panel-admin.fi-page-login {
                        /* Ganti URL di bawah dengan gambar background Anda */
                        background-image: url("'. asset('img/loginbanner.jpeg') .'"); 
                        background-size: cover;
                        background-position: center;
                        background-repeat: no-repeat;
                    }

                    /* Membuat efek overlay gelap agar tulisan terbaca (Opsional) */
                    .fi-body.fi-panel-admin.fi-page-login::before {
                        content: "";
                        position: absolute;
                        top: 0; right: 0; bottom: 0; left: 0;
                        background: rgba(0, 0, 0, 0.5); /* Hitam transparan 50% */
                        z-index: -1;
                    }

                    /* Membuat Kotak Login jadi Putih Transparan (Glassmorphism) */
                    .fi-page-login .fi-simple-main-ctn {
                        background-color: rgba(255, 255, 255, 0.9) !important; /* Putih 90% */
                        backdrop-filter: blur(10px); /* Efek blur di belakang kotak */
                        border-radius: 1rem;
                        padding: 2rem;
                        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                    }

                    /* Penyesuaian Dark Mode (Jika aktif) */
                    .dark .fi-page-login .fi-simple-main-ctn {
                        background-color: rgba(17, 24, 39, 0.9) !important; /* Hitam 90% */
                        border: 1px solid rgba(255,255,255,0.1);
                    }
                </style>
            '
        );

        // 3. Menambahkan Teks Copyright di Bawah Tombol Login
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): string => '
                <div class="text-center text-gray-500 dark:text-gray-400 mt-4">
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Copyright ' . date('Y') . ' TVRI Stasiun D.I. Yogyakarta | All rights reserved.
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        <span>&lt;/&gt;</span>
                        Dikembangkan oleh :
                        <a href="https://www.instagram.com/leoarethere/" target="_blank" class="underline hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                            Leonardo Putra Susanto
                        </a>
                        &amp;
                        <a href="https://www.instagram.com/destywahyu01/" target="_blank" class="underline hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                            Desty Wahyu Anjani
                        </a>
                    </p>
                </div>
            '
        );
    }
}