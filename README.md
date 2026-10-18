# wvoce-iscrizioni

Plugin WordPress per le iscrizioni di un'associazione fatte con [Forminator](https://wordpress.org/plugins/forminator/): iscrizione a socio e iscrizione ai corsi. Non dipende dal tema e non contiene dati di un'associazione in particolare: tutto si configura in **Strumenti → Iscrizioni**.

## Cosa fa

- **Codice fiscale controllato davvero** (formato e carattere di controllo; Forminator conta solo i caratteri), sia all'invio sia appena si esce dal campo.
- **Regole per anno sociale** (mese d'inizio a scelta, per esempio settembre–agosto, o anno solare):
  - modulo dei soci: una sola iscrizione per anno sociale;
  - modulo dei corsi: riservato a chi è socio dell'anno.
- **Dal modulo dei soci a quello dei corsi**: chi si iscrive a socio e vuole fare anche un corso arriva al modulo dei corsi già compilato. I dati passano con un codice usa e getta, mai nell'indirizzo.
- **"Recupera i tuoi dati"**: sotto il codice fiscale del modulo dei soci, un link manda all'email dell'ultima iscrizione un link personale che riapre il modulo compilato. A video il messaggio è sempre lo stesso, così nessuno scopre chi è socio; limiti per connessione e per codice fiscale; agli amministratori loggati l'esito vero.
- **Copia nei fogli Google**: ogni 15 minuti le iscrizioni nuove vanno nel foglio dei soci (un tab per anno sociale) e in quello dei corsi (una riga per corso, nel tab indicato dal foglio stesso), attraverso un'app web di Apps Script pubblicata dal foglio. Si avanza solo sulle righe che il foglio conferma, senza doppioni; bozze e iscrizioni tolte dallo spam partono quando diventano valide; dopo un giorno di errori l'amministratore riceve un'email.
- **Compilazione automatica del browser**: attributi `autocomplete` per i campi dei moduli.

## Configurazione

Strumenti → Iscrizioni:

| Scheda | Cosa si imposta |
|---|---|
| Collegamenti | anno sociale, pagine dei moduli, email di contatto, proxy; per soci e corsi: modulo Forminator, app web e foglio |
| Campi e regole | campo del codice fiscale (e dell'email, dei corsi), regole, messaggi d'errore, nomi dei tab, colonne del foglio (`Intestazione = campo`) |
| Passaggio e recupero | passaggio soci → corsi, "Recupera i tuoi dati" con i suoi testi, compilazione automatica |
| Invio soci / Invio corsi | stato, prova a secco, invio immediato, ripartenza |
| Script Apps Script | i due script da incollare nell'editor di Apps Script |

Gli script in `apps-script/` sono uguali per ogni sito: nomi dei tab e delle colonne speciali li manda il sito a ogni chiamata. Vanno incollati e pubblicati una volta (istruzioni nel pannello); dopo una modifica agli script: Gestisci deployment → Nuova versione.

## Requisiti

WordPress 6.5+, PHP 7.4+, Forminator.

## Sviluppo

```bash
php tests/run.php                 # test PHP senza WordPress (composer test)
node tests/apps-script.js         # test degli script Apps Script su un foglio finto
composer install && composer lint # phpcs: WordPress-Extra + PHPCompatibilityWP
bash bin/build-zip.sh             # build/wvoce-iscrizioni.zip dall'ultimo commit
```

Codice e commenti in italiano, prefisso `wvi_`. Struttura:

```
wvoce-iscrizioni.php   intestazione, caricamento dei moduli, disattivazione
uninstall.php          eliminazione: impostazioni, stato e giro programmato
inc/impostazioni.php   impostazioni generali e dei collegamenti, testi con segnaposto, importazione
inc/campi.php          valori dei campi Forminator, struttura dei moduli, codice fiscale
inc/archivio.php       query mirate sulle tabelle di Forminator: ricerca del codice fiscale, iscrizioni da inviare
inc/soci.php           anno sociale, iscrizioni di una persona, righe del foglio dei soci
inc/corsi.php          corsi scelti, righe del foglio dei corsi, corrispondenza corso → tab
inc/fogli.php          invio ai fogli: punto raggiunto, sospese, chiamate all'app web, cron
inc/moduli.php         regole, passaggio ai corsi, "Recupera i tuoi dati", script e stile sul sito
inc/admin.php          pannello Strumenti → Iscrizioni
assets/                script e stile delle parti aggiunte ai moduli (colori nelle variabili --wvi-*)
apps-script/           script per i fogli dei soci e dei corsi
```
