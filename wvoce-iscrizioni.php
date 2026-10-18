<?php
/**
 * Plugin Name:       wvoce-iscrizioni
 * Description:       Iscrizioni a socio e ai corsi con Forminator: codice fiscale controllato, socio richiesto per i corsi e unico per anno sociale, passaggio dal modulo dei soci a quello dei corsi, "Recupera i tuoi dati" con conferma via email, copia automatica delle iscrizioni nei fogli Google. Tutto si configura in Strumenti → Iscrizioni.
 * Version:           1.2.1
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

define( 'WVI_VERSIONE', '1.2.1' );
define( 'WVI_DIR', plugin_dir_path( __FILE__ ) );
define( 'WVI_URL', plugin_dir_url( __FILE__ ) );

require WVI_DIR . 'inc/schema.php';
require WVI_DIR . 'inc/impostazioni.php';
require WVI_DIR . 'inc/campi.php';
require WVI_DIR . 'inc/archivio.php';
require WVI_DIR . 'inc/soci.php';
require WVI_DIR . 'inc/corsi.php';
require WVI_DIR . 'inc/fogli.php';
require WVI_DIR . 'inc/moduli.php';
if ( is_admin() ) {
	require WVI_DIR . 'inc/admin/pannello.php';
}

/**
 * Alla disattivazione si ferma il giro verso i fogli; impostazioni e stato restano.
 */
function wvi_disattiva() {
	wp_clear_scheduled_hook( 'wvi_sync' );
}
register_deactivation_hook( __FILE__, 'wvi_disattiva' );
