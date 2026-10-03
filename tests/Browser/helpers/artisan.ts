import { execSync, ExecSyncOptions } from "child_process";

const OPTS: ExecSyncOptions = {
    encoding: "utf-8",
    timeout: 45_000,
    stdio: "pipe",
};

/** Run `php artisan <command>`. */
export function artisan(command: string): string {
    return execSync(`php artisan ${command}`, OPTS) as unknown as string;
}

/** Run a PHP snippet via `php artisan tinker --execute="..."`. */
export function tinker(code: string): string {
    const escaped = code.replace(/"/g, '\\"').replace(/\$/g, "\\$");
    return execSync(
        `php artisan tinker --execute="${escaped}"`,
        OPTS,
    ) as unknown as string;
}

/** Fresh schema + canonical Phase 02 seed. */
export function freshSeed(): void {
    artisan("migrate:fresh --seed --force");
}

/** Truncate the ledger tables — used between some specs. */
export function cleanLedger(): void {
    tinker(`
    \\App\\Models\\StockMovement::query()->delete();
    \\App\\Models\\StockMovementIdempotencyKey::query()->delete();
    \\App\\Models\\InTransit::query()->delete();
    \\App\\Models\\LossLedger::query()->delete();
  `);
}
