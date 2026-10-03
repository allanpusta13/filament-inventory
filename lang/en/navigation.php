<?php

declare(strict_types=1);

/**
 * Navigation group labels (§1A.1 / §0A.2a).
 *
 * The `make()` keys in `AdminPanelProvider` are stable identifiers
 * (`CATALOG`, `OPERATIONS`, etc.); the human-readable labels resolve
 * through these keys (§1A.1: "Never pass a translated string to
 * `make()`").
 */
return [
    'groups' => [
        'catalog' => 'Catalog',
        'operations' => 'Operations',
        'purchasing' => 'Purchasing',
        'sales' => 'Sales',
        'audit_ledgers' => 'Audit Ledgers',
        'system_admin' => 'System Admin',
    ],
];
