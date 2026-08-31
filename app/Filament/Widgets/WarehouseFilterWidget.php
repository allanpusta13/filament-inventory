<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Warehouse;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

final class WarehouseFilterWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    public ?string $selectedWarehouseId = null;

    protected static ?string $heading = 'Filter by Warehouse';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 'full',
        'lg' => 'full',
    ];

    protected string $view = 'filament.widgets.warehouse-filter';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() ?? false;
    }

    public static function getSelectedWarehouseId(): ?int
    {
        return session('admin_warehouse_filter') ? (int) session('admin_warehouse_filter') : null;
    }

    public function mount(): void
    {
        $this->selectedWarehouseId = session('admin_warehouse_filter');
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('selectedWarehouseId')
                    ->label('Warehouse')
                    ->placeholder('All Warehouses')
                    ->options(fn (): array => Warehouse::where('is_active', true)
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        if ($state !== null) {
                            session(['admin_warehouse_filter' => $state]);
                        } else {
                            session()->forget('admin_warehouse_filter');
                        }
                    }),
            ])
            ->columns(1);
    }

    public function clearFilter(): void
    {
        session()->forget('admin_warehouse_filter');
        $this->selectedWarehouseId = null;
    }
}
