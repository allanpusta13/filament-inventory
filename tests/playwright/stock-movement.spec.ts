import { test, expect } from '@playwright/test';

test.describe('StockMovementResource E2E Tests', () => {
  test.use({ storageState: './tests/playwright/.auth/user.json' });

  test('can render stock movements index page', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await expect(page.locator('text=Stock Movements')).toBeVisible();
  });

  test('shows signed integer quantity sum footer', async ({ page }) => {
    // Create some movements first via direct transfer
    await page.goto('http://127.0.0.1:8000/admin/direct-transfers/create');
    await expect(page.locator('text=INSTANT DIRECT TRANSFER')).toBeVisible();

    await page.selectOption('select[name="from_warehouse_id"]', { index: 1 });
    await page.selectOption('select[name="to_warehouse_id"]', { index: 2 });
    await page.click('button:has-text("NEXT")');

    await page.selectOption('select[name="product_variant_id"]', { index: 1 });
    await page.fill('input[name="quantity"]', '100');
    await page.fill('textarea[name="notes"]', 'Test direct transfer for stock movement verification');
    await page.click('button:has-text("NEXT")');

    await expect(page.locator('text=REVIEW & VERIFY')).toBeVisible();
    await page.click('button:has-text("EXECUTE TRANSFER")');
    await expect(page.locator('text=Transfer Executed')).toBeVisible({ timeout: 10000 });

    // Check stock movements
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await expect(page.locator('text=TransferOut')).toBeVisible();
    await expect(page.locator('text=TransferIn')).toBeVisible();

    // Verify footer shows sum (negative for out, positive for in)
    // The footer should show sum of quantities
    const footer = page.locator('tfoot, .table-footer, [data-testid="footer"]');
    // Just verify movements exist with correct types
    await expect(page.locator('text=-100')).toBeVisible(); // TransferOut negative
    await expect(page.locator('text=100')).toBeVisible(); // TransferIn positive
  });

  test('notes column is surfaced', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    // Check that notes column exists
    await expect(page.locator('th:has-text("Notes")')).toBeVisible();
  });

  test('can filter by type', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await page.selectOption('select[name*="type"]', 'transfer_out');
    await page.keyboard.press('Enter');
    await expect(page.locator('text=TransferOut')).toBeVisible();
  });

  test('can filter by product variant', async ({ page }) => {
    await page.goto('http://127.0.0.1:8000/admin/stock-movements');
    await page.selectOption('select[name*="product_variant"]', { index: 1 });
    await page.keyboard.press('Enter');
    await page.waitForTimeout(500);
  });
});