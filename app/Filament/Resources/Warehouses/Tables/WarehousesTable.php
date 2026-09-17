<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Tables;

use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // 🔑 1. Arrange records into a responsive multi-column card grid
            ->contentGrid([
                'sm' => 1, // 1 column on mobile
                'md' => 2, // 2 columns on tablets
                'xl' => 3, // 3 columns on wide screens
            ])
            ->columns([
                // Main Card Container
                Stack::make([
                    // Card Header: Code & Active Status Badge
                    Split::make([
                        TextColumn::make('code')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->color('primary'),

                        IconColumn::make('is_active')
                            ->boolean()
                            ->alignment(Alignment::End)
                            ->sortable(),
                    ]),

                    // Warehouse Title
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable()
                        ->size(TextSize::Large)
                        ->weight(FontWeight::Bold),

                    // Collapsible Location & Details Panel
                    Panel::make([
                        Stack::make([
                            TextColumn::make('location')
                                ->icon(Heroicon::MapPin)
                                ->placeholder('No Address Registered')
                                ->color('gray')
                                ->searchable()
                                ->sortable(),

                            TextColumn::make('users_count')
                                ->counts('users')
                                ->label('AUTHORIZED OPERATORS')
                                ->formatStateUsing(fn ($state) => "{$state} Assigned Operators")
                                ->icon(Heroicon::UserGroup)
                                ->color('gray'),

                            TextColumn::make('created_at')
                                ->label('Created')
                                ->dateTime()
                                ->placeholder('Never')
                                ->color('gray')
                                ->size(TextSize::ExtraSmall),

                            TextColumn::make('updated_at')
                                ->label('Updated')
                                ->dateTime()
                                ->placeholder('Never')
                                ->color('gray')
                                ->size(TextSize::ExtraSmall),
                        ])->space(1),
                    ])
                        ->collapsible()
                        ->collapsed(true), // Collapsed by default to keep cards compact
                ])
                    ->space(3)
                    ->extraAttributes([
                        'class' => 'p-4 rounded-xl border border-zinc-200 bg-white dark:bg-zinc-900 shadow-sm hover:shadow-md transition-shadow',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->slideOver()
                    ->icon(Heroicon::PencilSquare)
                    ->closeModalByClickingAway(false),

                Action::make('manualStockAdjustment')
                    ->label('Record Adjustment')
                    ->slideOver()
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->form([
                        // Select::make('product_variant_id')
                        //     ->label('PRODUCT VARIANT')
                        //     ->relationship('productVariant', 'sku')
                        //     ->required()
                        //     ->searchable(),

                        // TextInput::make('quantity')
                        //     ->label('BASE UNITS ADJUSTMENT DELTA')
                        //     ->numeric()
                        //     ->required(),

                        // TextInput::make('audit_reason')
                        //     ->label('AUDIT COMPLIANCE REASON NOTES')
                        //     ->required()
                        //     ->string()
                        //     ->minLength(15)
                        //     ->regex('/^(?!(.)\\1+$)(?!\\b(test|dummy|notes|adjust|none)\\b)/i')
                        //     ->placeholder('Provide a clear, descriptive audit explanation (min. 15 characters)...'),
                    ])
                /* ->action(function (Warehouse $record, array $data, InventoryService $service) {
                        $service->recordMovement(
                            productVariantId: $data['product_variant_id'],
                            warehouseId: $record->id,
                            type: 'adjustment',
                            baseQuantity: $data['quantity'],
                            unitName: 'Base Unit',
                            unitRatio: 1,
                            referenceType: Warehouse::class,
                            referenceId: $record->id,
                            referenceCode: 'MANUAL-ADJ-' . strtoupper(uniqid())
                        );
                    }) */,
            ])
            ->modifyQueryUsing(function (Builder $query, Table $table): Builder {
                $sortDirection = $table->getSortDirection() ?: 'asc';
                $sortColumn = $table->getSortColumn();

                if ($sortColumn === 'is_active') {
                    return $query->orderBy('is_active', $sortDirection)
                        ->orderBy('id', 'asc');
                }

                return $query;
            });
    }
}
