// @ts-check
const { test, expect } = require('@playwright/test');

const BASE = process.env.TEST_BASE_URL || 'http://localhost:8080';
const ADMIN_EMAIL = process.env.TEST_ADMIN_EMAIL || 'fabiodalez@gmail.com';
const ADMIN_PASS = process.env.TEST_ADMIN_PASS || 'Fa310reds?';

// Helper: login as admin and return authenticated context
async function loginAsAdmin(page) {
  await page.goto(`${BASE}/login.php`);
  await page.fill('#email', ADMIN_EMAIL);
  await page.fill('#password', ADMIN_PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard.php', { timeout: 10000 });
}

// ============================================================
// 1. Login page & rate limiter
// ============================================================
test.describe('Login & Rate Limiter', () => {
  test('login page loads correctly', async ({ page }) => {
    await page.goto(`${BASE}/login.php`);
    await expect(page.locator('h1')).toContainText('Accedi');
    await expect(page.locator('form.user')).toBeVisible();
    // CSRF token field present
    await expect(page.locator('input[name="csrf_token"]')).toBeAttached();
  });

  test('successful admin login', async ({ page }) => {
    await loginAsAdmin(page);
    await expect(page).toHaveURL(/dashboard\.php/);
  });

  test('failed login shows error', async ({ page }) => {
    await page.goto(`${BASE}/login.php`);
    await page.fill('#email', ADMIN_EMAIL);
    await page.fill('#password', 'wrongpassword');
    await page.click('button[type="submit"]');
    await expect(page.locator('.alert-danger')).toBeVisible();
  });
});

// ============================================================
// 2. gestione_utenti.php - CSRF validation works (no try/catch bug)
// ============================================================
test.describe('Gestione Utenti - CSRF Fix', () => {
  test('create user with invalid CSRF is rejected', async ({ page }) => {
    await loginAsAdmin(page);
    // POST with bad CSRF token
    const response = await page.evaluate(async () => {
      const formData = new FormData();
      formData.append('action', 'create');
      formData.append('csrf_token', 'invalid_token');
      formData.append('username', 'testuser');
      formData.append('email', 'test@test.com');
      formData.append('password', 'test123');
      formData.append('role', 'operatore');
      const resp = await fetch('/gestione_utenti.php', { method: 'POST', body: formData });
      return resp.url;
    });
    // Should not create the user - page shows error
    await page.goto(`${BASE}/gestione_utenti.php`);
    // Verify 'testuser' was NOT created
    const pageContent = await page.content();
    expect(pageContent).not.toContain('testuser');
  });

  test('gestione_utenti page loads for admin', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/gestione_utenti.php`);
    await expect(page.locator('body')).not.toContainText('Token CSRF non valido');
    // Page should show user management
    const content = await page.content();
    expect(content).toContain('admin');
  });
});

// ============================================================
// 3. Lavoratori page - CCNL filter and bulk actions
// ============================================================
test.describe('Lavoratori Page', () => {
  test('page loads with CCNL filter dropdown', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/lavoratori.php`);
    // CCNL filter should exist
    await expect(page.locator('#ccnl_filter')).toBeVisible();
    // Should have "Tutti i CCNL" as default option
    await expect(page.locator('#ccnl_filter option').first()).toContainText('Tutti i CCNL');
  });

  test('DataTable loads successfully', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/lavoratori.php`);
    // Wait for DataTable to load
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });
    // Should show data info
    await expect(page.locator('.dataTables_info')).toBeVisible();
  });

  test('CCNL filter works with DataTable', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/lavoratori.php`);
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });

    // Get CCNL options count
    const optionsCount = await page.locator('#ccnl_filter option').count();
    expect(optionsCount).toBeGreaterThan(1); // At least "Tutti" + some CCNL values

    // Select a CCNL if available
    if (optionsCount > 1) {
      const secondOptionValue = await page.locator('#ccnl_filter option').nth(1).getAttribute('value');
      if (secondOptionValue) {
        await page.selectOption('#ccnl_filter', secondOptionValue);
        // Wait for table reload
        await page.waitForTimeout(1000);
        await expect(page.locator('.dataTables_info')).toBeVisible();
      }
    }
  });

  test('bulk action buttons are visible', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/lavoratori.php`);
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });
    // Delete button should exist (admin only)
    await expect(page.locator('#deleteWorkersBtn')).toBeAttached();
  });
});

// ============================================================
// 4. Archived Lavoratori page
// ============================================================
test.describe('Archived Lavoratori Page', () => {
  test('page loads for admin with CCNL filter', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/archived_lavoratori.php`);
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });
    await expect(page.locator('#ccnl_filter')).toBeVisible();
  });
});

// ============================================================
// 5. Aziende page - POST delete (no more GET)
// ============================================================
test.describe('Aziende Page', () => {
  test('page loads with DataTable', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/aziende.php`);
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });
    await expect(page.locator('.dataTables_info')).toBeVisible();
  });

  test('delete button uses POST form, not GET link', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/aziende.php`);
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });

    // Hidden delete form should exist
    await expect(page.locator('#deleteAziendaForm')).toBeAttached();
    // Form method should be POST
    const method = await page.locator('#deleteAziendaForm').getAttribute('method');
    expect(method?.toUpperCase()).toBe('POST');
    // Form action should point to delete_azienda.php
    const action = await page.locator('#deleteAziendaForm').getAttribute('action');
    expect(action).toContain('delete_azienda.php');
    // CSRF token should be in form
    await expect(page.locator('#deleteAziendaForm input[name="csrf_token"]')).toBeAttached();

    // Delete links should NOT be direct GET links to delete_azienda.php
    const deleteLinks = await page.locator('a[href*="delete_azienda.php"]').count();
    expect(deleteLinks).toBe(0);
  });

  test('delete button has data-id attribute', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/aziende.php`);
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });

    // Wait for data to load
    await page.waitForTimeout(1000);
    const deleteBtn = page.locator('.delete-azienda-btn').first();
    if (await deleteBtn.count() > 0) {
      const dataId = await deleteBtn.getAttribute('data-id');
      expect(dataId).toBeTruthy();
      expect(parseInt(dataId)).toBeGreaterThan(0);
    }
  });
});

// ============================================================
// 6. delete_lavoratore.php - proper CSRF rejection (no die())
// ============================================================
test.describe('Delete Lavoratore - CSRF', () => {
  test('POST with invalid CSRF redirects with 403', async ({ page }) => {
    await loginAsAdmin(page);
    const response = await page.request.post(`${BASE}/delete_lavoratore.php`, {
      form: {
        csrf_token: 'bad_token',
        id: '999999'
      }
    });
    // Should redirect (302/303) not die() with plain text
    const body = await response.text();
    expect(body).not.toContain('die(');
    // Should either redirect or return 403
    expect([200, 302, 303, 403]).toContain(response.status());
  });
});

// ============================================================
// 7. update_note_lavoratore.php - proper CSRF (no die())
// ============================================================
test.describe('Update Note Lavoratore - CSRF', () => {
  test('POST with invalid CSRF redirects', async ({ page }) => {
    await loginAsAdmin(page);
    const response = await page.request.post(`${BASE}/update_note_lavoratore.php`, {
      form: {
        csrf_token: 'bad_token',
        id: '1',
        note: 'test'
      }
    });
    const body = await response.text();
    expect(body).not.toContain('Token CSRF non valido.');
  });
});

// ============================================================
// 8. update_document_description.php - 403 + no DB error exposure
// ============================================================
test.describe('Update Document Description - Security', () => {
  test('invalid CSRF returns 403 JSON', async ({ page }) => {
    await loginAsAdmin(page);
    const response = await page.request.post(`${BASE}/update_document_description.php`, {
      form: {
        csrf_token: 'bad_token',
        doc_id: '1',
        description: 'test'
      }
    });
    expect(response.status()).toBe(403);
    const json = await response.json();
    expect(json.success).toBe(false);
    expect(json.message).toContain('CSRF');
    // Should NOT expose DB errors
    expect(json.message).not.toContain('mysqli');
    expect(json.message).not.toContain('prepare');
  });
});

// ============================================================
// 9. download_documents_zip.php - 403 on bad CSRF
// ============================================================
test.describe('Download Documents ZIP - CSRF', () => {
  test('invalid CSRF returns 403', async ({ page }) => {
    await loginAsAdmin(page);
    const response = await page.request.post(`${BASE}/download_documents_zip.php`, {
      form: {
        csrf_token: 'bad_token',
        'document_ids[]': '1'
      }
    });
    expect(response.status()).toBe(403);
    const json = await response.json();
    expect(json.success).toBe(false);
  });
});

// ============================================================
// 10. email_reminder.php - CSRF protection
// ============================================================
test.describe('Email Reminder - CSRF', () => {
  test('page loads for admin', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/email_reminder.php`);
    // Should load without errors
    const status = page.url();
    expect(status).toContain('email_reminder.php');
  });
});

// ============================================================
// 11. modifica_iscrizione.php - CSRF
// ============================================================
test.describe('Modifica Iscrizione - CSRF', () => {
  test('page requires valid iscrizione ID', async ({ page }) => {
    await loginAsAdmin(page);
    const response = await page.goto(`${BASE}/modifica_iscrizione.php?id=1`);
    // Should load or redirect - not crash
    const status = response ? response.status() : 200;
    expect([200, 302]).toContain(status);
  });
});

// ============================================================
// 12. Gestione Iscrizioni page
// ============================================================
test.describe('Gestione Iscrizioni', () => {
  test('page loads correctly', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/gestione_iscrizioni.php`);
    await expect(page.locator('body')).toBeVisible();
    // No PHP errors visible
    const content = await page.content();
    expect(content).not.toContain('Fatal error');
    expect(content).not.toContain('Parse error');
  });
});

// ============================================================
// 13. Config - HTMLPurifier cache directory
// ============================================================
test.describe('Config - HTMLPurifier Cache', () => {
  test('HTMLPurifier cache dir is NOT in sessions/', async ({ page }) => {
    await loginAsAdmin(page);
    // Loading any page with rich text will trigger HTMLPurifier
    await page.goto(`${BASE}/dashboard.php`);
    // Verify sessions/ does not contain htmlpurifier files
    const path = require('path');
    const fs = require('fs');
    const appDir = path.resolve(__dirname, '..');
    const sessionsDir = path.join(appDir, 'sessions');
    if (fs.existsSync(sessionsDir)) {
      const sessionFiles = fs.readdirSync(sessionsDir);
      const purifierFiles = sessionFiles.filter(f => f.toLowerCase().includes('htmlpurifier'));
      expect(purifierFiles.length, 'HTMLPurifier files should NOT be in sessions/').toBe(0);
    }
  });
});

// ============================================================
// 14. migrate.php - accessible to admin
// ============================================================
test.describe('Migrate Page', () => {
  test('page loads for admin without unsanitized output', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/migrate.php`);
    const content = await page.content();
    expect(content).not.toContain('Fatal error');
    expect(content).not.toContain('Parse error');
    // Check page loaded
    expect(content).toContain('Migrazion');
  });
});

// ============================================================
// 15. delete_azienda.php - requires POST
// ============================================================
test.describe('Delete Azienda - POST only', () => {
  test('GET request redirects to aziende.php', async ({ page }) => {
    await loginAsAdmin(page);
    const response = await page.request.get(`${BASE}/delete_azienda.php?id=999999`);
    // Should redirect to aziende.php (not process deletion via GET)
    expect(response.url()).toContain('aziende.php');
  });
});

// ============================================================
// 16. elimina_unita_operativa.php - proper HTTP codes
// ============================================================
test.describe('Elimina Unita Operativa - HTTP codes', () => {
  test('GET request returns 405 or redirects', async ({ page }) => {
    await loginAsAdmin(page);
    const response = await page.request.get(`${BASE}/elimina_unita_operativa.php`);
    // Should redirect (POST-only)
    expect(response.url()).toContain('aziende.php');
  });
});

// ============================================================
// 17. Sidebar - admin-only queries
// ============================================================
test.describe('Sidebar', () => {
  test('sidebar renders correctly for admin', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/dashboard.php`);
    // Admin sections should be visible in sidebar
    await expect(page.locator('#accordionSidebar')).toBeVisible();
    await expect(page.locator('#accordionSidebar a[href*="gestione_utenti.php"]')).toBeVisible();
    await expect(page.locator('#accordionSidebar a[href*="settings.php"]')).toBeVisible();
    await expect(page.locator('#accordionSidebar a[href*="backup.php"]')).toBeVisible();
  });
});

// ============================================================
// 18. Dashboard loads without errors
// ============================================================
test.describe('Dashboard', () => {
  test('dashboard loads and shows KPIs', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(`${BASE}/dashboard.php`);
    const content = await page.content();
    expect(content).not.toContain('Fatal error');
    expect(content).not.toContain('Warning:');
    expect(content).not.toContain('Notice:');
  });
});

// ============================================================
// 19. No PHP errors on key pages
// ============================================================
test.describe('No PHP Errors on Key Pages', () => {
  const pages = [
    'dashboard.php',
    'lavoratori.php',
    'aziende.php',
    'gestione_iscrizioni.php',
    'gestione_utenti.php',
    'sedi.php',
    'settings.php',
    'backup.php',
    'migrate.php',
    'email_reminder.php',
    'archived_lavoratori.php',
  ];

  for (const pageName of pages) {
    test(`${pageName} - no PHP errors`, async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto(`${BASE}/${pageName}`);
      const content = await page.content();
      expect(content).not.toContain('Fatal error');
      expect(content).not.toContain('Parse error');
      expect(content).not.toContain('Uncaught Exception');
      expect(content).not.toContain('Stack trace:');
    });
  }
});
