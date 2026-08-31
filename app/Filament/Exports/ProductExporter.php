<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

final class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('sku'),
            ExportColumn::make('name'),
            ExportColumn::make('category'),
            ExportColumn::make('reorder_point'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your product export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
