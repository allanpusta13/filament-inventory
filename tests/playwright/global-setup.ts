import { execSync } from 'child_process';

export default async function globalSetup(): Promise<void> {
  execSync('php artisan cache:clear', {
    cwd: 'D:\\Personal\\filament-inventory',
    encoding: 'utf-8',
    timeout: 15000,
  });
}
