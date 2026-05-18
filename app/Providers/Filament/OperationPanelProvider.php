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
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Filament\Resources\SmeCategories\SmeCategoryResource;
use App\Filament\Resources\SmeItems\SmeItemsResource;
use App\Filament\Resources\SmeItemVariants\SmeItemVariantsResource;
use App\Filament\Resources\SmeRestocks\SmeRestocksResource;
use App\Filament\Resources\SmePurchaseOrders\SmePurchaseOrderResource;
use App\Filament\Resources\ForDeliveryReceipts\ForDeliveryReceiptResource;
use Filament\Navigation\NavigationGroup;


class OperationPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('operation')
            ->path('operation')
            ->login(false)
            ->userMenuItems([
                'logout' => MenuItem::make()
                    ->url(fn() => route('logout')),
            ])  
            ->colors([
                'primary' => Color::Amber,
            ])
            ->resources([
                SmeCategoryResource::class,
                SmeItemsResource::class,
                SmeItemVariantsResource::class,
                SmeRestocksResource::class,
                SmePurchaseOrdersResource::class,
                ForDeliveryReceiptResource::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->discoverResources(in: app_path('Filament/Operation/Resources'), for: 'App\Filament\Operation\Resources')
            ->discoverPages(in: app_path('Filament/Operation/Pages'), for: 'App\Filament\Operation\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Operation/Widgets'), for: 'App\Filament\Operation\Widgets')
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
            ]);
    }
}
