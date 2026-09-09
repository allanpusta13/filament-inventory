<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('FULL NAME')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('EMAIL ADDRESS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('CREATED')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('UPDATED')
                    ->dateTime()
                    ->sortable(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
