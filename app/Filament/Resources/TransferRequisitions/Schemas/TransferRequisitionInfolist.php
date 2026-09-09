<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class TransferRequisitionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        // Section 1: Requisition Profile (Spans 2 Columns)
                        Section::make('REQUISITION PROFILE')
                            ->icon(Heroicon::DocumentText)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('reference_code')
                                            ->label('REFERENCE CODE')
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->copyable()
                                            ->color('primary'),

                                        TextEntry::make('status')
                                            ->label('OPERATIONAL STATUS')
                                            ->badge(),

                                        TextEntry::make('fromWarehouse.name')
                                            ->label('ORIGIN BRANCH')
                                            ->icon(Heroicon::BuildingOffice),

                                        TextEntry::make('toWarehouse.name')
                                            ->label('RECEIVING BRANCH')
                                            ->icon(Heroicon::BuildingOffice2),
                                    ]),
                            ])
                            ->columnSpan(2),

                        // Section 2: Authorization Sign-Offs (Spans 1 Column)
                        Section::make('AUTHORIZATION SIGN-OFFS')
                            ->icon(Heroicon::ShieldCheck)
                            ->schema([
                                TextEntry::make('requestedBy.name')
                                    ->label('REQUESTED BY')
                                    ->icon(Heroicon::User)
                                    ->placeholder('System Initialized'),

                                TextEntry::make('approvedBy.name')
                                    ->label('APPROVED BY')
                                    ->icon(Heroicon::Check)
                                    ->placeholder('Pending Approval'),

                                TextEntry::make('dispatchedBy.name')
                                    ->label('DISPATCHED BY')
                                    ->icon(Heroicon::Truck)
                                    ->placeholder('Pending Dispatch'),

                                TextEntry::make('receivedBy.name')
                                    ->label('RECEIVED BY')
                                    ->icon(Heroicon::QrCode)
                                    ->placeholder('Pending Intake'),
                            ])
                            ->columnSpan(1),

                        // Section 3: Material Manifest (Full Width)
                        Section::make('MATERIAL MANIFEST ITEMS')
                            ->icon(Heroicon::ClipboardDocumentList)
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->label('')
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextEntry::make('productVariant.sku')
                                                    ->label('ORIGINAL SKU')
                                                    ->weight(FontWeight::Bold)
                                                    ->columnSpan(1),

                                                TextEntry::make('substituteProductVariant.sku')
                                                    ->label('PROPOSED SUBSTITUTE')
                                                    ->badge()
                                                    ->color('warning')
                                                    ->placeholder('No Substitute')
                                                    ->columnSpan(1),

                                                TextEntry::make('requested_qty')
                                                    ->label('REQUESTED')
                                                    ->state(fn ($record) => "{$record->requested_qty} {$record->requested_unit_name}")
                                                    ->columnSpan(1),

                                                TextEntry::make('approved_qty')
                                                    ->label('APPROVED')
                                                    ->state(fn ($record) => $record->approved_qty
                                                        ? "{$record->approved_qty} {$record->approved_unit_name}"
                                                        : 'Pending Verification')
                                                    ->color(fn ($record) => $record->approved_qty !== $record->requested_qty ? 'warning' : 'gray')
                                                    ->columnSpan(1),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
