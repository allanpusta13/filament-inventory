<?php

declare(strict_types=1);

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('name')
                            ->weight(FontWeight::Bold)
                            ->searchable()->sortable(),

                        TextColumn::make('is_active')
                            ->label(__('resources.suppliers.table.status'))
                            ->badge()->alignEnd()
                            ->formatStateUsing(fn (bool $state) => $state ? __('common.active') : __('common.inactive'))
                            ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                    ])->from('md'),

                    TextColumn::make('contact_person')
                        ->label(__('resources.suppliers.table.contact'))
                        ->icon(Heroicon::User)->iconColor('gray')
                        ->searchable()->placeholder('—'),

                    Split::make([
                        TextColumn::make('phone')
                            ->icon(Heroicon::Phone)->iconColor('gray')
                            ->copyable()->placeholder('—'),

                        TextColumn::make('email')
                            ->icon(Heroicon::Envelope)->iconColor('gray')
                            ->copyable()->placeholder('—'),
                    ])->from('md'),

                    TextColumn::make('purchase_orders_count')
                        ->label(__('resources.suppliers.table.purchase_orders'))
                        ->counts('purchaseOrders')
                        ->badge()->color('primary')->numeric(),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                TernaryFilter::make('is_active'),
                TrashedFilter::make(),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->modalWidth(Width::Large),

                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('delete'),

                RestoreAction::make()
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->authorize('restore'),

                ForceDeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('forceDelete')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ]);
    }
}
