<?php
/**
 * Scheda Script Apps Script: il testo da incollare nell'editor, con il pulsante per copiarlo
 * (assets/admin.js).
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contenuto della scheda.
 */
function wvi_admin_script() {
	?>
	<p class="wvi-largo">Testo completo degli script da incollare nell'editor di Apps Script, al posto del codice esistente. Istruzioni nella scheda <a href="<?php echo esc_url( wvi_admin_url() ); ?>">Collegamenti</a>. Dopo un aggiornamento del plugin conviene confrontare lo script pubblicato con quello qui sotto e, se diverso, pubblicare una Nuova versione del deployment.</p>
	<?php foreach ( array_keys( wvi_collegamenti() ) as $id ) : ?>
		<?php
		$c     = wvi_config( $id );
		$testo = wvi_script( $id );
		if ( '' === $testo ) {
			continue;
		}
		?>
		<h2 class="wvi-titolo"><?php echo esc_html( $c['titolo'] ); ?> <span class="wvi-nota">— <code><?php echo esc_html( $c['script'] ); ?></code></span></h2>
		<p>
			<button type="button" class="button button-primary" data-wvi-copia="wvi-script-<?php echo esc_attr( $id ); ?>">Copia lo script</button>
			<span class="wvi-copiato" aria-live="polite"></span>
		</p>
		<textarea id="wvi-script-<?php echo esc_attr( $id ); ?>" class="large-text code wvi-script" rows="18" readonly spellcheck="false"><?php echo esc_textarea( $testo ); ?></textarea>
	<?php endforeach; ?>
	<?php
}
