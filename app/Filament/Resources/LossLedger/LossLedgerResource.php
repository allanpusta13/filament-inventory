<?php

declare(strict_types=1);

namespace App\Filament\Resources\LossLedger;

use App\Models\LossLedger;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class LossLedgerResource extends Resource
{
    protected static ?string $model = LossLedger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static bool $canCreate = false;

    protected static bool $canEdit = false;

    protected static bool $canDelete = false;

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('requisition.reference_code')
                    ->label('Requisition Code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable(),
                Tables\Columns\TextColumn::make('lost_base_qty')
                    ->label('Lost Qty')
                    ->numeric(),
                Tables\Columns\TextColumn::make('damaged_base_qty')
                    ->label('Damaged Qty')
                    ->numeric(),
                Tables\Columns\TextColumn::make('unit_cost_price')
                    ->label('Cost (Base Unit)')
                    ->money('USD', 4),
                Tables\Columns\TextColumn::make('total_financial_loss')
                    ->label('Financial Loss')
                    ->money('USD', 4)
                    ->summarize(Sum::make()->label('Total Loss')->money('USD', 4)),
                Tables\Columns\TextColumn::make('loss_category')
                    ->label('Loss Category')
                    ->searchable(),
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label('Recorded At')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Since it's read-only, we don't define any actions (edit, delete)
            ])
            ->bulkActions([
                // No bulk actions for read-only
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->with(['requisition', 'variant', 'warehouse']);
            });
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['requisition', 'variant', 'warehouse']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLossLedgers::route('/'),
            'view' => Pages\ViewLossLedger::route('/view/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getModel()::count();
    }
}
