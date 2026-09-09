<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

final class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        // Section 1: User Account Profile (Spans 2 Columns)
                        Section::make('OPERATOR IDENTITY & ROLE')
                            ->icon(Heroicon::User)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('FULL NAME')
                                            ->weight(FontWeight::Bold)
                                            ->size('lg'),

                                        TextEntry::make('email')
                                            ->label('EMAIL ADDRESS')
                                            ->icon(Heroicon::Envelope)
                                            ->copyable(),

                                        TextEntry::make('role')
                                            ->label('SYSTEM ACCESS ROLE')
                                            ->badge(), // Native HasLabel, HasColor, HasIcon rendering

                                        TextEntry::make('created_at')
                                            ->label('REGISTERED DATE')
                                            ->dateTime('M d, Y H:i')
                                            ->color('gray'),
                                    ]),
                            ])
                            ->columnSpan(2),

                        // Section 2: Summary Metrics (Spans 1 Column)
                        Section::make('PERMISSIONS SUMMARY')
                            ->icon(Heroicon::ShieldCheck)
                            ->schema([
                                TextEntry::make('warehouses_count')
                                    ->counts('warehouses')
                                    ->label('AUTHORIZED LOCATIONS')
                                    ->badge()
                                    ->color('info')
                                    ->icon(Heroicon::BuildingOffice),
                            ])
                            ->columnSpan(1),

                        // Section 3: Assigned Physical Warehouses Directory (Full Width)
                        Section::make('ASSIGNED PHYSICAL WAREHOUSES')
                            ->icon(Heroicon::BuildingOffice2)
                            ->schema([
                                RepeatableEntry::make('warehouses')
                                    ->label('')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('code')
                                                    ->label('BRANCH CODE')
                                                    ->weight(FontWeight::Bold)
                                                    ->icon(Heroicon::Hashtag)
                                                    ->color('primary'),

                                                TextEntry::make('name')
                                                    ->label('WAREHOUSE NAME')
                                                    ->weight(FontWeight::Bold),

                                                TextEntry::make('location')
                                                    ->label('PHYSICAL ADDRESS')
                                                    ->placeholder('No Address Registered')
                                                    ->icon(Heroicon::MapPin),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
