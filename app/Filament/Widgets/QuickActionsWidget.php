<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * QuickActionsWidget — static shortcut buttons.
 *
 * Visibility: all authenticated users.
 * No cache — static shortcut buttons, no `cacheKey()` / `cacheTtl()`.
 * Column span: `['default' => 1, 'md' => 2, 'xl' => 4]`.
 */
class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = ['default' => 1, 'md' => 2, 'xl' => 4];

    protected string $view = 'filament.widgets.quick-actions';

    public static function canView(): bool
    {
        return auth()->user() !== null;
    }

    /**
     * @return array<int, array{label: string, url: string, icon: Heroicon}>
     */
    public static function actions(): array
    {
        return [
            [
                'label' => __('dashboard.quick_actions.new_transfer'),
                'url' => TransferRequisitionResource::getUrl('create'),
                'icon' => Heroicon::ArrowsRightLeft,
            ],
            [
                'label' => __('dashboard.quick_actions.new_purchase'),
                'url' => PurchaseOrderResource::getUrl('create'),
                'icon' => Heroicon::ShoppingCart,
            ],
            [
                'label' => __('dashboard.quick_actions.new_sale'),
                'url' => SalesOrderResource::getUrl('create'),
                'icon' => Heroicon::Banknotes,
            ],
            [
                'label' => __('dashboard.quick_actions.new_direct_transfer'),
                'url' => DirectTransferResource::getUrl('create'),
                'icon' => Heroicon::ArrowPath,
            ],
        ];
    }

    protected function getViewData(): array
    {
        return ['actions' => static::actions()];
    }
}
