<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Infolists\Components\IconEntry;
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
        return $schema
            ->schema([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // Section 1: Physical Warehouse Profile (Left, Spans 2 Columns)
                        Section::make('PHYSICAL WAREHOUSE PROFILE')
                            ->icon(Heroicon::BuildingOffice)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('code')
                                            ->label('BRANCH CODE')
                                            ->weight(FontWeight::Bold)
                                            ->copyable()
                                            ->icon(Heroicon::Hashtag)
                                            ->color('primary'),

                                        TextEntry::make('name')
                                            ->label('WAREHOUSE NAME')
                                            ->weight(FontWeight::Bold)
                                            ->size('lg'),

                                        TextEntry::make('location')
                                            ->label('PHYSICAL ADDRESS')
                                            ->placeholder('No Address Registered')
                                            ->icon(Heroicon::MapPin)
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpan(2),

                        // Section 2: System Status & Operator Metrics (Right, Spans 1 Column)
                        Section::make('SYSTEM & ROUTING STATUS')
                            ->icon(Heroicon::ShieldCheck)
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label('ROUTING ACTIVE')
                                    ->boolean(),

                                TextEntry::make('users_count')
                                    ->counts('users')
                                    ->label('AUTHORIZED OPERATORS')
                                    ->badge()
                                    ->color('info')
                                    ->icon(Heroicon::UserGroup),

                                TextEntry::make('created_at')
                                    ->label('REGISTERED DATE')
                                    ->dateTime('M d, Y H:i')
                                    ->color('gray'),
                            ])
                            ->columnSpan(1),

                        // Section 3: Authorized Operators Directory (Full Width)
                        Section::make('AUTHORIZED OPERATORS DIRECTORY')
                            ->icon(Heroicon::UserGroup)
                            ->schema([
                                RepeatableEntry::make('users')
                                    ->label('')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label('OPERATOR NAME')
                                                    ->weight(FontWeight::Bold)
                                                    ->icon(Heroicon::User),

                                                TextEntry::make('email')
                                                    ->label('EMAIL ADDRESS')
                                                    ->icon(Heroicon::Envelope),

                                                TextEntry::make('role')
                                                    ->label('SYSTEM ROLE')
                                                    ->badge()
                                                    ->color(fn ($state) => match ($state->value ?? $state) {
                                                        'admin' => 'danger',
                                                        'auditor' => 'info',
                                                        'branch_manager' => 'warning',
                                                        default => 'gray',
                                                    }),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
