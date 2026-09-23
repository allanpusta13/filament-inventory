<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Override;

class CreateTransferRequisition extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = TransferRequisitionResource::class;

    protected ?string $heading = 'CREATE INTER-WAREHOUSE REQUISITION';

    protected ?string $subheading = '3-step wizard: routing, items, review';

    public function getSteps(): array
    {
        return [
            Step::make('Routing Pathways')
                ->description('Identify dispatching & receiving locations')
                ->schema(TransferRequisitionForm::getRoutingSchema()),

            Step::make('Material Manifest')
                ->description('Declare variant items, order volumes')
                ->schema(TransferRequisitionForm::getItemsSchema()),

            Step::make('Review & Verify')
                ->description('Confirm accuracy before sending request')
                ->schema(TransferRequisitionForm::getReviewSchema()),
        ];
    }

    #[Override]
    public function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = 'TRQ-'.date('Ymd').'-'.mb_strtoupper(uniqid());
        $data['status'] = TransferRequisitionStatus::Draft->value;
        $data['requested_by'] = auth()->user()->id;
        $data['requested_at'] = now();

        return parent::mutateFormDataBeforeCreate($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
