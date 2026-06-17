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
use App\Filament\Resources\UniformCategories\UniformCategoryResource;
use App\Filament\Resources\Clients\ClientsResource;
use App\Filament\Resources\Positions\PositionsResource;
use App\Filament\Resources\Sites\SitesResource;
use App\Filament\Resources\Transmittals\TransmittalsResource;
use App\Filament\Resources\UniformIssuances\UniformIssuancesResource;
use App\Filament\Resources\UniformItems\UniformItemsResource;
use App\Filament\Resources\UniformItemVariants\UniformItemVariantsResource;
use App\Filament\Resources\UniformRestocks\UniformRestocksResource;
use App\Filament\Resources\UniformSets\UniformSetsResource;
use App\Filament\Resources\ReturnUniformItems\ReturnUniformItemsResource;
use Filament\Navigation\NavigationGroup;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Pages\UniformStockFlow;

class HrPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('hr')
            ->path('hr')
            ->login(false)
            ->userMenuItems([
                'logout' => MenuItem::make()
                    ->url(fn() => route('logout')),
            ])   
            ->colors([
                'primary' => Color::Blue,
            ])
            ->resources([
                UniformCategoryResource::class,
                ClientsResource::class,
                PositionsResource::class,
                SitesResource::class,
                TransmittalsResource::class,
                UniformIssuancesResource::class,
                UniformItemsResource::class,
                UniformItemVariantsResource::class,
                UniformRestocksResource::class,
                UniformSetsResource::class,
                ReturnUniformItemsResource::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            // ->discoverResources(in: app_path('Filament/Hr/Resources'), for: 'App\Filament\Hr\Resources')
            // ->discoverPages(in: app_path('Filament/Hr/Pages'), for: 'App\Filament\Hr\Pages')
            ->pages([
                Dashboard::class,
                UniformStockFlow::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Hr/Widgets'), for: 'App\Filament\Hr\Widgets')
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
                    ->label('Item Setup')
                    ->collapsed(false), 
                NavigationGroup::make()
                    ->label('Distributions')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Stock & Inventory')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Reports')
                    ->collapsed(false),
            ]);
    }
}
