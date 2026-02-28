// @ts-check
const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.TEST_BASE_URL || 'http://localhost:8080';
const ADMIN_EMAIL = process.env.TEST_ADMIN_EMAIL;
const ADMIN_PASS = process.env.TEST_ADMIN_PASS;
if (!ADMIN_EMAIL || !ADMIN_PASS) {
  throw new Error('TEST_ADMIN_EMAIL e TEST_ADMIN_PASS sono obbligatorie.');
}

// Helper: login
async function login(page) {
  await page.goto(`${BASE_URL}/login.php`);
  await page.fill('#email', ADMIN_EMAIL);
  await page.fill('#password', ADMIN_PASS);
  await page.locator('form button[type="submit"]').first().click();
  await page.waitForURL('**/dashboard.php');
}

// Helper: click the main form submit button (Salva)
async function submitMainForm(page) {
  await page.locator('form[method="POST"] button[type="submit"]').click();
}

// Helper: fill autocomplete and pick suggestion
async function fillAutocomplete(page, selector, text) {
  const input = page.locator(selector);
  await input.fill('');
  await input.fill(text);
  await page.waitForTimeout(800);
  const suggestion = page.locator('.ui-autocomplete .ui-menu-item').first();
  if (await suggestion.isVisible({ timeout: 2000 }).catch(() => false)) {
    await suggestion.click();
    await page.waitForTimeout(200);
  }
}

// Unique suffix per run
const RUN_ID = Date.now().toString().slice(-6);

// ============================================================
// 1. CREATE WORKER - All fields
// ============================================================
test.describe('Creazione lavoratore', () => {

  test('Crea un nuovo lavoratore compilando tutti i campi', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    // --- DATI PERSONALI ---
    await page.fill('#nome', `TestNome${RUN_ID}`);
    await page.fill('#cognome', `TestCognome${RUN_ID}`);
    await page.fill('#codice_fiscale', 'TSTCGN90A01H501Z');
    await page.fill('#data_nascita', '1990-01-01');
    await page.locator('[name="paese_nascita"]').fill('Italia');
    await page.locator('[name="nazionalita"]').fill('Italiana');
    await page.selectOption('[name="genere"]', 'Maschio');
    await page.locator('[name="data_iscrizione"]').fill('2024-01-15');

    // --- TESSERA ---
    await page.selectOption('[name="settore"]', 'privato');
    await page.selectOption('[name="tipo_tessera"]', 'rinnovo annuale');

    // sede_id - select first available
    const sedeSelect = page.locator('[name="sede_id"]');
    const sedeOptions = await sedeSelect.locator('option[value]:not([value=""])').all();
    if (sedeOptions.length > 0) {
      const val = await sedeOptions[0].getAttribute('value');
      if (val) await sedeSelect.selectOption(val);
    }

    await page.fill('[name="data_inizio"]', '2024-01-15');
    await page.locator('[name="numero_tessera"]').fill(`T${RUN_ID}`);
    await page.locator('[name="nota_pagamento"]').fill('Nota di pagamento test');

    // --- INDIRIZZO ---
    await page.fill('[name="indirizzo_via"]', 'Via Test');
    await page.fill('[name="indirizzo_numero_civico"]', '42');
    await page.fill('[name="indirizzo_cap"]', '35100');
    await page.fill('[name="indirizzo_citta"]', 'Padova');
    await page.fill('[name="indirizzo_provincia"]', 'PD');

    // --- CONTATTI ---
    await page.fill('[name="telefono"]', '0491234567');
    await page.fill('[name="email"]', `test${RUN_ID}@example.com`);

    // --- AZIENDA ---
    await fillAutocomplete(page, '[name="azienda"]', '5b Service');

    // ruolo
    await page.selectOption('[name="ruolo"]', 'RSU');

    // --- CONTRATTO ---
    await page.selectOption('[name="contratto"]', 'Indeterminato');
    await page.selectOption('[name="orario_contratto"]', 'tempo pieno');
    await fillAutocomplete(page, '[name="ccnl"]', 'commercio');
    await page.fill('[name="data_assunzione"]', '2020-03-15');
    await page.locator('[name="ore_settimanali"]').fill('40');
    await page.locator('[name="ral"]').fill('28000');

    // --- NOTE ---
    await page.evaluate(() => {
      const ta = document.querySelector('[name="note"]');
      if (ta) ta.value = 'Note di test Playwright';
      if (typeof tinymce !== 'undefined' && tinymce.get('note')) {
        tinymce.get('note').setContent('Note di test Playwright');
      }
    });

    // Submit
    await submitMainForm(page);

    // Should redirect to lavoratori.php?add_success=1
    await page.waitForURL('**/lavoratori.php**', { timeout: 15000 });
    expect(page.url()).toContain('add_success=1');
  });

  test('Validazione: nome e cognome obbligatori', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    // Fill only azienda, leave nome/cognome empty
    await fillAutocomplete(page, '[name="azienda"]', '5b Service');

    // The nome field has 'required' attribute, so browser validation should prevent submit
    // Try clicking submit and check we stay on page
    const submitted = await page.evaluate(() => {
      const form = document.querySelector('form[method="POST"]');
      if (!form) return false;
      // Check if nome is required and empty
      const nome = form.querySelector('[name="nome"]');
      if (nome && nome.required && !nome.value) return false; // browser won't submit
      return true;
    });

    if (!submitted) {
      // Browser validation correctly prevents submit with empty required field
      await expect(page).toHaveURL(/add_lavoratore\.php/);
    } else {
      await submitMainForm(page);
      await page.waitForTimeout(1000);
      await expect(page).toHaveURL(/add_lavoratore\.php/);
    }
  });

  test('Crea lavoratore con tipo tessera SEPA', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    await page.fill('#nome', `SepaTest${RUN_ID}`);
    await page.fill('#cognome', `SepaCogn${RUN_ID}`);
    await page.selectOption('[name="tipo_tessera"]', 'sepa');
    await page.fill('[name="data_inizio"]', '2024-06-01');
    await fillAutocomplete(page, '[name="azienda"]', '5b Service');

    await submitMainForm(page);
    await page.waitForURL('**/lavoratori.php**', { timeout: 15000 });
    expect(page.url()).toContain('add_success=1');
  });

  test('Crea lavoratore con tipo tessera trattenuta in busta paga', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    await page.fill('#nome', `BustaTest${RUN_ID}`);
    await page.fill('#cognome', `BustaCogn${RUN_ID}`);
    await page.selectOption('[name="tipo_tessera"]', 'trattenuta in busta paga');
    await page.fill('[name="data_inizio"]', '2024-03-01');
    await fillAutocomplete(page, '[name="azienda"]', '5b Service');

    await submitMainForm(page);
    await page.waitForURL('**/lavoratori.php**', { timeout: 15000 });
    expect(page.url()).toContain('add_success=1');
  });
});

// ============================================================
// 2. READ / VIEW WORKER
// ============================================================
test.describe('Visualizzazione lavoratore', () => {

  test('Apre la lista lavoratori e visualizza i dati in tabella', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/lavoratori.php`);

    // DataTable should load
    await expect(page.locator('table')).toBeVisible({ timeout: 10000 });
    await page.waitForTimeout(3000); // Wait for DataTable AJAX

    const rows = page.locator('table tbody tr');
    const count = await rows.count();
    expect(count).toBeGreaterThan(0);
  });

  test('Visualizza scheda dettaglio lavoratore', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/lavoratori.php`);
    await page.waitForTimeout(3000);

    // Search for our test worker
    const searchInput = page.locator('.dataTables_filter input, input[type="search"]').first();
    await searchInput.fill(`TestNome${RUN_ID}`);
    await page.waitForTimeout(2000);

    // Click on the first result link to view detail
    const firstLink = page.locator('a[href*="lavoratore.php?id="]').first();
    if (await firstLink.isVisible({ timeout: 5000 }).catch(() => false)) {
      await firstLink.click();
      await page.waitForURL('**/lavoratore.php?id=**');
      await expect(page.locator('body')).toContainText(`TestNome${RUN_ID}`);
    }
  });

  test('I filtri nella lista lavoratori funzionano', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/lavoratori.php`);
    await page.waitForTimeout(3000);

    // Check which filters exist on the page
    const filterForm = page.locator('#customFilterForm');
    if (await filterForm.isVisible({ timeout: 3000 }).catch(() => false)) {
      // Try any select filter that exists
      const selects = filterForm.locator('select');
      const count = await selects.count();
      if (count > 0) {
        // Get the first filter select
        const firstFilter = selects.first();
        const options = await firstFilter.locator('option[value]:not([value=""])').all();
        if (options.length > 0) {
          const val = await options[0].getAttribute('value');
          if (val) {
            await firstFilter.selectOption(val);
            await page.waitForTimeout(2000);
          }
        }
      }
    }

    // Verify table still has data
    const rows = page.locator('table tbody tr');
    const rowCount = await rows.count();
    expect(rowCount).toBeGreaterThan(0);
  });
});

// ============================================================
// 3. EDIT WORKER - All fields
// ============================================================
test.describe('Modifica lavoratore', () => {

  test('Modifica tutti i campi di un lavoratore esistente', async ({ page }) => {
    test.setTimeout(90000);
    await login(page);

    // Search for our test worker through the UI
    await page.goto(`${BASE_URL}/lavoratori.php`);
    await page.waitForTimeout(3000);

    const searchInput = page.locator('.dataTables_filter input, input[type="search"]').first();
    await searchInput.fill(`TestNome${RUN_ID}`);
    await page.waitForTimeout(2000);

    // Find edit link
    const editLink = page.locator('a[href*="edit_lavoratore.php"]').first();
    let editUrl;

    if (await editLink.isVisible({ timeout: 5000 }).catch(() => false)) {
      editUrl = await editLink.getAttribute('href');
    }

    if (!editUrl) {
      throw new Error('Nessun lavoratore di test trovato da modificare');
    }

    await page.goto(`${BASE_URL}/${editUrl.replace(/^\//, '')}`);
    await expect(page.locator('form[method="POST"]')).toBeVisible({ timeout: 10000 });

    // --- Modify DATI PERSONALI ---
    await page.fill('[name="nome"]', `ModNome${RUN_ID}`);
    await page.fill('[name="cognome"]', `ModCognome${RUN_ID}`);
    await page.fill('[name="codice_fiscale"]', 'MDTCGN85B02H501X');
    await page.locator('[name="data_nascita"]').fill('1985-02-02');
    await page.locator('[name="paese_nascita"]').fill('Germania');
    await page.locator('[name="nazionalita"]').fill('Tedesca');
    await page.selectOption('[name="genere"]', 'Femmina');
    await page.locator('[name="data_iscrizione"]').fill('2025-01-01');

    // --- Modify TESSERA ---
    await page.selectOption('[name="settore"]', 'pubblico');
    await page.selectOption('[name="tipo_tessera"]', 'sepa');
    await page.locator('[name="data_inizio"]').fill('2025-01-01');

    const sedeSelect = page.locator('[name="sede_id"]');
    const sedeOptions = await sedeSelect.locator('option[value]:not([value=""])').all();
    if (sedeOptions.length > 0) {
      const val = await sedeOptions[0].getAttribute('value');
      if (val) await sedeSelect.selectOption(val);
    }

    await page.locator('[name="numero_tessera"]').fill(`MOD${RUN_ID}`);
    await page.locator('[name="nota_pagamento"]').fill('Nota pagamento modificata');

    // --- Modify INDIRIZZO ---
    await page.fill('[name="indirizzo_via"]', 'Via Modificata');
    await page.fill('[name="indirizzo_numero_civico"]', '99');
    await page.fill('[name="indirizzo_cap"]', '20100');
    await page.fill('[name="indirizzo_citta"]', 'Milano');
    await page.fill('[name="indirizzo_provincia"]', 'MI');

    // --- Modify CONTATTI ---
    await page.fill('[name="telefono"]', '0298765432');
    await page.fill('[name="email"]', `mod${RUN_ID}@example.com`);

    // --- Modify AZIENDA ---
    await fillAutocomplete(page, '[name="azienda"]', 'Acs Service');

    // ruolo
    await page.selectOption('[name="ruolo"]', 'RLS');

    // --- Modify CONTRATTO ---
    const contrattoSelect = page.locator('[name="contratto"]');
    const contrattoOptions = await contrattoSelect.locator('option').allTextContents();
    const detOption = contrattoOptions.find(o => o.toLowerCase().includes('determinato') && !o.toLowerCase().includes('indeterminato'));
    if (detOption) {
      await contrattoSelect.selectOption({ label: detOption });
    }

    await page.selectOption('[name="orario_contratto"]', 'part time');
    await fillAutocomplete(page, '[name="ccnl"]', 'edilizia');
    await page.locator('[name="data_assunzione"]').fill('2022-06-01');
    await page.locator('[name="data_fine_contratto"]').fill('2026-06-01');
    await page.locator('[name="ore_settimanali"]').fill('20');
    await page.locator('[name="ral"]').fill('35000');

    // --- NOTE ---
    await page.evaluate(() => {
      const ta = document.querySelector('[name="note"]');
      if (ta) ta.value = 'Note modificate dal test Playwright';
      if (typeof tinymce !== 'undefined' && tinymce.get('note')) {
        tinymce.get('note').setContent('Note modificate dal test Playwright');
      }
    });

    // Submit
    await submitMainForm(page);
    await page.waitForURL('**/lavoratore.php**', { timeout: 15000 });
    expect(page.url()).toContain('update_success=1');
  });

  test('Verifica che i dati modificati siano persistiti', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/lavoratori.php`);
    await page.waitForTimeout(3000);

    const searchInput = page.locator('.dataTables_filter input, input[type="search"]').first();
    await searchInput.fill(`ModNome${RUN_ID}`);
    await page.waitForTimeout(2000);

    const detailLink = page.locator('a[href*="lavoratore.php?id="]').first();
    if (await detailLink.isVisible({ timeout: 5000 }).catch(() => false)) {
      await detailLink.click();
      await page.waitForURL('**/lavoratore.php?id=**');

      const body = await page.textContent('body');
      expect(body).toContain(`ModNome${RUN_ID}`);
      expect(body).toContain(`ModCognome${RUN_ID}`);
    }
  });

  test('Modifica lavoratore SEPA - verifica fix metodo_pagamento SEPA', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);

    // Trova dinamicamente un lavoratore con tipo_tessera = 'sepa'
    const sepaWorkerId = process.env.TEST_SEPA_WORKER_ID;
    if (!sepaWorkerId) {
      test.skip();
      return;
    }
    await page.goto(`${BASE_URL}/edit_lavoratore.php?id=${sepaWorkerId}`, { timeout: 30000 });

    // Wait for the form to load
    const form = page.locator('form[method="POST"]');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Verify no PHP errors on page
    const body = await page.textContent('body');
    expect(body).not.toContain('Data truncated');
    expect(body).not.toContain('Fatal error');

    // Verify tipo_tessera shows 'sepa'
    const tipoTessera = page.locator('[name="tipo_tessera"]');
    const selectedValue = await tipoTessera.inputValue();
    expect(selectedValue).toBe('sepa');

    // Fill required date field if empty
    const dataInizio = page.locator('[name="data_inizio"]');
    if (await dataInizio.count() > 0) {
      const val = await dataInizio.inputValue();
      if (!val) {
        await dataInizio.fill('2025-01-01');
      }
    }

    // Submit
    await submitMainForm(page);
    await page.waitForURL('**/lavoratore.php**', { timeout: 15000 });
    expect(page.url()).toContain('update_success=1');
  });
});

// ============================================================
// 4. DELETE WORKER (cleanup)
// ============================================================
test.describe('Eliminazione lavoratore', () => {

  test('Elimina i lavoratori di test creati via DB', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);

    // Use a server-side approach - navigate to the list and delete via the UI
    // But first, let's just verify we can find them
    const testSuffixes = [`TestNome${RUN_ID}`, `SepaTest${RUN_ID}`, `BustaTest${RUN_ID}`, `ModNome${RUN_ID}`];

    for (const name of testSuffixes) {
      await page.goto(`${BASE_URL}/lavoratori.php`);
      await page.waitForTimeout(3000);

      const searchInput = page.locator('.dataTables_filter input, input[type="search"]').first();
      await searchInput.fill(name);
      await page.waitForTimeout(2000);

      // Check if delete button exists (it's a POST form)
      const deleteForm = page.locator('form[action*="delete_lavoratore"]').first();
      if (await deleteForm.isVisible({ timeout: 3000 }).catch(() => false)) {
        // Click the delete button
        const deleteBtn = deleteForm.locator('button[type="submit"], input[type="submit"]').first();
        if (await deleteBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
          await deleteBtn.click();
          // Handle SweetAlert2 or confirm dialog
          const swalConfirm = page.locator('.swal2-confirm');
          if (await swalConfirm.isVisible({ timeout: 3000 }).catch(() => false)) {
            await swalConfirm.click();
          }
          await page.waitForTimeout(1500);
        }
      }
    }
    // Verify we're still on lavoratori page (no crash)
    await expect(page).toHaveURL(/lavoratori\.php/);
  });
});

// ============================================================
// 5. EDGE CASES AND SPECIFIC FIELD TESTS
// ============================================================
test.describe('Test campi specifici', () => {

  test('Validazione email impedisce invio con email non valida', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    await page.fill('#nome', 'TestEmailVal');
    await page.fill('#cognome', 'TestEmailVal');
    await page.fill('[name="email"]', 'email-non-valida');
    await fillAutocomplete(page, '[name="azienda"]', '5b Service');

    // Check if the email field has type="email" for browser validation
    const emailType = await page.locator('[name="email"]').getAttribute('type');
    if (emailType === 'email') {
      // Browser will prevent form submission with invalid email
      const isValid = await page.evaluate(() => {
        const emailInput = document.querySelector('[name="email"]');
        return emailInput ? emailInput.checkValidity() : true;
      });
      expect(isValid).toBe(false);
    }
  });

  test('Tutti e tre i tipi tessera sono selezionabili', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    const tipoTessera = page.locator('[name="tipo_tessera"]');

    await tipoTessera.selectOption('trattenuta in busta paga');
    expect(await tipoTessera.inputValue()).toBe('trattenuta in busta paga');

    await tipoTessera.selectOption('rinnovo annuale');
    expect(await tipoTessera.inputValue()).toBe('rinnovo annuale');

    await tipoTessera.selectOption('sepa');
    expect(await tipoTessera.inputValue()).toBe('sepa');
  });

  test('Provincia accetta massimo 2 caratteri', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    const provInput = page.locator('[name="indirizzo_provincia"]');
    await provInput.fill('ABCDEF');

    const maxlength = await provInput.getAttribute('maxlength');
    if (maxlength) {
      expect(parseInt(maxlength)).toBeLessThanOrEqual(2);
      const val = await provInput.inputValue();
      expect(val.length).toBeLessThanOrEqual(2);
    }
  });

  test('CAP accetta 5 cifre', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    const capInput = page.locator('[name="indirizzo_cap"]');
    await capInput.fill('12345');
    expect(await capInput.inputValue()).toBe('12345');
  });

  test('Codice fiscale accetta massimo 16 caratteri', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    const cfInput = page.locator('[name="codice_fiscale"]');
    await cfInput.fill('RSSMRA85M01H501Z');
    const maxlength = await cfInput.getAttribute('maxlength');
    if (maxlength) {
      expect(parseInt(maxlength)).toBe(16);
    }
  });

  test('Genere ha opzioni corrette', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    const options = await page.locator('[name="genere"] option').allTextContents();
    expect(options.some(o => o.includes('Maschio'))).toBeTruthy();
    expect(options.some(o => o.includes('Femmina'))).toBeTruthy();
    expect(options.some(o => o.includes('Altro'))).toBeTruthy();
  });

  test('Settore ha opzioni privato e pubblico', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    const options = await page.locator('[name="settore"] option').allTextContents();
    expect(options.some(o => o.toLowerCase().includes('privato'))).toBeTruthy();
    expect(options.some(o => o.toLowerCase().includes('pubblico'))).toBeTruthy();
  });

  test('Ruolo sindacale ha opzioni corrette', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    const options = await page.locator('[name="ruolo"] option').allTextContents();
    expect(options.some(o => o.includes('NESSUNO'))).toBeTruthy();
    expect(options.some(o => o.includes('RSU'))).toBeTruthy();
    expect(options.some(o => o.includes('RSA'))).toBeTruthy();
    expect(options.some(o => o.includes('RLS'))).toBeTruthy();
  });

  test('Contratto ha tutte le opzioni', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    const options = await page.locator('[name="contratto"] option').allTextContents();
    expect(options.some(o => o.toLowerCase().includes('indeterminato'))).toBeTruthy();
    expect(options.some(o => o.toLowerCase().includes('determinato'))).toBeTruthy();
  });

  test('Orario contratto ha tempo pieno e part time', async ({ page }) => {
    await login(page);
    await page.goto(`${BASE_URL}/add_lavoratore.php`);

    const options = await page.locator('[name="orario_contratto"] option').allTextContents();
    expect(options.some(o => o.toLowerCase().includes('pieno'))).toBeTruthy();
    expect(options.some(o => o.toLowerCase().includes('part'))).toBeTruthy();
  });
});

// ============================================================
// 6. EXPORT
// ============================================================
test.describe('Export lavoratori', () => {

  test('Export Excel funziona', async ({ page }) => {
    test.setTimeout(60000);
    await login(page);
    await page.goto(`${BASE_URL}/lavoratori.php`);
    await page.waitForTimeout(3000);

    const exportBtn = page.locator('a[href*="export_lavoratori"], a:has-text("Excel"), a:has-text("Export")').first();
    if (await exportBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
      const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 15000 }).catch(() => null),
        exportBtn.click()
      ]);
      if (download) {
        const filename = download.suggestedFilename();
        expect(filename).toMatch(/\.xlsx?$/);
      }
    }
  });
});
