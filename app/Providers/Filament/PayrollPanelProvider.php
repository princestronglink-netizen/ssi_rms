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
use App\Filament\Resources\Billings\BillingResource;
use App\Filament\Resources\SmeBillings\SmeBillingResource;
use App\Filament\Resources\UniformIssuanceBillings\UniformIssuanceBillingResource;
use Filament\Navigation\NavigationGroup;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;

class PayrollPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('payroll')
            ->path('payroll')
            ->login(false)
            ->userMenuItems([
                'logout' => MenuItem::make()
                    ->url(fn() => route('logout')),
            ])  
            ->colors([
                'primary' => Color::Blue,
            ])
            ->resources([
               BillingResource::class,
               UniformIssuanceBillingResource::class,
               SmeBillingResource::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->discoverResources(in: app_path('Filament/Payroll/Resources'), for: 'App\Filament\Payroll\Resources')
            ->discoverPages(in: app_path('Filament/Payroll/Pages'), for: 'App\Filament\Payroll\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Payroll/Widgets'), for: 'App\Filament\Payroll\Widgets')
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
                    ->label('Billing Management')
                    ->collapsed(false), 
            ]);
    }
}
