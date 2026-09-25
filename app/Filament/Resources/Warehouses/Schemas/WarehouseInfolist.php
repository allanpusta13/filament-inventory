<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class WarehouseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make('WAREHOUSE PROFILE')
                    ->icon(Heroicon::BuildingOffice)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('code')->label(__('resources.warehouses.fields.code'))
                            ->weight(FontWeight::Bold)->size('lg')->copyable()->color('primary')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('name')->label(__('resources.warehouses.fields.name'))
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                        TextEntry::make('location')->label(__('resources.warehouses.fields.location'))->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('STATUS')
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('is_active')
                            ->label(__('resources.warehouses.fields.is_active'))->badge()
                            ->color(fn (bool $state) => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn (bool $state) => $state ? __('common.active') : __('common.inactive')),
                        TextEntry::make('users_count')
                            ->label(__('resources.warehouses.fields.assigned_staff'))
                            ->state(fn ($record) => $record->users()->count()),
                    ]),

                Section::make('ASSIGNED STAFF')
                    ->icon(Heroicon::UserGroup)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('users')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                                    TextEntry::make('name')->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('email')->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                    TextEntry::make('role')->badge()->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                ]),
                            ])
                            ->placeholder(__('resources.warehouses.empty_staff')),
                    ]),
            ]),
        ]);
    }
}
