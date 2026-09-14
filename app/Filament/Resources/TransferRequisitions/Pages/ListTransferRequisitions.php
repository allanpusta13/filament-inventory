<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\TransferRequisition;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ListTransferRequisitions extends ListRecords
{
    protected static string $resource = TransferRequisitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTransferRequisition')
                ->label('NEW TRANSFER REQUEST')
                ->icon(Heroicon::Plus)
                ->modalWidth(Width::MaxContent)
                ->modalHeading('CREATE INTER-WAREHOUSE REQUISITION')
                ->modalSubmitActionLabel('SUBMIT REQUISITION')
                ->steps([
                    Step::make('Routing Pathways')
                        ->description('Identify dispatching & receiving locations')
                        ->schema(TransferRequisitionForm::getRoutingSchema()),

                    Step::make('Material Manifest')
                        ->description('Declare variant items and order volumes')
                        ->schema(TransferRequisitionForm::getItemsSchema()),

                    Step::make('Review & Verify')
                        ->description('Confirm accuracy before sending request')
                        ->schema(TransferRequisitionForm::getReviewSchema()),
                ])
                ->action(function (array $data) {
                    // Handle wizard data structure - data may be under 'wizardData' key
                    $formData = $data['wizardData'] ?? $data;

                    DB::transaction(function () use ($formData) {
                        $referenceCode = 'TRQ-'.date('Ymd').'-'.mb_strtoupper(uniqid());

                        $requisition = TransferRequisition::create([
                            'reference_code' => $referenceCode,
                            'from_warehouse_id' => $formData['from_warehouse_id'],
                            'to_warehouse_id' => $formData['to_warehouse_id'],
                            'status' => 'requested',
                            'requested_by' => auth()->id(),
                            'requested_at' => now(),
                        ]);

                        $items = $formData['items'] ?? [];
                        foreach ($items as $item) {
                            $ratio = (int) ($item['requested_unit_ratio'] ?? 1);
                            $qty = (int) ($item['requested_qty'] ?? 1);

                            $requisition->items()->create([
                                'product_variant_id' => $item['product_variant_id'],
                                'requested_unit_name' => $item['requested_unit_name'],
                                'requested_unit_ratio' => $ratio,
                                'requested_qty' => $qty,
                                'requested_base_qty' => $qty * $ratio,
                            ]);
                        }
                    });

                    Notification::make()
                        ->title('Requisition Request Submitted')
                        ->success()
                        ->send();
                }),
        ];
    }
}
