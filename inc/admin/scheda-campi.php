<?php
/**
 * Scheda Campi e regole: per ogni collegamento campi del modulo, regole, messaggi, tab e colonne del
 * foglio, con l'avviso sui campi indicati che il modulo non ha.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contenuto della scheda.
 */
function wvi_admin_campi() {
	foreach ( array_keys( wvi_collegamenti() ) as $id ) :
		$c = wvi_config( $id );
		wvi_admin_apri( $id, 'campi' );
		?>
		<h2 class="wvi-titolo"><?php echo esc_html( $c['titolo'] ); ?> <span class="wvi-nota">— modulo n. <?php echo (int) $c['form']; ?></span></h2>
		<?php if ( ! $c['form'] ) : ?>
			<p>Prima scegli il modulo nella scheda <a href="<?php echo esc_url( wvi_admin_url() ); ?>">Collegamenti</a>.</p>
			</form>
			<?php
			continue;
		endif;
		$voci = array( $c['campo_cf'], 'soci' === $id ? $c['campo_email'] : $c['campo_corsi'] );
		foreach ( (array) $c['colonne'] as $campo ) {
			$voci[] = is_array( $campo ) ? (string) $campo[0] : (string) $campo;
		}
		wvi_admin_avviso_assenti( $c['form'], $voci, 'Campi e colonne' );
		if ( 'corsi' === $id && $c['richiede_socio'] && ! wvi_soci_cercabili() ) :
			?>
			<div class="notice notice-warning inline"><p>"Riservato ai soci" è acceso ma nel collegamento dei soci mancano il modulo o il campo del codice fiscale: nessuno risulta socio e il modulo dei corsi si ferma per tutti. Completa il collegamento dei soci (schede <a href="<?php echo esc_url( wvi_admin_url() ); ?>">Collegamenti</a> e Campi e regole) o spegni la regola.</p></div>
			<?php
		endif;
		wvi_admin_elenco_campi( $c['form'] );
		wvi_admin_tabella( $id, 'campi' );
		?>
		<p><button class="button button-primary" name="wvi_azione" value="regole">Salva</button></p>
		</form>
	<?php endforeach; ?>
	<?php
}
