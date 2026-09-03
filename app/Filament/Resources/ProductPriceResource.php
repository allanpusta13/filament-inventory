<?php

declare(strict_types=1);

namespace App\Filament\Resources;

// use App\Filament\Resources\ProductPrices\Pages\CreateProductPrice;
// use App\Filament\Resources\ProductPrices\Pages\EditProductPrice;
// use App\Filament\Resources\ProductPrices\Pages\ListProductPrices;
use App\Models\ProductPrice;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

final class ProductPriceResource extends Resource
{
    protected static ?string $model = ProductPrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'unit_name';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Price Configuration')->schema([
                    Grid::make(2)->schema([
                        Select::make('variant_id')
                            ->relationship('variant', 'sku')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->sku} - {$record->name}"),
                        Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('Global (null = default price)'),
                        Select::make('unit_name')
                            ->options(fn () => \App\Models\ProductUnitConversion::distinct()->pluck('unit_name', 'unit_name')->toArray())
                            ->searchable()
                            ->required(),
                        Select::make('price_type')
                            ->options(['cost' => 'Cost', 'sale' => 'Sale'])
                            ->required(),
                        TextInput::make('price')
                            ->numeric()
                            ->required()
                            ->prefix('₱')
                            ->minValue(0),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with([
                'variant',
                'warehouse',
            ]))
            ->columns([
                TextColumn::make('variant.sku')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ProductPrice $record): string => $record->variant?->name ?? ''),
                TextColumn::make('variant.name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->default('Global')
                    ->sortable(),
                TextColumn::make('unit_name')
                    ->sortable(),
                TextColumn::make('price_type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'cost' ? 'gray' : 'success')
                    ->sortable(),
                TextColumn::make('price')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('price_type')
                    ->options(['cost' => 'Cost', 'sale' => 'Sale']),
                SelectFilter::make('warehouse_id')
                    ->relationship('warehouse', 'name')
                    ->placeholder('All Warehouses'),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            // TODO: Fix the routes for the ProductPriceResource pages. The current routes are commented out and need to be updated to match the correct page classes.
            // 'index' => ListProductPrices::route('/'),
            // 'create' => CreateProductPrice::route('/create'),
            // 'edit' => EditProductPrice::route('/{record}/edit'),
        ];
    }
}
