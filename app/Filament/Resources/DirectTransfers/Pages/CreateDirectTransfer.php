<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm;
use App\Support\GeneratesReferenceCodes;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class CreateDirectTransfer extends CreateRecord
{
    use HasWizard;

    protected static string $resource = DirectTransferResource::class;

    public function getMaxContentWidth(): ?string
    {
        return Width::SevenExtraLarge->value;
    }

    /** @return array<Step> */
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
                    View::make('filament.wizards.direct-transfer-review')
                        ->viewData(fn (Get $get): array => [
                            'state' => [
                                'from_warehouse_id' => $get('from_warehouse_id'),
                                'to_warehouse_id' => $get('to_warehouse_id'),
                                'items' => $get('items') ?? [],
                                'notes' => $get('notes'),
                            ],
                        ])
                        ->columnSpanFull(),
                ]),

        ];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        Gate::authorize('create', \App\Models\DirectTransfer::class);

        $referenceCode = $data['reference_code']
            ?? $this->generateReferenceCodeWithRetry('DT');

        return app(\App\Services\InventoryService::class)->directTransfer(
            fromWarehouseId: (int) $data['from_warehouse_id'],
            toWarehouseId: (int) $data['to_warehouse_id'],
            items: $data['items'] ?? [],
            referenceCode: $referenceCode,
            notes: $data['notes'],
        );
    }

    protected function generateReferenceCodeWithRetry(string $prefix): string
    {
        $maxAttempts = 5;
        for ($i = 0; $i < $maxAttempts; $i++) {
            return GeneratesReferenceCodes::generateReferenceCode($prefix);
        }
        throw new \App\Exceptions\DomainRuleViolationException('errors.reference_code_exhausted', [
            'prefix' => $prefix,
            'attempts' => $maxAttempts,
        ]);
    }
}
