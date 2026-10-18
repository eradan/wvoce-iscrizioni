# Note di versione

## Versione 1.2.1: correzioni dalla revisione del codice

- **La "&" nei testi semplici resta "&".** Gli avvisi sopra i moduli ricompilati, il messaggio di "Recupera i tuoi dati" e il testo delle due email venivano puliti come HTML: una "&" diventava "&amp;", e così si leggeva a video e nell'email. Ora sono testo semplice. I messaggi accanto al codice fiscale ("già socio", "non socio") restano in HTML semplice, con i link. Se un testo mostra già "&amp;", correggilo una volta nella scheda Passaggio e recupero.
- **Avviso su "Riservato ai soci"** nella scheda Campi e regole quando al collegamento dei soci mancano il modulo o il campo del codice fiscale: senza, nessuno risulta socio e il modulo dei corsi si ferma per tutti.
- **Lettura del tab di configurazione dei corsi conservata** quando si salvano le impostazioni dei soci o del passaggio. Si azzera solo se cambiano collegamento o campi e regole dei corsi.
- **Link di "Recupera i tuoi dati"** non più valido se nel frattempo l'iscrizione è diventata bozza o abbandonata, come già per quella spostata nello spam.
- Tolta l'importazione della configurazione, che serviva solo al passaggio di consegne dal tema VivaVoce 1.0.0.
- **Test:** 157 controlli.

## Versione 1.2.0: pannello riorganizzato

Stesso pannello, stesse impostazioni: nessun passo da fare dopo l'aggiornamento.

- **Schema unico delle impostazioni** (`inc/schema.php`): ogni campo del pannello è descritto una volta, con tipo, etichetta e valore predefinito. Da lì vengono i predefiniti, i campi delle schede e il salvataggio. Prima gli stessi campi erano elencati in tre posti da tenere allineati a mano, l'origine dell'errore corretto nella 1.0.1. Ogni modulo del pannello salva solo le sue voci, quindi le altre schede restano intatte per costruzione.
- **Pannello diviso in file piccoli** (`inc/admin/`): pagina e schede, azioni dei pulsanti, componenti, una scheda per file. Script e stile del pannello in `assets/admin.js` e `assets/admin.css`, caricati solo su quella pagina, al posto del codice in linea.
- **Corrispondenza corso → tab letta su richiesta:** la scheda Invio corsi non chiama più Google a ogni apertura. Mostra l'ultima lettura, con data e ora, valida un giorno, e ha il pulsante per rileggere il tab di configurazione. Senza lettura, la prova a secco raggruppa le righe per corso.
- **Test:** 152 controlli. In più: coerenza dello schema e "andata e ritorno" di ogni modulo del pannello, cioè disegnato, inviato com'è e salvato senza che cambi nulla, oltre alle azioni dei pulsanti. **Collaudo** su WordPress con Forminator: campi, valori ed etichette di tutte le schede identici alla 1.1.1, configurazione invariata dopo il salvataggio di tutti i moduli, verifica, lettura della mappa e invio con un finto foglio.

## Versione 1.1.1: invio ai fogli più robusto, ricerca del codice fiscale mirata

- **Ricerca del codice fiscale con una query mirata** (`inc/archivio.php`): le regole sui moduli e "Recupera i tuoi dati" cercano solo le iscrizioni con quel codice, invece di leggere tutte le iscrizioni del modulo con i loro campi. Su una prova con 304 iscrizioni: da 614 query a 6. Anche il giro verso i fogli legge numero e stato delle sole iscrizioni dopo l'ultima inviata, e i campi solo di quelle da inviare.
- **Iscrizioni tolte dallo spam di nuovo inviate.** Forminator esclude lo spam dall'elenco che il plugin usava: un'iscrizione messa nello spam e poi recuperata, se nel frattempo ne erano arrivate altre, non partiva più. Ora lo spam si vede, resta sospeso e parte quando torna valido (difetto ereditato dal tema).
- **Iscrizioni abbandonate, in attesa o con pagamento fallito** (stato di Forminator) non valgono più come iscrizioni: restano sospese, fuori dai fogli e dalle regole sui soci.
- **Due minuti di attesa per le iscrizioni appena arrivate**, perché il giro non le legga mentre Forminator ne sta ancora salvando i campi (finirebbe nel foglio una riga vuota). Il giro si ferma alla prima in attesa, così il punto raggiunto non la supera.
- **Un invio alla volta:** il giro automatico e "Invia adesso" non partono insieme.
- **"Solo le nuove" alla prima configurazione** fissa davvero il punto di partenza all'ultima iscrizione presente (prima partiva tutto lo storico).
- **Colonna del corso:** non può restare vuota né comparire fra le colonne del foglio (le righe dei corsi diventavano uguali); il pannello lo corregge e avvisa.
- **Collaudo** su WordPress con Forminator e un finto foglio: ricerca del codice fiscale (minuscole e spazi compresi, spam e abbandonate escluse), spam recuperato, attesa delle nuove, invio. **Test:** 144 controlli.

## Versione 1.1.0: pannello più sicuro, moduli anche come blocco

- **Avvisi sui campi:** nelle schede Campi e regole e Passaggio e recupero il pannello segnala i campi indicati (codice fiscale, email, corsi, colonne, campi da riportare o da recuperare) che il modulo non contiene: un errore di battitura non dà più in silenzio una colonna vuota o un campo non compilato.
- **Moduli inseriti come blocco** dell'editor (`wp:forminator/…`) riconosciuti come quelli con lo shortcode: anche lì arrivano controllo del codice fiscale, "Recupera i tuoi dati" e compilazione automatica.
- **Messaggio sul codice fiscale sbagliato** modificabile (Collegamenti → Impostazioni generali), lo stesso all'invio, all'uscita dal campo e in "Recupera i tuoi dati".
- **Eliminazione pulita:** eliminando il plugin (non disattivandolo) si cancellano impostazioni, stato dell'invio e giro programmato (`uninstall.php`). Le iscrizioni restano in Forminator e nei fogli.
- **Niente più legami con il tema VivaVoce 0.x:** tolto il controllo che fermava il plugin con un tema che gestiva da sé le iscrizioni.
- Impostazioni di un collegamento calcolate una volta per richiesta, non a ogni valore di ogni riga.
- **Collaudo** su WordPress con due moduli Forminator veri (shortcode e blocco): controllo del codice fiscale, "Recupera i tuoi dati" con il link e la ricompilazione, avvisi del pannello, salvataggio di tutte le schede senza cambiare le altre. **Test:** 134 controlli.

## Versione 1.0.1: il salvataggio di una scheda non cambia più le altre

- **Correzione importante.** Salvare una scheda del pannello spegneva le caselle sì/no che stanno in un'altra scheda dello stesso collegamento, e salvare le impostazioni generali cancellava la compilazione automatica. Per esempio, salvando "Passaggio e recupero" si spegnevano il controllo del codice fiscale e la regola "una sola iscrizione per anno" del modulo dei soci; salvando "Campi e regole" si spegneva "Recupera i tuoi dati". Ora ogni scheda salva solo i suoi campi.
- **Test:** 129 controlli.

Da fare dopo l'aggiornamento: in Strumenti → Iscrizioni ricontrollare le caselle di tutte le schede (Campi e regole per soci e corsi, Passaggio e recupero, Sito dietro un proxy) e la compilazione automatica, e salvarle una per una.

## Versione 1.0.0: prima versione

Gestione delle iscrizioni estratta dal tema VivaVoce (versioni fino alla 0.13) e resa indipendente da tema e associazione: tutto ciò che il tema aveva scritto nel codice è ora un'impostazione del pannello Strumenti → Iscrizioni.

- Codice fiscale controllato all'invio e all'uscita dal campo; regole per anno sociale (socio richiesto per i corsi, una sola iscrizione a socio per anno), accendibili una per una, con messaggi modificabili.
- Anno sociale con mese d'inizio a scelta (anche anno solare).
- Campi dei moduli scelti da tendina (codice fiscale, email, corsi), colonne del foglio scritte nel pannello (`Intestazione = campo`, `Intestazione = address-1.city`), nomi dei tab e delle colonne speciali.
- Passaggio dal modulo dei soci a quello dei corsi, con campi e caselle scelti nel pannello.
- "Recupera i tuoi dati" con anni, durata del link, limiti e testi (link, messaggio, avviso, due email) dal pannello; esito vero per gli amministratori; anteprima `?wvi-socio-prova=`.
- Indirizzo del visitatore dietro un proxy solo se acceso nel pannello.
- Script Apps Script generici: prefisso dei tab, tab di configurazione e di riserva, colonne dei riferimenti e del corso arrivano dal sito a ogni chiamata. Restano compatibili con i fogli già in uso; gli script pubblicati prima continuano a funzionare finché i nomi non cambiano.
- Importazione di una configurazione completa con lo stato dell'invio (`wvi_importa()`), usata per il passaggio dal tema.
- Test: 124 controlli PHP, 21 sugli script Apps Script.
