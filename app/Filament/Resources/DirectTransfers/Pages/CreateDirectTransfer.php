<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class CreateDirectTransfer extends CreateRecord
{
    use HasWizard;

    protected static string $resource = DirectTransferResource::class;

    public function getMaxContentWidth(): ?string
    {
        return Width::SevenExtraLarge->value;
    }

    /**
     * @return array<Step>
     */
    protected function getSteps(): array
    {
        return [
            Step::make(__('resources.direct_transfers.steps.location_mapping'))
                ->description(__('resources.direct_transfers.steps.location_mapping_description'))
                ->icon(Heroicon::BuildingOffice)
                ->schema(DirectTransferForm::getLocationMappingFields()),

            Step::make(__('resources.direct_transfers.steps.stock_allocation'))
                ->description(__('resources.direct_transfers.steps.stock_allocation_description'))
                ->icon(Heroicon::Cube)
                ->schema(DirectTransferForm::getStockAllocationFields()),

            Step::make(__('resources.direct_transfers.steps.review_verify'))
                ->description(__('resources.direct_transfers.steps.review_verify_description'))
                ->icon(Heroicon::CheckCircle)
                ->schema([
                    Placeholder::make('review_summary')
                        ->columnSpanFull()
                        ->content(fn (Get $get) => view(
                            'filament.wizards.direct-transfer-review',
                            ['state' => $get()],
                        )),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $referenceCode = 'DT-'.now()->format('YmdHis').'-'.random_int(100, 999);

        return app(\App\Services\InventoryService::class)->directTransfer(
            fromWarehouseId: (int) $data['from_warehouse_id'],
            toWarehouseId: (int) $data['to_warehouse_id'],
            items: $data['items'] ?? [],
            referenceCode: $referenceCode,
            notes: $data['notes'],
        );
    }
}
