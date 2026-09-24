<?php

namespace App\Providers\Filament;

use App\Filament\UserPanel\Pages\Auth\Login as CustomLogin;
use App\Filament\UserPanel\Pages\Auth\Profile;
use App\Filament\UserPanel\Pages\Memberships;
use App\Filament\UserPanel\Pages\UserDashboard;
use App\Http\Middleware\EnsureActiveUser;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Permission\Middleware\RoleMiddleware;

class UserPanelPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('userPanel')
            ->path('user')
            ->login(CustomLogin::class)
            ->profile(Profile::class, isSimple: false)
            ->brandName('Wrongshock')
            ->brandLogo(fn () => view('filament.user-panel.hooks.brand'))
            ->brandLogoHeight('3rem')
            ->sidebarWidth('15.5rem')
            ->colors([
                'primary' => Color::hex('#2F7D5A'),
            ])
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn () => view('filament.user-panel.hooks.styles'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn () => view('filament.user-panel.hooks.topbar-title'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => view('filament.user-panel.hooks.topbar-identity'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn () => view('filament.user-panel.hooks.sidebar-footer'),
            )
            ->discoverPages(in: app_path('Filament/UserPanel/Pages'), for: 'App\\Filament\\UserPanel\\Pages')
            ->pages([
                UserDashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('Profil')
                    ->icon('heroicon-o-user-circle')
                    ->activeIcon('heroicon-s-user-circle')
                    ->isActiveWhen(fn (): bool => request()->routeIs(Profile::getRouteName('userPanel')))
                    ->sort(3)
                    ->url(fn (): string => Profile::getUrl(panel: 'userPanel')),
                NavigationItem::make('Keanggotaan Saya')
                    ->icon('heroicon-o-building-storefront')
                    ->activeIcon('heroicon-s-building-storefront')
                    ->isActiveWhen(fn (): bool => request()->routeIs(Memberships::getRouteName('userPanel')))
                    ->sort(2)
                    ->url(fn (): string => Memberships::getUrl(panel: 'userPanel')),
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('users', fn () => redirect(Profile::getUrl(panel: 'userPanel')))
                    ->name('legacy-profile.index');
                Route::get('users/{record}/edit', fn () => redirect(Profile::getUrl(panel: 'userPanel')))
                    ->name('legacy-profile.edit');
            })
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
                RoleMiddleware::class.':user',
            ]);
    }
}
