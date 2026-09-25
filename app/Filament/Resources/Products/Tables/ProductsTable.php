<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\Actions\EditProductFamilyAction;
use App\Filament\Resources\Products\Actions\ManageUnitConversionsAction;
use App\Filament\Resources\Products\Actions\QuickStockAdjustmentAction;
use App\Filament\Resources\Products\Actions\SetCurrentPriceAction;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('sku')
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable()
                            ->copyMessage(__('common.copied')),

                        TextColumn::make('currentPrice.sale_price')
                            ->money(config('app.currency'))
                            ->weight(FontWeight::Bold)
                            ->alignEnd()
                            ->sortable(),
                    ])->from('md'),

                    TextColumn::make('name')
                        ->searchable()
                        ->sortable()
                        ->limit(50)
                        ->weight(FontWeight::SemiBold),

                    Split::make([
                        TextColumn::make('product.name')
                            ->badge()
                            ->color('gray')
                            ->searchable(),

                        TextColumn::make('base_unit_name')
                            ->badge()
                            ->color('info'),

                        TextColumn::make('reorder_point')
                            ->badge()
                            ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                            ->numeric(),
                    ])->from('md'),

                    IconColumn::make('is_active')
                        ->boolean()
                        ->trueIcon(Heroicon::CheckCircle)
                        ->falseIcon(Heroicon::XCircle)
                        ->trueColor('success')
                        ->falseColor('danger'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                SelectFilter::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->defaultSort('sku')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn ($record) => ProductResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->modalWidth(Width::Large),

                SetCurrentPriceAction::make()
                    ->icon(Heroicon::CurrencyDollar)
                    ->color('primary'),

                EditProductFamilyAction::make()
                    ->icon(Heroicon::FolderOpen),

                ManageUnitConversionsAction::make()
                    ->icon(Heroicon::Scale),

                QuickStockAdjustmentAction::make()
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->color('warning'),

                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('delete'),

                RestoreAction::make()
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->authorize('restore'),
            ]);
    }
}
