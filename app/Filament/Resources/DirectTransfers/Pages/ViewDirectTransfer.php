<?php

declare(strict_types=1);

namespace App\Filament\Resources\DirectTransfers\Pages;

use App\Enums\DirectTransferStatus;
use App\Filament\Resources\DirectTransfers\DirectTransferResource;
use App\Models\DirectTransfer;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ViewDirectTransfer extends ViewRecord
{
    protected static string $resource = DirectTransferResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Direct Transfer Details')->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('reference_code'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('fromWarehouse.name')->label('Source Warehouse'),
                        TextEntry::make('toWarehouse.name')->label('Destination Warehouse'),
                        TextEntry::make('executedByUser.name')->label('Executed By'),
                        TextEntry::make('executed_at')->dateTime(),
                        TextEntry::make('audit_reason')->label('Reason')->limit(100),
                    ]),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_stn')
                ->label('Print STN Manifest')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->visible(fn (DirectTransfer $record): bool => in_array($record->status, [DirectTransferStatus::Completed, DirectTransferStatus::Failed]))
                ->url(fn (DirectTransfer $record): string => route('stn.print-direct', $record), shouldOpenInNewTab: true),
        ];
    }
}
