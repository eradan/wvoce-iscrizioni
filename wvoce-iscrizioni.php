<?php
/**
 * Plugin Name:       wvoce-iscrizioni
 * Description:       Iscrizioni a socio e ai corsi con Forminator: codice fiscale controllato, socio richiesto per i corsi e unico per anno sociale, passaggio dal modulo dei soci a quello dei corsi, "Recupera i tuoi dati" con conferma via email, copia automatica delle iscrizioni nei fogli Google. Tutto si configura in Strumenti → Iscrizioni.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  forminator
 * Author:            Ivan Ricotti
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wvoce-iscrizioni
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

define( 'WVI_VERSIONE', '1.0.0' );
define( 'WVI_DIR', plugin_dir_path( __FILE__ ) );
define( 'WVI_URL', plugin_dir_url( __FILE__ ) );

require WVI_DIR . 'inc/impostazioni.php';
require WVI_DIR . 'inc/campi.php';
require WVI_DIR . 'inc/soci.php';
require WVI_DIR . 'inc/corsi.php';
require WVI_DIR . 'inc/fogli.php';
require WVI_DIR . 'inc/moduli.php';
if ( is_admin() ) {
	require WVI_DIR . 'inc/admin.php';
}

/**
 * Il plugin lavora? No se un tema gestisce ancora da sé le stesse iscrizioni (il tema VivaVoce prima
 * della 1.0.0): due controlli e due invii agli stessi fogli. Il pannello lo segnala.
 */
function wvi_attivo() {
	return ! function_exists( 'vv_forminator_check_cf' );
}

/**
 * Alla disattivazione si ferma il giro verso i fogli; impostazioni e stato restano.
 */
function wvi_disattiva() {
	wp_clear_scheduled_hook( 'wvi_sync' );
}
register_deactivation_hook( __FILE__, 'wvi_disattiva' );
