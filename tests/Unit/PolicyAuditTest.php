<?php

declare(strict_types=1);

/**
 * Phase 4 Principle-A8 audit: all permission/role logic must live in
 * app/Policies. pest-plugin-arch is installed, so the Gate-facade rule
 * uses arch(); the token scans are deterministic static scans because
 * arch() cannot match method-call tokens like ->isAdmin().
 *
 * Policy DELEGATION via $user->can('ability', ...) / auth()->user()?->can(...)
 * is the blessed A8 end-state (callers compose status-display logic with a
 * policy call) and is therefore NOT a violation — only re-derived role
 * logic (isAdmin/isAuditor/isBranchManager/isWarehouseStaff, Gate::, raw
 * role tokens) is flagged.
 *
 * Filament backlog: Phase-4 consolidation moved every relocatable site
 * into Policy methods (viewAny/viewAdminReview/forceDelete). What remains
 * below is the NEEDS-RULING set: sites with no zero-behavior-change Policy
 * home (DirectTransferResource admin-only gates vs StockMovementPolicy's
 * shared all-roles viewAny / false create; TransferRequisitionResource
 * warehouse data-scoping). Any NEW violation, or any silent fix of the
 * NEEDS-RULING set, fails the test.
 */
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array<string, list<string>> file path => matched lines (file:line:content)
 */
function scanRoleTokens(string $directory): array
{
    $pattern = '/\bisAdmin\s*\(|\bisAuditor\s*\(|\bisBranchManager\s*\(|\bisWarehouseStaff\s*\(|Gate::|auth\(\)->user\(\)->role/';

    $hits = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $lines = file($file->getPathname());

        foreach ($lines as $number => $content) {
            if (preg_match_all($pattern, $content) > 0) {
                $hits[$file->getPathname()][] = $file->getPathname().':'.($number + 1).': '.mb_trim($content);
            }
        }
    }

    ksort($hits);

    return $hits;
}

arch('services layer')->expect('App\Services')->not->toUse(['Illuminate\Support\Facades\Gate']);

it('services_contain_no_role_or_permission_logic', function () {
    $hits = scanRoleTokens(app_path('Services'));

    expect($hits)->toBeEmpty(
        'Role/permission logic found in app/Services (must live in app/Policies):'.PHP_EOL
        .implode(PHP_EOL, array_merge(...array_values($hits)))
    );
});

it('filament_role_checks_are_exactly_the_known_consolidation_backlog', function () {
    // file (relative to app/) => expected matched-line count.
    // Phase-4 consolidation eliminated every relocatable site; only the
    // NEEDS-RULING set (no zero-behavior-change Policy home) remains.
    $expected = [
        'Filament/Resources/DirectTransfers/DirectTransferResource.php' => 2,
        'Filament/Resources/TransferRequisitions/TransferRequisitionResource.php' => 1,
    ];

    $hits = scanRoleTokens(app_path('Filament'));

    $actual = [];
    foreach ($hits as $path => $lines) {
        $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $path);
        $actual[str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = count($lines);
    }
    ksort($actual);

    $report = empty($hits) ? '(no hits)' : implode(PHP_EOL, array_merge(...array_values($hits)));

    expect($actual)->toBe($expected,
        'Filament role/permission scan drifted from the known Phase-4 backlog.'
        .' New violations must be moved to app/Policies; silent fixes must be recorded.'
        .PHP_EOL.$report
    );
});
