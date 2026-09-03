<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\InTransitResource\Pages;
use App\Models\InTransit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InTransitResource extends Resource
{
    protected static ?string $model = InTransit::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static bool $canCreate = false;

    protected static bool $canEdit = false;

    protected static bool $canDelete = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requisition.reference_code')
                    ->label('Requisition Code')
                    ->searchable(),
                TextColumn::make('requisition.fromWarehouse.name')
                    ->label('From Warehouse')
                    ->searchable(),
                TextColumn::make('requisition.toWarehouse.name')
                    ->label('To Warehouse')
                    ->searchable(),
                TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('dispatched_base_qty')
                    ->label('Dispatched Qty')
                    ->numeric()
                    ->summarize(Sum::make()->label('Total In Transit')),
                TextColumn::make('dispatched_at')
                    ->label('Dispatched At')
                    ->dateTime(),
                TextColumn::make('dispatched_at')
                    ->label('Elapsed Duration')
                    ->formatStateUsing(fn ($state) => now()->diffInHours($state).' hours ago')
                    ->color('warning'),
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
                $user = auth()->user();

                return $query
                    ->with(['requisition.fromWarehouse', 'requisition.toWarehouse', 'variant'])
                    ->where('status', 'in_transit')
                    ->when(! $user->isAdmin() && ! $user->isAuditor(), function ($query) use ($user) {
                        // Restrict to shipments involving the worker's warehouse
                        $warehouseIds = $user->warehouses->pluck('id');
                        $query->whereHas('requisition', function ($q) use ($warehouseIds) {
                            $q->whereIn('from_warehouse_id', $warehouseIds)
                                ->orWhereIn('to_warehouse_id', $warehouseIds);
                        });
                    });
            });
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
            'index' => Pages\ListInTransits::route('/'),
            'create' => Pages\CreateInTransit::route('/create'),
            'edit' => Pages\EditInTransit::route('/edit/{record}'),
            'view' => Pages\ViewInTransit::route('/view/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::getModel()::count();
    }
}
