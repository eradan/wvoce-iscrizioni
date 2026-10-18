<?php
/**
 * Azioni dei pulsanti del pannello. wvi_admin_azioni() controlla nonce e permessi, chiama l'azione e
 * torna alla scheda con una GET (un aggiornamento della pagina non ripete l'azione); ogni azione è una
 * funzione che restituisce i messaggi da mostrare, così si prova senza il pannello.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Azioni: valore del pulsante "wvi_azione" => funzione( $id, $tab, $dati ) che restituisce i messaggi.
 */
function wvi_admin_azioni_note() {
	return array(
		'generali' => 'wvi_azione_generali',
		'salva'    => 'wvi_azione_salva',
		'verifica' => 'wvi_azione_verifica',
		'regole'   => 'wvi_azione_regole',
		'invia'    => 'wvi_azione_invia',
		'riparti'  => 'wvi_azione_riparti',
		'mappa'    => 'wvi_azione_mappa',
	);
}

/**
 * Prima che la pagina parta: azione richiesta da un pulsante, poi ritorno alla scheda.
 */
function wvi_admin_azioni() {
	if ( empty( $_POST['wvi_azione'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'wvi_iscrizioni' );
	$azione = sanitize_key( wp_unslash( $_POST['wvi_azione'] ) );
	$id     = isset( $_POST['wvi_id'] ) ? sanitize_key( wp_unslash( $_POST['wvi_id'] ) ) : '';
	$tab    = isset( $_POST['wvi_tab'] ) ? sanitize_key( wp_unslash( $_POST['wvi_tab'] ) ) : 'collegamenti';
	// Ogni voce è ripulita da wvi_salva() secondo il suo tipo nello schema (testi con HTML e a capo, elenchi, numeri).
	$dati  = isset( $_POST['wvi'] ) && is_array( $_POST['wvi'] ) ? wp_unslash( $_POST['wvi'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$note  = wvi_admin_azioni_note();
	$serve = 'generali' === $azione || isset( wvi_collegamenti()[ $id ] );
	if ( isset( $note[ $azione ] ) && $serve ) {
		wvi_admin_avvisi( call_user_func( $note[ $azione ], $id, $tab, $dati ) );
	}
	wp_safe_redirect( wvi_admin_url( $tab ) );
	exit;
}

/**
 * Avvisi del salvataggio come messaggi del pannello.
 *
 * @param string[] $avvisi Avvisi di wvi_salva().
 */
function wvi_azione_avvisi( $avvisi ) {
	return array_map( static fn( $a ) => array( 'warning', $a ), $avvisi );
}

/**
 * Impostazioni generali (scheda Collegamenti o, per la compilazione automatica, Passaggio e recupero).
 *
 * @param string $id   Non usato.
 * @param string $tab  Scheda.
 * @param array  $dati Valori inviati.
 */
function wvi_azione_generali( $id, $tab, $dati ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- firma comune delle azioni.
	return array_merge( wvi_azione_avvisi( wvi_salva( 'generali', $tab, $dati ) ), array( array( 'success', 'Impostazioni generali salvate.' ) ) );
}

/**
 * Collegamento con il foglio (scheda Collegamenti): salvato, giro riacceso o spento.
 *
 * @param string $id   Collegamento.
 * @param string $tab  Scheda.
 * @param array  $dati Valori inviati.
 */
function wvi_azione_salva( $id, $tab, $dati ) {
	$messaggi = wvi_azione_avvisi( wvi_salva( $id, $tab, $dati ) );
	wvi_schedule();
	delete_transient( 'wvi_corsi_mappa' );
	$messaggi[] = array( 'success', wvi_config( $id )['titolo'] . ': collegamento salvato.' );
	return $messaggi;
}

/**
 * Collegamento salvato e verificato: richiesta vuota al foglio.
 *
 * @param string $id   Collegamento.
 * @param string $tab  Scheda.
 * @param array  $dati Valori inviati.
 */
function wvi_azione_verifica( $id, $tab, $dati ) {
	$messaggi   = wvi_azione_salva( $id, $tab, $dati );
	$titolo     = wvi_config( $id )['titolo'];
	$esito      = wvi_verifica( $id );
	$messaggi[] = is_wp_error( $esito )
		? array( 'error', "{$titolo}, verifica non riuscita: " . $esito->get_error_message() )
		: array( 'success', "{$titolo}: risponde il foglio «{$esito}», indirizzo, codice e foglio sono giusti." );
	return $messaggi;
}

/**
 * Campi e regole, passaggio, recupero di un collegamento.
 *
 * @param string $id   Collegamento.
 * @param string $tab  Scheda.
 * @param array  $dati Valori inviati.
 */
function wvi_azione_regole( $id, $tab, $dati ) {
	$messaggi = wvi_azione_avvisi( wvi_salva( $id, $tab, $dati ) );
	delete_transient( 'wvi_corsi_mappa' );
	$messaggi[] = array( 'success', wvi_config( $id )['titolo'] . ': impostazioni salvate.' );
	return $messaggi;
}

/**
 * Invia adesso.
 *
 * @param string $id Collegamento.
 */
function wvi_azione_invia( $id ) {
	if ( ! wvi_configurato( $id ) ) {
		return array();
	}
	$esito = wvi_sync( $id );
	return array( array( $esito['errore'] ? 'error' : 'success', sprintf( 'Inviate %d iscrizioni.', $esito['inviate'] ) . ( $esito['errore'] ? ' Errore: ' . $esito['errore'] : '' ) ) );
}

/**
 * Ricomincia dal punto di partenza.
 *
 * @param string $id Collegamento.
 */
function wvi_azione_riparti( $id ) {
	if ( ! wvi_configurato( $id ) ) {
		return array();
	}
	delete_option( wvi_config( $id )['ultimo'] );
	return array( array( 'success', 'Si riparte dal punto di partenza: le righe già nel foglio restano senza doppioni.' ) );
}

/**
 * Legge dal foglio la corrispondenza corso → tab.
 *
 * @param string $id Collegamento (solo corsi).
 */
function wvi_azione_mappa( $id ) {
	if ( 'corsi' !== $id ) {
		return array();
	}
	$mappa = wvi_corsi_mappa( true );
	return array(
		is_wp_error( $mappa )
			? array( 'error', $mappa->get_error_message() )
			: array( 'success', sprintf( 'Tab "%s" letto: %d corsi.', wvi_config( 'corsi' )['tab_configurazioni'], count( $mappa['mappa'] ) ) ),
	);
}
