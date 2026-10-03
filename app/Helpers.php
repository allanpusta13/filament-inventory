<?php

declare(strict_types=1);

use Illuminate\Support\Number;

/*
 * Here you can define your own helper functions.
 * Make sure to use the `function_exists` check to not declare the function twice.
 */

if (! function_exists('example')) {
    function example(): string
    {
        return 'This is an example function you can use in your project.';
    }
}

if (! function_exists('format_money')) {
    /**
     * Format a stored decimal(15,4) value at a fixed precision.
     *
     * Filament v5's `->money()` no longer accepts a `decimals:` argument —
     * precision is derived from the locale (default 2). Every cost/price
     * field in this blueprint is stored as decimal(15,4) and displayed at
     * 4 decimals, so this helper is the single source of truth for the
     * display precision contract.
     */
    function format_money(mixed $state, int $precision = 4): string
    {
        return Number::currency(
            (float) $state,
            config('app.currency'),
            precision: $precision,
        );
    }
}
