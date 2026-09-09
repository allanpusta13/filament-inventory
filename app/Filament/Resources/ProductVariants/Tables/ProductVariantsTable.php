<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductVariants\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductVariantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->contentGrid([
                'sm' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->columns([
                Stack::make([
                    ImageColumn::make('images')
                        ->extraImgAttributes([
                            'class' => 'h-36 w-full object-cover rounded-lg mb-2',
                        ]),
                    Split::make([
                        TextColumn::make('sku')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->color('primary'),

                        IconColumn::make('is_active')
                            ->boolean(),
                    ]),

                    TextColumn::make('name')
                        ->searchable()
                        ->weight(FontWeight::Bold)
                        ->size('lg'),

                    TextColumn::make('product.name')
                        ->color('gray'),

                    Split::make([
                        TextColumn::make('cost_price')
                            ->state(fn ($record) => 'Cost: $'.number_format($record->cost_price, 4)),

                        TextColumn::make('sale_price')
                            ->state(fn ($record) => 'Sale: $'.number_format($record->sale_price, 4))
                            ->weight(FontWeight::Bold)
                            ->alignRight(),
                    ]),
                ])
                    ->space(2)
                    ->extraAttributes([
                        'class' => 'p-4 rounded-xl border border-zinc-200 bg-white dark:bg-zinc-900 shadow-sm hover:shadow-md transition-shadow',
                    ]),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),

                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
