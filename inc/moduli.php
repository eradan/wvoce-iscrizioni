<?php
/**
 * Moduli sul sito: regole sul codice fiscale e sull'iscrizione a socio, passaggio dal modulo dei soci a
 * quello dei corsi, "Recupera i tuoi dati", script e stile delle parti aggiunte ai moduli.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/*
 * Regole: codice fiscale controllato davvero (Forminator conta solo i caratteri) e, per anno sociale,
 * iscrizione a socio richiesta per i corsi e unica per il modulo dei soci. Tutte accendibili dal pannello.
 */

/**
 * Errore accanto al campo del codice fiscale quando non torna; nel modulo dei corsi quando manca fra
 * i soci dell'anno, in quello dei soci quando c'è già (il modulo non viene inviato).
 *
 * @param array $submit_errors    Errori già trovati da Forminator.
 * @param int   $form_id          Id del modulo.
 * @param array $field_data_array Campi inviati: array( 'name' => id del campo, 'value' => valore ).
 */
function wvi_controlla_invio( $submit_errors, $form_id, $field_data_array ) {
	if ( ! wvi_attivo() || ! is_array( $field_data_array ) ) {
		return $submit_errors;
	}
	foreach ( array_keys( wvi_collegamenti() ) as $id ) {
		$c = wvi_config( $id );
		if ( ! $c['form'] || $c['form'] !== (int) $form_id || '' === $c['campo_cf'] ) {
			continue;
		}
		if ( ! is_array( $submit_errors ) ) {
			$submit_errors = array();
		}
		foreach ( $field_data_array as $item ) {
			$nome  = is_array( $item ) ? (string) ( $item['name'] ?? '' ) : '';
			$value = $nome === $c['campo_cf'] ? trim( (string) ( $item['value'] ?? '' ) ) : '';
			if ( '' === $value ) {
				continue;
			}
			$adesso = current_time( 'mysql' );
			$anno   = wvi_anno_etichetta( wvi_anno_sociale( $adesso ) );
			if ( $c['controlla'] && ! wvi_codice_fiscale_valido( $value ) ) {
				$submit_errors[][ $c['campo_cf'] ] = 'Controlla il codice fiscale: sono 16 lettere e numeri, come sulla tessera sanitaria.';
			} elseif ( 'corsi' === $id && $c['richiede_socio'] && ! wvi_socio_iscritto( $value, $adesso ) ) {
				$submit_errors[][ $c['campo_cf'] ] = wvi_testo( $c['msg_socio'], array( 'anno' => $anno ) );
			} elseif ( 'soci' === $id && $c['unico'] && wvi_socio_iscritto( $value, $adesso ) ) {
				// L'iscrizione in corso entra nel database dopo questo controllo: si trovano solo le precedenti.
				$submit_errors[][ $c['campo_cf'] ] = wvi_testo( $c['msg_doppio'], array( 'anno' => $anno ) );
			}
		}
	}
	return $submit_errors;
}
add_filter( 'forminator_custom_form_submit_errors', 'wvi_controlla_invio', 15, 3 );

/*
 * Dal modulo dei soci a quello dei corsi. Chi nel modulo dei soci risponde di volersi iscrivere anche
 * a un corso viene mandato da Forminator (comportamento dopo l'invio, rinvio con condizione) alla
 * pagina dei corsi; il plugin aggiunge all'indirizzo un codice usa e getta (?wvi=…) e lì ricompila il
 * modulo dei corsi con i dati appena inviati, caselle comprese. I dati restano sul server (transient
 * di due ore), mai nell'indirizzo. I campi ripresi devono avere lo stesso id nei due moduli.
 */

/*
 * Durata del codice usa e getta del passaggio, in secondi: due ore.
 */
const WVI_PASSAGGIO_DURATA = 7200;

/**
 * Campo del pannello ("address-1.city") come campo e parte.
 *
 * @param string $voce Voce dell'elenco.
 * @return array array( campo, parte ).
 */
function wvi_campo_parte( $voce ) {
	return array_pad( explode( '.', (string) $voce, 2 ), 2, '' );
}

/**
 * Valori di un elenco di campi da un'iscrizione o da un invio: nome del campo nella pagina
 * ("address-1-city") => valore. Vuoti esclusi.
 *
 * @param array    $meta  Campi: id => array( 'value' => … ).
 * @param string[] $campi Campi del pannello ("name-1", "address-1.city").
 */
function wvi_valori_pagina( $meta, $campi ) {
	$dati = array();
	foreach ( (array) $campi as $voce ) {
		list( $field, $part ) = wvi_campo_parte( $voce );
		$valore               = wvi_valore( $meta[ $field ]['value'] ?? '', $field, $part );
		if ( '' !== $valore ) {
			$dati[ $part ? "{$field}-{$part}" : $field ] = $valore;
		}
	}
	return $dati;
}

/**
 * Campi inviati al modulo dei soci, tenuti da parte per il rinvio alla pagina dei corsi.
 *
 * @param array|null $campi Campi da tenere (null: solo lettura).
 * @return array Campi tenuti: nome nella pagina => valore.
 */
function wvi_passaggio_inviati( $campi = null ) {
	static $tenuti = array();
	if ( null !== $campi ) {
		$tenuti = $campi;
	}
	return $tenuti;
}

/**
 * Tiene da parte i campi da riportare da un invio del modulo dei soci.
 *
 * @param array $field_data_array Campi inviati: array( 'name' => id del campo, 'value' => valore ).
 * @param int   $form_id          Id del modulo.
 */
function wvi_passaggio_tieni( $field_data_array, $form_id ) {
	$corsi = wvi_config( 'corsi' );
	if ( wvi_attivo() && $corsi['passaggio'] && (int) wvi_config( 'soci' )['form'] === (int) $form_id && is_array( $field_data_array ) ) {
		$meta = array();
		foreach ( $field_data_array as $item ) {
			if ( is_array( $item ) && isset( $item['name'] ) ) {
				$meta[ (string) $item['name'] ] = array( 'value' => $item['value'] ?? '' );
			}
		}
		wvi_passaggio_inviati( wvi_valori_pagina( $meta, $corsi['passaggio_campi'] ) );
	}
	return $field_data_array;
}
add_filter( 'forminator_custom_form_submit_field_data', 'wvi_passaggio_tieni', 10, 2 );

/**
 * Due indirizzi portano alla stessa pagina (stesso percorso, con o senza barra finale)?
 *
 * @param string $url    Indirizzo.
 * @param string $pagina Indirizzo della pagina.
 */
function wvi_stessa_pagina( $url, $pagina ) {
	$path = static fn( $u ) => trailingslashit( (string) wp_parse_url( (string) $u, PHP_URL_PATH ) );
	return '' !== (string) $url && '' !== (string) $pagina && $path( $url ) === $path( $pagina );
}

/**
 * Invio riuscito del modulo dei soci con rinvio alla pagina dei corsi: i campi ripresi vanno in un
 * transient e il codice nell'indirizzo del rinvio.
 *
 * @param array $response Risposta di Forminator (success, url…).
 * @param int   $form_id  Id del modulo.
 */
function wvi_passaggio_rinvio( $response, $form_id ) {
	$campi = wvi_passaggio_inviati();
	if ( ! is_array( $response ) || empty( $response['success'] ) || empty( $response['url'] ) || ! $campi
		|| (int) wvi_config( 'soci' )['form'] !== (int) $form_id || ! wvi_stessa_pagina( $response['url'], wvi_generali()['pagina_corsi'] ) ) {
		return $response;
	}
	$codice = bin2hex( random_bytes( 16 ) );
	set_transient( 'wvi_passaggio_' . $codice, $campi, WVI_PASSAGGIO_DURATA );
	$response['url'] = add_query_arg( 'wvi', $codice, $response['url'] );
	wvi_passaggio_inviati( array() );
	return $response;
}
add_filter( 'forminator_form_ajax_submit_response', 'wvi_passaggio_rinvio', 10, 2 );
add_filter( 'forminator_form_submit_response', 'wvi_passaggio_rinvio', 10, 2 );

/**
 * Dati da ricompilare per un codice del passaggio, o null se il codice non è valido o è scaduto.
 *
 * @param mixed $codice Codice dall'indirizzo.
 * @return array|null
 */
function wvi_passaggio_dati( $codice ) {
	if ( ! is_string( $codice ) || ! preg_match( '/^[0-9a-f]{32}$/', $codice ) ) {
		return null;
	}
	$campi = get_transient( 'wvi_passaggio_' . $codice );
	return is_array( $campi ) && $campi ? $campi : null;
}

/**
 * Modulo da ricompilare nella pagina (assets/wvoce-iscrizioni.js): pagina fuori dalla cache, perché
 * dentro ci sono dati personali, e dati in fondo alla pagina.
 *
 * @param int      $form    Id del modulo.
 * @param array    $campi   Nome del campo nella pagina => valore (i radio per valore dell'opzione).
 * @param string[] $caselle Caselle da spuntare.
 * @param string   $avviso  Testo sopra il modulo.
 */
function wvi_ricompila( $form, $campi, $caselle, $avviso ) {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	add_action(
		'wp_footer',
		static function () use ( $form, $campi, $caselle, $avviso ) {
			$dati = array(
				'form'    => (int) $form,
				'campi'   => $campi,
				'caselle' => array_values( $caselle ),
				'avviso'  => $avviso,
			);
			printf( '<script id="wvi-ricompila" type="application/json">%s</script>' . "\n", wp_json_encode( $dati, JSON_HEX_TAG | JSON_HEX_AMP ) );
		}
	);
}

/*
 * Recupera i dati dell'ultima iscrizione. Nel modulo dei soci, sotto il codice fiscale, un link chiede
 * al sito di cercare l'ultima iscrizione con quel codice negli ultimi anni sociali. A video il
 * messaggio è sempre lo stesso, per non dire a nessuno chi è socio: la risposta arriva per email
 * all'indirizzo di quell'iscrizione. Se la persona è già iscritta quest'anno l'email lo dice;
 * altrimenti porta un link che riapre il modulo con i campi scelti nel pannello. Nel transient c'è solo
 * il riferimento all'iscrizione: i dati si rileggono all'apertura.
 */

/**
 * Ultima iscrizione a socio con questo codice fiscale negli anni sociali indicati nel pannello.
 *
 * @param string $cf     Codice fiscale.
 * @param string $adesso Data di riferimento, "Y-m-d H:i:s".
 * @return object|null
 */
function wvi_ultima_iscrizione( $cf, $adesso ) {
	$anni   = (int) wvi_config( 'soci' )['recupera_anni'];
	$ultima = null;
	foreach ( wvi_soci_iscrizioni( $cf, wvi_anno_inizio( wvi_anno_sociale( $adesso ) - $anni ) ) as $e ) {
		if ( ! $ultima || (string) $e->date_created_sql > (string) $ultima->date_created_sql ) {
			$ultima = $e;
		}
	}
	return $ultima;
}

/**
 * Indirizzo del visitatore. Con "sito dietro un proxy" nel pannello, se la richiesta arriva dal proxy
 * (indirizzo locale o privato) vale X-Real-IP o il primo di X-Forwarded-For, che imposta il proxy.
 */
function wvi_ip_visitatore() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( ! wvi_generali()['proxy'] || ( $ip && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) ) {
		return $ip;
	}
	$intestazioni = array(
		isset( $_SERVER['HTTP_X_REAL_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ) : '',
		isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) )[0] ) : '',
	);
	foreach ( $intestazioni as $reale ) {
		if ( filter_var( $reale, FILTER_VALIDATE_IP ) ) {
			return $reale;
		}
	}
	return $ip;
}

/**
 * Richiesta di recuperare i dati: controlli, limiti, email. L'esito serve ai test e a scegliere il
 * messaggio; a video "formato" e "troppe" hanno il loro, tutti gli altri lo stesso.
 *
 * @param string $cf     Codice fiscale scritto nel modulo.
 * @param string $ip     Indirizzo del visitatore.
 * @param string $adesso Data di riferimento, "Y-m-d H:i:s".
 * @return string spento|formato|troppe|nessuno|attesa|iscritto|link
 */
function wvi_recupera_richiesta( $cf, $ip, $adesso ) {
	$c = wvi_config( 'soci' );
	if ( ! wvi_attivo() || ! $c['recupera'] || '' === $c['campo_email'] ) {
		return 'spento';
	}
	if ( ! wvi_codice_fiscale_valido( $cf ) ) {
		return 'formato';
	}
	$chiave_ip = 'wvi_recupera_ip_' . md5( (string) $ip );
	$richieste = (int) get_transient( $chiave_ip );
	if ( $richieste >= (int) $c['recupera_richieste'] ) {
		return 'troppe';
	}
	set_transient( $chiave_ip, $richieste + 1, HOUR_IN_SECONDS );

	$cf     = wvi_cf_normalizza( $cf );
	$ultima = wvi_ultima_iscrizione( $cf, $adesso );
	$email  = $ultima ? wvi_valore( $ultima->meta_data[ $c['campo_email'] ]['value'] ?? '', $c['campo_email'] ) : '';
	if ( ! $ultima || ! is_email( $email ) ) {
		return 'nessuno';
	}
	$chiave_cf = 'wvi_recupera_cf_' . md5( $cf );
	if ( get_transient( $chiave_cf ) ) {
		return 'attesa';
	}
	set_transient( $chiave_cf, 1, (int) $c['recupera_attesa'] * MINUTE_IN_SECONDS );

	$anno = wvi_anno_sociale( $adesso );
	$ore  = (int) $c['recupera_ore'];
	if ( wvi_anno_sociale( $ultima->date_created_sql ) === $anno ) {
		$valori = array( 'anno' => wvi_anno_etichetta( $anno ) );
		wp_mail( $email, wvi_testo( $c['recupera_gia_ogg'], $valori ), wvi_testo( $c['recupera_gia_testo'], $valori ) );
		return 'iscritto';
	}

	$codice = bin2hex( random_bytes( 16 ) );
	set_transient(
		'wvi_recupera_' . $codice,
		array(
			'form'  => (int) $ultima->form_id,
			'entry' => (int) $ultima->entry_id,
			'cf'    => $cf,
		),
		$ore * HOUR_IN_SECONDS
	);
	$valori = array(
		'link' => add_query_arg( 'wvi-socio', $codice, wvi_generali()['pagina_soci'] ),
		'ore'  => $ore,
	);
	wp_mail( $email, wvi_testo( $c['recupera_link_ogg'], $valori ), wvi_testo( $c['recupera_link_testo'], $valori ) );
	return 'link';
}

/**
 * Esiti spiegati, per gli amministratori: i visitatori vedono sempre il messaggio neutro.
 */
function wvi_recupera_esiti() {
	$c = wvi_config( 'soci' );
	return array(
		'spento'   => '"Recupera i tuoi dati" è spento o manca il campo dell\'email (Strumenti → Iscrizioni).',
		'nessuno'  => sprintf( 'nessuna iscrizione valida con questo codice fiscale negli ultimi %d anni sociali, o email non valida: nessun invio.', (int) $c['recupera_anni'] ),
		'attesa'   => sprintf( 'a questo codice fiscale è già stata mandata un\'email negli ultimi %d minuti: nessun nuovo invio.', (int) $c['recupera_attesa'] ),
		'iscritto' => 'già iscritto quest\'anno: mandata l\'email "iscrizione già registrata", senza link.',
		'link'     => 'mandata l\'email con il link per recuperare i dati dell\'ultima iscrizione.',
	);
}

/**
 * Messaggio a video per un esito. Agli amministratori loggati l'esito vero, per le prove e per aiutare
 * i soci.
 *
 * @param string $esito Esito di wvi_recupera_richiesta().
 */
function wvi_recupera_messaggio( $esito ) {
	if ( current_user_can( 'manage_options' ) && isset( wvi_recupera_esiti()[ $esito ] ) ) {
		return 'Solo per gli amministratori: ' . wvi_recupera_esiti()[ $esito ];
	}
	if ( 'formato' === $esito ) {
		return 'Controlla il codice fiscale: sono 16 lettere e numeri, come sulla tessera sanitaria.';
	}
	if ( 'troppe' === $esito ) {
		return 'Troppe richieste da questa connessione: riprova fra un\'ora, oppure compila il modulo per intero.';
	}
	$c = wvi_config( 'soci' );
	return wvi_testo( $c['recupera_messaggio'], array( 'ore' => (int) $c['recupera_ore'] ) );
}

/**
 * Endpoint per il link: POST /wp-json/wvi/v1/recupera, con "cf".
 */
function wvi_recupera_rest() {
	register_rest_route(
		'wvi/v1',
		'/recupera',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'args'                => array(
				'cf' => array(
					'type'     => 'string',
					'required' => true,
				),
			),
			'callback'            => static function ( $request ) {
				$esito = wvi_recupera_richiesta( (string) $request['cf'], wvi_ip_visitatore(), current_time( 'mysql' ) );
				return array(
					'ok'        => ! in_array( $esito, array( 'formato', 'troppe' ), true ),
					'messaggio' => wvi_recupera_messaggio( $esito ),
				);
			},
		)
	);
}
add_action( 'rest_api_init', 'wvi_recupera_rest' );

/**
 * Dati da ricompilare per un codice del link, o null se il codice non è valido, è scaduto o
 * l'iscrizione non c'è più (cancellata, spostata nello spam, codice fiscale cambiato).
 *
 * @param mixed $codice Codice dall'indirizzo.
 * @return array|null
 */
function wvi_recupera_dati( $codice ) {
	if ( ! is_string( $codice ) || ! preg_match( '/^[0-9a-f]{32}$/', $codice ) || ! class_exists( 'Forminator_API' ) ) {
		return null;
	}
	$c     = wvi_config( 'soci' );
	$rif   = get_transient( 'wvi_recupera_' . $codice );
	$entry = is_array( $rif ) ? Forminator_API::get_entry( $rif['form'], $rif['entry'] ) : null;
	if ( ! is_object( $entry ) || ! empty( $entry->is_spam ) || wvi_cf_normalizza( $entry->meta_data[ $c['campo_cf'] ]['value'] ?? '' ) !== $rif['cf'] ) {
		return null;
	}
	return wvi_valori_pagina( (array) $entry->meta_data, $c['recupera_campi'] );
}

/**
 * Pagine con un codice: modulo dei corsi dal passaggio (?wvi=), modulo dei soci dal link dell'email
 * (?wvi-socio=). Per gli amministratori anche ?wvi-socio-prova=<id dell'iscrizione> (il numero dopo il
 * trattino nella colonna dei riferimenti del foglio): stessa ricompilazione senza email.
 */
function wvi_pagina_con_codice() {
	if ( ! wvi_attivo() ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Il codice fa da gettone; la prova è solo per gli amministratori e non cambia niente.
	$passaggio = isset( $_GET['wvi'] ) ? wvi_passaggio_dati( sanitize_key( wp_unslash( $_GET['wvi'] ) ) ) : null;
	$recupera  = isset( $_GET['wvi-socio'] ) ? wvi_recupera_dati( sanitize_key( wp_unslash( $_GET['wvi-socio'] ) ) ) : null;
	$prova     = isset( $_GET['wvi-socio-prova'] ) && current_user_can( 'manage_options' ) ? absint( $_GET['wvi-socio-prova'] ) : 0;
	// phpcs:enable
	$corsi = wvi_config( 'corsi' );
	$soci  = wvi_config( 'soci' );
	if ( $passaggio ) {
		wvi_ricompila( $corsi['form'], $passaggio, $corsi['passaggio_caselle'], $corsi['passaggio_avviso'] );
		return;
	}
	$avviso = $soci['recupera_avviso'];
	if ( ! $recupera && $prova && class_exists( 'Forminator_API' ) ) {
		$entry = Forminator_API::get_entry( $soci['form'], $prova );
		if ( is_object( $entry ) && (int) ( $entry->form_id ?? 0 ) === $soci['form'] ) {
			$recupera = wvi_valori_pagina( (array) $entry->meta_data, $soci['recupera_campi'] );
			$avviso   = sprintf( 'Anteprima per l\'amministrazione: modulo compilato dall\'iscrizione n. %d, senza email. Non inviarlo se non serve davvero.', $prova );
		}
	}
	if ( $recupera ) {
		wvi_ricompila( $soci['form'], $recupera, array(), $avviso );
	}
}
add_action( 'template_redirect', 'wvi_pagina_con_codice' );

/**
 * Script e stile nelle pagine con un modulo Forminator, con i dati dei moduli: id, campo del codice
 * fiscale, compilazione automatica, indirizzo e testo di "Recupera i tuoi dati".
 */
function wvi_risorse() {
	$post = get_post();
	if ( ! wvi_attivo() || ! is_singular() || ! $post || false === strpos( (string) $post->post_content, '[forminator_form' ) ) {
		return;
	}
	$soci  = wvi_config( 'soci' );
	$corsi = wvi_config( 'corsi' );
	wp_enqueue_style( 'wvoce-iscrizioni', WVI_URL . 'assets/wvoce-iscrizioni.css', array(), WVI_VERSIONE );
	wp_enqueue_script( 'wvoce-iscrizioni', WVI_URL . 'assets/wvoce-iscrizioni.js', array(), WVI_VERSIONE, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	$moduli = array();
	foreach ( array( $soci, $corsi ) as $c ) {
		if ( $c['form'] ) {
			$moduli[] = array(
				'form' => $c['form'],
				'cf'   => $c['controlla'] ? $c['campo_cf'] : '',
			);
		}
	}
	$dati = array(
		'moduli'       => $moduli,
		'autocomplete' => (object) wvi_generali()['autocomplete'],
	);
	if ( $soci['recupera'] && $soci['form'] && '' !== $soci['campo_cf'] ) {
		$dati['recupera'] = array(
			'form'   => $soci['form'],
			'cf'     => $soci['campo_cf'],
			'url'    => esc_url_raw( rest_url( 'wvi/v1/recupera' ) ),
			'invito' => wp_kses( $soci['recupera_invito'], array( 'a' => array() ) ),
		);
		// Solo da loggati: con il nonce la richiesta riconosce l'utente e agli amministratori mostra l'esito vero
		if ( is_user_logged_in() ) {
			$dati['recupera']['nonce'] = wp_create_nonce( 'wp_rest' );
		}
	}
	wp_add_inline_script( 'wvoce-iscrizioni', 'window.wviModuli = ' . wp_json_encode( $dati ) . ';', 'before' );

	// Nel campo del codice fiscale le lettere si vedono maiuscole mentre si scrive
	$regole = array();
	foreach ( array( $soci, $corsi ) as $c ) {
		if ( $c['form'] && '' !== $c['campo_cf'] ) {
			$regole[] = sprintf( '#forminator-module-%d input[name="%s"]', $c['form'], esc_attr( $c['campo_cf'] ) );
		}
	}
	if ( $regole ) {
		wp_add_inline_style( 'wvoce-iscrizioni', implode( ",\n", $regole ) . " {\n\ttext-transform: uppercase;\n}" );
	}
}
add_action( 'wp_enqueue_scripts', 'wvi_risorse', 21 );
