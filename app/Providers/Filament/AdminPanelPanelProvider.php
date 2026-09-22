<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\Auth\Login as CustomLogin;
use App\Http\Middleware\EnsureActiveUser;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Permission\Middleware\RoleMiddleware;

class AdminPanelPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('adminPanel')
            ->path('admin')
            ->login(CustomLogin::class)
            ->colors([
                'primary' => Color::Green,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                AdminDashboard::class,
            ])
            ->renderHook(PanelsRenderHook::STYLES_BEFORE, fn () => view('filament.admin.hooks.styles'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.admin.hooks.sidebar-footer'))
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn () => view('filament.admin.hooks.brand'))
            ->renderHook(PanelsRenderHook::TOPBAR_START, fn () => view('filament.admin.hooks.topbar-title'))
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn () => view('filament.admin.hooks.topbar-identity'))
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
                EnsureActiveUser::class,
                RoleMiddleware::class.':admin',
            ]);
    }
}
