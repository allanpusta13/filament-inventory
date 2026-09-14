<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedgers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LossLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transferRequisition.reference_code')
                    ->label('REQUISITION REF')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('primary'),

                TextColumn::make('productVariant.sku')
                    ->label('SKU')
                    ->fontFamily('mono')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('productVariant.name')
                    ->label('VARIANT NAME')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('warehouse.name')
                    ->label('WAREHOUSE')
                    ->sortable(),

                TextColumn::make('loss_category')
                    ->label('LOSS CATEGORY')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'shortfall' => 'danger',
                        'damage' => 'warning',
                        'spoilage' => 'gray',
                        'theft' => 'danger',
                        'other' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('lost_base_qty')
                    ->label('LOST (BASE)')
                    ->numeric()
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('damaged_base_qty')
                    ->label('DAMAGED (BASE)')
                    ->numeric()
                    ->sortable()
                    ->color('warning'),

                TextColumn::make('total_financial_loss')
                    ->label('TOTAL FINANCIAL LOSS')
                    ->money(config('app.currency'))
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color('danger'),

                TextColumn::make('recorded_at')
                    ->label('RECORDED AT')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('recordedBy.name')
                    ->label('RECORDED BY')
                    ->placeholder('Unknown'),
            ])
            ->filters([
                SelectFilter::make('loss_category')
                    ->options([
                        'shortfall' => 'Shortfall',
                        'damage' => 'Damage',
                        'spoilage' => 'Spoilage',
                        'theft' => 'Theft',
                        'other' => 'Other',
                    ])
                    ->label('LOSS CATEGORY'),

                SelectFilter::make('warehouse_id')
                    ->label('WAREHOUSE')
                    ->relationship('warehouse', 'name'),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
