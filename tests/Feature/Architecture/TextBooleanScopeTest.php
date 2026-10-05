<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * §12 — Filament v5 boolean scope guard.
 *
 * boolean() is defined on IconEntry / IconColumn only. On a TextEntry /
 * TextColumn the call falls through Macroable::__call() and throws
 * BadMethodCallException at render time.
 *
 * Line-based chain parser — deterministic, no framework boot, no reliance
 * on internal Filament component APIs. A Text(Entry|Column) chain ends at
 * the first line containing `;`, so unrelated components in the same file
 * are never conflated.
 */
it('does not call boolean() on TextEntry or TextColumn', function () {
    $offenders = [];

    foreach (File::allFiles(app_path('Filament')) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $inTextChain = false;

        foreach (File::lines($file->getPathname()) as $line) {
            // Strip comments so a doc/comment mention is never a violation.
            $code = preg_replace('#//.*$#', '', $line);

            if (preg_match('/Text(?:Entry|Column)::make\(/', $code) === 1) {
                $inTextChain = true;
            }

            if ($inTextChain && preg_match('/->boolean\(\)/', $code) === 1) {
                $offenders[] = $file->getRelativePathname();
                $inTextChain = false;

                break;
            }

            // A schema entry chain is terminated by the element's trailing
            // `,` (array context) or a `;` (statement) — never a bare
            // newline, since chains intentionally span lines.
            if (preg_match('/[,;]\s*$/', $code) === 1) {
                $inTextChain = false;
            }
        }
    }

    expect($offenders)->toBe([]);
});
