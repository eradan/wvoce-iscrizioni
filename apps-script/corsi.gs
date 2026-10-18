/**
 * wvoce-iscrizioni: riceve dal sito le iscrizioni ai corsi (modulo Forminator dei corsi) e le scrive nel
 * foglio dei corsi, nel tab del corso scelto.
 *
 * - Il tab di ogni corso si legge dal tab di configurazione del foglio (nome scelto nel sito, di solito
 *   "configurazioni"): riga 1 intestazioni (Tab, Corso), dalla riga 2 in colonna A il nome del tab e in
 *   colonna B il nome esteso del corso, cioè il testo dell'opzione del modulo. Maiuscole, accenti e
 *   spazi doppi contano poco. Per un corso nuovo basta un tab e una riga lì: lo script resta com'è.
 * - Ogni valore va nella colonna con la stessa intestazione (riga 1). Le altre colonne sono della
 *   segreteria: lo script le lascia intatte e scrive solo dove ha un valore.
 * - Un tab del corso ancora vuoto riceve le intestazioni del sito: riferimento, poi i campi del modulo.
 *   Un tab indicato per più corsi riceve anche la colonna del corso, così chi ne sceglie due ha due righe.
 * - Le righe nuove vanno sotto l'ultima con dati del sito: le colonne che la segreteria riempie in
 *   anticipo (caselle, formule) non spingono le iscrizioni in fondo, e le righe scritte a mano restano.
 * - Un corso assente dal tab di configurazione, o con un tab inesistente, va nel tab di riserva (nome
 *   scelto nel sito, di solito "Da smistare"), creato con le stesse intestazioni più il corso.
 * - La colonna dei riferimenti ("Rif.", numero dell'iscrizione sul sito) evita i doppioni. Chi sceglie
 *   due corsi ha una riga in ciascun tab, con lo stesso riferimento.
 * - Nomi dei tab speciali e delle colonne li manda il sito a ogni chiamata: lo script è lo stesso per
 *   ogni sito e non va modificato.
 *
 * Installazione (una volta), istruzioni complete nel sito: Strumenti → Iscrizioni → Collegamenti.
 * 1. In Google Drive: Nuovo → Altro → Google Apps Script (progetto autonomo), oppure dal foglio:
 *    Estensioni → Apps Script. Incolla questo file così com'è al posto del codice che c'è, salva.
 * 2. Scegli la funzione "creaToken" in alto e premi Esegui (la prima volta chiede le autorizzazioni):
 *    nel registro compare il codice segreto da mettere nel sito.
 * 3. Esegui il deployment → Nuovo deployment → tipo "App web"; Esegui come: me; Chi ha accesso:
 *    chiunque. L'URL dell'app web (finisce con /exec) va nel sito.
 * 4. L'ID del foglio va nel sito: lo script lo riceve a ogni chiamata, e per passare da una copia di
 *    prova al foglio vero basta cambiarlo lì.
 * 5. Lo script scrive con l'account di chi lo pubblica: il foglio può restare condiviso solo con
 *    account nominativi, senza "Chiunque abbia il link".
 * Se modifichi questo codice: Esegui il deployment → Gestisci deployment → modifica → Nuova versione
 * (URL e codice restano gli stessi).
 */

// Lasciare vuoto: l'ID del foglio lo manda il sito a ogni chiamata. Serve solo se il sito non lo indica;
// per uno script aperto dal foglio (Estensioni → Apps Script) vale comunque il foglio stesso.
var ID_FOGLIO = '';

// Valori predefiniti: il sito manda i suoi a ogni chiamata (impostazioni_).
var CONFIGURAZIONI = 'configurazioni';
var DA_SMISTARE = 'Da smistare';
var CORSO = 'Corso';
var RIF = 'Rif.';

/** Nomi scelti nel sito per questa chiamata. */
function impostazioni_(dati) {
  if (dati.configurazioni) {
    CONFIGURAZIONI = String(dati.configurazioni);
  }
  if (dati.riserva) {
    DA_SMISTARE = String(dati.riserva);
  }
  if (dati.corso) {
    CORSO = String(dati.corso);
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
    var righe = dati.righe || [];
    var conf = leggiConfigurazioni_(ss, righe.length > 0);
    if (dati.azione === 'mappa') {
      return risposta_({ ok: true, foglio: ss.getName(), mappa: conf.originale, tab: nomiTab_(ss) });
    }
    var fatti = [];
    var stati = {};
    righe.forEach(function (riga) {
      if (!riga[RIF]) {
        return;
      }
      scriviRiga_(tabCorso_(ss, conf, riga), riga, stati);
      if (fatti.indexOf(String(riga[RIF])) < 0) {
        fatti.push(String(riga[RIF]));
      }
    });
    SpreadsheetApp.flush();
    return risposta_({ ok: true, foglio: ss.getName(), rif: fatti });
  } catch (err) {
    return risposta_({ ok: false, errore: String(err && err.message || err) });
  } finally {
    lock.releaseLock();
  }
}

/**
 * Il tab "configurazioni": corso (normalizzato) → tab, più la stessa mappa con i nomi originali. Se
 * manca e ci sono righe da scrivere lo crea con le intestazioni: finché resta vuoto le righe vanno
 * in "Da smistare".
 */
function leggiConfigurazioni_(ss, crea) {
  var conf = { mappa: {}, originale: {}, corsi: {} };
  var tab = ss.getSheetByName(CONFIGURAZIONI);
  if (!tab && !crea) {
    return conf;
  }
  if (!tab) {
    tab = ss.insertSheet(CONFIGURAZIONI, ss.getNumSheets());
    tab.getRange(1, 1, 1, 2).setValues([['Tab', 'Corso']]).setFontWeight('bold');
    tab.setFrozenRows(1);
  }
  var ultima = tab.getLastRow();
  if (ultima < 2) {
    return conf;
  }
  tab.getRange(2, 1, ultima - 1, 2).getValues().forEach(function (r) {
    var nome = String(r[0]).trim();
    var corso = String(r[1]).trim();
    if (!nome || !corso) {
      return;
    }
    conf.mappa[normalizza_(corso)] = nome;
    conf.originale[corso] = nome;
    conf.corsi[nome] = (conf.corsi[nome] || 0) + 1;
  });
  return conf;
}

/** Nomi dei tab del foglio. */
function nomiTab_(ss) {
  return ss.getSheets().map(function (s) {
    return s.getName();
  });
}

/**
 * Il tab del corso secondo "configurazioni". Se esiste ma è vuoto riceve le intestazioni del sito;
 * se il corso manca o il tab non esiste la riga va in "Da smistare", creato la prima volta. "Da
 * smistare" e i tab indicati per più corsi hanno anche la colonna "Corso": senza, la seconda riga
 * della stessa iscrizione sembrerebbe un doppione.
 */
function tabCorso_(ss, conf, riga) {
  var nome = conf.mappa[normalizza_(String(riga[CORSO] || ''))];
  var tab = nome && nome !== DA_SMISTARE && nome !== CONFIGURAZIONI ? ss.getSheetByName(nome) : null;
  var conCorso = !tab || conf.corsi[nome] > 1;
  if (!tab) {
    tab = ss.getSheetByName(DA_SMISTARE) || ss.insertSheet(DA_SMISTARE, ss.getNumSheets());
  }
  if (tab.getLastColumn() === 0) {
    scriviIntestazioni_(tab, riga, conCorso);
  } else if (conCorso) {
    var intestazioni = leggiIntestazioni_(tab);
    if (indice_(intestazioni, CORSO) < 0) {
      tab.getRange(1, primaLibera_(intestazioni) + 1).setValue(CORSO).setFontWeight('bold');
    }
  }
  return tab;
}

/**
 * Intestazioni di un tab vuoto dalle voci della riga, nell'ordine del sito (Rif. per prima); "Corso"
 * solo in "Da smistare".
 */
function scriviIntestazioni_(tab, riga, conCorso) {
  var nomi = Object.keys(riga).filter(function (k) {
    return k !== '_tab' && (conCorso || k !== CORSO);
  });
  tab.getRange(1, 1, 1, nomi.length).setValues([nomi]).setFontWeight('bold');
  tab.setFrozenRows(1);
}

/**
 * Intestazioni e righe già scritte di un tab, lette una volta per chiamata e tenute in "stati"
 * (per id del tab). Se manca la colonna "Rif." la inserisce in colonna A. Chiavi contro i doppioni
 * da Rif. e Corso; prima riga libera dopo l'ultima con dati nelle colonne del sito.
 */
function statoTab_(tab, stati, riga) {
  var id = tab.getSheetId();
  if (stati[id]) {
    return stati[id];
  }
  var intestazioni = leggiIntestazioni_(tab);
  var colRif = indice_(intestazioni, RIF);
  if (colRif < 0) {
    tab.insertColumnBefore(1);
    tab.getRange(1, 1).setValue(RIF).setFontWeight('bold');
    intestazioni.unshift(RIF);
    colRif = 0;
  }
  var colCorso = indice_(intestazioni, CORSO);
  var dati = righeTab_(tab, intestazioni.length);
  var chiavi = {};
  dati.forEach(function (r) {
    if (r[colRif] !== '') {
      chiavi[chiave_(r[colRif], colCorso < 0 ? '' : r[colCorso], colCorso)] = true;
    }
  });
  stati[id] = {
    intestazioni: intestazioni,
    colCorso: colCorso,
    chiavi: chiavi,
    prossima: prossimaRiga_(dati, colonneSito_(intestazioni, riga))
  };
  return stati[id];
}

/** Chiave contro i doppioni: Rif. + Corso in un tab con la colonna "Corso", altrove il solo Rif. */
function chiave_(rif, corso, colCorso) {
  return String(rif) + (colCorso < 0 ? '' : '\u0001' + String(corso || ''));
}

/**
 * Scrive una riga sotto le altre, valore per valore nella colonna con la stessa intestazione. Le
 * colonne senza un valore dal sito non vengono toccate: si scrive a tratti di celle contigue, una
 * chiamata per tratto. In un tab con la colonna "Corso" il doppione è la stessa coppia Rif. + Corso,
 * altrove lo stesso Rif.
 */
function scriviRiga_(tab, riga, stati) {
  var stato = statoTab_(tab, stati, riga);
  var chiave = chiave_(riga[RIF], riga[CORSO], stato.colCorso);
  if (stato.chiavi[chiave]) {
    return;
  }
  var valori = [];
  Object.keys(riga).forEach(function (k) {
    if (k === '_tab') {
      return;
    }
    var col = indice_(stato.intestazioni, k);
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
    // Come testo: codici fiscali e telefoni restano come sono scritti (niente zeri persi).
    tab.getRange(nuova, inizio + 1, 1, fine - inizio + 1).setNumberFormat('@')
      .setValues([valori.slice(inizio, fine + 1)]);
    inizio = fine;
  }
  stato.chiavi[chiave] = true;
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
 * della segreteria riempite in anticipo (caselle, formule) non contano; le righe scritte a mano nelle
 * colonne del sito sì, e restano intatte.
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

/** Intestazioni della riga 1, ripulite dagli spazi. */
function leggiIntestazioni_(tab) {
  var colonne = Math.max(tab.getLastColumn(), 1);
  return tab.getRange(1, 1, 1, colonne).getValues()[0].map(function (h) {
    return String(h).trim();
  });
}

/** Prima colonna libera dopo l'ultima intestazione compilata (da 0). */
function primaLibera_(intestazioni) {
  for (var i = intestazioni.length - 1; i >= 0; i--) {
    if (intestazioni[i] !== '') {
      return i + 1;
    }
  }
  return 0;
}

/**
 * Valore come testo puro. I dati arrivano da un modulo pubblico: un nome che comincia con "="
 * diventerebbe una formula. L'apostrofo davanti, che il foglio non mostra, lo tiene testo.
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
 * Testo di un corso ridotto per il confronto: senza accenti, minuscole, spazi compressi. Il sito fa
 * lo stesso (wvi_corsi_normalizza).
 */
function normalizza_(testo) {
  return String(testo || '').normalize('NFD').replace(/[̀-ͯ]/g, '')
    .toLowerCase().replace(/\s+/g, ' ').trim();
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
