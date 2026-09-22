<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
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
                    ->columnSpanFull()
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
                                    ->table([
                                        TableColumn::make('ORIGINAL SKU'),
                                        TableColumn::make('PROPOSED SUBSTITUTE'),
                                        TableColumn::make('REQUESTED'),
                                        TableColumn::make('APPROVED'),
                                        TableColumn::make('APPROVED (BASE)'),
                                        TableColumn::make('SHIPPED (BASE)'),
                                        TableColumn::make('RECEIVED GOOD (BASE)'),
                                        TableColumn::make('RECEIVED DAMAGED (BASE)'),
                                        TableColumn::make('LOSS CATEGORY'),

                                    ])
                                    ->schema([
                                        Grid::make(6)
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

                                                TextEntry::make('approved_base_qty')
                                                    ->label('APPROVED (BASE)')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('shipped_base_qty')
                                                    ->label('SHIPPED (BASE)')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('received_good_base_qty')
                                                    ->label('RECEIVED GOOD (BASE)')
                                                    ->numeric()
                                                    ->columnSpan(1),

                                                TextEntry::make('received_damaged_base_qty')
                                                    ->label('RECEIVED DAMAGED (BASE)')
                                                    ->numeric()
                                                    ->color('danger')
                                                    ->columnSpan(1),

                                                TextEntry::make('lossCategory')
                                                    ->label('LOSS CATEGORY')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => match ($state) {
                                                        'shortfall' => 'warning',
                                                        'damage' => 'danger',
                                                        'spoilage' => 'danger',
                                                        'theft' => 'danger',
                                                        default => 'gray',
                                                    })
                                                    ->columnSpan(1),
                                            ]),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
