<?php

declare(strict_types=1);

namespace App\Filament\Resources\Warehouses\Pages;

use App\Exceptions\DomainRuleViolationException;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\Warehouse;
use Filament\Resources\Pages\CreateRecord;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // §2 warehouses.code rule: a blank code is derived from `name`,
        // never stored as an empty string.
        if (blank($data['code'] ?? null)) {
            $data['code'] = self::deriveCode((string) ($data['name'] ?? ''));
        }

        return $data;
    }

    /**
     * Derive a unique warehouse code from the warehouse name (§2):
     * uppercase, non-alphanumeric runs replaced with `-`, trimmed,
     * truncated to 50 chars, `-NNN` appended on unique collision.
     */
    private static function deriveCode(string $name): string
    {
        $base = mb_substr(mb_trim((string) preg_replace('/[^A-Z0-9]+/', '-', mb_strtoupper($name)), '-'), 0, 50);

        if ($base === '') {
            $base = 'WH';
        }

        if (! Warehouse::where('code', $base)->exists()) {
            return $base;
        }

        for ($i = 1; $i <= 999; $i++) {
            $candidate = mb_substr($base, 0, 46).'-'.sprintf('%03d', $i);

            if (! Warehouse::where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new DomainRuleViolationException('errors.warehouse_code_exhausted', ['name' => $name]);
    }
}
