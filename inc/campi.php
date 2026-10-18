<?php
/**
 * Campi dei moduli Forminator: valori come testo, struttura dei moduli, codice fiscale.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Campi indicati come codice fiscale nei collegamenti: i loro valori vanno in maiuscolo.
 *
 * @return string[]
 */
function wvi_campi_cf() {
	$campi = array();
	foreach ( array_keys( wvi_collegamenti() ) as $id ) {
		$campi[] = (string) wvi_config( $id )['campo_cf'];
	}
	return array_values( array_filter( array_unique( $campi ), 'strlen' ) );
}

/**
 * Valore di un campo come testo per il foglio. Forminator salva indirizzi, date a tendina,
 * caselle e file come array.
 *
 * @param mixed  $value Valore salvato.
 * @param string $field Id del campo (il tipo è nel nome: consent-1, upload-1…).
 * @param string $part  Parte di un indirizzo (city, state…).
 */
function wvi_valore( $value, $field, $part = '' ) {
	if ( $part ) {
		return is_array( $value ) ? trim( (string) ( $value[ $part ] ?? '' ) ) : '';
	}
	if ( 0 === strpos( $field, 'consent' ) ) {
		return ( '' !== $value && null !== $value && 'false' !== $value && false !== $value ) ? 'TRUE' : '';
	}
	if ( is_array( $value ) ) {
		if ( isset( $value['file'] ) ) {
			$url = $value['file']['file_url'] ?? '';
			return is_array( $url ) ? implode( ' ', array_filter( array_map( 'strval', $url ) ) ) : (string) $url;
		}
		if ( isset( $value['day'], $value['month'], $value['year'] ) ) {
			return sprintf( '%02d/%02d/%04d', (int) $value['day'], (int) $value['month'], (int) $value['year'] );
		}
		return implode( ', ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ), 'strlen' ) );
	}
	$value = trim( wp_strip_all_tags( (string) $value ) );
	return in_array( $field, wvi_campi_cf(), true ) ? strtoupper( $value ) : $value;
}

/**
 * Valori delle colonne da un'iscrizione.
 *
 * @param array $meta    Campi salvati da Forminator (id => array( 'value' => … )).
 * @param array $colonne Intestazione => campo, o array( campo, parte ); campo vuoto = colonna vuota.
 */
function wvi_valori( $meta, $colonne ) {
	$riga = array();
	foreach ( (array) $colonne as $intestazione => $campo ) {
		list( $field, $part )  = is_array( $campo ) ? array_pad( $campo, 2, '' ) : array( $campo, '' );
		$riga[ $intestazione ] = '' === (string) $field ? '' : wvi_valore( $meta[ $field ]['value'] ?? '', $field, $part );
	}
	return $riga;
}

/**
 * Riferimento di un'iscrizione nel foglio ("F274-12"), contro i doppioni.
 *
 * @param object $entry Iscrizione.
 */
function wvi_rif( $entry ) {
	return sprintf( 'F%d-%d', (int) $entry->form_id, (int) $entry->entry_id );
}

/**
 * Campi di un modulo (id, etichetta, tipo, opzioni con etichetta e valore), per il pannello e per le
 * opzioni dei corsi. Solo la struttura del modulo, nessun dato delle iscrizioni.
 *
 * @param int $form Id del modulo.
 */
function wvi_campi( $form ) {
	if ( ! $form || ! class_exists( 'Forminator_API' ) || ! method_exists( 'Forminator_API', 'get_form_fields' ) ) {
		return array();
	}
	$campi = Forminator_API::get_form_fields( $form );
	if ( ! is_array( $campi ) ) {
		return array();
	}
	$out = array();
	foreach ( $campi as $f ) {
		if ( is_object( $f ) && method_exists( $f, 'to_formatted_array' ) ) {
			$d = (array) $f->to_formatted_array();
		} elseif ( is_object( $f ) ) {
			$d = array_merge( is_array( $f->raw ?? null ) ? $f->raw : array(), array( 'element_id' => $f->slug ?? '' ) );
		} else {
			$d = (array) $f;
		}
		$opzioni = array();
		foreach ( (array) ( $d['options'] ?? array() ) as $o ) {
			if ( is_array( $o ) ) {
				$label     = (string) ( $o['label'] ?? '' );
				$value     = (string) ( $o['value'] ?? '' );
				$opzioni[] = array(
					'etichetta' => $label,
					'valore'    => '' === $value ? $label : $value,
				);
			}
		}
		$out[] = array(
			'id'        => (string) ( $d['element_id'] ?? $d['slug'] ?? $d['id'] ?? '' ),
			'etichetta' => wp_strip_all_tags( (string) ( $d['field_label'] ?? $d['label'] ?? '' ) ),
			'tipo'      => (string) ( $d['type'] ?? '' ),
			'opzioni'   => $opzioni,
		);
	}
	return $out;
}

/**
 * Codice fiscale ridotto per il confronto: maiuscolo, senza spazi.
 *
 * @param string $cf Codice fiscale.
 */
function wvi_cf_normalizza( $cf ) {
	return strtoupper( (string) preg_replace( '/\s+/', '', (string) $cf ) );
}

/**
 * Codice fiscale di una persona: 16 caratteri nel formato giusto (anche con le lettere al posto
 * dei numeri dei casi di omocodia) e carattere di controllo corretto.
 *
 * @param string $cf Valore inserito.
 */
function wvi_codice_fiscale_valido( $cf ) {
	$cf = wvi_cf_normalizza( $cf );
	if ( ! preg_match( '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/', $cf ) ) {
		return false;
	}
	// Valori dei caratteri in posizione dispari (1ª, 3ª…): cifre e lettere A–Z hanno la stessa tabella.
	$dispari = array( 1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23 );
	$somma   = 0;
	for ( $i = 0; $i < 15; $i++ ) {
		$n      = ctype_digit( $cf[ $i ] ) ? (int) $cf[ $i ] : ord( $cf[ $i ] ) - 65;
		$somma += 0 === $i % 2 ? $dispari[ $n ] : $n;
	}
	return chr( 65 + $somma % 26 ) === $cf[15];
}

/**
 * Campi indicati nelle impostazioni che il modulo non contiene: un errore di battitura darebbe solo una
 * colonna vuota o un campo non ricompilato. Per le parti di indirizzo conta il campo ("address-1.city",
 * nella pagina "address-1-city").
 *
 * @param int      $form Id del modulo.
 * @param string[] $voci Campi indicati.
 * @return string[] Voci senza un campo nel modulo ([] se il modulo non si legge).
 */
function wvi_campi_assenti( $form, $voci ) {
	$ids = array_column( wvi_campi( $form ), 'id' );
	if ( ! $ids ) {
		return array();
	}
	$assenti = array();
	foreach ( array_filter( array_map( 'strval', (array) $voci ), 'strlen' ) as $voce ) {
		$campo = explode( '.', $voce, 2 )[0];
		$noto  = in_array( $campo, $ids, true );
		foreach ( $ids as $id ) {
			$noto = $noto || 0 === strpos( $campo, $id . '-' );
		}
		if ( ! $noto ) {
			$assenti[] = $voce;
		}
	}
	return array_values( array_unique( $assenti ) );
}
