import { test, expect, BASE_URL, USERS, login } from './helpers';

test.describe('Loss Ledger List', () => {
  test.setTimeout(180000);

  test('admin can view loss ledger list', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/loss-ledgers`);
    await expect(page.locator('h1')).toContainText('Loss Ledger');
  });

  test('auditor can view loss ledger list', async ({ page }) => {
    await login(page, USERS.auditor);
    await page.goto(`${BASE_URL}/admin/loss-ledgers`);
    await expect(page.locator('h1')).toContainText('Loss Ledger');
  });

  test('loss ledger page has no JS errors', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', (err) => {
      const msg = typeof err === 'string' ? err : (err as Error).message || '';
      if (!msg.includes('PhpDebugBar')) {
        errors.push(msg);
      }
    });

    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/loss-ledgers`);
    await page.waitForTimeout(2000);

    expect(errors).toHaveLength(0);
  });

  test('loss ledger list shows data table', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto(`${BASE_URL}/admin/loss-ledgers`);

    await expect(page.locator('.fi-ta-table, .fi-ta-empty-state').first()).toBeVisible({ timeout: 15000 });
  });
});
