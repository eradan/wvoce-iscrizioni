<?php
/**
 * Impostazioni: i due collegamenti (soci e corsi) e le impostazioni generali, tutte nel pannello
 * Strumenti → Iscrizioni. Nessun valore di un'associazione è scritto nel codice: i predefiniti sono
 * neutri e ogni sito li adatta ai suoi moduli e ai suoi fogli.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Collegamenti modulo → foglio. Le regole (socio richiesto, iscrizione unica), le righe del foglio e
 * lo script Apps Script dipendono dal tipo; tutto il resto è nelle impostazioni.
 */
function wvi_collegamenti() {
	return array(
		'soci'  => array(
			'titolo' => 'Soci',
			'righe'  => 'wvi_soci_righe',
			'script' => 'soci.gs',
		),
		'corsi' => array(
			'titolo' => 'Corsi',
			'righe'  => 'wvi_corsi_righe',
			'script' => 'corsi.gs',
		),
	);
}

/**
 * Valori predefiniti delle impostazioni di un collegamento, dallo schema (inc/schema.php).
 *
 * @param string $id Collegamento.
 */
function wvi_predefiniti( $id ) {
	return wvi_schema_predefiniti( $id );
}

/**
 * Impostazioni generali: anno sociale, pagine dei moduli, contatto, proxy, colonna dei riferimenti,
 * messaggio sul codice fiscale, compilazione automatica del browser. Senza un'email di contatto vale
 * quella dell'amministratore del sito.
 */
function wvi_generali() {
	$g = array_merge( wvi_schema_predefiniti( 'generali' ), (array) get_option( 'wvi_generali', array() ) );
	if ( '' === (string) $g['email'] ) {
		$g['email'] = (string) get_option( 'admin_email' );
	}
	return $g;
}

/**
 * Impostazioni di un collegamento, con i nomi delle opzioni di stato.
 *
 * @param string $id Collegamento (soci, corsi).
 */
function wvi_config( $id = 'soci' ) {
	// Si chiama per ogni valore di ogni riga: il risultato si rifà solo se l'opzione è cambiata.
	static $copie = array();
	$s            = (array) get_option( "wvi_{$id}_impostazioni", array() );
	if ( isset( $copie[ $id ] ) && $copie[ $id ][0] === $s ) {
		return $copie[ $id ][1];
	}
	$tipo = (array) ( wvi_collegamenti()[ $id ] ?? array() );
	$c    = array_merge( wvi_predefiniti( $id ), $s );

	$c['id']           = $id;
	$c['titolo']       = (string) ( $tipo['titolo'] ?? $id );
	$c['righe']        = (string) ( $tipo['righe'] ?? '' );
	$c['script']       = (string) ( $tipo['script'] ?? '' );
	$c['form']         = (int) $c['form'];
	$c['impostazioni'] = "wvi_{$id}_impostazioni";
	$c['ultimo']       = "wvi_{$id}_ultimo";
	$c['stato']        = "wvi_{$id}_stato";
	$c['sospese']      = "wvi_{$id}_sospese";
	$c['inizio']       = $c['dalle_nuove'] ? "wvi_{$id}_inizio" : '';
	$copie[ $id ]      = array( $s, $c );
	return $c;
}

/**
 * Testo con i segnaposto sostituiti. Segnaposto sempre disponibili: {associazione} (nome del sito),
 * {email} (contatto), {link_soci} (pagina del modulo dei soci).
 *
 * @param string $modello Testo con {segnaposto}.
 * @param array  $valori  Altri segnaposto: nome => valore.
 */
function wvi_testo( $modello, $valori = array() ) {
	$g      = wvi_generali();
	$valori = $valori + array(
		'associazione' => get_bloginfo( 'name' ),
		'email'        => $g['email'],
		'link_soci'    => $g['pagina_soci'],
	);
	$sost   = array();
	foreach ( $valori as $nome => $valore ) {
		$sost[ '{' . $nome . '}' ] = (string) $valore;
	}
	return strtr( (string) $modello, $sost );
}

/**
 * Colonne del foglio da un testo, una per riga: "Intestazione = campo" o "Intestazione = campo.parte"
 * (per le parti di un indirizzo: street_address, city, state, zip…). "Intestazione =" lascia la colonna
 * vuota. Le righe senza "=" si ignorano.
 *
 * @param string $testo Testo del pannello.
 * @return array Intestazione => campo, o array( campo, parte ).
 */
function wvi_colonne_da_testo( $testo ) {
	$colonne = array();
	foreach ( preg_split( '/\R/', (string) $testo ) as $riga ) {
		if ( false === strpos( $riga, '=' ) ) {
			continue;
		}
		list( $titolo, $campo ) = array_map( 'trim', explode( '=', $riga, 2 ) );
		if ( '' === $titolo ) {
			continue;
		}
		$colonne[ $titolo ] = false !== strpos( $campo, '.' ) ? explode( '.', $campo, 2 ) : $campo;
	}
	return $colonne;
}

/**
 * Testo per il pannello dalle colonne (l'inverso di wvi_colonne_da_testo()).
 *
 * @param array $colonne Intestazione => campo, o array( campo, parte ).
 */
function wvi_colonne_in_testo( $colonne ) {
	$righe = array();
	foreach ( (array) $colonne as $titolo => $campo ) {
		$righe[] = $titolo . ' = ' . implode( '.', (array) $campo );
	}
	return implode( "\n", $righe );
}

/**
 * Elenco di campi da un testo: id separati da virgole, spazi o a capo ("name-1, address-1.city").
 *
 * @param string|array $testo Testo del pannello (o elenco già pronto).
 * @return string[]
 */
function wvi_elenco( $testo ) {
	$voci = is_array( $testo ) ? $testo : preg_split( '/[\s,]+/', (string) $testo );
	return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $voci ) ), 'strlen' ) ) );
}

/**
 * Corrispondenze "campo = valore" da un testo, una per riga (per la compilazione automatica).
 *
 * @param string|array $testo Testo del pannello.
 * @return array Campo => valore.
 */
function wvi_coppie( $testo ) {
	if ( is_array( $testo ) ) {
		return $testo;
	}
	$coppie = array();
	foreach ( preg_split( '/\R/', (string) $testo ) as $riga ) {
		if ( false !== strpos( $riga, '=' ) ) {
			list( $campo, $valore ) = array_map( 'trim', explode( '=', $riga, 2 ) );
			if ( '' !== $campo && '' !== $valore ) {
				$coppie[ $campo ] = $valore;
			}
		}
	}
	return $coppie;
}

/**
 * ID di un foglio Google da quanto incollato nel pannello: l'ID stesso o l'indirizzo intero
 * (https://docs.google.com/spreadsheets/d/ID/edit…). '' se non riconosciuto.
 *
 * @param string $testo ID o indirizzo.
 */
function wvi_id_foglio( $testo ) {
	$testo = trim( (string) $testo );
	if ( preg_match( '#/spreadsheets/d/([A-Za-z0-9_-]{20,})#', $testo, $m ) ) {
		return $m[1];
	}
	return preg_match( '/^[A-Za-z0-9_-]{20,}$/', $testo ) ? $testo : '';
}

/**
 * Data nel formato AAAA-MM-GG, altrimenti vuota.
 *
 * @param string $value Valore inserito.
 */
function wvi_data( $value ) {
	$value = trim( (string) $value );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
}

/**
 * Valore di una voce dello schema dal modulo inviato, pulito secondo il suo tipo.
 *
 * @param array $v     Voce dello schema.
 * @param array $dati  Valori inviati dal modulo del pannello.
 * @param mixed $prima Valore attuale.
 * @return array array( valore, avviso o '' ).
 */
function wvi_pulisci( $v, $dati, $prima ) {
	$grezzo = $dati[ $v['chiave'] ] ?? '';
	$testo  = is_scalar( $grezzo ) ? trim( (string) $grezzo ) : '';
	switch ( $v['tipo'] ) {
		case 'casella':
			// Una casella non spuntata non arriva: nel modulo di questa scheda vuol dire spenta.
			return array( ! empty( $grezzo ), '' );
		case 'numero':
			return array( max( 1, absint( $testo ) ), '' );
		case 'mese':
			return array( min( 12, max( 1, absint( $testo ) ) ), '' );
		case 'modulo':
			return array( absint( $testo ), '' );
		case 'elenco':
			return array( wvi_elenco( $testo ), '' );
		case 'colonne':
			return array( wvi_colonne_da_testo( $testo ), '' );
		case 'coppie':
			return array( wvi_coppie( $testo ), '' );
		case 'html':
		case 'area':
			$valore = wp_kses_post( $testo );
			break;
		case 'prefisso':
			// Può finire con uno spazio ("Soci 2026-2027"): wp_strip_all_tags() lo toglierebbe.
			$valore = ltrim( str_replace( array( "\r", "\n", "\t" ), '', strip_tags( is_scalar( $grezzo ) ? (string) $grezzo : '' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
			break;
		case 'url':
			$valore = esc_url_raw( $testo );
			break;
		case 'email':
			$valore = sanitize_email( $testo );
			break;
		case 'segreto':
			$valore = sanitize_text_field( $testo );
			if ( '' === $valore ) {
				return array( $prima, '' );
			}
			break;
		case 'data':
			$valore = wvi_data( $testo );
			if ( '' !== $testo && '' === $valore ) {
				return array( $prima, "{$v['etichetta']}: data non valida (serve aaaa-mm-gg), resta quella di prima." );
			}
			break;
		case 'foglio':
			$valore = wvi_id_foglio( $testo );
			if ( '' !== $testo && '' === $valore ) {
				return array( $prima, "{$v['etichetta']}: non riconosciuto (serve l'ID o l'indirizzo del foglio), resta quello di prima." );
			}
			break;
		default:
			$valore = sanitize_text_field( $testo );
	}
	if ( $v['obbligatorio'] && '' === $valore ) {
		$resta = '' !== (string) $prima ? $prima : $v['predefinito'];
		return array( $resta, "{$v['etichetta']} non può restare vuoto: resta \"{$resta}\"." );
	}
	return array( $valore, '' );
}

/**
 * Salva un modulo del pannello: le voci dello schema di quell'opzione e di quella scheda, ognuna pulita
 * secondo il tipo. Le voci delle altre schede restano come sono, per costruzione. Poi i controlli che
 * legano più voci (collegamento, colonna del corso).
 *
 * @param string $dove   generali, soci, corsi.
 * @param string $scheda Scheda del pannello.
 * @param array  $dati   Valori inviati.
 * @return string[] Avvisi per chi salva.
 */
function wvi_salva( $dove, $scheda, $dati ) {
	$voci = wvi_schema_voci( $dove, $scheda );
	if ( ! $voci ) {
		return array();
	}
	$generali = 'generali' === $dove;
	$opzione  = $generali ? 'wvi_generali' : "wvi_{$dove}_impostazioni";
	$attuali  = $generali ? array_merge( wvi_schema_predefiniti( 'generali' ), (array) get_option( 'wvi_generali', array() ) ) : wvi_config( $dove );
	$titolo   = $generali ? '' : $attuali['titolo'] . ': ';
	$nuove    = array();
	$avvisi   = array();
	foreach ( $voci as $chiave => $v ) {
		list( $nuove[ $chiave ], $avviso ) = wvi_pulisci( $v, (array) $dati, $attuali[ $chiave ] ?? $v['predefinito'] );
		if ( '' !== $avviso ) {
			$avvisi[] = $titolo . $avviso;
		}
	}
	if ( ! $generali && 'collegamenti' === $scheda ) {
		$avvisi = array_merge( $avvisi, wvi_salva_collegamento( $dove, $attuali, $nuove ) );
	}
	if ( 'corsi' === $dove && 'campi' === $scheda ) {
		$avvisi = array_merge( $avvisi, wvi_salva_colonna_corso( $nuove ) );
	}
	update_option( $opzione, array_merge( (array) get_option( $opzione, array() ), $nuove ), false );
	return $avvisi;
}

/**
 * Collegamento salvato: indirizzo insolito, modulo cambiato (si riparte da capo) o prima configurazione
 * (con "solo le nuove" il punto di partenza si fissa al primo giro).
 *
 * @param string $id      Collegamento.
 * @param array  $attuali Impostazioni prima del salvataggio.
 * @param array  $nuove   Voci salvate.
 * @return string[] Avvisi.
 */
function wvi_salva_collegamento( $id, $attuali, $nuove ) {
	$avvisi = array();
	if ( '' !== $nuove['url'] && ! preg_match( '#^https://script\.google\.com/macros/s/[^/]+/exec$#', $nuove['url'] ) ) {
		$avvisi[] = "{$attuali['titolo']}: l'indirizzo dell'app web ha una forma insolita, di solito è https://script.google.com/macros/s/…/exec.";
	}
	if ( $nuove['form'] !== $attuali['form'] ) {
		delete_option( "wvi_{$id}_ultimo" );
		delete_option( "wvi_{$id}_sospese" );
		if ( $attuali['form'] ) {
			// Modulo cambiato: da capo davvero, anche con le iscrizioni arrivate al modulo nuovo prima del
			// cambio nel pannello. Il punto di partenza "solo le nuove" vale per la prima attivazione.
			update_option( "wvi_{$id}_inizio", 0, false );
			$avvisi[] = "{$attuali['titolo']}: modulo cambiato, l'invio riparte da capo per il modulo n. {$nuove['form']}.";
		} else {
			delete_option( "wvi_{$id}_inizio" );
		}
	}
	return $avvisi;
}

/**
 * Il corso ha la sua colonna: una colonna del foglio con lo stesso nome la coprirebbe con il valore
 * unito ("A, B") e le righe dei corsi diventerebbero uguali. La toglie dalle colonne.
 *
 * @param array $nuove Voci salvate della scheda Campi e regole dei corsi (modificate).
 * @return string[] Avvisi.
 */
function wvi_salva_colonna_corso( &$nuove ) {
	$avvisi = array();
	foreach ( array_keys( (array) $nuove['colonne'] ) as $titolo ) {
		if ( 0 === strcasecmp( trim( (string) $titolo ), trim( (string) $nuove['col_corso'] ) ) ) {
			unset( $nuove['colonne'][ $titolo ] );
			$avvisi[] = "La colonna \"{$titolo}\" è già quella del corso (una riga per corso): tolta dalle colonne.";
		}
	}
	return $avvisi;
}

/**
 * Importa una configurazione completa (per esempio da un altro sito o da una versione precedente):
 * impostazioni generali, dei collegamenti e il loro stato (punto raggiunto, sospese, partenza).
 * Sovrascrive solo le voci presenti.
 *
 * @param array $dati array( 'generali' => …, 'soci' => …, 'corsi' => …, 'stato' => array( 'soci' => array( 'ultimo', 'sospese', 'inizio', 'stato' ) … ) ).
 */
function wvi_importa( $dati ) {
	if ( ! empty( $dati['generali'] ) && is_array( $dati['generali'] ) ) {
		update_option( 'wvi_generali', array_merge( (array) get_option( 'wvi_generali', array() ), $dati['generali'] ), false );
	}
	foreach ( array_keys( wvi_collegamenti() ) as $id ) {
		if ( ! empty( $dati[ $id ] ) && is_array( $dati[ $id ] ) ) {
			update_option( "wvi_{$id}_impostazioni", array_merge( (array) get_option( "wvi_{$id}_impostazioni", array() ), $dati[ $id ] ), false );
		}
		foreach ( array( 'ultimo', 'sospese', 'inizio', 'stato' ) as $voce ) {
			if ( isset( $dati['stato'][ $id ][ $voce ] ) ) {
				update_option( "wvi_{$id}_{$voce}", $dati['stato'][ $id ][ $voce ], false );
			}
		}
	}
}
