<?php
/**
 * Soci: anno sociale, iscrizioni di una persona, righe per il foglio dei soci (un tab per anno
 * sociale, "Soci 2026-2027" con il prefisso del pannello).
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Primo anno dell'anno sociale di una data "Y-m-d H:i:s": con l'anno sociale da settembre, 2026 per
 * il 2026-2027; con l'anno solare, l'anno stesso.
 *
 * @param string $data Data.
 */
function wvi_anno_sociale( $data ) {
	$t    = strtotime( $data );
	$anno = (int) gmdate( 'Y', $t );
	return (int) gmdate( 'n', $t ) < (int) wvi_generali()['mese_inizio'] ? $anno - 1 : $anno;
}

/**
 * Nome dell'anno sociale che comincia nell'anno indicato: "2026-2027", o "2026" con l'anno solare.
 *
 * @param int $anno Primo anno.
 */
function wvi_anno_etichetta( $anno ) {
	return 1 === (int) wvi_generali()['mese_inizio'] ? (string) $anno : sprintf( '%d-%d', $anno, $anno + 1 );
}

/**
 * Primo giorno dell'anno sociale che comincia nell'anno indicato, "Y-m-d".
 *
 * @param int $anno Primo anno.
 */
function wvi_anno_inizio( $anno ) {
	return sprintf( '%04d-%02d-01', $anno, (int) wvi_generali()['mese_inizio'] );
}

/**
 * Tab dell'anno sociale di una data "Y-m-d H:i:s".
 *
 * @param string $data Data dell'iscrizione.
 */
function wvi_soci_tab( $data ) {
	return wvi_config( 'soci' )['tab_prefisso'] . wvi_anno_etichetta( wvi_anno_sociale( $data ) );
}

/**
 * Iscrizioni a socio di una persona, sul modulo dei soci, dal giorno $dal in poi: niente spam né bozze.
 *
 * @param string $cf  Codice fiscale.
 * @param string $dal Primo giorno, "Y-m-d".
 * @return object[] Iscrizioni, nell'ordine di Forminator.
 */
function wvi_soci_iscrizioni( $cf, $dal ) {
	$c  = wvi_config( 'soci' );
	$cf = wvi_cf_normalizza( $cf );
	if ( '' === $cf || '' === $c['campo_cf'] || ! $c['form'] || ! class_exists( 'Forminator_API' ) ) {
		return array();
	}
	$trovate = array();
	$entries = Forminator_API::get_entries( $c['form'] );
	foreach ( is_array( $entries ) ? $entries : array() as $e ) {
		if ( ! is_object( $e ) || ! empty( $e->is_spam ) || ! empty( $e->draft_id ) || substr( (string) ( $e->date_created_sql ?? '' ), 0, 10 ) < $dal ) {
			continue;
		}
		$v = $e->meta_data[ $c['campo_cf'] ]['value'] ?? '';
		if ( is_scalar( $v ) && wvi_cf_normalizza( $v ) === $cf ) {
			$trovate[] = $e;
		}
	}
	return $trovate;
}

/**
 * La persona ha un'iscrizione a socio nell'anno sociale di $adesso?
 *
 * @param string $cf     Codice fiscale.
 * @param string $adesso Data di riferimento, "Y-m-d H:i:s" (ora del sito, come Forminator).
 */
function wvi_socio_iscritto( $cf, $adesso ) {
	return (bool) wvi_soci_iscrizioni( $cf, wvi_anno_inizio( wvi_anno_sociale( $adesso ) ) );
}

/**
 * Data nel formato dell'esportazione di Forminator: "Ott 5, 2026 @ 10:08 AM".
 *
 * @param string $data Data dell'iscrizione, "Y-m-d H:i:s" (ora del sito).
 */
function wvi_soci_data( $data ) {
	static $mesi = array( 'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic' );
	$t           = strtotime( $data );
	return $mesi[ (int) gmdate( 'n', $t ) - 1 ] . gmdate( ' j, Y @ g:i A', $t );
}

/**
 * Righe dei soci da un'iscrizione di Forminator: una, nel tab dell'anno sociale.
 *
 * @param object $entry Iscrizione.
 */
function wvi_soci_righe( $entry ) {
	$c    = wvi_config( 'soci' );
	$meta = is_array( $entry->meta_data ?? null ) ? $entry->meta_data : array();
	$riga = array(
		'_tab'                    => wvi_soci_tab( $entry->date_created_sql ),
		wvi_generali()['col_rif'] => wvi_rif( $entry ),
	);
	if ( '' !== $c['col_data'] ) {
		$riga[ $c['col_data'] ] = wvi_soci_data( $entry->date_created_sql );
	}
	return array( $riga + wvi_valori( $meta, $c['colonne'] ) );
}
