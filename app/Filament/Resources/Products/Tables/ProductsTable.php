<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\Products\Actions\EditProductFamilyAction;
use App\Filament\Resources\Products\Actions\ManageUnitConversionsAction;
use App\Filament\Resources\Products\Actions\QuickStockAdjustmentAction;
use App\Filament\Resources\Products\Actions\SetCurrentPriceAction;
use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductVariantPrice;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
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
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('sku')
                            ->label(__('resources.products.table.sku'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable()
                            ->copyMessage(__('common.copied')),

                        TextColumn::make('currentPrice.sale_price')
                            ->label(__('resources.products.table.sale_price'))
                            ->formatStateUsing(fn ($state): string => format_money($state, 2))
                            ->weight(FontWeight::Bold)
                            ->alignEnd()
                            ->sortable(query: function (Builder $query, string $direction): Builder {
                                return $query->orderBy(
                                    ProductVariantPrice::query()
                                        ->select('sale_price')
                                        ->whereColumn('product_variant_id', 'product_variants.id')
                                        ->where('is_current', true)
                                        ->limit(1),
                                    $direction
                                );
                            }),
                    ])->from('md'),

                    TextColumn::make('name')
                        ->label(__('resources.products.table.name'))
                        ->searchable()
                        ->sortable()
                        ->limit(50)
                        ->weight(FontWeight::SemiBold),

                    Split::make([
                        TextColumn::make('product.name')
                            ->label(__('resources.products.table.family'))
                            ->badge()
                            ->color('gray')
                            ->searchable(),

                        TextColumn::make('base_unit_name')
                            ->label(__('resources.products.table.base_unit'))
                            ->badge()
                            ->color('info'),

                        TextColumn::make('reorder_point')
                            ->label(__('resources.products.table.reorder_point'))
                            ->badge()
                            ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                            ->numeric(),
                    ])->from('md'),

                    IconColumn::make('is_active')
                        ->label(__('resources.products.table.status'))
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
                TernaryFilter::make('is_active')
                    ->label(__('resources.products.filters.is_active')),
                SelectFilter::make('product_id')
                    ->label(__('resources.products.filters.product_family'))
                    ->relationship('product', 'name')
                    ->searchable(),
                TrashedFilter::make(),
            ])
            ->defaultSort('sku')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn ($record) => ProductResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->modalWidth(Width::Large),

                ActionGroup::make([
                    // ── Section: Catalog operations ───────────────────────────
                    ActionGroup::make([
                        SetCurrentPriceAction::make(),

                        EditProductFamilyAction::make()
                            ->icon(Heroicon::FolderOpen),

                        ManageUnitConversionsAction::make()
                            ->icon(Heroicon::Scale),

                        QuickStockAdjustmentAction::make(),
                    ])->dropdown(false),

                    // ── Section: Destructive ──────────────────────────────────
                    ActionGroup::make([
                        DeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('delete'),

                        RestoreAction::make()
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->authorize('restore'),
                    ])->dropdown(false),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->iconButton()
                    ->size(Size::Small)
                    ->color('gray')
                    ->tooltip(__('resources.products.actions.more_actions'))
                    ->dropdownAutoPlacement()
                    ->dropdownWidth(Width::Large),
            ]);
    }
}
