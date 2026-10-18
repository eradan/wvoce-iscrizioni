<?php
/**
 * Iscrizioni dei moduli Forminator nei fogli Google, attraverso l'app web di Apps Script del foglio
 * (script in apps-script/, da incollare dal pannello).
 *
 * Ogni 15 minuti le iscrizioni nuove partono verso l'app web, che le scrive colonna per colonna
 * secondo le intestazioni. Si avanza solo sulle righe che il foglio conferma; la colonna dei
 * riferimenti ("Rif.") evita i doppioni. Senza indirizzo, codice e modulo un collegamento resta spento.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indirizzo, codice e modulo ci sono e Forminator è attivo? Allora il collegamento parte.
 *
 * @param string $id Collegamento.
 */
function wvi_configurato( $id = 'soci' ) {
	$c = wvi_config( $id );
	return '' !== $c['url'] && '' !== $c['token'] && $c['form'] > 0 && class_exists( 'Forminator_API' );
}

/**
 * Numero più alto fra le iscrizioni di un modulo (spam e bozze compresi).
 *
 * @param int $form Id del modulo.
 */
function wvi_ultimo_id( $form ) {
	$entries = Forminator_API::get_entries( $form );
	$max     = 0;
	foreach ( is_array( $entries ) ? $entries : array() as $e ) {
		$max = is_object( $e ) ? max( $max, (int) $e->entry_id ) : $max;
	}
	return $max;
}

/**
 * Ultima iscrizione confermata dal foglio. Per un collegamento che parte dalle iscrizioni nuove, la
 * prima volta si fissa come punto di partenza l'iscrizione più recente già presente.
 *
 * @param string $id Collegamento.
 */
function wvi_ultimo( $id = 'soci' ) {
	$c      = wvi_config( $id );
	$ultimo = get_option( $c['ultimo'], false );
	if ( false !== $ultimo ) {
		return (int) $ultimo;
	}
	if ( ! $c['inizio'] ) {
		return 0;
	}
	$inizio = get_option( $c['inizio'], false );
	if ( false === $inizio ) {
		$inizio = wvi_ultimo_id( $c['form'] );
		update_option( $c['inizio'], $inizio, false );
	}
	return (int) $inizio;
}

/**
 * Iscrizioni di un collegamento, lette una volta:
 * - "da_inviare": dopo l'ultima confermata, dalla data di partenza (se c'è) in poi, niente spam né
 *   bozze salvate, dalla più vecchia; più quelle annotate fra le sospese che ora sono valide;
 * - "sospese": bozze e spam dopo il punto di partenza. Una bozza completata, o un'iscrizione tolta
 *   dallo spam, può avere un numero già superato dall'ultima confermata: senza questo elenco non
 *   partirebbe più.
 *
 * @param string $id Collegamento.
 * @return array{da_inviare:object[],sospese:int[]}
 */
function wvi_iscrizioni( $id = 'soci' ) {
	$c        = wvi_config( $id );
	$ultimo   = wvi_ultimo( $id );
	$base     = $c['inizio'] ? (int) get_option( $c['inizio'], 0 ) : 0;
	$annotate = array_flip( array_map( 'intval', (array) get_option( $c['sospese'], array() ) ) );
	$entries  = Forminator_API::get_entries( $c['form'] );
	$out      = array(
		'da_inviare' => array(),
		'sospese'    => array(),
	);
	foreach ( is_array( $entries ) ? $entries : array() as $e ) {
		if ( ! is_object( $e ) || ( '' !== $c['dal'] && substr( (string) $e->date_created_sql, 0, 10 ) < $c['dal'] ) ) {
			continue;
		}
		$n = (int) $e->entry_id;
		if ( ! empty( $e->is_spam ) || ! empty( $e->draft_id ) ) {
			if ( $n > $base ) {
				$out['sospese'][] = $n;
			}
		} elseif ( $n > $ultimo || isset( $annotate[ $n ] ) ) {
			$out['da_inviare'][] = $e;
		}
	}
	usort( $out['da_inviare'], static fn( $a, $b ) => (int) $a->entry_id <=> (int) $b->entry_id );
	sort( $out['sospese'] );
	return $out;
}

/**
 * Righe per il foglio di un blocco di iscrizioni.
 *
 * @param array  $entries Iscrizioni.
 * @param string $id      Collegamento.
 */
function wvi_righe( $entries, $id = 'soci' ) {
	$c     = wvi_config( $id );
	$righe = array();
	foreach ( $entries as $entry ) {
		foreach ( (array) call_user_func( $c['righe'], $entry ) as $riga ) {
			$righe[] = $riga;
		}
	}
	return $righe;
}

/**
 * Nomi che lo script del foglio usa, mandati a ogni chiamata: lo script resta lo stesso per ogni sito.
 *
 * @param string $id Collegamento.
 */
function wvi_parametri_script( $id ) {
	$c = wvi_config( $id );
	$p = array( 'rif' => wvi_generali()['col_rif'] );
	if ( 'corsi' === $id ) {
		return $p + array(
			'configurazioni' => $c['tab_configurazioni'],
			'riserva'        => $c['tab_riserva'],
			'corso'          => $c['col_corso'],
		);
	}
	return $p + array( 'prefisso' => $c['tab_prefisso'] );
}

/**
 * Chiamata all'app web di un collegamento. Apps Script risponde con un reindirizzamento a
 * googleusercontent.com, da leggere con una GET (seguito come POST darebbe errore).
 *
 * @param string $id   Collegamento.
 * @param array  $dati Dati da mandare insieme al codice (righe, azione…).
 * @return array|WP_Error Risposta del foglio.
 */
function wvi_chiama( $id, $dati ) {
	$c    = wvi_config( $id );
	$dove = "l'indirizzo dell'app web ({$c['titolo']}) in Strumenti → Iscrizioni → Collegamenti";
	if ( '' === $c['url'] || '' === $c['token'] ) {
		return new WP_Error( 'wvi', "{$c['titolo']}: mancano indirizzo o codice segreto dell'app web in Strumenti → Iscrizioni → Collegamenti." );
	}
	$corpo = array( 'token' => $c['token'] ) + ( '' !== $c['foglio'] ? array( 'foglio' => $c['foglio'] ) : array() ) + wvi_parametri_script( $id ) + $dati;
	$args  = array(
		'timeout'     => 30,
		'redirection' => 0,
		'headers'     => array( 'Content-Type' => 'application/json' ),
		'body'        => wp_json_encode( $corpo ),
	);
	$res   = wp_remote_post( $c['url'], $args );
	$code  = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
		$location = (string) wp_remote_retrieve_header( $res, 'location' );
		if ( '' === $location ) {
			return new WP_Error( 'wvi', "Il foglio ha risposto: risposta inattesa (HTTP {$code} senza indirizzo)" );
		}
		$res  = wp_remote_get( $location, array( 'timeout' => 30 ) );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	}
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( in_array( $code, array( 401, 403 ), true ) ) {
		return new WP_Error( 'wvi', "Google chiede di accedere (HTTP {$code}): nel deployment dell'app web \"Chi ha accesso\" deve essere \"Chiunque\" e {$dove} deve finire con /exec." );
	}
	if ( 404 === $code ) {
		return new WP_Error( 'wvi', "App web non trovata (HTTP 404): controlla {$dove}." );
	}
	if ( 200 !== $code || ! is_array( $json ) || empty( $json['ok'] ) ) {
		$errore = is_array( $json ) && ! empty( $json['errore'] ) ? $json['errore'] : 'risposta inattesa (HTTP ' . $code . ')';
		return new WP_Error( 'wvi', 'Il foglio ha risposto: ' . $errore );
	}
	return $json;
}

/**
 * Manda un blocco di righe all'app web.
 *
 * @param array  $righe Righe per il foglio.
 * @param string $id    Collegamento.
 * @return array|WP_Error Riferimenti confermati dal foglio.
 */
function wvi_post( $righe, $id = 'soci' ) {
	$json = wvi_chiama( $id, array( 'righe' => $righe ) );
	return is_wp_error( $json ) ? $json : array_map( 'strval', (array) ( $json['rif'] ?? array() ) );
}

/**
 * Verifica di indirizzo, codice e foglio: un invio senza righe, che il foglio conferma senza
 * scrivere nulla.
 *
 * @param string $id Collegamento.
 * @return string|WP_Error Nome del foglio raggiunto.
 */
function wvi_verifica( $id ) {
	$json = wvi_chiama( $id, array( 'righe' => array() ) );
	return is_wp_error( $json ) ? $json : (string) ( $json['foglio'] ?? '' );
}

/**
 * Invia le iscrizioni nuove di un collegamento, a blocchi di 20. Avanza l'ultima confermata blocco
 * per blocco (un'iscrizione con più righe conta quando il foglio restituisce il suo riferimento); al
 * primo errore si ferma e riprova al giro dopo. Dopo 24 ore di errori avvisa l'amministratore.
 *
 * @param string $id Collegamento.
 * @return array Esito: inviate, errore.
 */
function wvi_sync( $id = 'soci' ) {
	if ( ! wvi_configurato( $id ) ) {
		return array( 'inviate' => 0, 'errore' => 'Collegamento non configurato.' );
	}
	$c       = wvi_config( $id );
	$col_rif = wvi_generali()['col_rif'];
	$stato   = (array) get_option( $c['stato'], array() );
	$inviate = 0;
	$errore  = '';
	$lista   = wvi_iscrizioni( $id );
	$ultimo  = wvi_ultimo( $id );
	// Iscrizioni sospese tornate valide: restano annotate finché il foglio non le conferma.
	$riprese = array();
	foreach ( $lista['da_inviare'] as $e ) {
		if ( (int) $e->entry_id <= $ultimo ) {
			$riprese[ (int) $e->entry_id ] = true;
		}
	}
	foreach ( array_chunk( $lista['da_inviare'], 20 ) as $blocco ) {
		$righe = wvi_righe( $blocco, $id );
		$esito = $righe ? wvi_post( $righe, $id ) : array();
		if ( is_wp_error( $esito ) ) {
			$errore = $esito->get_error_message();
			break;
		}
		if ( array_diff( wp_list_pluck( $righe, $col_rif ), $esito ) ) {
			$errore = 'Il foglio non ha confermato tutte le righe del blocco.';
			break;
		}
		$numeri = array_map( static fn( $e ) => (int) $e->entry_id, $blocco );
		// Mai indietro: un blocco può contenere solo iscrizioni riprese, con numeri già superati.
		$ultimo = max( $ultimo, ...$numeri );
		update_option( $c['ultimo'], $ultimo, false );
		$riprese  = array_diff_key( $riprese, array_flip( $numeri ) );
		$inviate += count( $blocco );
	}

	$sospese = array_merge( $lista['sospese'], array_keys( $riprese ) );
	sort( $sospese );
	update_option( $c['sospese'], $sospese, false );

	$adesso = time();
	if ( $errore ) {
		$stato['errore']     = $errore;
		$stato['errore_dal'] = $stato['errore_dal'] ?? $adesso;
		if ( empty( $stato['avvisato'] ) && $adesso - $stato['errore_dal'] > DAY_IN_SECONDS ) {
			wp_mail(
				get_option( 'admin_email' ),
				get_bloginfo( 'name' ) . ": le iscrizioni ({$c['titolo']}) non arrivano al foglio Google",
				"Da più di un giorno l'invio al foglio Google ({$c['titolo']}) non riesce.\n\nUltimo errore: {$errore}\n\nStato e prova: " . admin_url( 'tools.php?page=wvi-iscrizioni&tab=' . $id )
			);
			$stato['avvisato'] = true;
		}
	} else {
		unset( $stato['errore'], $stato['errore_dal'], $stato['avvisato'] );
	}
	$stato['controllo'] = $adesso;
	if ( $inviate ) {
		$stato['ultimo_invio'] = $adesso;
	}
	update_option( $c['stato'], $stato, false );
	return array( 'inviate' => $inviate, 'errore' => $errore );
}

/**
 * Un giro per ogni collegamento configurato: l'errore di uno non ferma gli altri.
 */
function wvi_sync_tutti() {
	if ( ! wvi_qualcuno() ) {
		wp_clear_scheduled_hook( 'wvi_sync' );
		return;
	}
	foreach ( array_keys( wvi_collegamenti() ) as $id ) {
		if ( wvi_configurato( $id ) ) {
			wvi_sync( $id );
		}
	}
}
add_action( 'wvi_sync', 'wvi_sync_tutti' );

/**
 * C'è almeno un collegamento configurato?
 */
function wvi_qualcuno() {
	foreach ( array_keys( wvi_collegamenti() ) as $id ) {
		if ( wvi_configurato( $id ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Ogni 15 minuti, finché un collegamento è configurato.
 *
 * @param array $schedules Frequenze di wp-cron.
 */
function wvi_schedules( $schedules ) {
	$schedules['wvi_15_minuti'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => 'Ogni 15 minuti',
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'wvi_schedules' );

/**
 * Accende o spegne il giro. Si controlla nell'amministrazione e dopo ogni salvataggio, non a ogni
 * pagina del sito (le impostazioni non sono in autoload); se tutti i collegamenti si spengono lo
 * toglie anche il giro stesso (wvi_sync_tutti()).
 */
function wvi_schedule() {
	$next = wp_next_scheduled( 'wvi_sync' );
	$on   = wvi_qualcuno();
	if ( $on && ! $next ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'wvi_15_minuti', 'wvi_sync' );
	} elseif ( ! $on && $next ) {
		wp_clear_scheduled_hook( 'wvi_sync' );
	}
}
add_action( 'admin_init', 'wvi_schedule' );

/**
 * Prova a secco: quante iscrizioni partirebbero, quante righe in ciascun tab, quante hanno ogni
 * colonna piena. Nessun valore.
 *
 * @param string        $id     Collegamento.
 * @param callable|null $gruppo Nome del gruppo di una riga (predefinito: il suo "_tab").
 */
function wvi_prova( $id = 'soci', $gruppo = null ) {
	$c       = wvi_config( $id );
	$tab     = array();
	$piene   = array_fill_keys( array_keys( (array) $c['colonne'] ), 0 );
	$entries = wvi_iscrizioni( $id )['da_inviare'];
	$righe   = wvi_righe( $entries, $id );
	foreach ( $righe as $riga ) {
		$nome         = $gruppo ? (string) call_user_func( $gruppo, $riga ) : (string) ( $riga['_tab'] ?? '' );
		$tab[ $nome ] = ( $tab[ $nome ] ?? 0 ) + 1;
		foreach ( $piene as $col => $n ) {
			if ( '' !== ( $riga[ $col ] ?? '' ) ) {
				++$piene[ $col ];
			}
		}
	}
	return array( 'totale' => count( $entries ), 'righe' => count( $righe ), 'tab' => $tab, 'piene' => $piene );
}

/**
 * Testo di uno script Apps Script da incollare nel foglio ('' se manca).
 *
 * @param string $id Collegamento.
 */
function wvi_script( $id ) {
	$c    = wvi_config( $id );
	$file = WVI_DIR . 'apps-script/' . basename( (string) $c['script'] );
	return $c['script'] && is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}
