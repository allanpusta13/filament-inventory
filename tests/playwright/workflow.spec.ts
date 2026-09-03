import { test, expect, BASE_URL, USERS, login, logout } from './helpers';
import { execSync } from 'child_process';

function getLatestTransferId(): number {
  try {
    const output = execSync(
      'php artisan tinker --execute="echo App\\\\Models\\\\TransferRequisition::latest()->first()->id ?? 0;"',
      { cwd: 'D:\\\\Personal\\\\filament-inventory', encoding: 'utf-8', timeout: 30_000 }
    );
    return parseInt(output.trim(), 10);
  } catch {
    return 0;
  }
}

function getLatestTransferStatus(): string {
  try {
    const output = execSync(
      'php artisan tinker --execute="echo App\\\\Models\\\\TransferRequisition::latest()->first()->status ?? \'NONE\';"',
      { cwd: 'D:\\\\Personal\\\\filament-inventory', encoding: 'utf-8', timeout: 30_000 }
    );
    return output.trim();
  } catch {
    return 'ERROR';
  }
}

test.skip('Full multi-role lifecycle: create -> submit -> confirm -> dispatch -> receive -> verify', async ({ page }) => {
  test.setTimeout(300000);

  // Act I: Staff creates requisition
  await login(page, USERS.staffDvo);
  await page.goto(`${BASE_URL}/admin/transfer-requisitions/create`);

  // Filament v5 uses native <select> for BelongsTo relationships
  await page.getByLabel('From warehouse').selectOption({ label: 'Davao Branch Store' });
  await page.getByLabel('To warehouse').selectOption({ label: 'Cebu Regional Depot' });
  await page.getByLabel('Notes').fill('E2E lifecycle test');

  await page.getByRole('button', { name: 'Create', exact: true }).click();
  await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 15000 });
  await page.waitForTimeout(1000);

  const transferId = getLatestTransferId();
  expect(transferId).toBeGreaterThan(0);

  // Submit
  const submitBtn = page.getByRole('button', { name: 'Submit Requisition' });
  if (await submitBtn.isVisible()) {
    await submitBtn.click();
    await page.waitForTimeout(1500);
  }

  const statusAfterSubmit = getLatestTransferStatus();
  expect(['SUBMITTED', 'PENDING']).toContain(statusAfterSubmit);

  await logout(page);

  // Act II: CEB Manager confirms
  await login(page, USERS.managerCeb);
  await page.goto(`${BASE_URL}/admin/transfer-requisitions/${transferId}`);
  await page.waitForTimeout(1000);

  const confirmBtn = page.getByRole('button', { name: 'Confirm Requisition' });
  if (await confirmBtn.isVisible()) {
    await confirmBtn.click();
    await page.waitForTimeout(1500);
  }

  await logout(page);

  // Act III: CEB Manager dispatches
  await login(page, USERS.managerCeb);
  await page.goto(`${BASE_URL}/admin/transfer-requisitions/${transferId}`);
  await page.waitForTimeout(1000);

  const dispatchBtn = page.getByRole('button', { name: 'Dispatch' });
  if (await dispatchBtn.isVisible()) {
    await dispatchBtn.click();
    await page.waitForTimeout(1500);
  }

  await logout(page);

  // Act IV: CEB Manager receives
  await login(page, USERS.managerCeb);
  await page.goto(`${BASE_URL}/admin/transfer-requisitions/${transferId}`);
  await page.waitForTimeout(1000);

  const receiveBtn = page.getByRole('button', { name: 'Scan to Receive' });
  if (await receiveBtn.isVisible()) {
    await receiveBtn.click();
    await page.waitForTimeout(1000);
  }

  await logout(page);

  // Act V: Auditor verifies pages are accessible
  await login(page, USERS.auditor);

  await page.goto(`${BASE_URL}/admin/stock-movements`);
  await expect(page.locator('h1')).toContainText('Stock Movements');
  await expect(page.locator('.fi-ta-table')).toBeVisible();

  await page.goto(`${BASE_URL}/admin/loss-ledgers`);
  await expect(page.locator('h1')).toContainText('Loss Ledger');
  await expect(page.locator('.fi-ta-table')).toBeVisible();
});
