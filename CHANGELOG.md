# Note di versione

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
