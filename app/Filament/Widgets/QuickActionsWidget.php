<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Traits\StockActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

final class QuickActionsWidget extends Widget
{
    use StockActions;

    protected string $view = 'filament.widgets.quick-actions';

    protected static ?int $sort = 12;

    protected static ?string $heading = 'Common Actions';

    protected int|string|array $columnSpan = [
        'sm' => 'full',
        'md' => 1,
        'lg' => 5,
    ];

    public function getActions(): array
    {
        return [
            $this->receiveStockAction(),
            $this->shipStockAction(),
            $this->transferStockAction(),
            $this->newProductAction(),
        ];
    }

    private function newProductAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('newProduct')
            ->label('New Product')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->form([
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('category')
                    ->label('Category')
                    ->maxLength(255),
                TextInput::make('unit')
                    ->label('Unit')
                    ->default('each')
                    ->required()
                    ->maxLength(255),
                TextInput::make('reorder_point')
                    ->label('Reorder Point')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ])
            ->action(function (array $data): void {
                Product::create($data);

                Notification::make()
                    ->title('Product created')
                    ->success()
                    ->send();
            });
    }
}
