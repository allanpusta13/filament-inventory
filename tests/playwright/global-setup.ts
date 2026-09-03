import { execSync } from 'child_process';

const CWD = 'D:\\\\Personal\\\\filament-inventory';

function run(cmd: string): void {
  try {
    execSync(cmd, { cwd: CWD, encoding: 'utf-8', timeout: 120_000, stdio: 'pipe' });
  } catch (error) {
    console.error(`Warning: Setup command failed: ${cmd}`);
    console.error(error);
    // We don't exit, but we log the error.
  }
}

export default async function globalSetup(): Promise<void> {
  console.log('Refreshing migrations and seeding database...');
  run('php artisan migrate:fresh --seed --no-interaction');
  console.log('Database seeded successfully.');
}