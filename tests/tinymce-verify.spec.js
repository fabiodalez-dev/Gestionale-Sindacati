// @ts-check
const { test, expect } = require('@playwright/test');

const BASE = process.env.TEST_BASE_URL || 'http://localhost:8080';
const ADMIN_EMAIL = process.env.TEST_ADMIN_EMAIL;
const ADMIN_PASS = process.env.TEST_ADMIN_PASS;
if (!ADMIN_EMAIL || !ADMIN_PASS) {
  throw new Error('TEST_ADMIN_EMAIL e TEST_ADMIN_PASS sono obbligatorie.');
}

async function loginAsAdmin(page) {
  await page.goto(`${BASE}/login.php`);
  await page.fill('#email', ADMIN_EMAIL);
  await page.fill('#password', ADMIN_PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard.php', { timeout: 10000 });
}

// Checks TinyMCE renders on a page (visible, non-zero dimensions)
async function expectTinyMCEVisible(page, selector = '.tox-tinymce') {
  const editor = page.locator(selector).first();
  await expect(editor).toBeVisible({ timeout: 10000 });
  const box = await editor.boundingBox();
  expect(box).not.toBeNull();
  expect(box.width).toBeGreaterThan(100);
  expect(box.height).toBeGreaterThan(50);
}

test.describe('TinyMCE verification across all pages', () => {

  test('dashboard.php - TinyMCE loads in admin message modal', async ({ page }) => {
    await loginAsAdmin(page);
    expect(await page.evaluate(() => typeof window.tinymce !== 'undefined')).toBe(true);

    await page.locator('button[data-target="#editMessaggioModal"]').click();
    await expect(page.locator('#editMessaggioModal')).toBeVisible();
    await expectTinyMCEVisible(page, '#editMessaggioModal .tox-tinymce');
  });

  test('add_lavoratore.php - TinyMCE loads for note field', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/add_lavoratore.php`);
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => typeof window.tinymce !== 'undefined')).toBe(true);
    await expectTinyMCEVisible(page);
  });

  test('edit_lavoratore.php - TinyMCE loads for note field', async ({ page }) => {
    await loginAsAdmin(page);

    // Get a valid worker ID via search
    await page.goto(`${BASE}/lavoratori.php`);
    // Wait for DataTables to load, then get first ID from table links
    await page.waitForTimeout(2000);
    const workerId = await page.evaluate(() => {
      const link = document.querySelector('a[href*="lavoratore.php?id="]');
      if (!link) return null;
      const match = link.getAttribute('href').match(/id=(\d+)/);
      return match ? match[1] : null;
    });
    if (!workerId) { test.skip(); return; }

    await page.goto(`${BASE}/edit_lavoratore.php?id=${workerId}`);
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => typeof window.tinymce !== 'undefined')).toBe(true);
    await expectTinyMCEVisible(page);
  });

  test('add_azienda.php - TinyMCE loads for note field', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/add_azienda.php`);
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => typeof window.tinymce !== 'undefined')).toBe(true);
    await expectTinyMCEVisible(page);
  });

  test('edit_azienda.php - TinyMCE loads for note field', async ({ page }) => {
    await loginAsAdmin(page);

    await page.goto(`${BASE}/aziende.php`);
    await page.waitForTimeout(2000);
    const companyId = await page.evaluate(() => {
      const link = document.querySelector('a[href*="azienda.php?id="]');
      if (!link) return null;
      const match = link.getAttribute('href').match(/id=(\d+)/);
      return match ? match[1] : null;
    });
    if (!companyId) { test.skip(); return; }

    await page.goto(`${BASE}/edit_azienda.php?id=${companyId}`);
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => typeof window.tinymce !== 'undefined')).toBe(true);
    await expectTinyMCEVisible(page);
  });

  test('lavoratore.php - TinyMCE available on worker detail page', async ({ page }) => {
    await loginAsAdmin(page);

    await page.goto(`${BASE}/lavoratori.php`);
    await page.waitForTimeout(2000);
    const workerId = await page.evaluate(() => {
      const link = document.querySelector('a[href*="lavoratore.php?id="]');
      if (!link) return null;
      const match = link.getAttribute('href').match(/id=(\d+)/);
      return match ? match[1] : null;
    });
    if (!workerId) { test.skip(); return; }

    await page.goto(`${BASE}/lavoratore.php?id=${workerId}`);
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => typeof window.tinymce !== 'undefined')).toBe(true);

    // TinyMCE editors are in event modals
    const addEventBtn = page.locator('button[data-target="#addEventModal"]');
    if (await addEventBtn.count() > 0) {
      await addEventBtn.click();
      await expect(page.locator('#addEventModal')).toBeVisible();
      await expectTinyMCEVisible(page, '#addEventModal .tox-tinymce');
    }
  });

  test('azienda.php - TinyMCE available on company detail page', async ({ page }) => {
    await loginAsAdmin(page);

    await page.goto(`${BASE}/aziende.php`);
    await page.waitForTimeout(2000);
    const companyId = await page.evaluate(() => {
      const link = document.querySelector('a[href*="azienda.php?id="]');
      if (!link) return null;
      const match = link.getAttribute('href').match(/id=(\d+)/);
      return match ? match[1] : null;
    });
    if (!companyId) { test.skip(); return; }

    await page.goto(`${BASE}/azienda.php?id=${companyId}`);
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => typeof window.tinymce !== 'undefined')).toBe(true);

    const addEventBtn = page.locator('button[data-target="#addEventModal"]');
    if (await addEventBtn.count() > 0) {
      await addEventBtn.click();
      await expect(page.locator('#addEventModal')).toBeVisible();
      await expectTinyMCEVisible(page, '#addEventModal .tox-tinymce');
    }
  });

  test('email_reminder.php - TinyMCE loads after toggle', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/email_reminder.php`);
    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => typeof window.tinymce !== 'undefined')).toBe(true);

    // Click toggle to reveal the template card with TinyMCE
    await page.locator('#toggleEditorBtn').click();
    await expectTinyMCEVisible(page);
  });
});
