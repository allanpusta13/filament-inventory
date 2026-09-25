<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class CreateTransferRequisition extends CreateRecord
{
    use HasWizard;

    protected static string $resource = TransferRequisitionResource::class;

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
            Step::make(__('resources.transfer_requisitions.steps.routing'))
                ->description(__('resources.transfer_requisitions.steps.routing_description'))
                ->icon(Heroicon::BuildingOffice)
                ->schema(TransferRequisitionForm::getRoutingFields()),

            Step::make(__('resources.transfer_requisitions.steps.manifest'))
                ->description(__('resources.transfer_requisitions.steps.manifest_description'))
                ->icon(Heroicon::ClipboardDocumentList)
                ->schema(TransferRequisitionForm::getMaterialManifestFields()),

            Step::make(__('resources.transfer_requisitions.steps.review'))
                ->description(__('resources.transfer_requisitions.steps.review_description'))
                ->icon(Heroicon::CheckCircle)
                ->schema([
                    Placeholder::make('review_summary')
                        ->columnSpanFull()
                        ->content(fn (Get $get) => view(
                            'filament.wizards.transfer-review',
                            ['state' => $get()],
                        )),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reference_code'] = $data['reference_code']
            ?? 'TR-'.now()->format('YmdHis').'-'.random_int(100, 999);
        $data['requested_by'] = auth()->id();
        $data['status'] = \App\Enums\TransferRequisitionStatus::Draft->value;

        return $data;
    }
}
