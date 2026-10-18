/* wvoce-iscrizioni: moduli ricompilati (passaggio soci → corsi, "Recupera i tuoi dati"), codice fiscale
 * controllato all'uscita dal campo, compilazione automatica del browser, link "Recupera i tuoi dati".
 * Dati da inc/moduli.php: window.wviModuli e, nelle pagine con un codice, #wvi-ricompila. */
(function () {
	'use strict';

	var moduli = window.wviModuli || { moduli: [], autocomplete: {} };

	// Forminator può disegnare il modulo dopo il caricamento della pagina: si riprova per qualche secondo.
	var quandoModulo = function (id, fatto) {
		var tentativi = 0;
		var cerca = function () {
			var form = id && document.getElementById('forminator-module-' + id);
			if (form) {
				fatto(form);
			} else if (++tentativi < 40) {
				setTimeout(cerca, 250);
			}
		};
		cerca();
	};

	// Modulo ricompilato. Radio per valore dell'opzione, caselle spuntate; i valori passano dagli
	// eventi, così il plugin di moduli li vede.
	var datiRicompila = document.getElementById('wvi-ricompila');
	var ricompila = null;
	if (datiRicompila) {
		try { ricompila = JSON.parse(datiRicompila.textContent); } catch (e) {}
	}
	if (ricompila) {
		quandoModulo(ricompila.form, function (form) {
			Object.keys(ricompila.campi).forEach(function (nome) {
				var valore = ricompila.campi[nome];
				form.querySelectorAll('[name="' + nome + '"]').forEach(function (input) {
					if (input.type === 'radio') {
						if (input.value === valore && !input.checked) {
							input.click();
						}
					} else if (!input.value && valore) {
						input.value = valore;
						input.dispatchEvent(new Event('input', { bubbles: true }));
						input.dispatchEvent(new Event('change', { bubbles: true }));
					}
				});
			});
			(ricompila.caselle || []).forEach(function (nome) {
				var casella = form.querySelector('input[type="checkbox"][name="' + nome + '"]');
				if (casella && !casella.checked) {
					casella.click();
				}
			});
			if (ricompila.avviso) {
				var avviso = document.createElement('p');
				avviso.className = 'wvi-avviso';
				avviso.setAttribute('role', 'status');
				avviso.textContent = ricompila.avviso;
				form.parentNode.insertBefore(avviso, form);
			}
		});
	}

	// Codice fiscale: formato e carattere di controllo, come wvi_codice_fiscale_valido().
	var cfValido = function (cf) {
		cf = cf.replace(/\s+/g, '').toUpperCase();
		if (!/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/.test(cf)) {
			return false;
		}
		var dispari = [1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23];
		var somma = 0;
		for (var i = 0; i < 15; i++) {
			var c = cf.charCodeAt(i);
			var n = c < 65 ? c - 48 : c - 65;
			somma += i % 2 === 0 ? dispari[n] : n;
		}
		return String.fromCharCode(65 + somma % 26) === cf.charAt(15);
	};
	var msgFormato = moduli.msgCf || 'Controlla il codice fiscale.';

	var campoDi = function (input) {
		return input.closest('.forminator-field') || input.parentNode;
	};

	(moduli.moduli || []).forEach(function (m) {
		quandoModulo(m.form, function (form) {
			// Compilazione automatica del browser: valori scelti nel pannello ("name-1 = given-name")
			Object.keys(moduli.autocomplete || {}).forEach(function (nome) {
				var input = form.querySelector('input[name="' + nome + '"]');
				if (input) {
					input.setAttribute('autocomplete', moduli.autocomplete[nome]);
				}
			});

			// Codice fiscale sbagliato: subito, all'uscita dal campo, invece che dopo l'invio
			var cf = m.cf && form.querySelector('input[name="' + m.cf + '"]');
			if (!cf) {
				return;
			}
			var errore = document.createElement('p');
			errore.className = 'wvi-cf-errore';
			errore.setAttribute('role', 'alert');
			errore.hidden = true;
			campoDi(cf).appendChild(errore);
			cf.addEventListener('blur', function () {
				var sbagliato = cf.value.trim() !== '' && !cfValido(cf.value);
				errore.textContent = sbagliato ? msgFormato : '';
				errore.hidden = !sbagliato;
			});
			cf.addEventListener('input', function () {
				errore.hidden = true;
			});
		});
	});

	// "Recupera i tuoi dati": un link a destra sotto il codice fiscale del modulo dei soci, non quando il
	// modulo è già stato ricompilato. L'esito va sotto la riga, a tutta larghezza.
	var rec = moduli.recupera;
	if (rec && !(ricompila && ricompila.form === rec.form)) {
		quandoModulo(rec.form, function (form) {
			var cf = form.querySelector('input[name="' + rec.cf + '"]');
			if (!cf) {
				return;
			}
			var campo = campoDi(cf);
			var invito = document.createElement('p');
			invito.className = 'wvi-recupera';
			invito.innerHTML = rec.invito;
			var link = invito.querySelector('a');
			if (!link) {
				link = document.createElement('a');
				link.textContent = 'Recupera i tuoi dati';
				invito.appendChild(link);
			}
			link.href = '#';
			link.setAttribute('role', 'button');
			link.className = 'wvi-recupera__link';
			campo.appendChild(invito);

			var riga = cf.closest('.forminator-row') || campo;
			var esito = document.createElement('p');
			esito.className = 'wvi-recupera__esito';
			esito.setAttribute('role', 'status');
			esito.hidden = true;
			riga.parentNode.insertBefore(esito, riga.nextSibling);

			var attesa = false;
			var mostra = function (testo) {
				esito.textContent = testo;
				esito.hidden = false;
			};
			link.addEventListener('click', function (e) {
				e.preventDefault();
				if (attesa) {
					return;
				}
				if (!cfValido(cf.value)) {
					mostra(msgFormato);
					cf.focus();
					return;
				}
				attesa = true;
				link.setAttribute('aria-disabled', 'true');
				var intestazioni = { 'Content-Type': 'application/json' };
				if (rec.nonce) {
					intestazioni['X-WP-Nonce'] = rec.nonce;
				}
				fetch(rec.url, {
					method: 'POST',
					headers: intestazioni,
					body: JSON.stringify({ cf: cf.value })
				}).then(function (r) {
					return r.json();
				}).then(function (d) {
					mostra(d.messaggio);
				}).catch(function () {
					mostra('Non è stato possibile inviare la richiesta: riprova fra poco, oppure compila il modulo per intero.');
				}).then(function () {
					attesa = false;
					link.removeAttribute('aria-disabled');
				});
			});
		});
	}
})();
