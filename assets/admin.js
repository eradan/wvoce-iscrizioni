/* wvoce-iscrizioni, pannello Strumenti → Iscrizioni: copia degli script Apps Script, conferma dei pulsanti
 * con data-wvi-conferma. */
(function () {
	'use strict';

	document.querySelectorAll('[data-wvi-copia]').forEach(function (b) {
		b.addEventListener('click', function () {
			var area = document.getElementById(b.getAttribute('data-wvi-copia'));
			var nota = b.parentNode.querySelector('.wvi-copiato');
			var fatto = function () { nota.textContent = 'Copiato.'; };
			var aMano = function () {
				area.focus();
				area.select();
				nota.textContent = document.execCommand('copy') ? 'Copiato.' : 'Testo selezionato: Ctrl+C per copiarlo.';
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(area.value).then(fatto, aMano);
			} else {
				aMano();
			}
		});
	});

	document.querySelectorAll('[data-wvi-conferma]').forEach(function (b) {
		b.addEventListener('click', function (e) {
			if (!window.confirm(b.getAttribute('data-wvi-conferma'))) {
				e.preventDefault();
			}
		});
	});
})();
