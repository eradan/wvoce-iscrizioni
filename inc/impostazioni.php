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
 * Valori predefiniti delle impostazioni di un collegamento.
 *
 * @param string $id Collegamento.
 */
function wvi_predefiniti( $id ) {
	$comuni = array(
		// Collegamento con il foglio
		'url'         => '',
		'token'       => '',
		'form'        => 0,
		'dal'         => '',
		'foglio'      => '',
		'dalle_nuove' => false,
		// Campi e colonne
		'campo_cf'    => '',
		'controlla'   => true,
		'colonne'     => array(),
	);
	if ( 'corsi' === $id ) {
		return $comuni + array(
			'campo_corsi'        => '',
			'col_corso'          => 'Corso',
			'tab_configurazioni' => 'configurazioni',
			'tab_riserva'        => 'Da smistare',
			'richiede_socio'     => true,
			'msg_socio'          => 'Questo codice fiscale manca ancora fra i soci {anno}: i corsi sono riservati ai soci. Prima occorre iscriversi all\'associazione con il <a href="{link_soci}" target="_blank" rel="noopener">modulo di iscrizione a socio</a>, poi si torna a questa pagina.',
			'passaggio'          => false,
			'passaggio_campi'    => array(),
			'passaggio_caselle'  => array(),
			'passaggio_avviso'   => 'Abbiamo ripreso i tuoi dati dall\'iscrizione a socio: scegli il corso e completa il modulo.',
		);
	}
	return $comuni + array(
		'campo_email'         => '',
		'col_data'            => 'Data',
		'tab_prefisso'        => 'Soci ',
		'unico'               => true,
		'msg_doppio'          => 'Questo codice fiscale risulta già fra i soci {anno}: l\'iscrizione di quest\'anno è già registrata. Per domande o per correggere i dati scrivi a <a href="mailto:{email}">{email}</a>.',
		'recupera'            => false,
		'recupera_campi'      => array(),
		'recupera_anni'       => 3,
		'recupera_ore'        => 24,
		'recupera_richieste'  => 5,
		'recupera_attesa'     => 5,
		'recupera_invito'     => 'Già stato socio? <a>Recupera i tuoi dati</a>',
		'recupera_messaggio'  => 'Se il codice fiscale è nel nostro archivio, ti abbiamo scritto all\'indirizzo email dell\'ultima iscrizione (guarda anche nello spam). Il link vale {ore} ore.',
		'recupera_avviso'     => 'Abbiamo recuperato i dati della tua ultima iscrizione: controlla che siano ancora giusti e completa il resto del modulo.',
		'recupera_link_ogg'   => '{associazione}: recupera i dati della tua iscrizione',
		'recupera_link_testo' => "Ciao,\n\nhai chiesto di recuperare i dati della tua ultima iscrizione a {associazione}. Apri il link qui sotto entro {ore} ore: troverai il modulo di iscrizione già compilato con i tuoi dati.\n\n{link}\n\nControlla che i dati siano ancora giusti e completa il resto del modulo.\n\nSe non sei stato tu, ignora questa email: senza il link nessuno vede i tuoi dati.\n\n{associazione}",
		'recupera_gia_ogg'    => '{associazione}: la tua iscrizione {anno} è già registrata',
		'recupera_gia_testo'  => "Ciao,\n\nqualcuno, probabilmente tu, ha chiesto di recuperare i dati della tua iscrizione a {associazione} dal modulo sul sito.\n\nLa tua iscrizione per l'anno sociale {anno} è già registrata: non serve compilare di nuovo il modulo. Per correggere i dati scrivi a {email}.\n\nSe non sei stato tu, ignora questa email.\n\n{associazione}",
	);
}

/**
 * Impostazioni generali: anno sociale, pagine dei moduli, contatto, proxy, colonna dei riferimenti,
 * compilazione automatica del browser.
 */
function wvi_generali() {
	$s = (array) get_option( 'wvi_generali', array() );
	return array_merge(
		array(
			'mese_inizio'  => 1,
			'email'        => (string) get_option( 'admin_email' ),
			'pagina_soci'  => '',
			'pagina_corsi' => '',
			'proxy'        => false,
			'col_rif'      => 'Rif.',
			'autocomplete' => array(),
			'msg_cf'       => 'Controlla il codice fiscale: sono 16 lettere e numeri, come sulla tessera sanitaria.',
		),
		$s
	);
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
 * Caselle sì/no presenti nel modulo del pannello che è stato inviato. Una casella non spuntata non
 * arriva affatto: senza questo elenco non si distinguerebbe da una casella che sta in un'altra scheda,
 * e salvare una scheda spegnerebbe le caselle delle altre.
 *
 * @param array $dati Valori inviati (con "_caselle", aggiunto da wvi_admin_casella()).
 * @return string[]
 */
function wvi_caselle_inviate( $dati ) {
	return array_map( 'strval', (array) ( $dati['_caselle'] ?? array() ) );
}

/**
 * Valore di una casella sì/no: quello inviato se la casella era nel modulo, altrimenti quello di prima.
 *
 * @param array  $dati   Valori inviati.
 * @param string $nome   Nome della casella.
 * @param bool   $prima  Valore salvato.
 */
function wvi_casella( $dati, $nome, $prima ) {
	return in_array( $nome, wvi_caselle_inviate( $dati ), true ) ? ! empty( $dati[ $nome ] ) : (bool) $prima;
}

/**
 * Salva il collegamento di un foglio dal pannello: indirizzo e codice dell'app web, foglio, modulo,
 * data di partenza. Codice vuoto: resta quello salvato. Se cambia il modulo si riparte da capo.
 *
 * @param string $id   Collegamento.
 * @param array  $dati url, token, form, dal, foglio, dalle_nuove.
 * @return array Avvisi per chi salva.
 */
function wvi_salva_collegamento( $id, $dati ) {
	$c      = wvi_config( $id );
	$prima  = (array) get_option( $c['impostazioni'], array() );
	$avvisi = array();

	$url = esc_url_raw( trim( (string) ( $dati['url'] ?? '' ) ) );
	if ( '' !== $url && ! preg_match( '#^https://script\.google\.com/macros/s/[^/]+/exec$#', $url ) ) {
		$avvisi[] = "{$c['titolo']}: l'indirizzo dell'app web ha una forma insolita, di solito è https://script.google.com/macros/s/…/exec.";
	}

	$token = trim( sanitize_text_field( (string) ( $dati['token'] ?? '' ) ) );
	if ( '' === $token ) {
		$token = (string) ( $prima['token'] ?? '' );
	}

	$foglio_inserito = trim( (string) ( $dati['foglio'] ?? '' ) );
	$foglio          = wvi_id_foglio( $foglio_inserito );
	if ( '' !== $foglio_inserito && '' === $foglio ) {
		$foglio   = (string) ( $prima['foglio'] ?? '' );
		$avvisi[] = "{$c['titolo']}: foglio non riconosciuto (serve l'ID o l'indirizzo del foglio), resta quello di prima.";
	}

	$dal_inserito = trim( (string) ( $dati['dal'] ?? '' ) );
	$dal          = wvi_data( $dal_inserito );
	if ( '' !== $dal_inserito && '' === $dal ) {
		$dal      = (string) ( $prima['dal'] ?? '' );
		$avvisi[] = "{$c['titolo']}: data di partenza non valida (serve aaaa-mm-gg), resta quella di prima.";
	}

	$form  = absint( $dati['form'] ?? 0 );
	$nuove = array(
		'url'         => $url,
		'token'       => $token,
		'form'        => $form,
		'dal'         => $dal,
		'foglio'      => $foglio,
		'dalle_nuove' => wvi_casella( $dati, 'dalle_nuove', $c['dalle_nuove'] ),
	);
	if ( $form !== $c['form'] ) {
		delete_option( $c['ultimo'] );
		delete_option( $c['sospese'] );
		// Da capo davvero: anche le iscrizioni arrivate al modulo nuovo prima del cambio nel pannello.
		// Il punto di partenza "dalle nuove" vale solo per la prima attivazione del collegamento.
		update_option( "wvi_{$id}_inizio", 0, false );
		if ( $c['form'] ) {
			$avvisi[] = "{$c['titolo']}: modulo cambiato, l'invio riparte da capo per il modulo n. {$form}.";
		}
	}
	update_option( $c['impostazioni'], array_merge( $prima, $nuove ), false );
	return $avvisi;
}

/**
 * Salva campi, colonne, regole e testi di un collegamento dal pannello. Le voci assenti dal modulo
 * del pannello restano come sono.
 *
 * @param string $id   Collegamento.
 * @param array  $dati Valori inviati.
 */
function wvi_salva_regole( $id, $dati ) {
	$c       = wvi_config( $id );
	$prima   = (array) get_option( $c['impostazioni'], array() );
	$def     = wvi_predefiniti( $id );
	$nuove   = array();
	$testi   = array( 'msg_socio', 'msg_doppio', 'passaggio_avviso', 'recupera_invito', 'recupera_messaggio', 'recupera_avviso', 'recupera_link_testo', 'recupera_gia_testo' );
	$interi  = array( 'recupera_anni', 'recupera_ore', 'recupera_richieste', 'recupera_attesa' );
	$elenchi = array( 'passaggio_campi', 'passaggio_caselle', 'recupera_campi' );
	foreach ( $def as $chiave => $predefinito ) {
		if ( in_array( $chiave, array( 'url', 'token', 'form', 'dal', 'foglio', 'dalle_nuove' ), true ) ) {
			continue;
		}
		if ( is_bool( $predefinito ) ) {
			$nuove[ $chiave ] = wvi_casella( $dati, $chiave, $c[ $chiave ] );
		} elseif ( ! array_key_exists( $chiave, $dati ) ) {
			continue;
		} elseif ( 'colonne' === $chiave ) {
			$nuove[ $chiave ] = wvi_colonne_da_testo( $dati[ $chiave ] );
		} elseif ( in_array( $chiave, $elenchi, true ) ) {
			$nuove[ $chiave ] = wvi_elenco( $dati[ $chiave ] );
		} elseif ( in_array( $chiave, $interi, true ) ) {
			$nuove[ $chiave ] = max( 1, absint( $dati[ $chiave ] ) );
		} elseif ( in_array( $chiave, $testi, true ) ) {
			$nuove[ $chiave ] = wp_kses_post( trim( (string) $dati[ $chiave ] ) );
		} else {
			$nuove[ $chiave ] = sanitize_text_field( (string) $dati[ $chiave ] );
		}
	}
	// Il prefisso dei tab dei soci può finire con uno spazio ("Soci 2026-2027").
	if ( isset( $dati['tab_prefisso'] ) ) {
		$nuove['tab_prefisso'] = ltrim( str_replace( array( "\r", "\n", "\t" ), '', strip_tags( (string) $dati['tab_prefisso'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- wp_strip_all_tags() toglierebbe anche lo spazio finale.
	}
	update_option( $c['impostazioni'], array_merge( $prima, $nuove ), false );
}

/**
 * Salva le impostazioni generali dal pannello. Le voci assenti dal modulo inviato (stanno in un'altra
 * scheda) restano come sono.
 *
 * @param array $dati Valori inviati.
 */
function wvi_salva_generali( $dati ) {
	$prima = (array) get_option( 'wvi_generali', array() );
	$g     = wvi_generali();
	$nuove = array( 'proxy' => wvi_casella( $dati, 'proxy', $g['proxy'] ) );
	if ( isset( $dati['mese_inizio'] ) ) {
		$nuove['mese_inizio'] = min( 12, max( 1, absint( $dati['mese_inizio'] ) ) );
	}
	if ( isset( $dati['email'] ) ) {
		$email          = sanitize_email( (string) $dati['email'] );
		$nuove['email'] = $email ? $email : (string) get_option( 'admin_email' );
	}
	foreach ( array( 'pagina_soci', 'pagina_corsi' ) as $voce ) {
		if ( isset( $dati[ $voce ] ) ) {
			$nuove[ $voce ] = esc_url_raw( trim( (string) $dati[ $voce ] ) );
		}
	}
	if ( isset( $dati['col_rif'] ) ) {
		$rif              = sanitize_text_field( (string) $dati['col_rif'] );
		$nuove['col_rif'] = '' !== $rif ? $rif : 'Rif.';
	}
	if ( isset( $dati['msg_cf'] ) && '' !== trim( (string) $dati['msg_cf'] ) ) {
		$nuove['msg_cf'] = sanitize_text_field( (string) $dati['msg_cf'] );
	}
	if ( isset( $dati['autocomplete'] ) ) {
		$nuove['autocomplete'] = wvi_coppie( (string) $dati['autocomplete'] );
	}
	update_option( 'wvi_generali', array_merge( $prima, $nuove ), false );
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
