<?php
/**
 * Pannello Strumenti → Iscrizioni: menu, schede, avvisi dopo un'azione, guscio della pagina, script e
 * stile del pannello. Le schede sono in inc/admin/scheda-*.php, le azioni in inc/admin/azioni.php, i
 * campi generati dallo schema in inc/admin/componenti.php.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/componenti.php';
require __DIR__ . '/azioni.php';
require __DIR__ . '/scheda-collegamenti.php';
require __DIR__ . '/scheda-campi.php';
require __DIR__ . '/scheda-funzioni.php';
require __DIR__ . '/scheda-invio.php';
require __DIR__ . '/scheda-script.php';

/**
 * Schede della pagina: id => titolo.
 */
function wvi_admin_schede() {
	return array(
		'collegamenti' => 'Collegamenti',
		'campi'        => 'Campi e regole',
		'funzioni'     => 'Passaggio e recupero',
		'soci'         => 'Invio soci',
		'corsi'        => 'Invio corsi',
		'script'       => 'Script Apps Script',
	);
}

/**
 * Scheda aperta (dal parametro "tab" dell'indirizzo).
 */
function wvi_admin_scheda() {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( wvi_admin_schede()[ $tab ] ) ? $tab : 'collegamenti';
}

/**
 * Indirizzo della pagina, su una scheda.
 *
 * @param string $tab Scheda.
 */
function wvi_admin_url( $tab = 'collegamenti' ) {
	return admin_url( 'tools.php?page=wvi-iscrizioni&tab=' . rawurlencode( $tab ) );
}

/**
 * Voce di menu in Strumenti; le azioni partono prima della pagina.
 */
function wvi_admin_menu() {
	$hook = add_management_page( 'Iscrizioni', 'Iscrizioni', 'manage_options', 'wvi-iscrizioni', 'wvi_admin_pagina' );
	if ( $hook ) {
		add_action( "load-{$hook}", 'wvi_admin_azioni' );
	}
}
add_action( 'admin_menu', 'wvi_admin_menu' );

/**
 * Collegamento "Impostazioni" nell'elenco dei plugin.
 *
 * @param array $link Collegamenti del plugin.
 */
function wvi_admin_link_plugin( $link ) {
	array_unshift( $link, '<a href="' . esc_url( wvi_admin_url() ) . '">Impostazioni</a>' );
	return $link;
}
add_filter( 'plugin_action_links_' . plugin_basename( WVI_DIR . 'wvoce-iscrizioni.php' ), 'wvi_admin_link_plugin' );

/**
 * Script e stile del pannello, solo nella sua pagina.
 *
 * @param string $hook Pagina dell'amministrazione.
 */
function wvi_admin_risorse( $hook ) {
	if ( 'tools_page_wvi-iscrizioni' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wvi-admin', WVI_URL . 'assets/admin.css', array(), WVI_VERSIONE );
	wp_enqueue_script( 'wvi-admin', WVI_URL . 'assets/admin.js', array(), WVI_VERSIONE, array( 'in_footer' => true ) );
}
add_action( 'admin_enqueue_scripts', 'wvi_admin_risorse' );

/**
 * Messaggi da mostrare dopo il reindirizzamento che segue un'azione.
 *
 * @param array[] $messaggi array( tipo (success, warning, error), testo ).
 */
function wvi_admin_avvisi( $messaggi ) {
	$chiave = 'wvi_avvisi_' . get_current_user_id();
	set_transient( $chiave, array_merge( (array) get_transient( $chiave ), (array) $messaggi ), MINUTE_IN_SECONDS );
}

/**
 * Apertura di un modulo del pannello: nonce, opzione, scheda a cui tornare.
 *
 * @param string $id  Collegamento ('' per le impostazioni generali).
 * @param string $tab Scheda.
 */
function wvi_admin_apri( $id, $tab ) {
	echo '<form method="post" action="' . esc_url( wvi_admin_url( $tab ) ) . '">';
	wp_nonce_field( 'wvi_iscrizioni' );
	echo '<input type="hidden" name="wvi_id" value="' . esc_attr( $id ) . '">';
	echo '<input type="hidden" name="wvi_tab" value="' . esc_attr( $tab ) . '">';
}

/**
 * Pagina del pannello: avvisi, schede, contenuto della scheda aperta.
 */
function wvi_admin_pagina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$attivo = wvi_admin_scheda();
	$chiave = 'wvi_avvisi_' . get_current_user_id();
	$avvisi = (array) get_transient( $chiave );
	delete_transient( $chiave );
	?>
	<div class="wrap">
		<h1>Iscrizioni</h1>
		<?php if ( ! class_exists( 'Forminator_API' ) ) : ?>
			<div class="notice notice-error inline"><p>Forminator non è attivo: il plugin lavora sui suoi moduli.</p></div>
		<?php endif; ?>
		<nav class="nav-tab-wrapper wvi-schede">
			<?php foreach ( wvi_admin_schede() as $tab => $titolo ) : ?>
				<a href="<?php echo esc_url( wvi_admin_url( $tab ) ); ?>" class="nav-tab<?php echo $tab === $attivo ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $titolo ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php foreach ( array_filter( $avvisi ) as $avviso ) : ?>
			<div class="notice notice-<?php echo esc_attr( $avviso[0] ); ?> is-dismissible"><p><?php echo esc_html( $avviso[1] ); ?></p></div>
		<?php endforeach; ?>
		<?php
		$schede = array(
			'collegamenti' => 'wvi_admin_collegamenti',
			'campi'        => 'wvi_admin_campi',
			'funzioni'     => 'wvi_admin_funzioni',
			'script'       => 'wvi_admin_script',
		);
		if ( isset( $schede[ $attivo ] ) ) {
			call_user_func( $schede[ $attivo ] );
		} else {
			wvi_admin_invio( $attivo );
		}
		?>
	</div>
	<?php
}
