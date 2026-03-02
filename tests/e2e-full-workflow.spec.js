// @ts-check
const { test, expect } = require('@playwright/test');

const BASE = process.env.TEST_BASE_URL || 'http://localhost:8080';
const ADMIN_EMAIL = process.env.TEST_ADMIN_EMAIL;
const ADMIN_PASS = process.env.TEST_ADMIN_PASS;
if (!ADMIN_EMAIL || !ADMIN_PASS) {
  throw new Error('TEST_ADMIN_EMAIL e TEST_ADMIN_PASS sono obbligatorie.');
}

const RUN_ID = Date.now().toString().slice(-6);

// Helper: login as admin
async function login(page) {
  await page.goto(`${BASE}/login.php`);
  await page.fill('#email', ADMIN_EMAIL);
  await page.fill('#password', ADMIN_PASS);
  await page.locator('form button[type="submit"]').first().click();
  await page.waitForURL('**/dashboard.php');
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

// Shared state across serial tests
let createdWorkerId = null;
let createdAziendaId = null;
let iscrizioneId = null;

const WORKER_NOME = `WFNome${RUN_ID}`;
const WORKER_COGNOME = `WFCogn${RUN_ID}`;
const AZIENDA_NOME = `WFAzienda${RUN_ID}`;

// ============================================================
// Full Workflow E2E Test
// ============================================================
test.describe.serial('Full Workflow: create, edit, event, azienda, PDF, iscrizione, archive', () => {

  // ── 1. Crea lavoratore compilando TUTTI i campi ──────────
  test('1. Crea lavoratore con tutti i campi', async ({ page }) => {
    test.setTimeout(45000);
    await login(page);
    await page.goto(`${BASE}/add_lavoratore.php`);

    // --- Dati Personali ---
    await page.fill('input[name="nome"]', WORKER_NOME);
    await page.fill('input[name="cognome"]', WORKER_COGNOME);
    await page.fill('input[name="codice_fiscale"]', `WFLTST${RUN_ID.slice(0,2)}A01H501Z`);
    await page.fill('input[name="data_nascita"]', '1988-03-15');

    // Paese nascita (autocomplete)
    await fillAutocomplete(page, 'input[name="paese_nascita"]', 'Roma');

    // Nazionalità (autocomplete)
    await fillAutocomplete(page, 'input[name="nazionalita"]', 'Italiana');

    // Genere
    await page.selectOption('select[name="genere"]', 'Maschio');

    // --- Iscrizione ---
    await page.fill('input[name="data_iscrizione"]', '2024-01-10');
    await page.selectOption('select[name="iscritto"]', '1');
    await page.selectOption('select[name="settore"]', 'privato');
    await page.selectOption('select[name="tipo_tessera"]', 'rinnovo annuale');
    await page.waitForTimeout(300);

    // Data inizio tessera (required per rinnovo annuale)
    const dataInizioGroup = page.locator('#data_inizio_group');
    if (await dataInizioGroup.isVisible({ timeout: 2000 }).catch(() => false)) {
      await page.fill('#data_inizio', '2024-06-01');
      await page.locator('#data_inizio').dispatchEvent('change');
      await page.waitForTimeout(300);
    }

    // Numero tessera
    await page.fill('input[name="numero_tessera"]', `TESS${RUN_ID}`);

    // Nota pagamento
    await page.fill('textarea[name="nota_pagamento"]', `Nota test ${RUN_ID}`);

    // Sede sindacato (select first available option if any)
    const sedeSelect = page.locator('select[name="sede_id"]');
    const sedeOptions = await sedeSelect.locator('option').count();
    if (sedeOptions > 1) {
      // Pick the first non-empty option
      await sedeSelect.selectOption({ index: 1 });
    }

    // --- Indirizzo ---
    await page.fill('input[name="indirizzo_via"]', 'Via del Workflow');
    await page.fill('input[name="indirizzo_numero_civico"]', '77');
    await page.fill('input[name="indirizzo_cap"]', '00185');
    await page.fill('input[name="indirizzo_citta"]', 'Roma');
    await page.fill('input[name="indirizzo_provincia"]', 'RM');

    // --- Contatti ---
    await page.fill('input[name="telefono"]', '+393201234567');
    await page.fill('input[name="email"]', `workflow${RUN_ID}@test.com`);

    // --- Azienda (autocomplete - type new name) ---
    const aziendaInput = page.locator('input[name="azienda"]');
    await aziendaInput.fill(`AziendaInit${RUN_ID}`);
    await page.waitForTimeout(500);

    // --- Contratto ---
    await page.selectOption('select[name="ruolo"]', 'NESSUNO');
    await page.selectOption('select[name="contratto"]', 'Indeterminato');
    await page.selectOption('select[name="orario_contratto"]', 'tempo pieno');

    // CCNL (autocomplete field)
    const ccnlInput = page.locator('input[name="ccnl"]');
    if (await ccnlInput.count() > 0) {
      await ccnlInput.fill('Commercio');
    }

    await page.fill('input[name="data_assunzione"]', '2023-09-01');

    const dataFineContratto = page.locator('input[name="data_fine_contratto"]');
    if (await dataFineContratto.count() > 0) {
      await dataFineContratto.fill('2028-12-31');
    }

    const oreSettimanali = page.locator('input[name="ore_settimanali"]');
    if (await oreSettimanali.count() > 0) {
      await oreSettimanali.fill('40');
    }

    const ral = page.locator('input[name="ral"]');
    if (await ral.count() > 0) {
      await ral.fill('30000');
    }

    // Note (TinyMCE editor wraps the textarea — set content via JS API)
    // Wait for TinyMCE to initialize
    await page.waitForFunction(() => typeof tinymce !== 'undefined' && tinymce.get('note'), { timeout: 10000 }).catch(() => {});
    await page.evaluate((text) => {
      if (typeof tinymce !== 'undefined' && tinymce.get('note')) {
        tinymce.get('note').setContent(`<p>${text}</p>`);
      }
    }, `Note di test workflow ${RUN_ID}`);

    // --- Submit ---
    // Bypass JS azienda_id validation right before submit (server auto-creates company from name)
    // Must be done just before click because autocomplete 'change' event resets it to 0 on blur
    await page.evaluate(() => {
      document.getElementById('azienda_id').value = '-1';
    });
    await page.locator('form[method="POST"] button[type="submit"]').click();

    // Dovrebbe reindirizzare alla lista lavoratori
    await page.waitForURL('**/lavoratori.php*', { timeout: 15000 });
    const content = await page.content();
    expect(content).not.toContain('Fatal error');
    expect(content).not.toContain('Parse error');

    // Recupera l'ID del lavoratore cercandolo nella DataTable
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });
    await page.waitForTimeout(2000);

    const searchInput = page.locator('.dataTables_filter input, input[type="search"]').first();
    if (await searchInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      await searchInput.fill(WORKER_COGNOME);
      await page.waitForTimeout(1500);
    }

    const workerLink = page.locator(`a[href*="lavoratore.php"]:has-text("${WORKER_COGNOME}")`).first();
    await expect(workerLink).toBeVisible({ timeout: 5000 });
    const href = await workerLink.getAttribute('href');
    const match = href.match(/id=(\d+)/);
    expect(match).not.toBeNull();
    createdWorkerId = match[1];
  });

  // ── 2. Crea un evento per il lavoratore ──────────────────
  test('2. Crea evento lavoratore', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/lavoratore.php?id=${createdWorkerId}`);

    // Attendi il calendario
    await page.waitForSelector('#calendar .fc-view', { timeout: 10000 });

    const csrfToken = await page.locator('input[name="csrf_token"]').first().getAttribute('value');
    const today = new Date();
    const startDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}T10:00`;

    const result = await page.evaluate(async ({ csrf, workerId, title, start }) => {
      const formData = new URLSearchParams();
      formData.append('csrf_token', csrf);
      formData.append('lavoratore_id', workerId);
      formData.append('title', title);
      formData.append('description', 'Evento test full workflow');
      formData.append('start', start);
      formData.append('allDay', '0');
      formData.append('is_company_event', '0');

      const resp = await fetch('add_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
      });
      const text = await resp.text();
      return { status: resp.status, body: text };
    }, { csrf: csrfToken, workerId: createdWorkerId, title: `EventoWF${RUN_ID}`, start: startDate });

    expect(result.status).toBe(200);
    const json = JSON.parse(result.body);
    expect(json.success).toBe(true);
    expect(json.event_id).toBeGreaterThan(0);

    // Verifica che l'evento appaia dopo reload
    await page.reload();
    await page.waitForSelector('#calendar .fc-view', { timeout: 10000 });
    const calendarContent = await page.locator('#calendar').textContent();
    expect(calendarContent).toContain(`EventoWF${RUN_ID}`);
  });

  // ── 3. Modifica TUTTI i campi del lavoratore ─────────────
  test('3. Modifica tutti i campi del lavoratore', async ({ page }) => {
    test.setTimeout(45000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/edit_lavoratore.php?id=${createdWorkerId}`);

    // --- Dati Personali ---
    await page.fill('input[name="nome"]', `Mod${WORKER_NOME}`);
    await page.fill('input[name="cognome"]', `Mod${WORKER_COGNOME}`);
    await page.fill('input[name="codice_fiscale"]', `MWFLT${RUN_ID.slice(0,3)}A01H501Y`);
    await page.fill('input[name="data_nascita"]', '1992-07-20');

    // Paese nascita
    const paeseNascitaInput = page.locator('input[name="paese_nascita"]');
    if (await paeseNascitaInput.count() > 0) {
      await paeseNascitaInput.fill('Milano');
    }

    // Nazionalità
    const nazionalitaInput = page.locator('input[name="nazionalita"]');
    if (await nazionalitaInput.count() > 0) {
      await nazionalitaInput.fill('Italiana');
    }

    // Genere
    const genereSelect = page.locator('select[name="genere"]');
    if (await genereSelect.count() > 0) {
      await genereSelect.selectOption('Femmina');
    }

    // --- Iscrizione ---
    const dataIscrizione = page.locator('input[name="data_iscrizione"]');
    if (await dataIscrizione.count() > 0) {
      await dataIscrizione.fill('2025-02-01');
    }

    const settoreSelect = page.locator('select[name="settore"]');
    if (await settoreSelect.count() > 0) {
      await settoreSelect.selectOption('pubblico');
    }

    const tipoTesseraSelect = page.locator('select[name="tipo_tessera"]');
    if (await tipoTesseraSelect.count() > 0) {
      await tipoTesseraSelect.selectOption('sepa');
    }

    // --- Indirizzo ---
    await page.fill('input[name="indirizzo_via"]', 'Corso Modificato');
    await page.fill('input[name="indirizzo_numero_civico"]', '33');
    await page.fill('input[name="indirizzo_cap"]', '10121');
    await page.fill('input[name="indirizzo_citta"]', 'Torino');
    await page.fill('input[name="indirizzo_provincia"]', 'TO');

    // --- Contatti ---
    await page.fill('input[name="telefono"]', '+393289999888');
    await page.fill('input[name="email"]', `modwf${RUN_ID}@test.com`);

    // --- Contratto ---
    const ruoloSelect = page.locator('select[name="ruolo"]');
    if (await ruoloSelect.count() > 0) {
      await ruoloSelect.selectOption('RSU');
    }

    const contrattoSelect = page.locator('select[name="contratto"]');
    if (await contrattoSelect.count() > 0) {
      await contrattoSelect.selectOption('Determinato');
    }

    const orarioSelect = page.locator('select[name="orario_contratto"]');
    if (await orarioSelect.count() > 0) {
      await orarioSelect.selectOption('part time');
    }

    // CCNL
    const ccnlInput = page.locator('input[name="ccnl"]');
    if (await ccnlInput.count() > 0) {
      await ccnlInput.fill('Metalmeccanica');
    }

    const dataAssunzione = page.locator('input[name="data_assunzione"]');
    if (await dataAssunzione.count() > 0) {
      await dataAssunzione.fill('2021-01-15');
    }

    const dataFineContratto = page.locator('input[name="data_fine_contratto"]');
    if (await dataFineContratto.count() > 0) {
      await dataFineContratto.fill('2029-06-30');
    }

    const oreSettimanali = page.locator('input[name="ore_settimanali"]');
    if (await oreSettimanali.count() > 0) {
      await oreSettimanali.fill('30');
    }

    const ralInput = page.locator('input[name="ral"]');
    if (await ralInput.count() > 0) {
      await ralInput.fill('42000');
    }

    // Note (TinyMCE editor — set via JS API)
    await page.waitForFunction(() => typeof tinymce !== 'undefined' && tinymce.get('note'), { timeout: 10000 }).catch(() => {});
    await page.evaluate((text) => {
      if (typeof tinymce !== 'undefined' && tinymce.get('note')) {
        tinymce.get('note').setContent(`<p>${text}</p>`);
      }
    }, `Note modificate workflow ${RUN_ID}`);

    // --- Submit ---
    await page.locator('form[method="POST"] button[type="submit"]').click();

    // Redirect alla scheda lavoratore
    await page.waitForURL(`**/lavoratore.php?id=${createdWorkerId}*`, { timeout: 10000 });

    const content = await page.content();
    expect(content).toContain(`Mod${WORKER_NOME}`);
    expect(content).toContain(`Mod${WORKER_COGNOME}`);
    expect(content).not.toContain('Fatal error');
  });

  // ── 4. Verifica persistenza modifiche ────────────────────
  test('4. Verifica persistenza dati modificati', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/edit_lavoratore.php?id=${createdWorkerId}`);

    // Campi testo
    await expect(page.locator('input[name="nome"]')).toHaveValue(`Mod${WORKER_NOME}`);
    await expect(page.locator('input[name="cognome"]')).toHaveValue(`Mod${WORKER_COGNOME}`);
    await expect(page.locator('input[name="data_nascita"]')).toHaveValue('1992-07-20');
    await expect(page.locator('input[name="email"]')).toHaveValue(`modwf${RUN_ID}@test.com`);
    await expect(page.locator('input[name="telefono"]')).toHaveValue('+393289999888');

    // Indirizzo
    await expect(page.locator('input[name="indirizzo_via"]')).toHaveValue('Corso Modificato');
    await expect(page.locator('input[name="indirizzo_numero_civico"]')).toHaveValue('33');
    await expect(page.locator('input[name="indirizzo_cap"]')).toHaveValue('10121');
    await expect(page.locator('input[name="indirizzo_citta"]')).toHaveValue('Torino');
    await expect(page.locator('input[name="indirizzo_provincia"]')).toHaveValue('TO');

    // Select
    const genereSelect = page.locator('select[name="genere"]');
    if (await genereSelect.count() > 0) {
      await expect(genereSelect).toHaveValue('Femmina');
    }

    const settoreSelect = page.locator('select[name="settore"]');
    if (await settoreSelect.count() > 0) {
      await expect(settoreSelect).toHaveValue('pubblico');
    }

    const tipoTesseraSelect = page.locator('select[name="tipo_tessera"]');
    if (await tipoTesseraSelect.count() > 0) {
      await expect(tipoTesseraSelect).toHaveValue('sepa');
    }

    const ruoloSelect = page.locator('select[name="ruolo"]');
    if (await ruoloSelect.count() > 0) {
      await expect(ruoloSelect).toHaveValue('RSU');
    }

    const contrattoSelect = page.locator('select[name="contratto"]');
    if (await contrattoSelect.count() > 0) {
      const val = await contrattoSelect.inputValue();
      expect(val.toLowerCase()).toBe('determinato');
    }

    const orarioSelect = page.locator('select[name="orario_contratto"]');
    if (await orarioSelect.count() > 0) {
      await expect(orarioSelect).toHaveValue('part time');
    }
  });

  // ── 5. Crea azienda e assegna il lavoratore ─────────────
  test('5. Crea azienda e assegna lavoratore', async ({ page }) => {
    test.setTimeout(45000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);

    // 5a. Crea nuova azienda
    await page.goto(`${BASE}/add_azienda.php`);

    await page.fill('#nome_azienda', AZIENDA_NOME);
    const piva = RUN_ID.padStart(11, '0').slice(-11);
    await page.fill('#partita_iva', piva);
    await page.fill('#indirizzo_via', 'Via Azienda WF');
    await page.fill('#indirizzo_numero_civico', '1');
    await page.fill('#indirizzo_cap', '20100');
    await page.fill('#indirizzo_citta', 'Milano');
    await page.fill('#indirizzo_provincia', 'MI');
    await page.fill('#telefono', '+390212345678');
    await page.fill('#email', `azwf${RUN_ID}@test.com`);

    const settoreAz = page.locator('#settore');
    if (await settoreAz.count() > 0) {
      await settoreAz.fill('Servizi');
    }

    const pecField = page.locator('#pec');
    if (await pecField.count() > 0) {
      await pecField.fill(`pecwf${RUN_ID}@test.com`);
    }

    await page.locator('form#aggiungiAziendaForm button[type="submit"]').click();

    await page.waitForURL('**/azienda.php?id=*', { timeout: 10000 });
    const aziendaUrl = page.url();
    const aziendaMatch = aziendaUrl.match(/id=(\d+)/);
    expect(aziendaMatch).not.toBeNull();
    createdAziendaId = aziendaMatch[1];

    const aziendaContent = await page.content();
    expect(aziendaContent).toContain(AZIENDA_NOME);

    // 5b. Assegna il lavoratore all'azienda via edit
    await page.goto(`${BASE}/edit_lavoratore.php?id=${createdWorkerId}`);
    await fillAutocomplete(page, 'input[name="azienda"]', AZIENDA_NOME);
    await page.locator('form[method="POST"] button[type="submit"]').click();
    await page.waitForURL(`**/lavoratore.php?id=${createdWorkerId}*`, { timeout: 10000 });

    const detailContent = await page.content();
    expect(detailContent).toContain(AZIENDA_NOME);
  });

  // ── 6. Scarica il PDF del lavoratore ─────────────────────
  test('6. Download PDF lavoratore', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/lavoratore.php?id=${createdWorkerId}`);

    // Click "PDF Trattenuta" button and wait for download
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.locator(`a[href*="generate_pdf.php?id=${createdWorkerId}"]`).click()
    ]);

    // Verify the download
    expect(download).not.toBeNull();
    const filename = download.suggestedFilename();
    expect(filename).toContain('.pdf');

    // Verify file is not empty
    const path = await download.path();
    expect(path).not.toBeNull();

    // Optionally test PDF Rinnovo too
    const pdfRinnovoLink = page.locator(`a[href*="generate_pdf_rinnovo.php?id=${createdWorkerId}"]`);
    if (await pdfRinnovoLink.isVisible({ timeout: 2000 }).catch(() => false)) {
      const [download2] = await Promise.all([
        page.waitForEvent('download'),
        pdfRinnovoLink.click()
      ]);
      expect(download2).not.toBeNull();
      const filename2 = download2.suggestedFilename();
      expect(filename2).toContain('.pdf');
    }
  });

  // ── 7. Modifica lo stato dell'iscrizione ─────────────────
  test('7. Modifica iscrizione del lavoratore', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/lavoratore.php?id=${createdWorkerId}`);

    // Trova il link "Modifica Iscrizione" ed estrai l'ID
    const modIscrizioneLink = page.locator('a[href*="modifica_iscrizione.php?id="]').first();
    await expect(modIscrizioneLink).toBeVisible({ timeout: 5000 });
    const iscrizioneHref = await modIscrizioneLink.getAttribute('href');
    const iscrizioneMatch = iscrizioneHref.match(/id=(\d+)/);
    expect(iscrizioneMatch).not.toBeNull();
    iscrizioneId = iscrizioneMatch[1];

    // Naviga alla pagina modifica iscrizione
    await page.goto(`${BASE}/modifica_iscrizione.php?id=${iscrizioneId}`);

    // Cambia tipo tessera da sepa (impostato nel test 3) a "trattenuta in busta paga"
    await page.selectOption('#tipo_tessera', 'trattenuta in busta paga');
    await page.waitForTimeout(300);

    // Aggiorna data inizio
    await page.fill('#data_inizio', '2025-03-01');

    // Aggiorna numero tessera
    await page.fill('#numero_tessera', `TESSMOD${RUN_ID}`);

    // Aggiorna nota pagamento
    await page.fill('#nota_pagamento', `Iscrizione modificata via test ${RUN_ID}`);

    // Submit
    await page.locator('form[method="POST"] button[type="submit"]').click();

    // La pagina rimane sulla stessa URL (modifica_iscrizione.php) con messaggio di successo
    await page.waitForTimeout(1000);
    const content = await page.content();
    expect(content).toContain('Iscrizione aggiornata con successo');
  });

  // ── 8. Archivia il lavoratore ────────────────────────────
  test('8. Archivia il lavoratore', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/lavoratore.php?id=${createdWorkerId}`);

    // Trova il form di archiviazione e submit
    const archiveForm = page.locator('form[action="archive_lavoratore.php"]');
    await expect(archiveForm).toBeVisible({ timeout: 5000 });

    await archiveForm.locator('button[type="submit"]').click();

    // Dovrebbe reindirizzare a lavoratore.php con archive_success=1
    await page.waitForURL(`**/lavoratore.php?id=${createdWorkerId}*archive_success*`, { timeout: 10000 });

    const content = await page.content();
    // Dovrebbe mostrare il pulsante "Ripristina" (perché è ora archiviato)
    expect(content).toContain('Ripristina lavoratore');
    // Non dovrebbe più mostrare "Archivia lavoratore"
    expect(content).not.toContain('Archivia lavoratore');
  });

  // ── 9. Verifica che il lavoratore appaia negli archiviati ─
  test('9. Verifica lavoratore in lista archiviati', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/archived_lavoratori.php`);

    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });
    await page.waitForTimeout(2000);

    // Cerca il lavoratore modificato nella DataTable
    const searchInput = page.locator('.dataTables_filter input, input[type="search"]').first();
    if (await searchInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      await searchInput.fill(`Mod${WORKER_COGNOME}`);
      await page.waitForTimeout(1500);
    }

    // Deve essere presente nella lista archiviati
    const pageContent = await page.content();
    expect(pageContent).toContain(`Mod${WORKER_COGNOME}`);
  });

  // ── 10. Cleanup: elimina dati di test ────────────────────
  test('10. Cleanup - elimina lavoratore e azienda di test', async ({ page }) => {
    test.setTimeout(45000);
    await login(page);

    // Elimina il lavoratore (è archiviato, ma delete_lavoratore funziona comunque)
    if (createdWorkerId) {
      await page.goto(`${BASE}/lavoratori.php`);
      const csrfToken1 = await page.locator('input[name="csrf_token"]').first().getAttribute('value');

      const delResult = await page.evaluate(async ({ csrf, id }) => {
        const formData = new URLSearchParams();
        formData.append('csrf_token', csrf);
        formData.append('id', id);
        const resp = await fetch('delete_lavoratore.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: formData.toString(),
          redirect: 'follow'
        });
        const text = await resp.text();
        return { status: resp.status, url: resp.url, bodySnippet: text.substring(0, 500) };
      }, { csrf: csrfToken1, id: createdWorkerId });

      expect(delResult.status).toBe(200);
      expect(delResult.url).toContain('delete_success=1');
    }

    // Elimina l'azienda creata al test 1 (auto-creata da add_lavoratore)
    // Prima elimina l'azienda principale creata al test 5
    if (createdAziendaId) {
      await page.goto(`${BASE}/aziende.php`);
      const csrfToken2 = await page.locator('input[name="csrf_token"]').first().getAttribute('value');

      await page.evaluate(async ({ csrf, id }) => {
        const formData = new URLSearchParams();
        formData.append('csrf_token', csrf);
        formData.append('id', id);
        await fetch('delete_azienda.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: formData.toString(),
          redirect: 'follow'
        });
      }, { csrf: csrfToken2, id: createdAziendaId });
    }

    // Elimina anche l'azienda auto-creata "AziendaInit{RUN_ID}" se esiste
    // (creata automaticamente in add_lavoratore.php quando il nome non esiste)
    await page.goto(`${BASE}/aziende.php`);
    await page.waitForSelector('.dataTables_wrapper, table', { timeout: 10000 });
    await page.waitForTimeout(1500);

    const searchInput = page.locator('.dataTables_filter input, input[type="search"]').first();
    if (await searchInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      await searchInput.fill(`AziendaInit${RUN_ID}`);
      await page.waitForTimeout(1500);
    }

    // Se esiste, elimina
    const initAziendaLink = page.locator(`a[href*="azienda.php"]:has-text("AziendaInit${RUN_ID}")`).first();
    if (await initAziendaLink.isVisible({ timeout: 2000 }).catch(() => false)) {
      const initHref = await initAziendaLink.getAttribute('href');
      const initMatch = initHref.match(/id=(\d+)/);
      if (initMatch) {
        const csrfToken3 = await page.locator('input[name="csrf_token"]').first().getAttribute('value');
        await page.evaluate(async ({ csrf, id }) => {
          const formData = new URLSearchParams();
          formData.append('csrf_token', csrf);
          formData.append('id', id);
          await fetch('delete_azienda.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString(),
            redirect: 'follow'
          });
        }, { csrf: csrfToken3, id: initMatch[1] });
      }
    }
  });
});
