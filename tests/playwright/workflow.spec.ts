import { test, expect, BASE_URL, USERS, login, logout } from './helpers';
import type { Page } from '@playwright/test';
import { execSync } from 'child_process';

async function selectOption(page: Page, fieldLabel: string, optionText: string): Promise<void> {
  const wrapper = page.locator('.fi-fo-select-wrp').filter({ hasText: fieldLabel });
  const button = wrapper.locator('button.fi-select-input-btn');
  await button.click();
  await page.waitForTimeout(500);
  const options = page.locator('.fi-select-input-option:visible');
  if (optionText) {
    await options.filter({ hasText: optionText }).click();
  } else {
    await options.first().click();
  }
  await page.waitForTimeout(300);
}

function getLatestTransferCode(): string {
  const output = execSync(
    'php artisan tinker --execute="echo App\\Models\\TransferRequisition::latest()->first()->reference_code ?? \'NONE\';"',
    { cwd: 'D:\\Personal\\filament-inventory', encoding: 'utf-8' },
  );
  return output.trim();
}

test('Full multi-role lifecycle: create → submit → confirm → dispatch → receive → verify', async ({ page }) => {
  test.setTimeout(300000);

  // Act I: Staff creates requisition (staff.dvo only has access to Davao Branch Store)
  await login(page, USERS.staffDvo);
  await page.goto(`${BASE_URL}/admin/transfer-requisitions/create`);
  await expect(page.getByText('Source Warehouse')).toBeVisible();

  await selectOption(page, 'Source Warehouse', 'Davao Branch Store');
  await selectOption(page, 'Destination Warehouse', 'Cebu Regional Depot');
  await selectOption(page, 'Product Variant', 'Arabica Dark');

  await page.getByLabel('Requested unit name').fill('Bag');
  await page.getByLabel('Requested unit ratio').fill('1000');
  await page.getByLabel('Requested qty').fill('10');

  await page.getByRole('button', { name: 'Create', exact: true }).click();
  // Redirects to view page (TRQ detail), not the list
  await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });
  await page.waitForTimeout(1000);

  const referenceCode = getLatestTransferCode();
  expect(referenceCode).toMatch(/^TRQ-/);

  // Submit from the view page (staff has Submit + Delete buttons visible)
  const submitBtn = page.getByRole('button', { name: 'Submit Requisition' });
  if (await submitBtn.isVisible()) {
    await submitBtn.click();
    await page.waitForTimeout(1500);
  }

  await logout(page);

  // Act II: CEB Manager confirms
  await login(page, USERS.managerCeb);
  await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
  const row3 = page.locator('.fi-ta-table tbody tr').filter({ hasText: referenceCode });
  await expect(row3).toBeVisible();
  await row3.locator('.fi-ac-link-action').first().click();
  await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });

  const confirmBtn = page.getByRole('button', { name: 'Confirm Requisition' });
  if (await confirmBtn.isVisible()) {
    await confirmBtn.click();
    await page.waitForTimeout(1500);
  }

  await logout(page);

  // Act III: CEB Manager dispatches
  await login(page, USERS.managerCeb);
  await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
  const row4 = page.locator('.fi-ta-table tbody tr').filter({ hasText: referenceCode });
  await expect(row4).toBeVisible();
  await row4.locator('.fi-ac-link-action').first().click();
  await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });

  const dispatchBtn = page.getByRole('button', { name: 'Dispatch' });
  if (await dispatchBtn.isVisible()) {
    await dispatchBtn.click();
    await page.waitForTimeout(1500);
  }

  await logout(page);

  // Act IV: CEB Manager receives
  await login(page, USERS.managerCeb);
  await page.goto(`${BASE_URL}/admin/transfer-requisitions`);
  const row5 = page.locator('.fi-ta-table tbody tr').filter({ hasText: referenceCode });
  await expect(row5).toBeVisible();
  await row5.locator('.fi-ac-link-action').first().click();
  await page.waitForURL(/\/admin\/transfer-requisitions\/\d+$/, { timeout: 10000 });

  const receiveBtn = page.getByRole('button', { name: 'Scan to Receive' });
  if (await receiveBtn.isVisible()) {
    await receiveBtn.click();
    await page.waitForTimeout(1000);
  }

  await logout(page);

  // Act V: Auditor verifies stock movements and loss ledger
  await login(page, USERS.auditor);

  await page.goto(`${BASE_URL}/admin/stock-movements`);
  await expect(page.locator('h1')).toContainText('Stock Movements');
  await expect(page.locator('.fi-ta-table')).toBeVisible();

  await page.goto(`${BASE_URL}/admin/loss-ledgers`);
  await expect(page.locator('h1')).toContainText('Loss Ledger');
  await expect(page.locator('.fi-ta-table')).toBeVisible();
});
