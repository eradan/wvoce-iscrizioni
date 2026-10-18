<?php
/**
 * Corsi: corsi scelti in un'iscrizione e righe per il foglio dei corsi, una per corso. Il tab lo sceglie
 * il foglio, dal suo tab di configurazione (colonna A: tab, colonna B: testo del corso); un corso che
 * non vi compare va nel tab di riserva.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valori delle opzioni del campo del corso, letti dal modulo.
 */
function wvi_corsi_opzioni() {
	static $copie = array();

	$c   = wvi_config( 'corsi' );
	$key = $c['form'] . '|' . $c['campo_corsi'];
	if ( ! isset( $copie[ $key ] ) ) {
		$copie[ $key ] = array();
		foreach ( wvi_campi( $c['form'] ) as $f ) {
			if ( $c['campo_corsi'] === $f['id'] ) {
				$copie[ $key ] = array_values( array_filter( array_column( $f['opzioni'], 'valore' ), 'strlen' ) );
				break;
			}
		}
	}
	return $copie[ $key ];
}

/**
 * Corsi scelti in un'iscrizione. Le caselle arrivano come elenco; se arrivano come testo unito
 * ("a, b") si cercano dentro le opzioni del modulo, perché le opzioni stesse contengono virgole.
 * Prima le opzioni più lunghe: il tratto trovato viene coperto, così un'opzione contenuta in
 * un'altra ("Il gioco del teatro" in "Il gioco del teatro avanzato") non lo ritrova.
 *
 * @param mixed      $valore  Valore salvato del campo del corso.
 * @param array|null $opzioni Opzioni del modulo (null: lette dal modulo).
 */
function wvi_corsi_scelti( $valore, $opzioni = null ) {
	if ( ! is_array( $valore ) ) {
		$testo  = trim( wp_strip_all_tags( (string) $valore ) );
		$resto  = $testo;
		$noti   = array();
		$elenco = array_filter( array_map( 'strval', '' === $testo ? array() : ( null === $opzioni ? wvi_corsi_opzioni() : $opzioni ) ), 'strlen' );
		usort( $elenco, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
		foreach ( $elenco as $opzione ) {
			$pos = strpos( $resto, $opzione );
			if ( false !== $pos ) {
				$noti[ $pos ] = $opzione;
				$resto        = substr_replace( $resto, str_repeat( "\0", strlen( $opzione ) ), $pos, strlen( $opzione ) );
			}
		}
		ksort( $noti );
		$valore = $noti ? array_values( $noti ) : array( $testo );
	}
	$corsi = array();
	foreach ( $valore as $v ) {
		$v = is_scalar( $v ) ? trim( wp_strip_all_tags( (string) $v ) ) : '';
		if ( '' !== $v ) {
			$corsi[] = $v;
		}
	}
	return array_values( array_unique( $corsi ) );
}

/**
 * Righe per il foglio dei corsi da un'iscrizione: una per ogni corso scelto, con lo stesso
 * riferimento e il testo del corso nella colonna del corso.
 *
 * @param object $entry Iscrizione.
 */
function wvi_corsi_righe( $entry ) {
	$c     = wvi_config( 'corsi' );
	$meta  = is_array( $entry->meta_data ?? null ) ? $entry->meta_data : array();
	$corsi = '' !== $c['campo_corsi'] ? wvi_corsi_scelti( $meta[ $c['campo_corsi'] ]['value'] ?? '' ) : array();
	$base  = array( wvi_generali()['col_rif'] => wvi_rif( $entry ) ) + wvi_valori( $meta, $c['colonne'] );
	$righe = array();
	foreach ( $corsi ? $corsi : array( '' ) as $corso ) {
		$righe[] = $base + array( $c['col_corso'] => $corso );
	}
	return $righe;
}

/**
 * Testo di un corso ridotto per il confronto con il tab di configurazione: minuscole, senza accenti,
 * spazi compressi. Lo script del foglio fa lo stesso (normalizza_).
 *
 * @param string $testo Nome del corso.
 */
function wvi_corsi_normalizza( $testo ) {
	// Come in JavaScript: \s comprende lo spazio non separabile, le minuscole valgono oltre l'ASCII.
	return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( remove_accents( (string) $testo ), 'UTF-8' ) ) );
}

/**
 * Corrispondenza corso → tab letta dal tab di configurazione del foglio (azione "mappa" dello script),
 * tenuta 5 minuti.
 *
 * @param bool $fresca Rilegge dal foglio anche se c'è una copia recente.
 * @return array|WP_Error array( 'mappa' => corso => tab, 'tab' => tab esistenti ).
 */
function wvi_corsi_mappa( $fresca = false ) {
	$copia = $fresca ? false : get_transient( 'wvi_corsi_mappa' );
	if ( is_array( $copia ) ) {
		return $copia;
	}
	$json = wvi_chiama( 'corsi', array( 'azione' => 'mappa' ) );
	if ( is_wp_error( $json ) ) {
		return $json;
	}
	if ( ! isset( $json['mappa'] ) ) {
		return new WP_Error( 'wvi', 'Lo script del foglio dei corsi non legge il tab di configurazione: va incollata e pubblicata la versione nuova (Strumenti → Iscrizioni → Script).' );
	}
	$mappa = array(
		'mappa' => array_map( 'strval', (array) $json['mappa'] ),
		'tab'   => array_map( 'strval', (array) ( $json['tab'] ?? array() ) ),
	);
	set_transient( 'wvi_corsi_mappa', $mappa, 5 * MINUTE_IN_SECONDS );
	return $mappa;
}

/**
 * Tab in cui il foglio scriverà un corso, secondo la mappa: come lo script, il tab di riserva se il
 * corso manca dal tab di configurazione o il tab indicato non esiste.
 *
 * @param string $corso Testo del corso.
 * @param array  $mappa Risultato di wvi_corsi_mappa().
 */
function wvi_corsi_tab( $corso, $mappa ) {
	$c      = wvi_config( 'corsi' );
	$chiave = wvi_corsi_normalizza( $corso );
	foreach ( (array) ( $mappa['mappa'] ?? array() ) as $nome => $tab ) {
		if ( '' !== $chiave && wvi_corsi_normalizza( $nome ) === $chiave ) {
			// Come lo script: il tab di configurazione e quello di riserva non sono tab di un corso.
			$valido = ! in_array( $tab, array( $c['tab_configurazioni'], $c['tab_riserva'] ), true ) && in_array( $tab, (array) ( $mappa['tab'] ?? array() ), true );
			return $valido ? $tab : $c['tab_riserva'];
		}
	}
	return $c['tab_riserva'];
}
