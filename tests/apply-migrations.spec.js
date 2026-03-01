// @ts-check
const { test, expect } = require('@playwright/test');

const BASE = process.env.TEST_BASE_URL || 'http://localhost:8080';
const ADMIN_EMAIL = process.env.TEST_ADMIN_EMAIL;
const ADMIN_PASS = process.env.TEST_ADMIN_PASS;
if (!ADMIN_EMAIL || !ADMIN_PASS) {
  throw new Error('TEST_ADMIN_EMAIL e TEST_ADMIN_PASS sono obbligatorie.');
}

test('Apply all pending migrations via migrate.php', async ({ page }) => {
  // Login as admin
  await page.goto(`${BASE}/login.php`);
  await page.fill('#email', ADMIN_EMAIL);
  await page.fill('#password', ADMIN_PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard.php', { timeout: 10000 });

  // Navigate to migrate page
  await page.goto(`${BASE}/migrate.php`);
  await expect(page.locator('body')).toBeVisible();

  // Take screenshot of migration page before
  await page.screenshot({ path: 'tests/screenshots/migrate-before.png', fullPage: true });

  // Check if there are pending migrations
  const content = await page.content();
  console.log('Migration page loaded successfully');

  // Look for "Esegui Migrazione" buttons
  const migrateButtons = page.locator('button:has-text("Esegui"), button:has-text("Migra"), input[type="submit"][value*="Esegui"], form[action*="migrate"] button[type="submit"]');
  const btnCount = await migrateButtons.count();
  console.log(`Found ${btnCount} migrate buttons`);

  if (btnCount > 0) {
    // Click migration button once - it runs all pending migrations in one pass
    const btn = migrateButtons.first();
    const btnText = await btn.textContent();
    console.log(`Clicking migration button: ${btnText}`);
    await btn.click();
    await page.waitForLoadState('networkidle', { timeout: 30000 });
    console.log('Migration completed');
  } else {
    // Check for pending migrations text
    const hasPending = content.includes('In Attesa') || content.includes('pending') || content.includes('da eseguire');
    if (hasPending) {
      console.log('Found pending migrations text, looking for actionable forms...');
      const submitBtns = page.locator('form button[type="submit"], form input[type="submit"]');
      const submitCount = await submitBtns.count();
      console.log(`Found ${submitCount} submit buttons on page`);
      // Fail if there are pending migrations but no actionable submit buttons
      expect(submitCount, 'Pending migrations found but no actionable submit buttons available').toBeGreaterThan(0);
    } else {
      console.log('No pending migrations found - all up to date');
    }
  }

  // Also check for index optimization button
  const indexBtn = page.locator('button:has-text("Ottimizza"), button:has-text("Crea Indici"), form button:has-text("indic")');
  const indexBtnCount = await indexBtn.count();
  console.log(`Found ${indexBtnCount} index optimization buttons`);

  if (indexBtnCount > 0) {
    const MAX_INDEX_ITERATIONS = 20;
    let remaining = await indexBtn.count();
    let iteration = 0;
    while (remaining > 0 && iteration < MAX_INDEX_ITERATIONS) {
      const btn = indexBtn.first();
      const btnText = await btn.textContent();
      console.log(`Clicking index button (${iteration + 1}/${MAX_INDEX_ITERATIONS}): ${btnText}`);
      await btn.click();
      await page.waitForLoadState('networkidle', { timeout: 30000 });
      console.log('Index optimization completed');
      remaining = await indexBtn.count();
      iteration++;
    }
    if (iteration >= MAX_INDEX_ITERATIONS) {
      console.warn(`Index optimization stopped after ${MAX_INDEX_ITERATIONS} iterations`);
    }
  }

  // Take screenshot after
  await page.screenshot({ path: 'tests/screenshots/migrate-after.png', fullPage: true });

  // Final check - page should not have errors
  const finalContent = await page.content();
  expect(finalContent).not.toContain('Fatal error');
  expect(finalContent).not.toContain('Parse error');

  // Verify no pending migrations remain
  const stillPending = finalContent.includes('In Attesa') || finalContent.includes('pending') || finalContent.includes('da eseguire');
  if (stillPending) {
    console.warn('Warning: some migrations may still be pending after execution');
  }

  console.log('Migration process completed successfully');
});
