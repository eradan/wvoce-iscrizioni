<?php
/**
 * Scheda Collegamenti: impostazioni generali e, per ogni foglio, modulo, app web, foglio, codice e
 * partenza, con la guida.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contenuto della scheda.
 */
function wvi_admin_collegamenti() {
	wvi_admin_apri( '', 'collegamenti' );
	?>
	<h2>Impostazioni generali</h2>
	<?php wvi_admin_tabella( 'generali', 'collegamenti' ); ?>
	<p><button class="button button-primary" name="wvi_azione" value="generali">Salva le impostazioni generali</button></p>
	</form>

	<p class="wvi-largo wvi-staccato">Le iscrizioni dei moduli Forminator arrivano da sole nei fogli Google, ogni 15 minuti, attraverso un'app web di Apps Script pubblicata dal foglio. Per ogni foglio servono l'ID del foglio, l'indirizzo dell'app web e il suo codice segreto. La guida è in fondo alla pagina.</p>
	<?php
	foreach ( array_keys( wvi_collegamenti() ) as $id ) :
		$c = wvi_config( $id );
		wvi_admin_apri( $id, 'collegamenti' );
		?>
		<h2 class="wvi-titolo"><?php echo esc_html( $c['titolo'] ); ?> <span class="wvi-nota">— <?php echo wvi_configurato( $id ) ? '<strong>attivo</strong>' : 'spento'; ?></span></h2>
		<?php wvi_admin_tabella( $id, 'collegamenti' ); ?>
		<p>
			<button class="button button-primary" name="wvi_azione" value="salva">Salva</button>
			<button class="button" name="wvi_azione" value="verifica">Salva e verifica il collegamento</button>
		</p>
		</form>
	<?php endforeach; ?>

	<hr class="wvi-separatore">
	<h2>Guida</h2>
	<ol class="wvi-largo">
		<li><strong>Script nel Drive.</strong> In Google Drive: Nuovo → Altro → Google Apps Script (progetto autonomo, consigliato per i fogli in un Drive condiviso). In alternativa, dal foglio: Estensioni → Apps Script. Incollare al posto del codice esistente il testo della scheda <a href="<?php echo esc_url( wvi_admin_url( 'script' ) ); ?>">Script Apps Script</a>. Lo script è lo stesso per ogni sito: nomi dei tab e delle colonne speciali li manda il sito a ogni chiamata.</li>
		<li><strong>Foglio.</strong> L'indirizzo del foglio, copiato dalla barra del browser, o il solo ID. Basta che l'account che pubblica lo script possa modificarlo.</li>
		<li><strong>Codice segreto.</strong> Nell'editor, scegliere la funzione <code>creaToken</code> e premere Esegui. La prima volta Google chiede le autorizzazioni (Avanzate → Vai a … → Consenti). Nel registro di esecuzione compare il codice di 64 caratteri da incollare qui.</li>
		<li><strong>Indirizzo.</strong> Esegui il deployment → Nuovo deployment → tipo App web; Esegui come: Me; Chi ha accesso: Chiunque. L'URL termina con <code>/exec</code>.</li>
		<li><strong>Campi e colonne.</strong> Nella scheda <a href="<?php echo esc_url( wvi_admin_url( 'campi' ) ); ?>">Campi e regole</a>: quale campo è il codice fiscale, quali colonne scrivere nel foglio e come si chiamano i tab.</li>
		<li><strong>Verifica.</strong> "Salva e verifica il collegamento" manda al foglio una richiesta vuota: la risposta riporta il nome del foglio raggiunto senza scrivere nulla. Nelle schede d'invio la prova a secco mostra cosa partirebbe.</li>
		<li><strong>Script modificato.</strong> Dopo aver incollato una versione nuova: Esegui il deployment → Gestisci deployment → matita → Versione: Nuova versione. URL, codice e foglio restano gli stessi.</li>
	</ol>
	<h3>Se il deployment risponde "Non disponi dell'autorizzazione necessaria"</h3>
	<ul class="wvi-largo wvi-punti">
		<li>Script collegato a un foglio in un Drive condiviso: in Dettagli progetto compare "Contenitore". Rimedio: progetto autonomo.</li>
		<li>Più account Google aperti nel browser (l'indirizzo dell'editor contiene <code>/u/1/</code>): una finestra anonima con un solo account.</li>
		<li>Nel dominio Workspace manca la voce "Chiunque" in "Chi ha accesso": serve l'amministratore del dominio.</li>
	</ul>
	<?php
}
