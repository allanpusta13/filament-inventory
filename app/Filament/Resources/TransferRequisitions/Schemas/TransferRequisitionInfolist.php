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
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make('REQUISITION PROFILE')
                    ->icon(Heroicon::DocumentText)
                    ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                    ->schema([
                        TextEntry::make('reference_code')
                            ->label(__('resources.transfer_requisitions.fields.reference_code'))
                            ->weight(FontWeight::Bold)
                            ->size('lg')
                            ->copyable()
                            ->color('primary')
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('status')
                            ->label(__('resources.transfer_requisitions.fields.status'))
                            ->badge()
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('fromWarehouse.name')
                            ->label(__('resources.transfer_requisitions.fields.from_warehouse'))
                            ->icon(Heroicon::BuildingOffice)
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                        TextEntry::make('toWarehouse.name')
                            ->label(__('resources.transfer_requisitions.fields.to_warehouse'))
                            ->icon(Heroicon::BuildingOffice2)
                            ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                    ]),

                Section::make('AUTHORIZATION SIGN-OFFS')
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('requestedBy.name')->label(__('resources.transfer_requisitions.fields.requested_by'))->icon(Heroicon::User)->placeholder('System Initialized'),
                        TextEntry::make('approvedBy.name')->label(__('resources.transfer_requisitions.fields.approved_by'))->icon(Heroicon::Check)->placeholder('Pending Approval'),
                        TextEntry::make('dispatchedBy.name')->label(__('resources.transfer_requisitions.fields.dispatched_by'))->icon(Heroicon::Truck)->placeholder('Pending Dispatch'),
                        TextEntry::make('receivedBy.name')->label(__('resources.transfer_requisitions.fields.received_by'))->icon(Heroicon::QrCode)->placeholder('Pending Intake'),
                    ]),

                Section::make('MATERIAL MANIFEST ITEMS')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->schema([
                                Grid::make(['default' => 1, 'md' => 3, 'xl' => 6])->schema([
                                    TextEntry::make('productVariant.sku')
                                        ->label(__('resources.transfer_requisitions.fields.original_sku'))
                                        ->weight(FontWeight::Bold)
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                                    TextEntry::make('substituteProductVariant.sku')
                                        ->label(__('resources.transfer_requisitions.fields.substitute_sku'))
                                        ->badge()
                                        ->color('warning')
                                        ->placeholder('—')
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                                    TextEntry::make('requested_qty')
                                        ->label(__('resources.transfer_requisitions.fields.requested'))
                                        ->state(fn ($record) => "{$record->requested_qty} {$record->requested_unit_name}")
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                                    TextEntry::make('approved_qty')
                                        ->label(__('resources.transfer_requisitions.fields.approved'))
                                        ->state(fn ($record) => $record->approved_qty
                                            ? "{$record->approved_qty} {$record->approved_unit_name}"
                                            : '—')
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                                    TextEntry::make('shipped_base_qty')
                                        ->label(__('resources.transfer_requisitions.fields.shipped_base'))
                                        ->numeric()
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),

                                    TextEntry::make('received_good_base_qty')
                                        ->label(__('resources.transfer_requisitions.fields.received_good_base'))
                                        ->numeric()
                                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1]),
                                ]),
                            ]),
                    ]),
            ]),
        ]);
    }
}
