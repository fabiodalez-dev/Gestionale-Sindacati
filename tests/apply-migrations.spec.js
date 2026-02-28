// @ts-check
const { test, expect } = require('@playwright/test');

const BASE = process.env.TEST_BASE_URL || 'http://localhost:8080';
const ADMIN_EMAIL = process.env.TEST_ADMIN_EMAIL || 'fabiodalez@gmail.com';
const ADMIN_PASS = process.env.TEST_ADMIN_PASS || 'Fa310reds?';

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
    // Click the first migration button once (runs all pending migrations)
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
      const forms = page.locator('form');
      const formCount = await forms.count();
      console.log(`Found ${formCount} forms on page`);
      // Fail if there are pending migrations but no actionable forms
      expect(formCount, 'Pending migrations found but no actionable forms available').toBeGreaterThan(0);
    } else {
      console.log('No pending migrations found - all up to date');
    }
  }

  // Also check for index optimization button
  const indexBtn = page.locator('button:has-text("Ottimizza"), button:has-text("Crea Indici"), form button:has-text("indic")');
  const indexBtnCount = await indexBtn.count();
  console.log(`Found ${indexBtnCount} index optimization buttons`);

  if (indexBtnCount > 0) {
    for (let i = 0; i < indexBtnCount; i++) {
      const btn = indexBtn.nth(i);
      const btnText = await btn.textContent();
      console.log(`Clicking index button: ${btnText}`);
      await btn.click();
      await page.waitForLoadState('networkidle', { timeout: 30000 });
      console.log('Index optimization completed');
    }
  }

  // Take screenshot after
  await page.screenshot({ path: 'tests/screenshots/migrate-after.png', fullPage: true });

  // Final check - page should not have errors
  const finalContent = await page.content();
  expect(finalContent).not.toContain('Fatal error');
  expect(finalContent).not.toContain('Parse error');

  console.log('Migration process completed successfully');
});
