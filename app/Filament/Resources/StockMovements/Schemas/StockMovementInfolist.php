<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class StockMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 2, 'xl' => 2])->schema([
                Section::make('MOVEMENT')
                    ->icon(Heroicon::QueueList)
                    ->columnSpanFull()
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('created_at')->label(__('resources.stock_movements.fields.timestamp'))->dateTime('M j, Y H:i')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('type')->badge()
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('productVariant.sku')->label(__('resources.stock_movements.fields.sku'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('warehouse.name')->label(__('resources.stock_movements.fields.warehouse'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('quantity')->numeric()
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('unit_name_used')->label(__('resources.stock_movements.fields.unit'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('reference_code')->label(__('resources.stock_movements.fields.reference'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('createdBy.name')->label(__('resources.stock_movements.fields.by'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('notes')->columnSpanFull(),
                    ]),
            ]),
        ]);
    }
}
