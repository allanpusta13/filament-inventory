<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Tables;

use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RevisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('PROPOSED ON')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('PROPOSED BY')
                    ->weight(FontWeight::Bold),

                TextColumn::make('productVariant.sku')
                    ->label('ORIGINAL SKU'),

                TextColumn::make('substituteProductVariant.sku')
                    ->label('SUBSTITUTE SKU')
                    ->badge()
                    ->color('warning')
                    ->placeholder('No Swap'),

                TextColumn::make('proposed_qty')
                    ->label('PROPOSED VOLUME')
                    ->state(fn ($record) => "{$record->proposed_qty} {$record->proposed_unit_name}")
                    ->alignRight(),

                TextColumn::make('negotiation_reason')
                    ->label('COMPLIANCE REASON')
                    ->wrap()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
