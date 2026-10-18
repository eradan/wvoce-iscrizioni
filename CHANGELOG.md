# Note di versione

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
