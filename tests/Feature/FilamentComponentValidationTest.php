<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Filament Component Validation Test Suite
|--------------------------------------------------------------------------
|
| Production-ready diagnostic scan of all app/Filament/ PHP files.
| Detects: broken imports, invalid base classes, wrong Filament v4->v5
| namespaces, malformed Heroicons, PSR-4 misalignment, debug statements.
|
| Usage:
|   php artisan test tests/Feature/FilamentComponentValidationTest.php --verbose
|
| Output format for ALL failures:
|   STRICT FAILURE: [Component/Class Name] — [Reason] — in: [Relative File Path]
|
*/

// ── Ground Truth (verified against vendor/filament/ v5.7.8) ──────────────────────

const VALID_BASE_CLASSES = [
    'Filament\Resources\Resource',
    'Filament\Schemas\Components\Component',
    'Filament\Tables\Columns\Column',
    'Filament\Resources\Pages\Page',
    'Filament\Pages\Page',
    'Filament\Pages\Dashboard',
    'Filament\Clusters\Cluster',
    'Filament\Infolists\Components\Entry',
    'Filament\Infolists\Components\TextEntry',
    'Filament\Infolists\Components\IconEntry',
    'Filament\Infolists\Components\ImageEntry',
    'Filament\Infolists\Components\KeyValueEntry',
    'Filament\Infolists\Components\CodeEntry',
    'Filament\Infolists\Components\ColorEntry',
    'Filament\Infolists\Components\RepeatableEntry',
    'Filament\Infolists\Components\ViewEntry',
    'Filament\Widgets\Widget',
    'Filament\Resources\RelationManagers\RelationManager',
    'Filament\Auth\Pages\Login',
    'Filament\Actions\Action',
    'Filament\Actions\BulkAction',
    'Filament\Actions\CreateAction',
    'Filament\Actions\DeleteAction',
    'Filament\Actions\EditAction',
    'Filament\Actions\ViewAction',
    'Filament\Actions\Exports\Exporter',
    'Filament\Tables\Columns\BadgeColumn',
    'Filament\Tables\Columns\SelectColumn',
    'Filament\Tables\Columns\TextColumn',
    'Filament\Tables\Columns\ImageColumn',
    'Filament\Tables\Columns\IconColumn',
    'Filament\Tables\Columns\ToggleColumn',
    'Filament\Tables\Columns\CheckboxColumn',
    'Filament\Tables\Columns\Layout\Split',
    'Filament\Schemas\Components\Section',
    'Filament\Schemas\Components\Grid',
    'Filament\Schemas\Components\Fieldset',
    'Filament\Schemas\Components\Tabs',
    'Filament\Schemas\Components\Tab',
    'Filament\Schemas\Components\Wizard',
    'Filament\Schemas\Components\Utilities\Set',
    'Filament\Schemas\Components\Utilities\Get',
    'Filament\Forms\Components\TextInput',
    'Filament\Forms\Components\Textarea',
    'Filament\Forms\Components\Select',
    'Filament\Forms\Components\Checkbox',
    'Filament\Forms\Components\Toggle',
    'Filament\Forms\Components\DatePicker',
    'Filament\Forms\Components\DateTimePicker',
    'Filament\Forms\Components\TimePicker',
    'Filament\Forms\Components\ColorPicker',
    'Filament\Forms\Components\FileUpload',
    'Filament\Forms\Components\RichEditor',
    'Filament\Forms\Components\Repeater',
    'Filament\Forms\Components\TagsInput',
    'Filament\Forms\Components\KeyValues',
    'Filament\Forms\Components\Placeholder',
    'Filament\Forms\Components\Hidden',
    'Filament\Forms\Components\Radio',
    'Filament\Forms\Components\Boolean',
    'Filament\Forms\Components\MarkdownEditor',
];

const KNOWN_BROKEN_NAMESPACES = [
    'Filament\Pages\Actions' => 'Moved to Filament\Actions\* in v5',
    'Filament\Forms\Set' => 'Moved to Filament\Schemas\Components\Utilities\Set',
    'Filament\Forms\Get' => 'Moved to Filament\Schemas\Components\Utilities\Get',
    'Filament\Forms\Components\Section' => 'Moved to Filament\Schemas\Components\Section',
    'Filament\Tables\Actions\BulkAction' => 'Moved to Filament\Actions\BulkAction',
    'Filament\Infolists\Components\Infolist' => 'Does not exist; use Filament\Schemas\Schema',
    'Filament\Infolists\Components\Split' => 'Moved to Filament\Tables\Columns\Layout\Split',
];

// ── Dataset ──────────────────────────────────────────────────────────────────────

dataset('filamentPhpFiles', function (): array {
    $files = [];
    $projectRoot = dirname(__DIR__, 2);
    $directory = $projectRoot.'/app/Filament';

    if (! is_dir($directory)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = mb_ltrim(Str::after($file->getPathname(), $projectRoot), DIRECTORY_SEPARATOR);
        $files[$relativePath] = [$file->getPathname(), $relativePath];
    }

    return $files;
});

dataset('filamentPhpFilesWithContent', function (): array {
    $files = [];
    $projectRoot = dirname(__DIR__, 2);
    $directory = $projectRoot.'/app/Filament';

    if (! is_dir($directory)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relativePath = mb_ltrim(Str::after($file->getPathname(), $projectRoot), DIRECTORY_SEPARATOR);
        $content = file_get_contents($file->getPathname());
        $files[$relativePath] = [$file->getPathname(), $relativePath, $content];
    }

    return $files;
});

// ── Helpers ──────────────────────────────────────────────────────────────────────

function extractClassName(string $filePath): string
{
    return pathinfo($filePath, PATHINFO_FILENAME);
}

function extractNamespace(string $content): ?string
{
    if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $content, $matches)) {
        return $matches[1];
    }

    return null;
}

function extractUseStatements(string $content): array
{
    preg_match_all('/^use\s+([\w\\\\]+(?:\s+as\s+\w+)?)\s*;/m', $content, $matches);

    return array_map(function ($statement) {
        $statement = mb_trim($statement);
        if (preg_match('/^(.+)\s+as\s+(\w+)$/', $statement, $matches)) {
            return ['fqcn' => $matches[1], 'alias' => $matches[2]];
        }

        return ['fqcn' => $statement, 'alias' => null];
    }, $matches[1]);
}

function extractExtendsClass(string $content): ?string
{
    if (preg_match('/class\s+\w+\s+extends\s+([\w\\\\]+)/', $content, $matches)) {
        return $matches[1];
    }

    return null;
}

function resolveClassName(string $className, array $useStatements, array $currentNamespaceParts): string
{
    if (str_starts_with($className, '\\')) {
        return mb_ltrim($className, '\\');
    }

    foreach ($useStatements as $use) {
        $shortName = class_basename($use['fqcn']);
        if ($shortName === $className || $use['alias'] === $className) {
            return $use['fqcn'];
        }
    }

    foreach ($useStatements as $use) {
        if (str_ends_with($use['fqcn'], '\\'.$className)) {
            return $use['fqcn'];
        }
    }

    return implode('\\', array_merge($currentNamespaceParts, [$className]));
}

function validateHeroicon(string $heroiconCall): ?string
{
    // Filament v5 heroicon format: heroicon-{size}-{name} where size = c|o|s|m
    if (preg_match('/^heroicon-([cosm])-(.+)$/', $heroiconCall, $matches)) {
        $size = $matches[1];
        $name = $matches[2];

        // Build the icon value as stored in the Heroicon enum (e.g. 'o-arrow-left', 'arrow-left')
        $enumValue = $name;
        if ($size === 'o') {
            $enumValue = 'o-'.$name;
        }

        // Validate against the Filament Heroicon enum
        $heroiconEnum = Filament\Support\Icons\Heroicon::tryFrom($enumValue);
        if ($heroiconEnum === null) {
            // Also try without prefix (solid/mini variants use bare name)
            $heroiconEnum = Filament\Support\Icons\Heroicon::tryFrom($name);
        }

        if ($heroiconEnum === null) {
            return "heroicon '{$heroiconCall}' — no matching Heroicon enum value for '{$enumValue}'";
        }

        return null;
    }

    return "heroicon call '{$heroiconCall}' does not match pattern heroicon-{{c|o|s|m}}-{{name}}";
}

// ── Tests ────────────────────────────────────────────────────────────────────────

/*
|--------------------------------------------------------------------------
| Group 1: PSR-4 Namespace Alignment
|--------------------------------------------------------------------------
*/

test('PSR-4 namespace matches filesystem path', function (string $filePath, string $relativePath): void {
    $content = file_get_contents($filePath);
    $namespace = extractNamespace($content);
    $className = extractClassName($filePath);

    // Assert file was read successfully
    expect($content)->not->toBeEmpty();

    if ($namespace === null) {
        $this->fail("STRICT FAILURE: {$className} — No namespace declaration — in: {$relativePath}");

        return;
    }

    $normalizedPath = str_replace('\\', '/', $relativePath);
    $afterFilament = Str::after($normalizedPath, 'app/Filament/');
    $classNamePart = class_basename($filePath).'.php';
    $subPath = preg_replace('/\/[^\/]+\.php$/', '', $afterFilament);
    $expectedNamespace = 'App\\Filament';
    if ($subPath !== '') {
        $expectedNamespace .= '\\'.str_replace('/', '\\', $subPath);
    }

    // Assert namespace was extracted and matches expected PSR-4 path
    expect($namespace)->toBe($expectedNamespace, "PSR-4 mismatch in {$relativePath}");
})->with('filamentPhpFiles');

/*
|--------------------------------------------------------------------------
| Group 2: Broken Imports / Class Loading
|--------------------------------------------------------------------------
*/

test('all use imports resolve to valid classes', function (string $filePath, string $relativePath, string $content): void {
    $className = extractClassName($filePath);
    $uses = extractUseStatements($content);

    // Assert use statements were extracted
    expect($uses)->toBeArray();

    $broken = [];

    foreach ($uses as $use) {
        $fqcn = $use['fqcn'];

        if (str_contains($fqcn, '{')) {
            continue;
        }

        // Skip bare namespace imports (e.g. `use Filament\Forms;`)
        // These are valid when the code uses Forms\Components\TextInput::make() style
        $lastSegment = class_basename($fqcn);
        $isKnownNamespace = in_array($fqcn, [
            'Filament\Forms', 'Filament\Tables', 'Filament\Actions',
            'Filament\Infolists', 'Filament\Schemas', 'Filament\Resources',
            'Filament\Pages', 'Filament\Widgets', 'Filament\Clusters',
            'Filament\Support', 'Filament\Notifications', 'Filament\Enums',
            'Filament\Facades', 'App\Filament',
        ]);
        if ($isKnownNamespace) {
            continue;
        }

        // Check if the class/interface/trait/enum actually exists
        if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! trait_exists($fqcn) && ! enum_exists($fqcn)) {
            $reason = constant('KNOWN_BROKEN_NAMESPACES')[$fqcn] ?? 'Class not found';
            $broken[] = "{$fqcn} — {$reason}";
        }
    }

    // Assert no broken imports were found
    expect($broken)->toBeEmpty("Broken imports in {$relativePath}: ".implode('; ', $broken));
})->with('filamentPhpFilesWithContent');

/*
|--------------------------------------------------------------------------
| Group 3: Filament v5 Base Class Verification
|--------------------------------------------------------------------------
*/

test('extends references a valid Filament v5 base class', function (string $filePath, string $relativePath, string $content): void {
    $className = extractClassName($filePath);
    $extends = extractExtendsClass($content);

    // If no extends statement, test passes (nothing to validate)
    if ($extends === null) {
        expect(true)->toBeTrue();

        return;
    }

    $uses = extractUseStatements($content);
    $namespaceParts = explode('\\', extractNamespace($content) ?? '');
    $resolvedClass = resolveClassName($extends, $uses, $namespaceParts);

    // If not a Filament class, skip validation
    if (! str_starts_with($resolvedClass, 'Filament\\')) {
        expect(true)->toBeTrue();

        return;
    }

    $isValid = false;

    foreach (constant('VALID_BASE_CLASSES') as $valid) {
        if ($resolvedClass === $valid) {
            $isValid = true;
            break;
        }

        if (class_exists($resolvedClass) && class_exists($valid) && is_subclass_of($resolvedClass, $valid)) {
            $isValid = true;
            break;
        }
    }

    if (! $isValid && preg_match('/^Filament\\Resources\\Pages\\(Create|Edit|List|View)Record$/', $resolvedClass)) {
        $isValid = true;
    }

    if (! $isValid && preg_match('/^Filament\\Resources\\RelationManagers\\[A-Za-z]+$/', $resolvedClass)) {
        $isValid = true;
    }

    // Assert the base class is valid
    expect($isValid)->toBeTrue("Invalid Filament base class: {$resolvedClass} in {$relativePath}");
})->with('filamentPhpFilesWithContent');

/*
|--------------------------------------------------------------------------
| Group 4: Component/Field Namespace Audit
|--------------------------------------------------------------------------
*/

test('no deprecated Filament v4 namespace imports', function (string $filePath, string $relativePath, string $content): void {
    $className = extractClassName($filePath);
    $uses = extractUseStatements($content);

    $violations = [];

    foreach ($uses as $use) {
        $fqcn = $use['fqcn'];

        if (isset(constant('KNOWN_BROKEN_NAMESPACES')[$fqcn])) {
            $violations[] = "{$fqcn} → ".constant('KNOWN_BROKEN_NAMESPACES')[$fqcn];
        }
    }

    // Assert no deprecated v4 namespaces were found
    expect($violations)->toBeEmpty("Deprecated v4 namespaces in {$relativePath}: ".implode('; ', $violations));
})->with('filamentPhpFilesWithContent');

/*
|--------------------------------------------------------------------------
| Group 5: Heroicon Audit
|--------------------------------------------------------------------------
*/

test('heroicon calls reference valid blade files', function (string $filePath, string $relativePath): void {
    $className = extractClassName($filePath);
    $content = file_get_contents($filePath);

    // Assert file was read successfully
    expect($content)->not->toBeEmpty();

    $pattern = '/heroicon-(?:cosm)-[a-z0-9-]+/';
    preg_match_all($pattern, $content, $matches);

    $errors = [];

    foreach ($matches[0] as $heroiconCall) {
        $result = validateHeroicon($heroiconCall);
        if ($result !== null) {
            $errors[] = $result;
        }
    }

    // Assert no heroicon errors were found
    expect($errors)->toBeEmpty("Heroicon issues in {$relativePath}: ".implode('; ', $errors));
})->with('filamentPhpFiles');

/*
|--------------------------------------------------------------------------
| Group 6: Debug Statement Detection
|--------------------------------------------------------------------------
*/

test('no debug statements in production code', function (string $filePath, string $relativePath, string $content): void {
    $className = extractClassName($filePath);

    // Assert content was provided
    expect($content)->not->toBeEmpty();

    $debugPatterns = [
        '/\bdd\s*\(/' => 'dd()',
        '/\bdump\s*\(/' => 'dump()',
        '/\bvar_dump\s*\(/' => 'var_dump()',
        '/\bprint_r\s*\(/' => 'print_r()',
        '/\bkray\s*\(/' => 'ray()',
        '/\blogger\s*\(/' => 'logger()',
        '/\bconsole\.log\s*\(/' => 'console.log()',
        '/\bdie\s*\(/' => 'die()',
        '/\bexit\s*\(/' => 'exit()',
    ];

    $found = [];

    foreach ($debugPatterns as $pattern => $label) {
        if (preg_match($pattern, $content)) {
            $found[] = $label;
        }
    }

    // Assert no debug statements were found
    expect($found)->toBeEmpty("Debug statements in {$relativePath}: ".implode(', ', $found));
})->with('filamentPhpFilesWithContent');
