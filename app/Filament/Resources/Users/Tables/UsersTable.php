<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()->sortable()->weight('bold'),

                TextColumn::make('email')
                    ->searchable()->copyable()->visibleFrom('md'),

                TextColumn::make('role')
                    ->badge()->sortable(),

                TextColumn::make('warehouses_count')
                    ->label(__('resources.users.table.warehouses'))
                    ->counts('warehouses')
                    ->numeric()->badge()->color('gray')->alignEnd(),

                IconColumn::make('is_active')
                    ->label(__('resources.users.table.active'))
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('created_at')
                    ->label(__('resources.users.table.created'))
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('role')->options(\App\Enums\UserRole::class),
                SelectFilter::make('warehouse_id')
                    ->label(__('resources.users.filters.warehouse'))
                    ->relationship('warehouses', 'name')
                    ->searchable(),
                TernaryFilter::make('is_active'),
            ])
            ->defaultSort('name')
            ->stackedOnMobile()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->modalWidth(Width::Large)
                    ->authorize('update'),

                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('delete'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->icon(Heroicon::Trash)->authorize('deleteAny'),
                ]),
            ]);
    }
}
