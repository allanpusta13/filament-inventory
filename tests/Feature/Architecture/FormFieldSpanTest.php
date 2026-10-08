<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * §7O.8 / §0A.13 unit-ratio field guard.
 *
 * Auto-derived `*_unit_ratio` fields live inside `->table([...])`
 * repeaters, where the visible column label comes from the sibling
 * `TableColumn`, not the field. So `->hiddenLabel()` alone strips the
 * accessible name and a screen reader announces the input with none.
 *
 * Invariants enforced per ratio field:
 *   1. accessible name via `->label(__())` OR `->hiddenLabel()` +
 *      `->extraAttributes(['aria-label' => __()])`;
 *   2. explicit `->columnSpan([...])` carrying a `'default'` key;
 *   3. ratio stays auto-derived (`->disabled()` + `->dehydrated()`);
 *   4. `aria-label` is never a hard-coded string.
 *
 * Line-based chain parser with bracket-depth tracking (the chains
 * embed multi-line arrays, so a naive trailing-comma terminator would
 * stop early inside `->extraAttributes([...])`). No framework boot.
 */
$sourceForms = function (): array {
    $files = [];
    foreach (File::allFiles(app_path('Filament/Resources')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace('/', '\\', $file->getRelativePathname());

        if (! preg_match('#Schemas\\\\[A-Za-z]+Form\.php$#', $relative)) {
            continue;
        }

        $files[$relative] = $file->getPathname();
    }

    return $files;
};

it('ratio fields declare an accessible name and an explicit column-span default', function () use ($sourceForms) {
    $violations = [];

    foreach ($sourceForms() as $relative => $path) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $index => $line) {
            if (preg_match('/TextInput::make\(\'[a-z_]*unit_ratio\'\)/', $line) !== 1) {
                continue;
            }

            // Accumulate the whole chained call, tracking bracket depth so
            // nested multi-line arrays never terminate the chain early.
            $chain = '';
            $depth = 0;

            for ($i = $index; $i < count($lines); $i++) {
                $code = preg_replace('#//.*$#', '', $lines[$i]);
                $chain .= $code."\n";

                $depth += mb_substr_count($code, '(') + mb_substr_count($code, '[');
                $depth -= mb_substr_count($code, ')') + mb_substr_count($code, ']');

                if ($depth === 0 && preg_match('/[,;]\s*$/', $code) === 1) {
                    break;
                }
            }

            $field = $index + 1;

            if (preg_match("/->columnSpan\(\[[^\]]*'default'\s*=>/s", $chain) !== 1) {
                $violations[] = "{$relative}:{$field} ratio field lacks ->columnSpan([...]) with an explicit 'default' key";
            }

            $hasLabel = str_contains($chain, '->label(__(');
            $hasAriaLabel = preg_match("/'aria-label'\s*=>\s*__\(/", $chain) === 1;
            $hasHiddenWithAria = str_contains($chain, '->hiddenLabel()') && $hasAriaLabel;

            if (! $hasLabel && ! $hasHiddenWithAria) {
                $violations[] = "{$relative}:{$field} ratio field has no accessible name (needs ->label(__()) or ->hiddenLabel() + translated aria-label)";
            }

            if (str_contains($chain, 'aria-label') && ! $hasAriaLabel) {
                $violations[] = "{$relative}:{$field} ratio field hard-codes aria-label instead of wrapping a translation key in __()";
            }

            if (! str_contains($chain, '->disabled()') || ! str_contains($chain, '->dehydrated()')) {
                $violations[] = "{$relative}:{$field} ratio field must stay auto-derived (->disabled() + ->dehydrated())";
            }
        }
    }

    expect($violations)->toBe([]);
});
