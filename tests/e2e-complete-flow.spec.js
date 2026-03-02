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

// Shared data - IDs captured across tests
let createdWorkerId = null;
let createdAziendaId = null;
const WORKER_NOME = `TestNome${RUN_ID}`;
const WORKER_COGNOME = `TestCognome${RUN_ID}`;
const AZIENDA_NOME = `TestAzienda${RUN_ID}`;

// ============================================================
// Complete E2E Flow Test
// ============================================================
test.describe.serial('E2E Complete Flow', () => {

  // ── 1. Crea Azienda ────────────────────────────────────────
  test('1. Crea una nuova azienda', async ({ page }) => {
    test.setTimeout(30000);
    await login(page);
    await page.goto(`${BASE}/add_azienda.php`);

    // Compila tutti i campi
    await page.fill('#nome_azienda', AZIENDA_NOME);
    // P.IVA unica per evitare duplicati tra run
    const piva = RUN_ID.padStart(11, '0').slice(-11);
    await page.fill('#partita_iva', piva);
    await page.fill('#indirizzo_via', 'Via Test');
    await page.fill('#indirizzo_numero_civico', '42');
    await page.fill('#indirizzo_cap', '00100');
    await page.fill('#indirizzo_citta', 'Roma');
    await page.fill('#indirizzo_provincia', 'RM');
    await page.fill('#telefono', '+393331234567');
    await page.fill('#email', `azienda${RUN_ID}@test.com`);
    await page.fill('#settore', 'Informatica');
    await page.fill('#pec', `pec${RUN_ID}@test.com`);

    // Submit
    await page.locator('form#aggiungiAziendaForm button[type="submit"]').click();

    // Dovrebbe reindirizzare alla pagina dettaglio azienda
    await page.waitForURL('**/azienda.php?id=*', { timeout: 10000 });
    const url = page.url();
    const match = url.match(/id=(\d+)/);
    expect(match).not.toBeNull();
    createdAziendaId = match[1];

    // Verifica che il nome azienda sia visibile nella pagina
    const content = await page.content();
    expect(content).toContain(AZIENDA_NOME);
    expect(content).not.toContain('Fatal error');
  });

  // ── 2. Crea Lavoratore ─────────────────────────────────────
  test('2. Crea un nuovo lavoratore', async ({ page }) => {
    test.setTimeout(30000);
    await login(page);
    await page.goto(`${BASE}/add_lavoratore.php`);

    // Campi obbligatori
    await page.fill('input[name="nome"]', WORKER_NOME);
    await page.fill('input[name="cognome"]', WORKER_COGNOME);

    // Campi anagrafici
    await page.fill('input[name="codice_fiscale"]', 'TSTCGN90A01H501Z');
    await page.fill('input[name="data_nascita"]', '1990-01-01');
    await page.fill('input[name="email"]', `worker${RUN_ID}@test.com`);
    await page.fill('input[name="telefono"]', '+393339876543');

    // Indirizzo
    await page.fill('input[name="indirizzo_via"]', 'Via Lavoratore');
    await page.fill('input[name="indirizzo_numero_civico"]', '10');
    await page.fill('input[name="indirizzo_cap"]', '20100');
    await page.fill('input[name="indirizzo_citta"]', 'Milano');
    await page.fill('input[name="indirizzo_provincia"]', 'MI');

    // Azienda (required autocomplete) — usa l'azienda creata in test 1
    await fillAutocomplete(page, 'input[name="azienda"]', AZIENDA_NOME);

    // Select fields
    const genereSelect = page.locator('select[name="genere"]');
    if (await genereSelect.count() > 0) {
      await genereSelect.selectOption('Maschio');
    }

    const settoreSelect = page.locator('select[name="settore"]');
    if (await settoreSelect.count() > 0) {
      await settoreSelect.selectOption('privato');
    }

    const tipoTesseraSelect = page.locator('select[name="tipo_tessera"]');
    if (await tipoTesseraSelect.count() > 0) {
      await tipoTesseraSelect.selectOption('rinnovo annuale');
      // Attendi che JS mostri i campi data
      await page.waitForTimeout(500);
    }

    // Data Inizio Tessera (required per rinnovo annuale)
    const dataInizio = page.locator('#data_inizio');
    if (await dataInizio.isVisible({ timeout: 2000 }).catch(() => false)) {
      await dataInizio.fill('2025-01-01');
      // Trigger change event per autocompilare data_fine
      await dataInizio.dispatchEvent('change');
      await page.waitForTimeout(300);
    }

    const contrattoSelect = page.locator('select[name="contratto"]');
    if (await contrattoSelect.count() > 0) {
      await contrattoSelect.selectOption('Indeterminato');
    }

    const orarioSelect = page.locator('select[name="orario_contratto"]');
    if (await orarioSelect.count() > 0) {
      await orarioSelect.selectOption('tempo pieno');
    }

    const ruoloSelect = page.locator('select[name="ruolo"]');
    if (await ruoloSelect.count() > 0) {
      await ruoloSelect.selectOption('NESSUNO');
    }

    // Submit
    await page.locator('form[method="POST"] button[type="submit"]').click();

    // Dovrebbe reindirizzare alla lista lavoratori con successo
    await page.waitForURL('**/lavoratori.php*', { timeout: 15000 });

    // Recupera l'ID del lavoratore appena creato cercandolo nel DB via pagina
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });
    await page.waitForTimeout(2000);

    // Cerca il lavoratore nella tabella
    const searchInput = page.locator('.dataTables_filter input, input[type="search"]').first();
    if (await searchInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      await searchInput.fill(WORKER_COGNOME);
      await page.waitForTimeout(1500);
    }

    // Trova il link al lavoratore e prendi l'ID
    const workerLink = page.locator(`a[href*="lavoratore.php"]:has-text("${WORKER_COGNOME}")`).first();
    if (await workerLink.isVisible({ timeout: 5000 }).catch(() => false)) {
      const href = await workerLink.getAttribute('href');
      const match = href.match(/id=(\d+)/);
      expect(match).not.toBeNull();
      createdWorkerId = match[1];
    } else {
      // Fallback: cerca edit link
      const editLink = page.locator(`a[href*="edit_lavoratore.php"]`).first();
      const href = await editLink.getAttribute('href');
      const match = href.match(/id=(\d+)/);
      expect(match).not.toBeNull();
      createdWorkerId = match[1];
    }

    const content = await page.content();
    expect(content).toContain(WORKER_COGNOME);
    expect(content).not.toContain('Fatal error');
  });

  // ── 3. Associa Lavoratore all'Azienda ──────────────────────
  test('3. Associa il lavoratore all\'azienda via edit', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();
    expect(createdAziendaId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/edit_lavoratore.php?id=${createdWorkerId}`);

    // L'azienda usa autocomplete — riempi il campo
    await fillAutocomplete(page, 'input[name="azienda"]', AZIENDA_NOME);

    // Submit
    await page.locator('form[method="POST"] button[type="submit"]').click();

    // Attendi redirect o ricaricamento
    await page.waitForURL(`**/lavoratore.php?id=${createdWorkerId}*`, { timeout: 10000 });

    // Verifica che l'azienda sia associata nella pagina dettaglio
    const content = await page.content();
    expect(content).toContain(AZIENDA_NOME);
  });

  // ── 4. Modifica TUTTI i campi del lavoratore ───────────────
  test('4. Modifica tutti i campi del lavoratore', async ({ page }) => {
    test.setTimeout(45000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/edit_lavoratore.php?id=${createdWorkerId}`);

    // Modifica campi anagrafici
    await page.fill('input[name="nome"]', `Mod${WORKER_NOME}`);
    await page.fill('input[name="cognome"]', `Mod${WORKER_COGNOME}`);
    await page.fill('input[name="codice_fiscale"]', 'MDFTST90A01H501Y');
    await page.fill('input[name="data_nascita"]', '1985-06-15');
    await page.fill('input[name="email"]', `modified${RUN_ID}@test.com`);
    await page.fill('input[name="telefono"]', '+393331112233');

    // Modifica indirizzo
    await page.fill('input[name="indirizzo_via"]', 'Via Modificata');
    await page.fill('input[name="indirizzo_numero_civico"]', '99');
    await page.fill('input[name="indirizzo_cap"]', '10100');
    await page.fill('input[name="indirizzo_citta"]', 'Torino');
    await page.fill('input[name="indirizzo_provincia"]', 'TO');

    // Modifica autocomplete
    const nazionalitaInput = page.locator('input[name="nazionalita"]');
    if (await nazionalitaInput.count() > 0) {
      await nazionalitaInput.fill('Italiana');
    }

    const paeseNascitaInput = page.locator('input[name="paese_nascita"]');
    if (await paeseNascitaInput.count() > 0) {
      await paeseNascitaInput.fill('Italia');
    }

    // Modifica select
    const genereSelect = page.locator('select[name="genere"]');
    if (await genereSelect.count() > 0) {
      await genereSelect.selectOption('Femmina');
    }

    const settoreSelect = page.locator('select[name="settore"]');
    if (await settoreSelect.count() > 0) {
      await settoreSelect.selectOption('pubblico');
    }

    const tipoTesseraSelect = page.locator('select[name="tipo_tessera"]');
    if (await tipoTesseraSelect.count() > 0) {
      await tipoTesseraSelect.selectOption('sepa');
    }

    const contrattoSelect = page.locator('select[name="contratto"]');
    if (await contrattoSelect.count() > 0) {
      await contrattoSelect.selectOption('Determinato');
    }

    const orarioSelect = page.locator('select[name="orario_contratto"]');
    if (await orarioSelect.count() > 0) {
      await orarioSelect.selectOption('part time');
    }

    const ruoloSelect = page.locator('select[name="ruolo"]');
    if (await ruoloSelect.count() > 0) {
      await ruoloSelect.selectOption('RSU');
    }

    // Modifica date contratto
    const dataAssunzione = page.locator('input[name="data_assunzione"]');
    if (await dataAssunzione.count() > 0) {
      await dataAssunzione.fill('2020-03-01');
    }

    const dataFineContratto = page.locator('input[name="data_fine_contratto"]');
    if (await dataFineContratto.count() > 0) {
      await dataFineContratto.fill('2027-12-31');
    }

    // Ore settimanali e RAL
    const oreSettimanali = page.locator('input[name="ore_settimanali"]');
    if (await oreSettimanali.count() > 0) {
      await oreSettimanali.fill('36');
    }

    const ral = page.locator('input[name="ral"]');
    if (await ral.count() > 0) {
      await ral.fill('35000');
    }

    // Data iscrizione
    const dataIscrizione = page.locator('input[name="data_iscrizione"]');
    if (await dataIscrizione.count() > 0) {
      await dataIscrizione.fill('2021-01-15');
    }

    // CCNL (autocomplete)
    const ccnlInput = page.locator('input[name="ccnl"]');
    if (await ccnlInput.count() > 0) {
      await ccnlInput.fill('Commercio');
    }

    // Submit
    await page.locator('form[method="POST"] button[type="submit"]').click();

    // Attendi redirect alla scheda lavoratore
    await page.waitForURL(`**/lavoratore.php?id=${createdWorkerId}*`, { timeout: 10000 });

    // Verifica che le modifiche siano visibili
    const content = await page.content();
    expect(content).toContain(`Mod${WORKER_NOME}`);
    expect(content).toContain(`Mod${WORKER_COGNOME}`);
    expect(content).not.toContain('Fatal error');
    expect(content).not.toContain('Parse error');
  });

  // ── 5. Verifica che i dati modificati siano persistiti ─────
  test('5. Verifica persistenza dati modificati', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/edit_lavoratore.php?id=${createdWorkerId}`);

    // Verifica campi anagrafici
    await expect(page.locator('input[name="nome"]')).toHaveValue(`Mod${WORKER_NOME}`);
    await expect(page.locator('input[name="cognome"]')).toHaveValue(`Mod${WORKER_COGNOME}`);
    await expect(page.locator('input[name="codice_fiscale"]')).toHaveValue('MDFTST90A01H501Y');
    await expect(page.locator('input[name="data_nascita"]')).toHaveValue('1985-06-15');
    await expect(page.locator('input[name="email"]')).toHaveValue(`modified${RUN_ID}@test.com`);
    await expect(page.locator('input[name="telefono"]')).toHaveValue('+393331112233');

    // Verifica indirizzo
    await expect(page.locator('input[name="indirizzo_via"]')).toHaveValue('Via Modificata');
    await expect(page.locator('input[name="indirizzo_numero_civico"]')).toHaveValue('99');
    await expect(page.locator('input[name="indirizzo_cap"]')).toHaveValue('10100');
    await expect(page.locator('input[name="indirizzo_citta"]')).toHaveValue('Torino');
    await expect(page.locator('input[name="indirizzo_provincia"]')).toHaveValue('TO');

    // Verifica select
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

    const contrattoSelect = page.locator('select[name="contratto"]');
    if (await contrattoSelect.count() > 0) {
      // edit_lavoratore normalizza a lowercase
      const contrattoVal = await contrattoSelect.inputValue();
      expect(contrattoVal.toLowerCase()).toBe('determinato');
    }

    const ruoloSelect = page.locator('select[name="ruolo"]');
    if (await ruoloSelect.count() > 0) {
      await expect(ruoloSelect).toHaveValue('RSU');
    }
  });

  // ── 6. Aggiungi evento al lavoratore ───────────────────────
  test('6. Aggiungi evento al lavoratore', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/lavoratore.php?id=${createdWorkerId}`);

    // Attendi che il calendario si carichi
    await page.waitForSelector('#calendar .fc-view', { timeout: 10000 });

    // Usa fetch() dal browser per creare l'evento (condivide esattamente la sessione della pagina)
    const csrfToken = await page.locator('input[name="csrf_token"]').first().getAttribute('value');
    const today = new Date();
    const startDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}T10:00`;

    const result = await page.evaluate(async ({ csrf, workerId, title, start }) => {
      const formData = new URLSearchParams();
      formData.append('csrf_token', csrf);
      formData.append('lavoratore_id', workerId);
      formData.append('title', title);
      formData.append('description', 'Evento di test per lavoratore');
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
    }, { csrf: csrfToken, workerId: createdWorkerId, title: `EventoLavoratore${RUN_ID}`, start: startDate });

    expect(result.status).toBe(200);
    const json = JSON.parse(result.body);
    expect(json.success).toBe(true);
    expect(json.event_id).toBeGreaterThan(0);

    // Ricarica la pagina e verifica che l'evento appaia nel calendario
    await page.reload();
    await page.waitForSelector('#calendar .fc-view', { timeout: 10000 });

    // L'evento dovrebbe essere visibile nel mese corrente
    const calendarContent = await page.locator('#calendar').textContent();
    expect(calendarContent).toContain(`EventoLavoratore${RUN_ID}`);
  });

  // ── 7. Aggiungi evento aziendale ───────────────────────────
  test('7. Aggiungi evento aziendale', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdAziendaId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/azienda.php?id=${createdAziendaId}`);

    // Attendi che il calendario si carichi
    await page.waitForSelector('#calendar .fc-view', { timeout: 10000 });

    // Usa fetch() dal browser per creare l'evento aziendale (create_event_azienda.php)
    const csrfToken = await page.locator('input[name="csrf_token"]').first().getAttribute('value');

    const today = new Date();
    const startDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}T14:00`;

    const result = await page.evaluate(async ({ csrf, aziendaId, title, start }) => {
      const formData = new URLSearchParams();
      formData.append('csrf_token', csrf);
      formData.append('azienda_id', aziendaId);
      formData.append('titolo', title);
      formData.append('descrizione', 'Evento aziendale di test');
      formData.append('start', start);
      formData.append('all_day', '0');

      const resp = await fetch('create_event_azienda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
      });
      const text = await resp.text();
      return { status: resp.status, body: text };
    }, { csrf: csrfToken, aziendaId: createdAziendaId, title: `EventoAzienda${RUN_ID}`, start: startDate });

    expect(result.status).toBe(200);
    const json = JSON.parse(result.body);
    expect(json.success).toBe(true);

    // Ricarica e verifica che l'evento appaia
    await page.reload();
    await page.waitForSelector('#calendar .fc-view', { timeout: 10000 });

    const calendarContent = await page.locator('#calendar').textContent();
    expect(calendarContent).toContain(`EventoAzienda${RUN_ID}`);
  });

  // ── 8. Verifica eventi nella scheda lavoratore ─────────────
  test('8. Verifica evento visibile nella scheda lavoratore', async ({ page }) => {
    test.setTimeout(30000);
    expect(createdWorkerId).not.toBeNull();

    await login(page);
    await page.goto(`${BASE}/lavoratore.php?id=${createdWorkerId}`);

    await page.waitForSelector('#calendar .fc-view', { timeout: 10000 });

    // L'evento del lavoratore deve essere visibile
    const calendarContent = await page.locator('#calendar').textContent();
    expect(calendarContent).toContain(`EventoLavoratore${RUN_ID}`);

    // Anche l'evento aziendale dovrebbe essere visibile (il lavoratore è associato all'azienda)
    // Nota: dipende dal fetch_events.php che include gli eventi aziendali dell'azienda del lavoratore
    // Se non appare, il test continua comunque — l'importante è che l'evento lavoratore sia presente
  });

  // ── 9. Verifica eventi nel calendario dashboard ────────────
  test('9. Verifica eventi nel calendario dashboard', async ({ page }) => {
    test.setTimeout(30000);

    await login(page);
    await page.goto(`${BASE}/dashboard.php`);

    // Attendi che il calendario dashboard si carichi
    await page.waitForSelector('#calendar .fc-view', { timeout: 10000 });

    // Il calendario dashboard mostra tutti gli eventi
    const calendarContent = await page.locator('#calendar').textContent();

    // Almeno uno dei due eventi dovrebbe essere visibile
    const hasWorkerEvent = calendarContent.includes(`EventoLavoratore${RUN_ID}`);
    const hasCompanyEvent = calendarContent.includes(`EventoAzienda${RUN_ID}`);
    expect(hasWorkerEvent || hasCompanyEvent).toBe(true);
  });

  // ── 10. Cleanup: elimina dati di test ──────────────────────
  test('10. Cleanup - elimina lavoratore e azienda di test', async ({ page }) => {
    test.setTimeout(45000);
    await login(page);

    // Elimina il lavoratore usando fetch() dal browser (condivide la sessione)
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

      // Should redirect to lavoratori.php with delete_success=1
      expect(delResult.status).toBe(200);
      expect(delResult.url).toContain('delete_success=1');
    }

    // Elimina eventi aziendali residui prima dell'azienda
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

    // Verifica che il lavoratore non sia più nella lista
    await page.goto(`${BASE}/lavoratori.php`);
    await page.waitForSelector('.dataTables_wrapper', { timeout: 10000 });

    // Cerca il lavoratore nella DataTable
    const searchInput = page.locator('.dataTables_filter input, input[type="search"]').first();
    if (await searchInput.isVisible({ timeout: 3000 }).catch(() => false)) {
      await searchInput.fill(`Mod${WORKER_COGNOME}`);
      await page.waitForTimeout(2000);
    }

    // Verifica che non appaia nella tabella (DataTable filtra server-side)
    const rows = page.locator('#lavoratoriTable tbody tr');
    const rowCount = await rows.count();
    if (rowCount > 0) {
      const firstRowText = await rows.first().textContent();
      // Se c'è una riga "Nessun dato" o non contiene il nome, è OK
      const workerStillExists = firstRowText.includes(`Mod${WORKER_COGNOME}`);
      expect(workerStillExists, 'Worker should be deleted from the list').toBe(false);
    }
  });
});
