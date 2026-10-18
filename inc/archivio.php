<?php
/**
 * Lettura delle iscrizioni di Forminator con query mirate sulle sue tabelle, invece di caricare ogni
 * volta tutte le iscrizioni di un modulo con i loro campi (Forminator_API::get_entries(), che oltre a
 * tutto esclude lo spam): una ricerca per codice fiscale legge solo le righe con quel codice, il giro
 * verso i fogli solo i numeri dopo l'ultimo inviato.
 *
 * Valida = iscrizione completa e accettata: non spam, non bozza e, con le versioni di Forminator che
 * hanno lo stato, "active" (fuori anche abbandonate, in attesa di pagamento, pagamento fallito).
 *
 * Senza le tabelle di Forminator (nei test) le stesse funzioni lavorano su Forminator_API.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Database e tabelle di Forminator, o null se non disponibili.
 *
 * @return array{0:wpdb,1:string,2:string,3:bool}|null Database, tabella delle iscrizioni, dei campi, presenza dello stato.
 */
function wvi_archivio() {
	global $wpdb;
	static $tabelle = null;
	if ( ! class_exists( 'Forminator_Database_Tables' ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) ) {
		return null;
	}
	if ( null === $tabelle ) {
		$iscrizioni = Forminator_Database_Tables::get_table_name( Forminator_Database_Tables::FORM_ENTRY );
		$campi      = Forminator_Database_Tables::get_table_name( Forminator_Database_Tables::FORM_ENTRY_META );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome di tabella di Forminator, letto una volta per richiesta.
		$stato   = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$iscrizioni}` LIKE %s", 'status' ) );
		$tabelle = array( $iscrizioni, $campi, $stato );
	}
	return array( $wpdb, $tabelle[0], $tabelle[1], $tabelle[2] );
}

/**
 * Condizione SQL "iscrizione valida" sulla tabella delle iscrizioni (alias e).
 *
 * @param bool $stato La tabella ha la colonna dello stato.
 */
function wvi_archivio_valida( $stato ) {
	return "e.is_spam = 0 AND ( e.draft_id IS NULL OR e.draft_id = '' )" . ( $stato ? " AND e.status = 'active'" : '' );
}

/**
 * Un'iscrizione dell'oggetto di Forminator è valida? (Per i test e per le iscrizioni già caricate.)
 *
 * @param object $e Iscrizione.
 */
function wvi_iscrizione_valida( $e ) {
	if ( ! is_object( $e ) ) {
		return false;
	}
	$stato = (string) ( $e->status ?? '' );
	return empty( $e->is_spam ) && empty( $e->draft_id ) && ( '' === $stato || 'active' === $stato );
}

/**
 * Iscrizione con i suoi campi.
 *
 * @param int $form Id del modulo.
 * @param int $id   Numero dell'iscrizione.
 * @return object|null
 */
function wvi_iscrizione( $form, $id ) {
	$e = class_exists( 'Forminator_API' ) ? Forminator_API::get_entry( $form, $id ) : null;
	return is_object( $e ) && ! empty( $e->entry_id ) ? $e : null;
}

/**
 * Numero più alto fra le iscrizioni di un modulo (spam, bozze e abbandonate comprese).
 *
 * @param int $form Id del modulo.
 */
function wvi_ultimo_id( $form ) {
	$db = wvi_archivio();
	if ( $db ) {
		list( $wpdb, $iscrizioni ) = $db;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabella di Forminator.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(entry_id) FROM `{$iscrizioni}` WHERE form_id = %d", $form ) );
	}
	$max = 0;
	foreach ( wvi_archivio_tutte( $form ) as $e ) {
		$max = max( $max, (int) $e->entry_id );
	}
	return $max;
}

/**
 * Elenco leggero delle iscrizioni di un modulo dopo un numero (più quelle indicate), dalla data di
 * partenza: numero, validità e data, senza i campi. Dalla più vecchia.
 *
 * @param int    $form   Id del modulo.
 * @param int    $dopo   Solo i numeri più alti di questo…
 * @param int[]  $ancora …più questi.
 * @param string $dal    Dalla data "Y-m-d" ('' per tutte).
 * @return array<int,array{id:int,valida:bool,data:string}>
 */
function wvi_archivio_elenco( $form, $dopo, $ancora, $dal ) {
	$ancora = array_values( array_filter( array_map( 'intval', (array) $ancora ) ) );
	$db     = wvi_archivio();
	$righe  = array();
	if ( $db ) {
		list( $wpdb, $iscrizioni, , $stato ) = $db;
		$valida                              = wvi_archivio_valida( $stato );
		$ancora_sql                          = $ancora ? ' OR e.entry_id IN (' . implode( ',', $ancora ) . ')' : '';
		$dal_sql                             = '' !== $dal ? $wpdb->prepare( ' AND e.date_created >= %s', $dal . ' 00:00:00' ) : '';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabella di Forminator; numeri già interi, data preparata.
		$trovate = $wpdb->get_results(
			$wpdb->prepare( "SELECT e.entry_id AS id, ( {$valida} ) AS valida, e.date_created AS data FROM `{$iscrizioni}` e WHERE e.form_id = %d AND ( e.entry_id > %d{$ancora_sql} ){$dal_sql} ORDER BY e.entry_id", $form, $dopo )
		);
		// phpcs:enable
		foreach ( (array) $trovate as $r ) {
			$righe[] = array(
				'id'     => (int) $r->id,
				'valida' => (bool) (int) $r->valida,
				'data'   => (string) $r->data,
			);
		}
		return $righe;
	}
	$annotate = array_flip( $ancora );
	foreach ( wvi_archivio_tutte( $form ) as $e ) {
		$n    = (int) $e->entry_id;
		$data = (string) ( $e->date_created_sql ?? '' );
		if ( ( $n > $dopo || isset( $annotate[ $n ] ) ) && ( '' === $dal || substr( $data, 0, 10 ) >= $dal ) ) {
			$righe[] = array(
				'id'     => $n,
				'valida' => wvi_iscrizione_valida( $e ),
				'data'   => $data,
			);
		}
	}
	usort( $righe, static fn( $a, $b ) => $a['id'] <=> $b['id'] );
	return $righe;
}

/**
 * Iscrizioni valide di un modulo con un certo codice fiscale, dal giorno indicato. Il confronto ignora
 * spazi e maiuscole, come wvi_cf_normalizza().
 *
 * @param int    $form  Id del modulo.
 * @param string $campo Campo del codice fiscale.
 * @param string $cf    Codice fiscale (già normalizzato).
 * @param string $dal   Primo giorno, "Y-m-d".
 * @return object[] Iscrizioni con i loro campi.
 */
function wvi_archivio_cerca_cf( $form, $campo, $cf, $dal ) {
	$db = wvi_archivio();
	if ( $db ) {
		list( $wpdb, $iscrizioni, $campi, $stato ) = $db;
		$valida                                    = wvi_archivio_valida( $stato );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabelle di Forminator.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT e.entry_id FROM `{$iscrizioni}` e JOIN `{$campi}` m ON m.entry_id = e.entry_id
				WHERE e.form_id = %d AND {$valida} AND e.date_created >= %s AND m.meta_key = %s
				AND UPPER( REPLACE( REPLACE( REPLACE( m.meta_value, ' ', '' ), CHAR(9), '' ), CHAR(10), '' ) ) = %s
				ORDER BY e.entry_id",
				$form,
				$dal . ' 00:00:00',
				$campo,
				$cf
			)
		);
		// phpcs:enable
		return array_values( array_filter( array_map( static fn( $id ) => wvi_iscrizione( $form, (int) $id ), (array) $ids ) ) );
	}
	$trovate = array();
	foreach ( wvi_archivio_tutte( $form ) as $e ) {
		if ( ! wvi_iscrizione_valida( $e ) || substr( (string) ( $e->date_created_sql ?? '' ), 0, 10 ) < $dal ) {
			continue;
		}
		$v = $e->meta_data[ $campo ]['value'] ?? '';
		if ( is_scalar( $v ) && wvi_cf_normalizza( $v ) === $cf ) {
			$trovate[] = $e;
		}
	}
	return $trovate;
}

/**
 * Tutte le iscrizioni di un modulo attraverso Forminator_API (solo senza le tabelle, cioè nei test).
 *
 * @param int $form Id del modulo.
 * @return object[]
 */
function wvi_archivio_tutte( $form ) {
	$entries = class_exists( 'Forminator_API' ) ? Forminator_API::get_entries( $form ) : array();
	return array_values( array_filter( is_array( $entries ) ? $entries : array(), 'is_object' ) );
}
