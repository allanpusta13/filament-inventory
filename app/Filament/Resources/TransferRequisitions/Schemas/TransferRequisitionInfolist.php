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
        return $schema->components([
            Grid::make(['default' => 1, 'md' => 3, 'xl' => 3])->schema([
                Section::make(__('resources.transfer_requisitions.infolist.profile'))
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

                        TextEntry::make('notes')
                            ->label(__('resources.transfer_requisitions.fields.notes'))
                            ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                            ->placeholder(__('common.empty')),
                    ]),

                Section::make(__('resources.transfer_requisitions.infolist.signoffs'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                    ->schema([
                        TextEntry::make('requestedBy.name')
                            ->label(__('resources.transfer_requisitions.fields.requested_by'))
                            ->icon(Heroicon::User)
                            ->placeholder(__('resources.transfer_requisitions.placeholders.system_initialized')),
                        TextEntry::make('approvedBy.name')
                            ->label(__('resources.transfer_requisitions.fields.approved_by'))
                            ->icon(Heroicon::Check)
                            ->placeholder(__('resources.transfer_requisitions.placeholders.pending_approval')),
                        TextEntry::make('dispatchedBy.name')
                            ->label(__('resources.transfer_requisitions.fields.dispatched_by'))
                            ->icon(Heroicon::Truck)
                            ->placeholder(__('resources.transfer_requisitions.placeholders.pending_dispatch')),
                        TextEntry::make('receivedBy.name')
                            ->label(__('resources.transfer_requisitions.fields.received_by'))
                            ->icon(Heroicon::QrCode)
                            ->placeholder(__('resources.transfer_requisitions.placeholders.pending_intake')),
                    ]),

                Section::make(__('resources.transfer_requisitions.infolist.manifest'))
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->table([
                                TableColumn::make(__('resources.transfer_requisitions.fields.original_sku')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.substitute_sku')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.requested')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.approved')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.shipped_base')),
                                TableColumn::make(__('resources.transfer_requisitions.fields.received_good_base')),
                            ])
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('substituteProductVariant.sku')
                                    ->badge()
                                    ->color('warning')
                                    ->placeholder(__('common.empty')),

                                TextEntry::make('requested_qty')
                                    ->state(fn ($record) => "{$record->requested_qty} {$record->requested_unit_name}"),

                                TextEntry::make('approved_qty')
                                    ->state(fn ($record) => $record->approved_qty
                                        ? "{$record->approved_qty} {$record->approved_unit_name}"
                                        : __('common.empty')),

                                TextEntry::make('shipped_base_qty')
                                    ->numeric()
                                    ->alignEnd(),

                                TextEntry::make('received_good_base_qty')
                                    ->numeric()
                                    ->alignEnd(),
                            ]),
                    ]),

                Section::make(__('resources.transfer_requisitions.infolist.negotiation_history'))
                    ->icon(Heroicon::ChatBubbleLeftRight)
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record->items
                        ->flatMap(fn ($item) => $item->revisions)
                        ->isNotEmpty())
                    ->schema([
                        // Outer `items` intentionally stays Grid-composed: its
                        // cell embeds a nested `revisions` RepeatableEntry,
                        // which does not size well inside a table cell.
                        RepeatableEntry::make('items')
                            ->schema([
                                TextEntry::make('productVariant.sku')
                                    ->label(__('resources.transfer_requisitions.fields.original_sku'))
                                    ->weight(FontWeight::Bold),

                                RepeatableEntry::make('revisions')
                                    ->label(__('resources.transfer_requisitions.fields.revisions'))
                                    ->table([
                                        TableColumn::make(__('resources.transfer_requisitions.fields.negotiation_side')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.status')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.proposed')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.proposed_by')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.responded_at')),
                                        TableColumn::make(__('resources.transfer_requisitions.fields.negotiation_reason')),
                                    ])
                                    ->schema([
                                        TextEntry::make('side')->badge(),
                                        TextEntry::make('status')->badge(),
                                        TextEntry::make('proposed_qty')
                                            ->state(fn ($record) => "{$record->proposed_qty} {$record->proposed_unit_name}"),
                                        TextEntry::make('user.name')
                                            ->icon(Heroicon::User)
                                            ->placeholder(__('common.empty')),
                                        TextEntry::make('responded_at')
                                            ->dateTime('M j, Y H:i')
                                            ->placeholder(__('resources.transfer_requisitions.placeholders.pending_approval')),
                                        TextEntry::make('negotiation_reason')
                                            ->placeholder(__('common.empty')),
                                    ]),
                            ]),
                    ]),
            ]),
        ]);
    }
}