<?php
// Contenuto completo del manuale utente, con ogni sezione inclusa nel div corrispondente alla TOC
?>
<div id="inserimento">
  <h2>📌 Inserimento Nuovo Lavoratore</h2>
  <p>Per aggiungere un nuovo lavoratore nel gestionale, segui attentamente i passaggi descritti di seguito. I campi contrassegnati con <span class="text-danger">*</span> sono obbligatori.</p>

  <h4>1. Accesso alla sezione</h4>
  <ul>
    <li>Dal menu principale, vai su <strong>Lavoratori > Aggiungi Nuovo Lavoratore</strong>.</li>
  </ul>

  <h4>2. Campi obbligatori</h4>
  <p>I seguenti campi devono essere obbligatoriamente compilati per poter salvare il lavoratore:</p>
  <ul>
    <li><strong>Nome</strong> <span class="text-danger">*</span></li>
    <li><strong>Cognome</strong> <span class="text-danger">*</span></li>
    <li><strong>Tipo Tessera</strong> <span class="text-danger">*</span></li>
    <li><strong>Data Inizio Tessera</strong> (in base al tipo selezionato)</li>
    <li><strong>Azienda</strong> <span class="text-danger">*</span></li>
  </ul>

  <h4>3. Inserimento dei dati personali</h4>
  <ul>
    <li><strong>Nome e Cognome</strong>: Obbligatori.</li>
    <li><strong>Codice Fiscale, Data di Nascita, Paese di Nascita, Nazionalità, Genere</strong>: Facoltativi ma consigliati per una gestione più completa.</li>
  </ul>

  <h4>4. Tipo di Tessera e Iscrizione</h4>
  <p>È obbligatorio specificare la modalità di iscrizione del lavoratore:</p>
  <ul>
    <li><strong>Trattenuta in busta paga</strong>: richiede la <strong>Data Inizio Tessera</strong>. La fine non viene calcolata automaticamente.</li>
    <li><strong>Rinnovo annuale</strong>: richiede la <strong>Data Inizio Tessera</strong> e calcola in automatico la data di fine (1 anno dopo). ⚠️ <strong>Attenzione:</strong> alla scadenza è necessario aggiornare manualmente la tessera per il nuovo anno.</li>
  </ul>
  <p>Puoi anche inserire il <strong>numero tessera</strong> (se disponibile) e aggiungere una <strong>nota pagamento</strong> per eventuali dettagli sul metodo.</p>

  <h4>5. Sede Sindacale</h4>
  <ul>
    <li>La sede deve essere creata preventivamente nella sezione <strong>Sedi</strong>.</li>
    <li>Una volta creata, sarà disponibile nel menu a tendina del form lavoratore.</li>
  </ul>

  <h4>6. Azienda e Unità Operativa</h4>
  <p><strong>Ricerca Azienda</strong>:</p>
  <ul>
    <li>Inizia a digitare almeno 2 lettere per visualizzare le aziende disponibili tramite <em>autocomplete</em>.</li>
    <li>Se l’azienda non è presente, clicca su <strong>+ Nuova Azienda</strong> per crearla direttamente dal form.</li>
  </ul>
  <p><strong>Unità Operativa</strong>:</p>
  <ul>
    <li>Visibile solo dopo la selezione di un'azienda.</li>
    <li>Può essere selezionata tramite <em>autocomplete</em> oppure creata con <strong>+ Nuova Unità Operativa</strong>.</li>
  </ul>

  <h4>7. Indirizzo e Contatti</h4>
  <ul>
    <li>Inserire indirizzo completo (via, numero civico, CAP, città, provincia).</li>
    <li>Email e telefono non sono obbligatori ma fortemente consigliati.</li>
  </ul>

  <h4>8. Informazioni Contrattuali</h4>
  <ul>
    <li>Tipo di contratto (indeterminato, determinato, apprendistato, ecc.).</li>
    <li>Orario (tempo pieno o part-time).</li>
    <li>CCNL, data assunzione, eventuale data di fine contratto.</li>
    <li>Ore settimanali e RAL.</li>
  </ul>

  <h4>9. Ruolo e Settore</h4>
  <ul>
    <li>Ruolo sindacale (RSA, RSU, RLS) o "NESSUNO" se non ricopre incarichi.</li>
    <li>Settore di appartenenza: <strong>Privato</strong> o <strong>Pubblico</strong>.</li>
  </ul>

  <h4>10. Note aggiuntive</h4>
  <p>È presente un editor testuale per aggiungere osservazioni specifiche o particolari comunicazioni sul lavoratore.</p>

  <h4>✅ Salvataggio</h4>
  <p>Una volta compilato il modulo, clicca su <strong>"Salva"</strong>. Il sistema eseguirà dei controlli per evitare errori e garantire la correttezza dei dati inseriti.</p>
</div>


<div id="archiviazione">
<h2>📌 Come funziona l’Archiviazione Lavoratore</h2>
<p>I lavoratori non vengono mai eliminati. L'archiviazione li rende solo non attivi:</p>
<ul>
  <li>Non appaiono nelle liste attive né nelle statistiche.</li>
  <li>Tutti i dati rimangono salvati e possono essere riattivati.</li>
</ul>
</div>

<div id="iscrizioni">
  <h2>📌 Gestione delle Iscrizioni Attive</h2>
  <p>Il sistema controlla automaticamente lo stato delle iscrizioni dei lavoratori in base alla tipologia di tessera e alla data di scadenza:</p>
  <ul>
    <li><strong>Iscrizione Attiva</strong>: 
      <ul>
        <li>Per tessere a <em>trattenuta in busta paga</em> o <em>SEPA</em>: l’iscrizione è considerata sempre attiva finché il lavoratore non viene archiviato.</li>
        <li>Per tessere a <em>rinnovo annuale</em>: l’iscrizione è attiva se la data di fine è uguale o successiva a quella odierna.</li>
      </ul>
    </li>
    <li><strong>Iscrizione in Scadenza</strong>: 
      <ul>
        <li>Se la data di fine è entro i prossimi 30 giorni, l’iscrizione risulta attiva ma in scadenza. È contrassegnata da un <span style="color:#ffc107;"><strong>pallino giallo</strong></span>.</li>
      </ul>
    </li>
    <li><strong>Iscrizione Scaduta</strong>: 
      <ul>
        <li>Per tessere a <em>rinnovo annuale</em>, l’iscrizione è considerata scaduta se la data di fine è superata e non ci sono altre iscrizioni attive. È contrassegnata da un <span style="color:#dc3545;"><strong>pallino rosso</strong></span>.</li>
      </ul>
    </li>
  </ul>
  <p>Lo stato dell’iscrizione è visibile nella scheda del lavoratore tramite un pallino colorato:</p>
  <ul>
    <li><strong style="color:#28a745;">🟢 Verde</strong>: Iscrizione attiva</li>
    <li><strong style="color:#ffc107;">🟡 Giallo</strong>: In scadenza</li>
    <li><strong style="color:#dc3545;">🔴 Rosso</strong>: Scaduta o lavoratore archiviato</li>
    <li><strong style="color:#6c757d;">⚪ Grigio</strong>: Nessuna iscrizione presente</li>
  </ul>
</div>


<div id="pdf">
  <h2>📌 Esportazione PDF</h2>
  <p>Dalla scheda anagrafica di ciascun lavoratore è possibile scaricare due documenti PDF precompilati con i suoi dati:</p>
  <ul>
    <li><strong>PDF per il Rinnovo Annuale</strong>: contiene le informazioni necessarie per il rinnovo dell’iscrizione annuale.</li>
    <li><strong>PDF per la Trattenuta in Busta Paga</strong>: utile per autorizzare la trattenuta automatica della quota sindacale sullo stipendio.</li>
  </ul>
  <p>I pulsanti per scaricare i PDF sono disponibili in alto nella pagina del profilo lavoratore.</p>
</div>


<div id="filtri">
  <h2>📌 Utilizzo dei Filtri nella Pagina Lavoratori</h2>
  <p>La pagina "Lavoratori" consente di applicare filtri avanzati per trovare rapidamente i profili desiderati, senza ricaricare la pagina grazie all'aggiornamento dinamico via AJAX.</p>
  <ul>
    <li>Puoi filtrare i lavoratori per: <strong>Azienda</strong>, <strong>Unità Operativa</strong>, <strong>Sede</strong>, <strong>Paese di nascita</strong>, <strong>Settore</strong>, <strong>Tipo tessera</strong>, <strong>Ruolo</strong>, <strong>Presenza di vertenze</strong>, <strong>Stato iscrizione</strong> e anche per parole chiave (nome, cognome o note).</li>
    <li>Il filtro per <strong>Azienda</strong> offre l’elenco completo delle aziende registrate. Se selezioni un’azienda, il filtro “Unità Operativa” si aggiornerà automaticamente (autocomplete) con le sole unità collegate a quella azienda.</li>
    <li>Il filtro per <strong>Sede</strong> viene impostato in automatico se l’operatore è associato a una sede, ma può essere modificato se necessario.</li>
    <li>È possibile usare più filtri contemporaneamente, ad esempio: “Azienda + Iscritto + Settore + Paese di nascita”.</li>
    <li>Una volta impostati i filtri, clicca su <strong>"Filtra"</strong> per applicarli oppure su <strong>"Reset"</strong> per rimuoverli tutti.</li>
    <li>I risultati vengono caricati istantaneamente senza ricaricare la pagina.</li>
    <li>È anche possibile <strong>esportare un file CSV</strong> con tutti i lavoratori o solo quelli filtrati. Il nome del file generato riflette i criteri di ricerca selezionati (es. `lavoratori_azienda_xyz_iscritto_si.csv`).</li>
    <li>Lo stato dell’iscrizione del lavoratore è visibile direttamente nella tabella grazie a un <strong>pallino colorato</strong>:
      <ul>
        <li><span style="color:green;">●</span> Iscrizione attiva</li>
        <li><span style="color:orange;">●</span> In scadenza</li>
        <li><span style="color:gray;">●</span> Non iscritto</li>
      </ul>
    </li>
    <li>Puoi effettuare ricerche libere usando la barra di ricerca (in alto a destra) inserendo nome, cognome o eventuali note associate al lavoratore.</li>
  </ul>
</div>


<div id="campi-condizionali">
<h2>📌 Campi Condizionali nel Form</h2>
<ul>
  <li>Il tipo tessera cambia i campi visibili (es. data fine per rinnovo annuale).</li>
  <li>L'unità operativa compare solo se è stata selezionata un'azienda.</li>
</ul>
</div>

<div id="scheda">
<h2>📌 Scheda Anagrafica del Lavoratore</h2>
<p>Permette di consultare, aggiornare e gestire tutte le informazioni di ogni lavoratore.</p>
</div>

<div id="stato-iscrizione">
<h2>✅ Stato Iscrizione e Archiviazione</h2>
<ul>
  <li><strong>🟢 Attiva</strong>: iscrizione valida.</li>
  <li><strong>🟡 In scadenza</strong>: entro 30 giorni dalla fine.</li>
  <li><strong>🔴 Scaduta</strong>: oltre la data finale.</li>
  <li><strong>⚫️ Nessuna iscrizione</strong>: mai iscritto o archiviato.</li>
</ul>
</div>

<div id="gestione-iscrizioni">
<h2>✅ Gestione delle Iscrizioni</h2>
<ul>
  <li><strong>Trattenuta</strong>: sempre attiva, senza scadenza.</li>
  <li><strong>Rinnovo annuale</strong>: un anno dalla data inizio, scadenza automatica.</li>
</ul>
<p><strong>Esempio:</strong> Iscritto 01/01/2024 → Scadenza 01/01/2025 → In scadenza dal 01/12/2024.</p>
</div>

<div id="vertenza">
<h2>✅ Pulsante Vertenza</h2>
<p>Indica se un lavoratore ha una vertenza attiva:</p>
<ul>
  <li>Attivabile/disattivabile nella scheda lavoratore.</li>
  <li>Puoi filtrare per vertenze nella pagina lavoratori.</li>
</ul>
</div>

<div id="calendario">
<h2>✅ Gestione Eventi nel Calendario</h2>
<p>Calendario interattivo con eventi:</p>
<h4>Eventi Individuali:</h4>
<ul>
  <li>Appuntamenti, colloqui, scadenze.</li>
</ul>
<h4>Eventi Aziendali:</h4>
<ul>
  <li>Visibili per tutti i lavoratori dell'azienda.</li>
  <li>Eliminazione globale o per singolo lavoratore.</li>
</ul>
</div>

<div id="documenti">
<h2>✅ Documenti e Pacchetto ZIP</h2>
<ul>
  <li>Upload di PDF e immagini con descrizione.</li>
  <li>Filtri per anno e descrizione.</li>
  <li>Selezione multipla → Download ZIP.</li>
  <li>Link temporaneo generato automaticamente.</li>
</ul>
</div>

<div id="criteri-iscrizione">
<h2>📌 Criteri Iscrizione</h2>
<ul>
  <li>Tipo tessera: <strong>trattenuta</strong>, <strong>rinnovo annuale</strong> o <strong>SEPA</strong>.</li>
</ul>
</div>

<div id="attualmente-iscritto">
<h2>📌 Come il gestionale determina se un lavoratore è iscritto</h2>
<h4>Trattenuta in busta paga / SEPA:</h4>
<ul>
  <li>Sempre iscritto se non archiviato.</li>
</ul>
<h4>Rinnovo annuale:</h4>
<ul>
  <li>Attivo: oltre 30 giorni dalla fine.</li>
  <li>In scadenza: entro 30 giorni.</li>
  <li>Scaduto: dopo la fine.</li>
  <li>Sospeso: se archiviato.</li>
</ul>
</div>

<div id="riattivazione">
<h2>🗂 Come Riattivare un Lavoratore Archiviato</h2>
<ul>
  <li>Clicca su <strong>Ripristina lavoratore</strong>.</li>
  <li>Il sistema riattiva lo stato precedente.</li>
</ul>
</div>

<div id="conclusione">
<h2>📌 Conclusioni sullo stato di iscrizione</h2>
<ul>
  <li>Tipo tessera = modalità (permanente o annuale).</li>
  <li>Date determinano validità per rinnovo annuale.</li>
  <li>Archiviazione sospende qualsiasi iscrizione.</li>
</ul>
</div>

<div id="modifica">
<h2>🔖 Modifica Lavoratore</h2>
<p>Permette di aggiornare tutti i dati del lavoratore, inclusi anagrafica, contratto e iscrizione.</p>
<h4>Quali modifiche puoi fare?</h4>
<ul>
  <li>Dati personali</li>
  <li>Sede sindacale</li>
  <li>Tipo di tessera e iscrizione</li>
  <li>Indirizzo e contatti</li>
  <li>Azienda e unità operativa</li>
  <li>Informazioni contrattuali</li>
  <li>Note interne</li>
</ul>
<h4>🔐 Cosa succede al salvataggio?</h4>
<ul>
  <li>Le modifiche vengono salvate immediatamente.</li>
  <li>Viene aggiornato anche lo stato dell'iscrizione.</li>
  <li>Eventuali aziende/unità nuove vengono create.</li>
</ul>
<h4>✅ Feedback</h4>
<ul>
  <li>Messaggio di conferma visibile subito.</li>
  <li>Eventuali errori sono segnalati in pagina.</li>
</ul>
</div>
