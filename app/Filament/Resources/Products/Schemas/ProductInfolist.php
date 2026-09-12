<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('product.name'),
            TextEntry::make('sku'),
            TextEntry::make('barcode'),
            TextEntry::make('name'),
            TextEntry::make('currentPrice.cost_price')->money(config('app.currency')),
            TextEntry::make('currentPrice.sale_price')->money(config('app.currency')),
            RepeatableEntry::make('unitConversions'),
        ]);
    }
}
