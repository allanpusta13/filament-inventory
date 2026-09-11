<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class CreateDirectTransfer extends CreateRecord
{
    protected static string $resource = DirectTransferResource::class;

    protected function getFormSchema(): array
    {
        return DirectTransferForm::configure(\Filament\Schemas\Schema::make());
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
        return 'EXECUTE TRANSFER';
    }

    protected function getFormHeading(): string
    {
        return 'INSTANT DIRECT TRANSFER';
    }

    protected function getFormDescription(): string
    {
        return '3-step wizard: origin/destination, stock allocation, review';
    }

    protected function getFormWidth(): string
    {
        return 'max-content';
    }

    protected function getFormSubmitAction(): \Filament\Actions\Action
    {
        return Action::make('create')
            ->label('EXECUTE TRANSFER')
            ->color('primary')
            ->action(function (array $data) {
                DB::transaction(function () use ($data) {
                    $fromId = $data['from_warehouse_id'];
                    $toId = $data['to_warehouse_id'];
                    $variantId = $data['product_variant_id'];
                    $qty = $data['quantity'];
                    $notes = $data['notes'];

                    $referenceCode = 'DTR-' . date('Ymd') . '-' . mb_strtoupper(uniqid());
                    $variant = ProductVariant::findOrFail($variantId);

                    app(InventoryService::class)->directTransfer(
                        $variantId,
                        $fromId,
                        $toId,
                        $qty,
                        unitName: $variant->base_unit_name,
                        unitRatio: 1,
                        referenceCode: $referenceCode,
                        notes: $notes
                    );

                    $this->record = \App\Models\StockMovement::where('reference_code', $referenceCode)
                        ->where('type', 'transfer_out')
                        ->first();
                });

                Notification::make()
                    ->title('Transfer Executed')
                    ->body("Direct transfer #{$this->record->reference_code} completed successfully.")
                    ->success()
                    ->send();
            });
    }

    protected function getWizardSteps(): array
    {
        return [
            \Filament\Schemas\Components\Wizard\Step::make('Location Mapping')
                ->description('Map origin and destination warehouses')
                ->schema(\App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm::getLocationSchema()),

            \Filament\Schemas\Components\Wizard\Step::make('Stock Allocation')
                ->description('Select variant, quantity, and add notes')
                ->schema(\App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm::getAllocationSchema()),

            \Filament\Schemas\Components\Wizard\Step::make('Review & Verify')
                ->description('Confirm all details before executing')
                ->schema(\App\Filament\Resources\DirectTransfers\Schemas\DirectTransferForm::getReviewSchema()),
        ];
    }

    }
