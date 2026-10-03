<?php

declare(strict_types=1);

namespace App\Livewire\Warehouses;

use App\Models\Warehouse;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * AssignedUsersList — read-only display of a warehouse's assigned users.
 *
 * Replaces the (removed in Filament v5)
 * `Filament\Schemas\Components\Placeholder` that `WarehouseForm`
 * previously used to show the `user_warehouse` pivot read-only.
 */
class AssignedUsersList extends Component
{
    public ?int $warehouseId = null;

    public function render(): View
    {
        $names = $this->warehouseId
            ? Warehouse::find($this->warehouseId)?->users()->pluck('name')->all() ?? []
            : [];

        return view('livewire.warehouses.assigned-users-list', [
            'names' => $names,
        ]);
    }
}
