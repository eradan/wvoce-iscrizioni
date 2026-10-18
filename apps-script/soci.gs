/**
 * wvoce-iscrizioni: riceve dal sito le iscrizioni a socio (modulo Forminator dei soci) e le scrive nel
 * foglio dei soci, nel tab dell'anno sociale (prefisso scelto nel sito + anno: "Soci 2026-2027").
 *
 * - Ogni valore va nella colonna con la stessa intestazione (riga 1): le colonne si possono spostare
 *   o aggiungere, e quelle che il sito non manda restano della segreteria.
 * - Se il tab dell'anno non c'è lo crea, con le intestazioni del tab più recente con lo stesso prefisso.
 * - La colonna dei riferimenti ("Rif.", numero dell'iscrizione sul sito) evita i doppioni: una riga già
 *   scritta non viene ripetuta, anche se il sito la rimanda.
 * - Lo script scrive solo nelle colonne del sito e sotto l'ultima riga con dati in quelle colonne: le
 *   colonne della segreteria (caselle, formule riempite in anticipo) restano intatte e non spingono le
 *   iscrizioni in fondo.
 * - Prefisso dei tab e nome della colonna dei riferimenti li manda il sito a ogni chiamata: lo script è
 *   lo stesso per ogni sito e non va modificato.
 *
 * Installazione (una volta), istruzioni complete nel sito: Strumenti → Iscrizioni → Collegamenti.
 * 1. In Google Drive: Nuovo → Altro → Google Apps Script (progetto autonomo), oppure dal foglio:
 *    Estensioni → Apps Script. Incolla questo file così com'è al posto del codice che c'è, salva.
 * 2. Scegli la funzione "creaToken" in alto e premi Esegui (la prima volta chiede le autorizzazioni):
 *    nel registro compare il codice segreto da mettere nel sito.
 * 3. Esegui il deployment → Nuovo deployment → tipo "App web"; Esegui come: me; Chi ha accesso:
 *    chiunque. L'URL dell'app web (finisce con /exec) va nel sito.
 * 4. L'ID del foglio va nel sito: lo script lo riceve a ogni chiamata. Basta che l'account che pubblica
 *    lo script sia editor del foglio, non serve esserne proprietari.
 * Se modifichi questo codice: Esegui il deployment → Gestisci deployment → modifica → Nuova versione
 * (URL e codice restano gli stessi).
 */

// Lasciare vuoto: l'ID del foglio lo manda il sito a ogni chiamata. Serve solo se il sito non lo indica;
// per uno script aperto dal foglio (Estensioni → Apps Script) vale comunque il foglio stesso.
var ID_FOGLIO = '';

// Valori predefiniti: il sito manda i suoi a ogni chiamata (impostazioni_).
var PREFISSO = 'Soci ';
var RIF = 'Rif.';

/** Nomi scelti nel sito per questa chiamata. */
function impostazioni_(dati) {
  if (dati.prefisso) {
    PREFISSO = String(dati.prefisso);
  }
  if (dati.rif) {
    RIF = String(dati.rif);
  }
}

function doPost(e) {
  var dati;
  try {
    dati = JSON.parse(e.postData.contents);
  } catch (err) {
    return risposta_({ ok: false, errore: 'richiesta non leggibile' });
  }
  var token = PropertiesService.getScriptProperties().getProperty('TOKEN');
  if (!token || dati.token !== token) {
    return risposta_({ ok: false, errore: 'codice segreto sbagliato' });
  }
  impostazioni_(dati);
  var lock = LockService.getScriptLock();
  if (!lock.tryLock(30000)) {
    return risposta_({ ok: false, errore: 'foglio occupato, riprovo dopo' });
  }
  try {
    var ss = foglio_(dati);
    var fatti = [];
    var stati = {};
    (dati.righe || []).forEach(function (riga) {
      if (!riga[RIF]) {
        return;
      }
      scriviRiga_(tabAnno_(ss, riga._tab), riga, stati);
      fatti.push(String(riga[RIF]));
    });
    SpreadsheetApp.flush();
    return risposta_({ ok: true, foglio: ss.getName(), rif: fatti });
  } catch (err) {
    return risposta_({ ok: false, errore: String(err && err.message || err) });
  } finally {
    lock.releaseLock();
  }
}

/** Il tab dell'anno sociale; se manca lo crea accanto al più recente, con le sue intestazioni. */
function tabAnno_(ss, nome) {
  var ammessi = new RegExp('^' + PREFISSO.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\d{4}(-\\d{4})?$');
  if (!ammessi.test(nome || '')) {
    throw new Error('nome del tab non valido: ' + nome);
  }
  var tab = ss.getSheetByName(nome);
  if (tab) {
    return tab;
  }
  var modello = ss.getSheets()
    .filter(function (s) { return s.getName().indexOf(PREFISSO) === 0; })
    .sort(function (a, b) { return a.getName() < b.getName() ? 1 : -1; })[0];
  if (!modello) {
    throw new Error('nessun tab "' + PREFISSO + '…" da cui copiare le intestazioni');
  }
  tab = ss.insertSheet(nome, modello.getIndex() - 1);
  var colonne = modello.getLastColumn();
  modello.getRange(1, 1, 1, colonne).copyTo(tab.getRange(1, 1, 1, colonne));
  tab.setFrozenRows(1);
  return tab;
}

/**
 * Intestazioni, Rif. già scritti e prima riga libera di un tab, letti una volta per chiamata e tenuti
 * in "stati" (per id del tab). Se manca la colonna "Rif." la aggiunge.
 */
function statoTab_(tab, stati, riga) {
  var id = tab.getSheetId();
  if (stati[id]) {
    return stati[id];
  }
  var colonne = Math.max(tab.getLastColumn(), 1);
  var intestazioni = tab.getRange(1, 1, 1, colonne).getValues()[0].map(function (h) {
    return String(h).trim();
  });
  var colRif = indice_(intestazioni, RIF);
  if (colRif < 0) {
    colRif = intestazioni.length;
    tab.getRange(1, colRif + 1).setValue(RIF);
    intestazioni.push(RIF);
  }
  var dati = righeTab_(tab, intestazioni.length);
  var presenti = {};
  dati.forEach(function (r) {
    if (r[colRif] !== '') {
      presenti[String(r[colRif])] = true;
    }
  });
  stati[id] = {
    intestazioni: intestazioni,
    presenti: presenti,
    prossima: prossimaRiga_(dati, colonneSito_(intestazioni, riga))
  };
  return stati[id];
}

/**
 * Scrive una riga sotto le altre, valore per valore nella colonna con la stessa intestazione. Le
 * colonne senza un valore dal sito non vengono toccate: si scrive a tratti di celle contigue.
 */
function scriviRiga_(tab, riga, stati) {
  var stato = statoTab_(tab, stati, riga);
  var rif = String(riga[RIF]);
  if (stato.presenti[rif]) {
    return;
  }
  var valori = [];
  Object.keys(riga).forEach(function (k) {
    var col = k === '_tab' ? -1 : indice_(stato.intestazioni, k);
    var valore = testo_(riga[k]);
    if (col >= 0 && valore !== '') {
      valori[col] = valore;
    }
  });
  var nuova = stato.prossima++;
  for (var inizio = 0; inizio < valori.length; inizio++) {
    if (valori[inizio] === undefined) {
      continue;
    }
    var fine = inizio;
    while (fine + 1 < valori.length && valori[fine + 1] !== undefined) {
      fine++;
    }
    // Come testo: codici fiscali, telefoni e CAP restano come sono scritti (niente zeri persi).
    tab.getRange(nuova, inizio + 1, 1, fine - inizio + 1).setNumberFormat('@')
      .setValues([valori.slice(inizio, fine + 1)]);
    inizio = fine;
  }
  stato.presenti[rif] = true;
}

/** Valori delle righe sotto le intestazioni (vuoto se il tab ha solo la riga 1). */
function righeTab_(tab, colonne) {
  var ultima = tab.getLastRow();
  return ultima > 1 ? tab.getRange(2, 1, ultima - 1, colonne).getValues() : [];
}

/** Indici delle colonne che il sito scrive: le intestazioni che corrispondono alle voci della riga. */
function colonneSito_(intestazioni, riga) {
  var colonne = [];
  Object.keys(riga).forEach(function (k) {
    var col = k === '_tab' ? -1 : indice_(intestazioni, k);
    if (col >= 0) {
      colonne.push(col);
    }
  });
  return colonne;
}

/**
 * Numero della riga in cui scrivere: sotto l'ultima con un valore in una colonna del sito. Le colonne
 * della segreteria riempite in anticipo non contano; le righe scritte a mano nelle colonne del sito
 * sì, e restano intatte.
 */
function prossimaRiga_(dati, colonne) {
  for (var i = dati.length - 1; i >= 0; i--) {
    for (var j = 0; j < colonne.length; j++) {
      var v = dati[i][colonne[j]];
      if (v !== '' && v !== null && v !== undefined) {
        return i + 3;
      }
    }
  }
  return 2;
}

/**
 * Valore come testo puro. I dati arrivano da un modulo pubblico: un nome che comincia con "="
 * diventerebbe una formula (che può leggere altri tab o chiamare indirizzi esterni con IMPORTXML).
 * L'apostrofo davanti, che il foglio non mostra, lo tiene testo anche se il formato della cella
 * venisse cambiato. Gli altri segni (+, -, @) nel foglio Google non avviano formule: i numeri di
 * telefono "+39…" restano come sono.
 */
function testo_(valore) {
  var s = valore === null || valore === undefined ? '' : String(valore);
  return /^\s*=/.test(s) ? "'" + s : s;
}

function indice_(intestazioni, nome) {
  for (var i = 0; i < intestazioni.length; i++) {
    if (uguali_(intestazioni[i], nome)) {
      return i;
    }
  }
  return -1;
}

/** Intestazioni uguali senza badare a maiuscole e spazi ("CODICE FISCALE" = "Codice Fiscale"). */
function uguali_(a, b) {
  return String(a).trim().toLowerCase() === String(b).trim().toLowerCase();
}

/**
 * Il foglio su cui scrivere: l'ID mandato dal sito, altrimenti ID_FOGLIO, altrimenti il foglio a cui
 * lo script è collegato. Il codice segreto resta l'unica chiave: chi lo conosce può indicare un
 * qualsiasi foglio modificabile dall'account che ha pubblicato lo script, quindi va custodito.
 */
function foglio_(dati) {
  var id = String(dati.foglio || ID_FOGLIO || '').trim();
  if (!id) {
    var attivo = SpreadsheetApp.getActiveSpreadsheet();
    if (attivo) {
      return attivo;
    }
    throw new Error('manca l\'ID del foglio: va indicato nel sito, in Strumenti → Iscrizioni → Collegamenti');
  }
  try {
    return SpreadsheetApp.openById(id);
  } catch (err) {
    throw new Error('foglio non raggiungibile (' + id + '): controlla il foglio indicato nel sito e che '
      + 'l\'account che ha pubblicato lo script possa modificarlo');
  }
}

function risposta_(oggetto) {
  return ContentService.createTextOutput(JSON.stringify(oggetto)).setMimeType(ContentService.MimeType.JSON);
}

/** Crea il codice segreto (da eseguire una volta) e lo mostra nel registro di esecuzione. */
function creaToken() {
  var token = Utilities.getUuid().replace(/-/g, '') + Utilities.getUuid().replace(/-/g, '');
  PropertiesService.getScriptProperties().setProperty('TOKEN', token);
  Logger.log('Codice segreto: ' + token);
}
