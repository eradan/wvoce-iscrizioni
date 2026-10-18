<?php
/**
 * Scheda Passaggio e recupero: passaggio dal modulo dei soci a quello dei corsi, "Recupera i tuoi dati",
 * compilazione automatica del browser.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contenuto della scheda.
 */
function wvi_admin_funzioni() {
	$soci  = wvi_config( 'soci' );
	$corsi = wvi_config( 'corsi' );

	wvi_admin_apri( 'corsi', 'funzioni' );
	?>
	<h2>Dal modulo dei soci a quello dei corsi</h2>
	<p class="wvi-largo">Chi nel modulo dei soci sceglie di iscriversi anche a un corso arriva alla pagina dei corsi con il modulo già compilato. In Forminator, nel modulo dei soci: un comportamento dopo l'invio "Reindirizza" verso la pagina dei corsi (Collegamenti → Impostazioni generali), con la condizione voluta. I campi ripresi devono avere lo stesso id nei due moduli.</p>
	<?php
	wvi_admin_tabella( 'corsi', 'funzioni' );
	if ( $corsi['passaggio'] ) {
		wvi_admin_avviso_assenti( $soci['form'], $corsi['passaggio_campi'], 'Campi da riportare, nel modulo dei soci' );
		wvi_admin_avviso_assenti( $corsi['form'], array_merge( $corsi['passaggio_campi'], $corsi['passaggio_caselle'] ), 'Campi e caselle da riportare, nel modulo dei corsi' );
	}
	wvi_admin_elenco_campi( $corsi['form'] );
	?>
	<p><button class="button button-primary" name="wvi_azione" value="regole">Salva</button></p>
	</form>

	<?php wvi_admin_apri( 'soci', 'funzioni' ); ?>
	<h2>Recupera i tuoi dati</h2>
	<p class="wvi-largo">Sotto il codice fiscale del modulo dei soci, un link manda all'indirizzo email dell'ultima iscrizione un link personale che riapre il modulo già compilato. A video il messaggio è sempre lo stesso, che il codice fiscale sia in archivio o no; agli amministratori loggati compare l'esito vero. Servono il campo del codice fiscale e quello dell'email (scheda <a href="<?php echo esc_url( wvi_admin_url( 'campi' ) ); ?>">Campi e regole</a>) e la pagina del modulo dei soci. Anteprima per gli amministratori: <code>?wvi-socio-prova=</code> + numero dell'iscrizione, sulla pagina del modulo.</p>
	<?php
	wvi_admin_tabella( 'soci', 'funzioni' );
	if ( $soci['recupera'] ) {
		wvi_admin_avviso_assenti( $soci['form'], $soci['recupera_campi'], 'Campi da recuperare' );
	}
	wvi_admin_elenco_campi( $soci['form'] );
	?>
	<p><button class="button button-primary" name="wvi_azione" value="regole">Salva</button></p>
	</form>

	<?php wvi_admin_apri( '', 'funzioni' ); ?>
	<h2>Compilazione automatica del browser</h2>
	<p class="wvi-largo">Valori dell'attributo <code>autocomplete</code> per i campi dei moduli dei soci e dei corsi, così il browser propone i dati salvati: <code>given-name</code>, <code>family-name</code>, <code>email</code>, <code>tel</code>, <code>street-address</code>, <code>postal-code</code>… e <code>off</code> per i campi da non riempire (per esempio il luogo di nascita, che altrimenti riceverebbe la città di residenza).</p>
	<?php wvi_admin_tabella( 'generali', 'funzioni' ); ?>
	<p><button class="button button-primary" name="wvi_azione" value="generali">Salva</button></p>
	</form>
	<?php
}
