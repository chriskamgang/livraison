<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\OrdersChart;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\PaymentMethodsChart;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('ChillOut Admin')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary'   => Color::Orange,
                'gray'      => Color::Slate,
                'info'      => Color::Blue,
                'success'   => Color::Green,
                'warning'   => Color::Amber,
                'danger'    => Color::Red,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->navigationGroups([
                'Restaurants',
                'Menu',
                'Commandes',
                'Utilisateurs',
                'Parametres',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                StatsOverview::class,
                OrdersChart::class,
                RevenueChart::class,
                PaymentMethodsChart::class,
                LatestOrders::class,
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
            ])
            ->renderHook(
                'panels::body.start',
                fn () => session('impersonator_id')
                    ? new HtmlString('
                        <div style="background: #f59e0b; color: #000; padding: 8px 16px; text-align: center; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 12px;">
                            <span>Vous êtes connecté en tant que : ' . e(auth()->user()?->name) . '</span>
                            <a href="' . route('admin.impersonate.leave') . '" style="background: #000; color: #fff; padding: 4px 12px; border-radius: 6px; text-decoration: none; font-size: 14px;">
                                Revenir à mon compte
                            </a>
                        </div>
                    ')
                    : ''
            );
    }
}
