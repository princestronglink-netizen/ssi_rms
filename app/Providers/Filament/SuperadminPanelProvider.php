<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Pages\PosOfficeSupplyRequest;
use App\Filament\Pages\UniformStockFlow;
use App\Filament\Pages\SmeStockFlow;

class SuperadminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('superadmin')
            ->path('superadmin')
            ->login(false)
            ->userMenuItems([
                'logout' => MenuItem::make()
                    ->url(fn() => route('logout')),
            ])  
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('User Management'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                PosOfficeSupplyRequest::class,
                UniformStockFlow::class,
                SmeStockFlow::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ->viteTheme('resources/css/filament/theme.css')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Organizations')
                    ->collapsed(false), 
                NavigationGroup::make()
                    ->label('Delivery Receipt')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Item Setup')
                    ->collapsed(false), 
                NavigationGroup::make()
                    ->label('Distributions')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Stock & Inventory')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Billing Management')
                    ->collapsed(false), 
                NavigationGroup::make()
                    ->label('Reports')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('User Management')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Filament Shield')
                    ->collapsed(false),
                
            ]);
    }
}
