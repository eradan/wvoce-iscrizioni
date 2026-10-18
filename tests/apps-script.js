/**
 * Test degli script Apps Script (apps-script/) su un foglio Google finto in memoria, senza Google.
 * Si lanciano con: node tests/apps-script.js
 */
'use strict';
// Foglio Google finto in memoria, quanto basta agli script del plugin.
const vm = require('vm'), fs = require('fs');
let nextId = 1;
class Sheet {
  constructor(ss, name) { this.ss = ss; this.name = name; this.d = []; this.id = nextId++; }
  getName() { return this.name; } getSheetId() { return this.id; }
  getIndex() { return this.ss.sheets.indexOf(this) + 1; }
  filled(v) { return v !== '' && v !== undefined && v !== null; }
  getLastRow() { let r = 0; this.d.forEach((row, i) => { if (row && row.some(v => this.filled(v))) r = i + 1; }); return r; }
  getLastColumn() { let c = 0; this.d.forEach(row => (row || []).forEach((v, j) => { if (this.filled(v)) c = Math.max(c, j + 1); })); return c; }
  get(r, c) { return ((this.d[r - 1] || [])[c - 1]) ?? ''; }
  set(r, c, v) { while (this.d.length < r) this.d.push([]); this.d[r - 1][c - 1] = v; }
  getRange(r, c, nr = 1, nc = 1) { const s = this; const rg = {
    getValues: () => Array.from({ length: nr }, (_, i) => Array.from({ length: nc }, (_, j) => s.get(r + i, c + j))),
    setValues: (v) => { v.forEach((row, i) => row.forEach((x, j) => s.set(r + i, c + j, x))); return rg; },
    setValue: (x) => { s.set(r, c, x); return rg; },
    setNumberFormat: () => rg, setFontWeight: () => rg,
    copyTo: (dst) => { rg.getValues().forEach((row, i) => row.forEach((x, j) => dst.sheet.set(dst.r + i, dst.c + j, x))); },
    sheet: s, r, c }; return rg; }
  insertColumnBefore(c) { this.d.forEach(row => row && row.splice(c - 1, 0, '')); }
  setFrozenRows() {}
}
class SS {
  constructor(name) { this.name = name; this.sheets = []; }
  getName() { return this.name; } getSheets() { return this.sheets; } getNumSheets() { return this.sheets.length; }
  getSheetByName(n) { return this.sheets.find(s => s.name === n) || null; }
  insertSheet(n, i) { const s = new Sheet(this, n); this.sheets.splice(i ?? this.sheets.length, 0, s); return s; }
}
function carica(file, ss) {
  const ctx = {
    SpreadsheetApp: { openById: () => ss, getActiveSpreadsheet: () => ss, flush() {} },
    PropertiesService: { getScriptProperties: () => ({ getProperty: () => 't' }) },
    LockService: { getScriptLock: () => ({ tryLock: () => true, releaseLock() {} }) },
    ContentService: { MimeType: { JSON: 1 }, createTextOutput: (t) => ({ setMimeType: () => JSON.parse(t) }) },
    Utilities: {}, Logger: { log() {} }, String, Object, Math, JSON, Array, Error, RegExp,
  };
  vm.createContext(ctx); vm.runInContext(fs.readFileSync(file, 'utf8'), ctx);
  return (dati) => ctx.doPost({ postData: { contents: JSON.stringify(Object.assign({ token: 't', foglio: 'x' }, dati)) } });
}

const R = require('path').join(__dirname, '..', 'apps-script') + '/';
let falliti = 0, totale = 0;
const ok = (n, a, b) => { totale++; if (JSON.stringify(a) !== JSON.stringify(b)) { falliti++; console.log('FALLITO: ' + n + '\n  atteso:   ' + JSON.stringify(a) + '\n  ottenuto: ' + JSON.stringify(b)); } };
const rigaCorso = (rif, corso, nome) => ({ 'Rif.': rif, Nome: nome, Cognome: '', 'Codice Fiscale': 'RSSMRA85T10A562S', Corso: corso });

// --- Corsi ---
{
  const ss = new SS('Iscrizione Corsi'); const post = carica(R + 'corsi.gs', ss);
  const conf = ss.insertSheet('configurazioni');
  conf.getRange(1, 1, 4, 2).setValues([['Tab', 'Corso'], ['lettura', 'Lettura ott-dic'], ['lettura', 'Lettura gen-giu'], ['teatro', 'Teatro']]);
  const lettura = ss.insertSheet('lettura'); const teatro = ss.insertSheet('teatro');
  // La segreteria ha già intestato "teatro" con una colonna di caselle "Pagato" per 50 righe.
  teatro.getRange(1, 1, 1, 4).setValues([['Rif.', 'Nome', 'Codice Fiscale', 'Pagato']]);
  for (let r = 2; r <= 51; r++) teatro.set(r, 4, false);
  let esito = post({ righe: [rigaCorso('F1617-1', 'Lettura ott-dic', 'Anna'), rigaCorso('F1617-1', 'Lettura gen-giu', 'Anna'), rigaCorso('F1617-2', 'Teatro', 'Bruno')] });
  ok('corsi: risposta', { ok: true, rif: ['F1617-1', 'F1617-2'] }, { ok: esito.ok, rif: esito.rif });
  ok('corsi: due corsi nello stesso tab, due righe', 2, lettura.getLastRow() - 1);
  ok('corsi: tab condiviso, colonna Corso', true, lettura.getRange(1, 1, 1, lettura.getLastColumn()).getValues()[0].includes('Corso'));
  ok('corsi: riga sotto le intestazioni, non dopo le caselle', 'Bruno', teatro.get(2, 2));
  ok('corsi: casella della segreteria intatta', false, teatro.get(2, 4));
  post({ righe: [rigaCorso('F1617-1', 'Lettura ott-dic', 'Anna'), rigaCorso('F1617-2', 'Teatro', 'Bruno')] });
  ok('corsi: reinvio senza doppioni (lettura)', 2, lettura.getLastRow() - 1);
  ok('corsi: reinvio senza doppioni (teatro)', '', teatro.get(3, 2));
  // Iscrizione cartacea scritta a mano dalla segreteria, senza Rif.: non va coperta
  teatro.set(3, 2, 'Carla (cartacea)');
  post({ righe: [rigaCorso('F1617-3', 'Teatro', 'Dario')] });
  ok('corsi: riga a mano intatta', 'Carla (cartacea)', teatro.get(3, 2));
  ok('corsi: nuova riga dopo quella a mano', 'Dario', teatro.get(4, 2));
  post({ righe: [rigaCorso('F1617-4', 'Sconosciuto', 'Elsa')] });
  const ds = ss.getSheetByName('Da smistare');
  ok('corsi: da smistare con Corso', 'Sconosciuto', ds.get(2, ds.getRange(1, 1, 1, ds.getLastColumn()).getValues()[0].indexOf('Corso') + 1));
}

// --- Soci ---
{
  const ss = new SS('Albo Soci'); const invia = carica(R + 'soci.gs', ss);
  const post = (dati) => invia(Object.assign({ prefisso: 'DomandeAmmissione ' }, dati));
  const t = ss.insertSheet('DomandeAmmissione 2026-2027');
  t.getRange(1, 1, 1, 5).setValues([['Assicurazione', 'Rif.', 'Nome', 'CODICE FISCALE', 'Tessera']]);
  for (let r = 2; r <= 31; r++) t.set(r, 1, false);
  const riga = (rif, nome) => ({ _tab: 'DomandeAmmissione 2026-2027', 'Rif.': rif, Nome: nome, 'CODICE FISCALE': 'X' });
  post({ righe: [riga('F274-1', 'Anna'), riga('F274-2', '=IMPORTXML("x")')] });
  ok('soci: prima riga sotto le intestazioni', 'Anna', t.get(2, 3));
  ok('soci: casella Assicurazione intatta', false, t.get(2, 1));
  ok('soci: formula neutralizzata', "'=IMPORTXML(\"x\")", t.get(3, 3));
  post({ righe: [riga('F274-1', 'Anna')] });
  ok('soci: reinvio senza doppioni', '', t.get(4, 3));
  post({ righe: [{ _tab: 'DomandeAmmissione 2027-2028', 'Rif.': 'F274-9', Nome: 'Zoe', 'CODICE FISCALE': 'Y' }] });
  const nuovo = ss.getSheetByName('DomandeAmmissione 2027-2028');
  ok('soci: tab dell\'anno nuovo con le intestazioni', ['Assicurazione', 'Rif.', 'Nome', 'CODICE FISCALE', 'Tessera'], nuovo.getRange(1, 1, 1, 5).getValues()[0]);
  ok('soci: tab dell\'anno nuovo, riga 2', 'Zoe', nuovo.get(2, 3));
}
// --- Nomi scelti nel sito ---
{
  const ss = new SS('Corsi'); const post = carica(R + 'corsi.gs', ss);
  const conf = ss.insertSheet('mappa corsi');
  conf.getRange(1, 1, 2, 2).setValues([['Tab', 'Corso'], ['coro', 'Coro']]);
  const coro = ss.insertSheet('coro');
  const r = (rif, corso) => ({ 'N.': rif, Nome: 'Ugo', Laboratorio: corso });
  post({ configurazioni: 'mappa corsi', riserva: 'Altri', corso: 'Laboratorio', rif: 'N.', righe: [r('F9-1', 'Coro'), r('F9-2', 'Danza')] });
  ok('nomi del sito: corso nel suo tab', 'Ugo', coro.get(2, 2));
  ok('nomi del sito: tab di riserva', true, !!ss.getSheetByName('Altri'));
  ok('nomi del sito: nessun tab con il nome predefinito', null, ss.getSheetByName('Da smistare'));
}
{
  const ss = new SS('Soci'); const post = carica(R + 'soci.gs', ss);
  const t = ss.insertSheet('Tessere 2026');
  t.getRange(1, 1, 1, 2).setValues([['N.', 'Nome']]);
  post({ prefisso: 'Tessere ', rif: 'N.', righe: [{ _tab: 'Tessere 2027', 'N.': 'F1-1', Nome: 'Ada' }] });
  ok('nomi del sito: anno solare, tab nuovo con le intestazioni', 'Ada', ss.getSheetByName('Tessere 2027').get(2, 2));
  const esito = post({ prefisso: 'Tessere ', rif: 'N.', righe: [{ _tab: 'Altro 2027', 'N.': 'F1-2', Nome: 'Bea' }] });
  ok('nomi del sito: tab con un altro prefisso rifiutato', false, esito.ok);
}
console.log(falliti ? falliti + ' su ' + totale + ' controlli falliti.' : totale + ' controlli superati.');
process.exit(falliti ? 1 : 0);
