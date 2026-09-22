<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateTransferRequisition extends CreateRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    protected function getFormSchema(): array
    {
        return TransferRequisitionForm::getRoutingSchema();
    }

    protected function getFormStatePath(): string
    {
        return 'wizardData';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActionLabel(): string
    {
        return 'SUBMIT REQUISITION';
    }

    protected function getFormHeading(): string
    {
        return 'CREATE INTER-WAREHOUSE REQUISITION';
    }

    protected function getFormDescription(): string
    {
        return '3-step wizard: routing, items, review';
    }

    protected function getFormWidth(): string
    {
        return 'max-content';
    }

    protected function getFormSubmitAction(): Action
    {
        return Action::make('create')
            ->label('SUBMIT REQUISITION')
            ->color('primary')
            ->action(function (array $data) {
                DB::transaction(function () use ($data) {
                    try {
                        $referenceCode = 'TRQ-'.date('Ymd').'-'.mb_strtoupper(uniqid());
                        $requisition = \App\Models\TransferRequisition::create([
                            'reference_code' => $referenceCode,
                            'from_warehouse_id' => $data['from_warehouse_id'],
                            'to_warehouse_id' => $data['to_warehouse_id'],
                            'status' => 'draft',
                            'requested_by' => auth()->id(),
                            'requested_at' => now(),
                        ]);

                        if (! empty($data['items'])) {
                            foreach ($data['items'] as $item) {
                                $variant = \App\Models\ProductVariant::find($item['product_variant_id']);
                                $ratio = $item['requested_unit_ratio'] ?? 1;
                                $baseQty = ($item['requested_qty'] ?? 1) * $ratio;

                                $requisition->items()->create([
                                    'product_variant_id' => $item['product_variant_id'],
                                    'requested_unit_name' => $item['requested_unit_name'],
                                    'requested_unit_ratio' => $item['requested_unit_ratio'],
                                    'requested_qty' => $item['requested_qty'],
                                    'requested_base_qty' => $baseQty,
                                ]);
                            }
                        }

                        $this->record = $requisition;
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('Error')
                            ->body('Failed to create requisition: '.$e->getMessage())
                            ->danger()
                            ->send();
                        throw new RuntimeException('Failed to create requisition: '.$e->getMessage());
                    }
                });

                Notification::make()
                    ->title('Requisition created')
                    ->body('Transfer requisition #'.$this->record->reference_code.' submitted for review.')
                    ->success()
                    ->send();
            });
    }

    protected function getWizardSteps(): array
    {
        return [
            Step::make('Routing Pathways')
                ->description('Identify dispatching & receiving locations')
                ->schema(TransferRequisitions\Schemas\TransferRequisitionForm::getRoutingSchema()),

            Step::make('Material Manifest')
                ->description('Declare variant items, order volumes')
                ->schema(TransferRequisitions\Schemas\TransferRequisitionForm::getItemsSchema()),

            Step::make('Review & Verify')
                ->description('Confirm accuracy before sending request')
                ->schema(TransferRequisitions\Schemas\TransferRequisitionForm::getReviewSchema()),
        ];
    }
}
