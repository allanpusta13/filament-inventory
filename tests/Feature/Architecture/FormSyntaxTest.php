<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * §12 / §14 static guard for the Filament form layer.
 *
 *   1. Every PHP file under app/Filament/ must parse. A parse fatal
 *      (e.g. PHP 8.4 "Cannot use empty array elements in arrays")
 *      compiles the whole file away and only surfaces at request time
 *      as a 500 — far cheaper to catch here.
 *
 *   2. Auto-derived `*_unit_ratio` fields use the icon-with-tooltip
 *      pattern (§7O.8 rule #11): ->hiddenLabel() + ->afterContent(
 *      Icon::make(Heroicon::InformationCircle)->tooltip(__())). The
 *      legacy ->hintIcon() + ->hint() pair is rejected — it renders
 *      hint text on the label row, which is not the project pattern.
 */
it('has no PHP syntax errors in Filament classes', function () {
    $failures = [];

    foreach (File::allFiles(app_path('Filament')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $output = [];
        $exit = 0;

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $exit);

        if ($exit !== 0) {
            $failures[] = $file->getPathname().': '.implode(' ', $output);
        }
    }

    expect($failures)->toBe([]);
});

it('uses icon-with-tooltip on every ratio field, not hintIcon', function () {
    $violations = [];

    foreach (File::allFiles(app_path('Filament/Resources')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);

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

            // Only auto-derived ratio fields (disabled + dehydrated, filled
            // from the selected unit) carry the icon-with-tooltip pattern.
            // A user-entered definition field such as `base_unit_ratio`
            // keeps a visible label and has no tooltip, so skip it.
            if (! str_contains($chain, '->dehydrated()')) {
                continue;
            }

            if (str_contains($chain, '->hintIcon(')) {
                $violations[] = "{$file->getRelativePathname()}:{$field} ratio field uses ->hintIcon(); use ->afterContent(Icon::make(...)->tooltip(...))";
            }

            if (preg_match('/->afterContent\(\s*Icon::make\(/', $chain) !== 1) {
                $violations[] = "{$file->getRelativePathname()}:{$field} ratio field lacks ->afterContent(Icon::make(...)->tooltip(...))";
            }
        }
    }

    expect($violations)->toBe([]);
});
