<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use Filament\Widgets\Widget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class LowStockAlertsWidget extends BaseWidget
{
    protected ?string $heading = 'Low Stock Alerts';

    protected function getView(): string
    {
        return 'widgets.low-stock-alerts';
    }

    protected function getViewData(): array
    {
        return [
            'alerts' => Cache::remember('low.stock.alerts', 300, function () {
                return ProductVariant::query()
                    ->join('warehouse_stocks', 'warehouse_stocks.variant_id', '=', 'product_variants.id')
                    ->join('products', 'products.id', '=', 'product_variants.product_id')
                    ->whereColumn('warehouse_stocks.on_hand_quantity', '<', 'products.reorder_point')
                    ->where('warehouse_stocks.on_hand_quantity', '>=', 0)
                    ->select([
                        'products.sku',
                        'products.name as product_name',
                        'product_variants.sku as variant_sku',
                        'product_variants.name as variant_name',
                        'warehouse_stocks.on_hand_quantity',
                        'products.reorder_point',
                        // Calculate days of supply based on average daily usage (simplified)
                        \DB::raw('ROUND(warehouse_stocks.on_hand_quantity / NULLIF((SELECT AVG(quantity) FROM stock_movements WHERE stock_movements.variant_id = product_variants.id AND stock_movements.created_at >= NOW() - INTERVAL 7 DAY AND quantity < 0), 999999)) as days_supply')
                    ])
                    ->get()
                    ->map(function ($row) {
                        return (array) $row;
                    })
                    ->toArray();
            })
        ];
    }
}