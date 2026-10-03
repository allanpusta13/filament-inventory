<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * i18n audit — lang/en as the source of truth.
 *
 * Loads lang/en/*.php directly (bypasses Laravel's translator) so the
 * catalogue cannot fail silently. Walks app/** and resources/** and
 * reports every referenced __() / @lang() / trans() key that does not
 * exist in the catalogue.
 *
 * On failure, prints:
 *   - A per-file count of how many keys were loaded from lang/en
 *     (so a partially-loaded catalogue is immediately visible)
 *   - The full list of missing keys with source file paths
 *   - A warning if resources/lang/ exists (Laravel 11+ does NOT read it)
 */

// ===========================================================================
// Helpers
// ===========================================================================

/**
 * Paths walked for translation references.
 *
 * @return array<int, string>
 */
function auditRoots(): array
{
    return array_values(array_filter([
        base_path('app'),
        base_path('resources'),
    ], 'is_dir'));
}

/**
 * Locate the canonical lang/en directory.
 *
 * Returns the path that exists, preferring `lang/en` (Laravel 11+
 * canonical) over the legacy `resources/lang/en`.
 *
 * @return array{path: string, is_legacy: bool}|null
 */
function locateLangEn(): ?array
{
    $canonical = base_path('lang/en');
    $legacy = base_path('resources/lang/en');

    if (is_dir($canonical)) {
        return ['path' => $canonical, 'is_legacy' => false];
    }

    if (is_dir($legacy)) {
        return ['path' => $legacy, 'is_legacy' => true];
    }

    return null;
}

/**
 * Flatten a nested array into dot-notation keys.
 *
 * @param  array<int|string, mixed>  $array
 * @return array<int, string>
 */
function flattenCatalogue(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = array_merge($keys, flattenCatalogue($value, $full));
        } else {
            $keys[] = $full;
        }
    }

    return $keys;
}

/**
 * Load every key from the given lang directory as a flat catalogue.
 *
 * Uses scandir() — the most reliable directory enumeration on Windows.
 * Fails loudly if a file does not return an array.
 *
 * @return array{
 *     catalogue: array<string, string>,
 *     per_file: array<string, int>,
 *     errors: array<string, string>,
 * }
 */
function loadCatalogue(string $langEnPath): array
{
    $catalogue = [];
    $perFile = [];
    $errors = [];

    // scandir gives us just filenames — no path separator surprises.
    $entries = scandir($langEnPath);

    if ($entries === false) {
        return ['catalogue' => [], 'per_file' => [], 'errors' => ['scandir' => 'failed']];
    }

    $phpFiles = array_values(array_filter(
        $entries,
        fn (string $entry): bool => str_ends_with($entry, '.php'),
    ));

    sort($phpFiles);

    foreach ($phpFiles as $filename) {
        $namespace = pathinfo($filename, PATHINFO_FILENAME);

        // Use DIRECTORY_SEPARATOR — require() is most reliable with the
        // native separator on Windows.
        $path = $langEnPath.DIRECTORY_SEPARATOR.$filename;

        try {
            $contents = require $path;
        } catch (Throwable $e) {
            $errors[$filename] = 'Threw '.$e::class.': '.$e->getMessage();
            $perFile[$filename] = 0;

            continue;
        }

        if (! is_array($contents)) {
            $errors[$filename] = 'Did not return an array — returned '.get_debug_type($contents);
            $perFile[$filename] = 0;

            continue;
        }

        $fileKeys = flattenCatalogue($contents);
        $perFile[$filename] = count($fileKeys);

        foreach ($fileKeys as $key) {
            $catalogue["{$namespace}.{$key}"] = $filename;
        }
    }

    ksort($catalogue);

    return [
        'catalogue' => $catalogue,
        'per_file' => $perFile,
        'errors' => $errors,
    ];
}

/**
 * Recursively enumerate every .php and .blade.php file under a root.
 *
 * @return array<int, string> Absolute native-separator paths.
 */
function auditFilesUnder(string $root): array
{
    $files = [];

    if (! is_dir($root)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS,
        ),
    );

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $ext = mb_strtolower($file->getExtension());
        $name = $file->getFilename();

        // Include .php and .blade.php (both end with .php).
        if ($ext === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/**
 * Extract every statically-resolvable translation key from a file.
 *
 * Handles:
 *   __('key')
 *   __("key")
 *   trans('key')
 *   trans_choice('key', ...)
 *
 *   @lang('key')
 *
 * @return array<int, string>
 */
function extractKeysFromFile(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        return [];
    }

    preg_match_all(
        '/(?:__|@lang|trans|trans_choice)\(\s*[\'"]([^\'"]+)[\'"]/',
        $contents,
        $matches,
    );

    $keys = [];

    foreach ($matches[1] as $key) {
        // Skip interpolated strings — dynamic, cannot be resolved.
        if (str_contains($key, '$') || str_contains($key, '{')) {
            continue;
        }

        $keys[] = $key;
    }

    return array_values(array_unique($keys));
}

/**
 * Human-readable path relative to base_path().
 */
function rel(string $path): string
{
    return mb_ltrim(str_replace(
        [base_path().DIRECTORY_SEPARATOR, base_path().'/', '\\'],
        ['', '', '/'],
        $path,
    ), '/');
}

// ===========================================================================
// THE AUDIT
// ===========================================================================

it('resolves every i18n key under app/** and resources/** against lang/en', function () {
    // ---------------------------------------------------------------------
    // 0. Locate lang/en — flag the Laravel 10 layout.
    // ---------------------------------------------------------------------
    $langInfo = locateLangEn();

    if ($langInfo === null) {
        $this->fail(
            "No lang/en directory found.\n".
            "Looked in:\n".
            '  - '.base_path('lang/en')."\n".
            '  - '.base_path('resources/lang/en')."\n"
        );
    }

    $langEnPath = $langInfo['path'];

    // ---------------------------------------------------------------------
    // 1. Load the catalogue directly — bypass Laravel's translator.
    // ---------------------------------------------------------------------
    ['catalogue' => $catalogue, 'per_file' => $perFile, 'errors' => $loadErrors]
        = loadCatalogue($langEnPath);

    // ---------------------------------------------------------------------
    // 2. Walk app/** and resources/** for translation references.
    // ---------------------------------------------------------------------
    $allReferences = []; // key => [source paths]

    foreach (auditRoots() as $root) {
        foreach (auditFilesUnder($root) as $file) {
            foreach (extractKeysFromFile($file) as $key) {
                $allReferences[$key][] = rel($file);
            }
        }
    }

    // ---------------------------------------------------------------------
    // 3. Cross-reference: which references are NOT in the catalogue?
    // ---------------------------------------------------------------------
    $missing = [];

    foreach ($allReferences as $key => $sources) {
        if (! array_key_exists($key, $catalogue)) {
            $missing[$key] = array_values(array_unique($sources));
        }
    }

    ksort($missing);

    // ---------------------------------------------------------------------
    // 4. Build a diagnostics block — always shown on failure.
    // ---------------------------------------------------------------------
    $diagnostics = [];

    $diagnostics[] = 'lang/en: '.rel($langEnPath)
        .($langInfo['is_legacy']
            ? '  ⚠  LEGACY PATH — Laravel 11+ does NOT read resources/lang/. Move to lang/en/.'
            : '');
    $diagnostics[] = 'Total catalogue keys loaded: '.count($catalogue);
    $diagnostics[] = 'Total references found: '.count($allReferences);
    $diagnostics[] = 'Missing from catalogue: '.count($missing);
    $diagnostics[] = '';

    $diagnostics[] = 'Per-file catalogue load (lang/en/*.php):';
    foreach ($perFile as $file => $count) {
        $flag = $count === 0 ? '  ❌' : '  ✅';
        $diagnostics[] = "{$flag} {$file} — {$count} key(s)";
    }

    if (! empty($loadErrors)) {
        $diagnostics[] = '';
        $diagnostics[] = 'Loader errors:';
        foreach ($loadErrors as $file => $error) {
            $diagnostics[] = "  ❌ {$file} — {$error}";
        }
    }

    // ---------------------------------------------------------------------
    // 5. Write a persistent report — always, regardless of pass/fail.
    // ---------------------------------------------------------------------
    $report = "# i18n Audit — app/** + resources/**\n\n";

    $report .= "## Diagnostics\n\n```\n"
        .implode("\n", $diagnostics)
        ."\n```\n\n";

    if (! empty($missing)) {
        $report .= "## Missing keys\n\n";

        // Group by namespace.
        $byNamespace = [];
        foreach ($missing as $key => $sources) {
            $namespace = explode('.', $key, 2)[0];
            $byNamespace[$namespace][$key] = $sources;
        }
        ksort($byNamespace);

        foreach ($byNamespace as $namespace => $entries) {
            $report .= "### `{$namespace}.*` — ".count($entries)." key(s)\n\n";
            foreach ($entries as $key => $sources) {
                $report .= "- `{$key}`\n";
                foreach ($sources as $source) {
                    $report .= "    - `{$source}`\n";
                }
            }
            $report .= "\n";
        }
    } else {
        $report .= "No missing keys.\n";
    }

    File::ensureDirectoryExists(storage_path('logs'));
    File::put(storage_path('logs/i18n-audit.md'), $report);

    // ---------------------------------------------------------------------
    // 6. Fail loudly with everything — no truncation.
    // ---------------------------------------------------------------------
    if (! empty($missing)) {
        $inline = collect($missing)
            ->map(function (array $sources, string $key): string {
                $files = collect($sources)
                    ->map(fn (string $p) => "        {$p}")
                    ->implode("\n");

                return "  - {$key}\n{$files}";
            })
            ->implode("\n");

        $this->fail(
            implode("\n", $diagnostics)
            ."\nFull report: storage/logs/i18n-audit.md\n\n"
            .$inline
        );
    }

    // Sanity assertion even on pass.
    expect(count($catalogue))->toBeGreaterThan(
        500,
        'Catalogue has only '.count($catalogue).' keys. See storage/logs/i18n-audit.md.'
    );

    expect($missing)->toBeEmpty();
});
