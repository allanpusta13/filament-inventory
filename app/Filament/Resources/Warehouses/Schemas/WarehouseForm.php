<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Schemas;

use App\Livewire\Warehouses\AssignedUsersList;
use App\Models\Warehouse;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

// use Livewire\Livewire;

/**
 * Warehouse form — §7K.1 canonical contract.
 *
 * The users pivot is intentionally read-only. Assignments are edited
 * from UserResource only (§2.13) to avoid last-write-wins conflicts.
 */
class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources.warehouses.form.profile'))
                ->icon(Heroicon::BuildingOffice)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    TextInput::make('code')
                        ->label(__('resources.warehouses.fields.code'))
                        ->prefixIcon(Heroicon::Tag)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->nullable()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->placeholder(__('resources.warehouses.placeholders.code'))
                        ->helperText(__('resources.warehouses.help.code')),

                    TextInput::make('name')
                        ->label(__('resources.warehouses.fields.name'))
                        ->prefixIcon(Heroicon::Identification)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->required()
                        ->maxLength(255),

                    Textarea::make('location')
                        ->label(__('resources.warehouses.fields.location'))
                        // ->prefixIcon(Heroicon::MapPin)
                        ->columnSpanFull()
                        ->rows(2)
                        ->maxLength(500),
                ]),

            Section::make(__('resources.warehouses.form.access_status'))
                ->icon(Heroicon::ShieldCheck)
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'xl' => 2])
                ->schema([
                    Livewire::make(AssignedUsersList::class, fn (?Warehouse $record): array => [
                        'warehouseId' => $record?->id,
                    ])
                        ->columnSpan(['default' => 1, 'md' => 2, 'xl' => 2])
                        ->helperText(__('resources.warehouses.help.users_readonly')),

                    Toggle::make('is_active')
                        ->label(__('resources.warehouses.fields.is_active'))
                        ->onIcon(Heroicon::CheckCircle)
                        ->offIcon(Heroicon::XCircle)
                        ->columnSpan(['default' => 1, 'md' => 1, 'xl' => 1])
                        ->default(true),
                ]),
        ]);
    }
}
