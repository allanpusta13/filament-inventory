<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

final class StockAdjustment extends Page implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Stock Adjustment';

    protected static ?string $title = 'Record Stock Adjustment';

    protected static ?string $slug = 'stock-adjustment';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.pages.stock-adjustment';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user->role === UserRole::Admin || $user->role === UserRole::BranchManager;
    }

    public function form(Form $form): Form
    {
        $userWarehouses = Auth::user()->warehouses()->pluck('warehouses.id', 'warehouses.name')->toArray();

        return $form
            ->schema([
                Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->options($userWarehouses)
                    ->required()
                    ->reactive()
                    ->preload(),
                Select::make('variant_id')
                    ->label('Product Variant')
                    ->options(function (?int $state): array {
                        if (! $state) {
                            return [];
                        }

                        return ProductVariant::query()
                            ->whereHas('currentStock', fn (Builder $q) => $q->where('warehouse_id', $state))
                            ->with('product')
                            ->get()
                            ->mapWithKeys(function (ProductVariant $v) {
                                $qty = $v->currentStock->first()?->on_hand_quantity ?? 0;

                                return [$v->id => "{$v->product->sku} - {$v->name} ({$qty} on hand)"];
                            })
                            ->toArray();
                    })
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('quantity')
                    ->label('Adjustment Quantity')
                    ->hint('Positive to add, negative to remove')
                    ->required()
                    ->integer()
                    ->maxLength(11),
                Textarea::make('reason')
                    ->label('Reason')
                    ->rows(3)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit(InventoryService $inventoryService): void
    {
        $data = $this->form->getState();

        $quantity = (int) $data['quantity'];

        if ($quantity === 0) {
            Notification::make()
                ->title('Invalid quantity')
                ->body('Adjustment quantity cannot be zero.')
                ->danger()
                ->send();

            return;
        }

        try {
            $inventoryService->recordMovement(
                variantId: (int) $data['variant_id'],
                warehouseId: (int) $data['warehouse_id'],
                type: MovementType::Adjustment,
                baseQuantity: $quantity,
                referenceCode: 'ADJ-'.now()->format('Ymd-His'),
            );

            $this->form->fill();

            Notification::make()
                ->title('Stock adjusted')
                ->body("Adjustment of {$quantity} units recorded successfully.")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Adjustment failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
