<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Schemas;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Models\ProductVariant;
use App\Models\ProductVariantUnitConversion;
use App\Models\TransferRequisition;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Transfer requisition form — §7B.1 canonical contract.
 *
 * Line-item repeater uses `->table([...])` for a compact row layout
 * (deviation from §7O.2's `->columns(...)` — see class docblock).
 */
class TransferRequisitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::getRoutingFields(),
            ...self::getMaterialManifestFields(),
        ]);
    }

    public static function getRoutingFields(): array
    {
        return [
            Section::make(__('resources.transfer_requisitions.sections.routing'))
                ->icon(Heroicon::BuildingOffice)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Select::make('from_warehouse_id')
                        ->label(__('resources.transfer_requisitions.fields.from_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                            ? \App\Models\Warehouse::query()->pluck('name', 'id')
                            : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->default(fn () => auth()->user()->warehouses()->count() === 1
                            ? auth()->user()->warehouses()->first()->id
                            : null)
                        ->required(),

                    Select::make('to_warehouse_id')
                        ->label(__('resources.transfer_requisitions.fields.to_warehouse'))
                        ->prefixIcon(Heroicon::BuildingOffice2)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->options(fn () => auth()->user()->isAdmin() || auth()->user()->isAuditor()
                            ? \App\Models\Warehouse::query()->pluck('name', 'id')
                            : auth()->user()->warehouses()->pluck('name', 'id'))
                        ->required()
                        ->different('from_warehouse_id'),

                    Textarea::make('notes')
                        ->label(__('resources.transfer_requisitions.fields.notes'))
                        // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getMaterialManifestFields(): array
    {
        return [
            Repeater::make('items')
                ->relationship('items')
                ->columnSpanFull()
                ->table([
                    TableColumn::make(__('resources.transfer_requisitions.fields.variant_sku')),
                    TableColumn::make(__('resources.transfer_requisitions.fields.unit')),
                    TableColumn::make(__('resources.transfer_requisitions.fields.ratio_base')),
                    TableColumn::make(__('resources.transfer_requisitions.fields.qty')),
                ])
                ->schema([
                    Select::make('product_variant_id')
                        ->hiddenLabel()
                        ->relationship('productVariant', 'sku')
                        ->prefixIcon(Heroicon::Tag)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->live()
                        ->afterStateUpdated(function ($set) {
                            $set('requested_unit_name', null);
                            $set('requested_unit_ratio', null);
                        }),

                    Select::make('requested_unit_name')
                        ->hiddenLabel()
                        ->prefixIcon(Heroicon::Scale)
                        ->options(function (Get $get) {
                            $variantId = $get('product_variant_id');
                            if (! $variantId) {
                                return [];
                            }

                            return ProductVariantUnitConversion::where('product_variant_id', $variantId)
                                ->orderByDesc('base_unit_ratio')
                                ->pluck('unit_name', 'unit_name')
                                ->toArray();
                        })
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, $set, $state) {
                            $ratio = ProductVariantUnitConversion::where('product_variant_id', $get('product_variant_id'))
                                ->where('unit_name', $state)
                                ->value('base_unit_ratio');
                            $set('requested_unit_ratio', $ratio ?? 1);
                        }),

                    TextInput::make('requested_unit_ratio')
                        ->hiddenLabel()
                        ->afterContent(Icon::make(Heroicon::InformationCircle)->tooltip(__('resources.transfer_requisitions.hints.ratio_auto')))
                        ->extraAttributes(['aria-label' => __('resources.transfer_requisitions.fields.ratio_base')])
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    TextInput::make('requested_qty')
                        ->hiddenLabel()
                        ->prefixIcon(Heroicon::Hashtag)
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                ])
                ->minItems(1)
                ->required()
                ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                    $data['requested_base_qty'] = (int) $data['requested_qty'] * (int) $data['requested_unit_ratio'];

                    return $data;
                })
                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                    $data['requested_base_qty'] = (int) $data['requested_qty'] * (int) $data['requested_unit_ratio'];

                    return $data;
                }),
        ];
    }

    /**
     * Negotiation revision modal fields (§7B.1).
     *
     * @return array<int, mixed>
     */
    public static function getRevisionFields(TransferRequisition $requisition): array
    {
        $requisition->loadMissing(['items.productVariant', 'items.revisions']);

        return [
            Select::make('transfer_requisition_item_id')
                ->label(__('resources.transfer_requisitions.fields.revision_item'))
                ->prefixIcon(Heroicon::ClipboardDocumentList)
                ->columnSpanFull()
                ->options(fn () => $requisition->items
                    ->mapWithKeys(fn ($item) => [
                        $item->id => $item->productVariant->sku,
                    ])
                    ->toArray())
                ->required()
                ->live(),

            Select::make('substitute_product_variant_id')
                ->label(__('resources.transfer_requisitions.fields.substitute_sku'))
                ->prefixIcon(Heroicon::Tag)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->options(fn () => ProductVariant::query()
                    ->where('is_active', true)
                    ->orderBy('sku')
                    ->pluck('sku', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($set) {
                    $set('proposed_unit_name', null);
                    $set('proposed_unit_ratio', null);
                }),

            Select::make('side')
                ->label(__('resources.transfer_requisitions.fields.negotiation_side'))
                ->prefixIcon(Heroicon::ChatBubbleLeftRight)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->options(NegotiationSide::class)
                ->required(),

            Select::make('proposed_unit_name')
                ->label(__('resources.transfer_requisitions.fields.unit'))
                ->prefixIcon(Heroicon::Scale)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->options(function (Get $get) use ($requisition) {
                    $item = $requisition->items->firstWhere(
                        'id',
                        (int) $get('transfer_requisition_item_id')
                    );
                    $variantId = $get('substitute_product_variant_id')
                        ?? $item?->product_variant_id;
                    if (! $variantId) {
                        return [];
                    }

                    return ProductVariantUnitConversion::where('product_variant_id', $variantId)
                        ->orderByDesc('base_unit_ratio')
                        ->pluck('unit_name', 'unit_name')
                        ->toArray();
                })
                ->required()
                ->live()
                ->afterStateUpdated(function (Get $get, $set, $state) use ($requisition) {
                    $item = $requisition->items->firstWhere(
                        'id',
                        (int) $get('transfer_requisition_item_id')
                    );
                    $variantId = $get('substitute_product_variant_id')
                        ?? $item?->product_variant_id;
                    $ratio = $variantId
                        ? ProductVariantUnitConversion::where('product_variant_id', $variantId)
                            ->where('unit_name', $state)
                            ->value('base_unit_ratio')
                        : null;
                    $set('proposed_unit_ratio', $ratio ?? 1);
                }),

            TextInput::make('proposed_unit_ratio')
                ->hiddenLabel()
                ->afterContent(Icon::make(Heroicon::InformationCircle)->tooltip(__('resources.transfer_requisitions.hints.ratio_auto')))
                ->extraAttributes(['aria-label' => __('resources.transfer_requisitions.fields.ratio_base')])
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->numeric()
                ->disabled()
                ->dehydrated()
                ->required(),

            TextInput::make('proposed_qty')
                ->label(__('resources.transfer_requisitions.fields.qty'))
                ->prefixIcon(Heroicon::Hashtag)
                ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                ->numeric()
                ->minValue(1)
                ->required(),

            Select::make('responds_to_revision_id')
                ->label(__('resources.transfer_requisitions.fields.responds_to'))
                ->prefixIcon(Heroicon::ChatBubbleLeftRight)
                ->columnSpanFull()
                ->options(function (Get $get) use ($requisition) {
                    $itemId = (int) $get('transfer_requisition_item_id');
                    if (! $itemId) {
                        return [];
                    }
                    $item = $requisition->items->firstWhere('id', $itemId);
                    if (! $item) {
                        return [];
                    }

                    return $item->revisions
                        ->where('status', RevisionStatus::Pending)
                        ->mapWithKeys(fn ($revision) => [
                            $revision->id => "{$revision->proposed_qty} {$revision->proposed_unit_name}",
                        ])
                        ->toArray();
                }),

            Textarea::make('negotiation_reason')
                ->label(__('resources.transfer_requisitions.fields.negotiation_reason'))
                // ->prefixIcon(Heroicon::ChatBubbleBottomCenterText)
                ->columnSpanFull(),
        ];
    }
}
