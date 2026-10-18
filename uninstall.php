<?php
/**
 * Eliminazione del plugin (Plugin → Elimina): via impostazioni, stato dell'invio ai fogli e giro
 * programmato. Le iscrizioni restano in Forminator e nei fogli Google. Disattivare il plugin, invece,
 * non cancella niente.
 *
 * @package WvoceIscrizioni
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wvi_generali' );
foreach ( array( 'soci', 'corsi' ) as $wvi_id ) {
	foreach ( array( 'impostazioni', 'ultimo', 'stato', 'sospese', 'inizio' ) as $wvi_voce ) {
		delete_option( "wvi_{$wvi_id}_{$wvi_voce}" );
	}
}
delete_transient( 'wvi_corsi_mappa' );
wp_clear_scheduled_hook( 'wvi_sync' );
