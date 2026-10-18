<?php
/**
 * Test del plugin senza WordPress: codice fiscale, regole sui moduli, fogli dei soci e dei corsi,
 * passaggio ai corsi, "Recupera i tuoi dati", impostazioni. Si lanciano con: php tests/run.php
 *
 * Le poche funzioni di WordPress che servono sono imitate qui sotto, nel modo più semplice. La
 * configurazione di prova è quella di un'associazione reale (moduli 274 e 1617, anno sociale da
 * settembre), impostata come farebbe il pannello.
 *
 * @package WvoceIscrizioni
 */

// phpcs:disable

define( 'ABSPATH', __DIR__ . '/' );
define( 'WVI_DIR', dirname( __DIR__ ) . '/' );
define( 'WVI_URL', 'https://esempio.it/wp-content/plugins/wvoce-iscrizioni/' );
define( 'WVI_VERSIONE', 'test' );
date_default_timezone_set( 'UTC' ); // Come WordPress.

function add_action() {}
function add_filter() {}
$wvi_opzioni = array();
function get_option( $name, $default = false ) {
	global $wvi_opzioni;
	return array_key_exists( $name, $wvi_opzioni ) ? $wvi_opzioni[ $name ] : $default;
}
function update_option( $name, $value ) {
	global $wvi_opzioni;
	$wvi_opzioni[ $name ] = $value;
}
function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
	global $wvi_opzioni;
	if ( array_key_exists( $name, $wvi_opzioni ) ) {
		return false;
	}
	$wvi_opzioni[ $name ] = $value;
	return true;
}
function delete_option( $name ) {
	global $wvi_opzioni;
	unset( $wvi_opzioni[ $name ] );
}
function remove_accents( $text ) {
	return strtr( (string) $text, array( 'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u', 'À' => 'A', 'È' => 'E', 'É' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U' ) );
}
function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function wp_unslash( $text ) {
	return $text;
}
$wvi_singolare = true;
function is_singular() {
	global $wvi_singolare;
	return $wvi_singolare;
}
function plugin_basename( $file ) {
	return 'wvoce-iscrizioni/' . basename( $file );
}
function wp_kses_post( $text ) {
	return (string) $text;
}
function esc_url_raw( $url ) {
	return preg_match( '#^https?://#', (string) $url ) ? (string) $url : '';
}
function sanitize_text_field( $text ) {
	return trim( strip_tags( (string) $text ) );
}
function sanitize_email( $email ) {
	return filter_var( trim( (string) $email ), FILTER_VALIDATE_EMAIL ) ? trim( (string) $email ) : '';
}
function absint( $n ) {
	return abs( (int) $n );
}
class WP_Error {
	public $messaggio;
	public function __construct( $codice, $messaggio ) {
		$this->messaggio = $messaggio;
	}
	public function get_error_message() {
		return $this->messaggio;
	}
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
function wp_json_encode( $x ) {
	return json_encode( $x );
}
$wvi_richieste = array();
$wvi_risposta  = array( 'ok' => true );
$wvi_http      = null; // Risposta HTTP grezza al POST, al posto di quella con $wvi_risposta.
function wp_remote_post( $url, $args ) {
	global $wvi_richieste, $wvi_risposta, $wvi_http;
	$wvi_richieste[] = json_decode( $args['body'], true );
	return $wvi_http ? $wvi_http : array( 'code' => 200, 'body' => json_encode( $wvi_risposta ) );
}
function wp_remote_get( $url, $args = array() ) {
	global $wvi_risposta;
	return '' === (string) $url ? new WP_Error( 'http_request_failed', 'A valid URL was not provided.' ) : array( 'code' => 200, 'body' => json_encode( $wvi_risposta ) );
}
function wp_remote_retrieve_response_code( $res ) {
	return $res['code'];
}
function wp_remote_retrieve_header( $res, $name ) {
	return $res['headers'][ $name ] ?? '';
}
function wp_remote_retrieve_body( $res ) {
	return $res['body'];
}
$wvi_adesso = '2026-10-07 10:00:00';
function current_time( $type ) {
	global $wvi_adesso;
	return $wvi_adesso;
}
$wvi_admin = false;
function current_user_can( $cap ) {
	global $wvi_admin;
	return $wvi_admin;
}
function get_bloginfo( $show ) {
	return 'name' === $show ? 'VivaVoce' : '';
}
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
function wp_list_pluck( $list, $field ) {
	return array_map( static fn( $x ) => is_object( $x ) ? $x->$field : $x[ $field ], $list );
}
$wvi_email = array();
function wp_mail( $a, $oggetto, $testo ) {
	global $wvi_email;
	$wvi_email[] = array( 'a' => $a, 'oggetto' => $oggetto, 'testo' => $testo );
	return true;
}
function is_email( $email ) {
	return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}
function admin_url( $path = '' ) {
	return 'https://wvoce.it/wp-admin/' . $path;
}
$wvi_transient = array();
function set_transient( $name, $value, $durata ) {
	global $wvi_transient;
	$wvi_transient[ $name ] = $value;
}
function get_transient( $name ) {
	global $wvi_transient;
	return $wvi_transient[ $name ] ?? false;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function trailingslashit( $text ) {
	return rtrim( (string) $text, '/' ) . '/';
}
function add_query_arg( $key, $value, $url ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}
function checked( $a, $b = true, $stampa = true ) {
	return (string) $a === (string) $b ? ' checked=\'checked\'' : '';
}
function selected( $a, $b = true, $stampa = true ) {
	return (string) $a === (string) $b ? ' selected=\'selected\'' : '';
}
function delete_transient( $name ) {
	global $wvi_opzioni;
	unset( $wvi_opzioni[ "_transient_{$name}" ] );
}

/**
 * Finto Forminator: iscrizioni e campi dei moduli.
 */
class Forminator_API {
	public static $entries = array();
	public static $campi   = array();
	public static function get_entries( $form ) {
		return self::$entries;
	}
	public static function get_form_fields( $form ) {
		return self::$campi;
	}
	public static function get_entry( $form, $id ) {
		foreach ( self::$entries as $e ) {
			if ( (int) $e->entry_id === (int) $id ) {
				return $e;
			}
		}
		return null;
	}
}

require WVI_DIR . 'inc/schema.php';
require WVI_DIR . 'inc/impostazioni.php';
require WVI_DIR . 'inc/campi.php';
require WVI_DIR . 'inc/archivio.php';
require WVI_DIR . 'inc/soci.php';
require WVI_DIR . 'inc/corsi.php';
require WVI_DIR . 'inc/fogli.php';
require WVI_DIR . 'inc/moduli.php';
require WVI_DIR . 'inc/admin/pannello.php';

/**
 * Configurazione di prova, come la salverebbe il pannello.
 */
function wvi_configura() {
	global $wvi_opzioni;
	$wvi_opzioni = array(
		'wvi_generali'          => array(
			'mese_inizio'  => 9,
			'email'        => 'info@wvoce.it',
			'pagina_soci'  => 'https://wvoce.it/cosa-puoi-fare/diventa-socio/',
			'pagina_corsi' => 'https://wvoce.it/cosa-puoi-fare/iscrizione-corso/',
			'proxy'        => true,
			'col_rif'      => 'Rif.',
		),
		'wvi_soci_impostazioni' => array(
			'form'           => 274,
			'dal'            => '2026-09-01',
			'campo_cf'       => 'text-1',
			'campo_email'    => 'email-1',
			'tab_prefisso'   => 'DomandeAmmissione ',
			'col_data'       => 'Submission Time',
			'colonne'        => array(
				'Tipologia'      => 'radio-6',
				'Nome'           => 'name-1',
				'Cognome'        => 'name-2',
				'CODICE FISCALE' => 'text-1',
				'Email'          => 'email-1',
				'Citta'          => array( 'address-1', 'city' ),
				'GDPR'           => 'consent-1',
			),
			'recupera'       => true,
			'recupera_campi' => array( 'radio-6', 'name-1', 'name-2', 'text-1', 'email-1', 'address-1.street_address', 'address-1.city', 'address-1.zip' ),
		),
		'wvi_corsi_impostazioni' => array(
			'form'              => 1617,
			'dalle_nuove'       => true,
			'campo_cf'          => 'text-1',
			'campo_corsi'       => 'checkbox-1',
			'colonne'           => array(
				'Nome'           => 'name-1',
				'Cognome'        => 'name-2',
				'Codice Fiscale' => 'text-1',
				'Email'          => 'email-1',
			),
			'passaggio'         => true,
			'passaggio_campi'   => array( 'name-1', 'name-2', 'text-1', 'email-1', 'phone-2' ),
			'passaggio_caselle' => array( 'consent-1', 'consent-2', 'consent-3' ),
		),
	);
}
wvi_configura();

$wvi_falliti = 0;
$wvi_totale  = 0;

/**
 * @param string $nome     Cosa si controlla.
 * @param mixed  $atteso   Valore atteso.
 * @param mixed  $ottenuto Valore ottenuto.
 */
function uguale( $nome, $atteso, $ottenuto ) {
	global $wvi_falliti, $wvi_totale;
	++$wvi_totale;
	if ( $atteso === $ottenuto ) {
		return;
	}
	++$wvi_falliti;
	echo "FALLITO: {$nome}\n  atteso:   " . var_export( $atteso, true ) . "\n  ottenuto: " . var_export( $ottenuto, true ) . "\n";
}

// Codice fiscale
uguale( 'CF valido', true, wvi_codice_fiscale_valido( 'RSSMRA85T10A562S' ) );
uguale( 'CF valido, minuscolo e con spazi', true, wvi_codice_fiscale_valido( ' rssmra85t10a562s ' ) );
uguale( 'CF con omocodia', true, wvi_codice_fiscale_valido( 'RSSMRA85T10A56NH' ) );
uguale( 'CF con carattere di controllo sbagliato', false, wvi_codice_fiscale_valido( 'RSSMRA85T10A562T' ) );
uguale( 'CF troppo corto', false, wvi_codice_fiscale_valido( 'RSSMRA85T10A562' ) );
uguale( 'Partita IVA al posto del CF', false, wvi_codice_fiscale_valido( '02441190507' ) );

$wvi_msg_formato = 'Controlla il codice fiscale: sono 16 lettere e numeri, come sulla tessera sanitaria.';
uguale( 'Modulo dei corsi: CF sbagliato segnalato', array( array( 'text-1' => $wvi_msg_formato ) ), wvi_controlla_invio( array(), 1617, array( array( 'name' => 'text-1', 'value' => 'RSSMRA85T10A562T' ) ) ) );
uguale( 'Modulo dei soci: CF sbagliato segnalato', 1, count( wvi_controlla_invio( array(), 274, array( array( 'name' => 'text-1', 'value' => 'RSSMRA85T10A562T' ) ) ) ) );
uguale( 'Altro modulo: nessun controllo', array(), wvi_controlla_invio( array(), 999, array( array( 'name' => 'text-1', 'value' => 'xxx' ) ) ) );

// Anno sociale, tab e data
uguale( 'Tab di settembre', 'DomandeAmmissione 2026-2027', wvi_soci_tab( '2026-09-01 00:00:00' ) );
uguale( 'Tab di agosto', 'DomandeAmmissione 2025-2026', wvi_soci_tab( '2026-08-31 23:59:59' ) );
uguale( 'Anno sociale: 31 agosto, 1° settembre, gennaio', array( 2025, 2026, 2026 ), array( wvi_anno_sociale( '2026-08-31 23:59:59' ), wvi_anno_sociale( '2026-09-01 00:00:00' ), wvi_anno_sociale( '2027-01-15 10:00:00' ) ) );
uguale( 'Data come l\'esportazione di Forminator', 'Ott 5, 2026 @ 10:08 AM', wvi_soci_data( '2026-10-05 10:08:00' ) );
$wvi_opzioni['wvi_generali']['mese_inizio'] = 1;
uguale( 'Anno solare: anno, etichetta, inizio', array( 2026, '2026', '2026-01-01' ), array( wvi_anno_sociale( '2026-08-31 23:59:59' ), wvi_anno_etichetta( 2026 ), wvi_anno_inizio( 2026 ) ) );
$wvi_opzioni['wvi_generali']['mese_inizio'] = 3;
uguale( 'Anno da marzo: febbraio è dell\'anno prima', array( 2025, '2025-2026', '2025-03-01' ), array( wvi_anno_sociale( '2026-02-28 10:00:00' ), wvi_anno_etichetta( 2025 ), wvi_anno_inizio( 2025 ) ) );
wvi_configura();

// Valori dei campi
uguale( 'Consenso dato', 'TRUE', wvi_valore( 'checked', 'consent-1' ) );
uguale( 'Consenso assente', '', wvi_valore( '', 'consent-1' ) );
uguale( 'Parte di un indirizzo', 'Pisa', wvi_valore( array( 'city' => ' Pisa ' ), 'address-1', 'city' ) );
uguale( 'Data a tendina', '07/03/1990', wvi_valore( array( 'day' => '7', 'month' => '3', 'year' => '1990' ), 'date-1' ) );
uguale( 'Caselle', 'Teatro, Letture', wvi_valore( array( 'Teatro', '', 'Letture' ), 'checkbox-2' ) );
uguale( 'File caricato', 'https://esempio.it/a.pdf', wvi_valore( array( 'file' => array( 'file_url' => 'https://esempio.it/a.pdf' ) ), 'upload-1' ) );
uguale( 'Codice fiscale in maiuscolo (campo scelto nel pannello)', 'RSSMRA85T10A562S', wvi_valore( 'rssmra85t10a562s', 'text-1' ) );
uguale( 'Altro campo di testo come scritto', 'rossi', wvi_valore( 'rossi', 'text-2' ) );
uguale( 'Tag HTML tolti', 'Mario', wvi_valore( '<b>Mario</b>', 'name-1' ) );

// Foglio dei soci: righe
$entry = (object) array(
	'form_id'          => 274,
	'entry_id'         => 12,
	'date_created_sql' => '2026-10-05 10:08:00',
	'meta_data'        => array(
		'name-1'    => array( 'value' => 'Maria' ),
		'text-1'    => array( 'value' => 'rssmra85t10a562s' ),
		'address-1' => array( 'value' => array( 'city' => 'Pisa' ) ),
	),
);
$riga  = wvi_soci_righe( $entry )[0];
uguale( 'Riga: riferimento, tab, data', array( 'F274-12', 'DomandeAmmissione 2026-2027', 'Ott 5, 2026 @ 10:08 AM' ), array( $riga['Rif.'], $riga['_tab'], $riga['Submission Time'] ) );
uguale( 'Riga: nome, CF maiuscolo, città', array( 'Maria', 'RSSMRA85T10A562S', 'Pisa' ), array( $riga['Nome'], $riga['CODICE FISCALE'], $riga['Citta'] ) );
uguale( 'Riga: colonna senza valore', '', $riga['Cognome'] );
uguale( 'Riga: tutte le colonne', 7 + 3, count( $riga ) );
$wvi_opzioni['wvi_soci_impostazioni']['col_data'] = '';
uguale( 'Senza colonna della data', false, array_key_exists( 'Submission Time', wvi_soci_righe( $entry )[0] ) );
wvi_configura();

// Corsi: opzioni del modulo, righe, tab
$wvi_opzioni_1617 = array(
	'Adulti: Laboratorio di lettura ad alta voce corale, ottobre-dicembre 2026 (martedì 18.30-20.30)',
	'Adulti: Laboratorio di tecniche attoriali, ottobre-dicembre 2026 (giovedì 18.30-20.30)',
	'Adulti: Laboratorio di messa in scena, gennaio-giugno 2027 (giovedì 18.30-20.30)',
	'Bambini (elementari): Il gioco del teatro, ottobre-dicembre 2026',
);
Forminator_API::$campi = array(
	array( 'element_id' => 'name-1', 'field_label' => 'Nome', 'type' => 'name' ),
	array(
		'element_id'  => 'checkbox-1',
		'field_label' => 'Corso',
		'type'        => 'checkbox',
		'options'     => array_map( static fn( $o ) => array( 'label' => $o, 'value' => $o ), $wvi_opzioni_1617 ),
	),
);
uguale( 'Opzioni del corso lette dal campo scelto nel pannello', $wvi_opzioni_1617, wvi_corsi_opzioni() );
uguale( 'Campi del modulo per il pannello', array( 'name-1', 'checkbox-1' ), array_column( wvi_campi( 1617 ), 'id' ) );

$wvi_corsi_entry = static function ( $corso ) {
	return (object) array(
		'form_id'          => 1617,
		'entry_id'         => 40,
		'date_created_sql' => '2026-10-05 10:08:00',
		'meta_data'        => array(
			'text-1'     => array( 'value' => 'rssmra85t10a562s' ),
			'checkbox-1' => array( 'value' => $corso ),
		),
	);
};
$righe = wvi_corsi_righe( $wvi_corsi_entry( 'Tecnica attoriale' ) );
uguale( 'Corso singolo: una riga con corso, riferimento, CF', array( 1, 'Tecnica attoriale', 'F1617-40', 'RSSMRA85T10A562S' ), array( count( $righe ), $righe[0]['Corso'], $righe[0]['Rif.'], $righe[0]['Codice Fiscale'] ) );
uguale( 'Corso singolo: chiavi nell\'ordine, nessun _tab', array( 'Rif.', 'Nome', 'Cognome', 'Codice Fiscale', 'Email', 'Corso' ), array_keys( $righe[0] ) );
$righe = wvi_corsi_righe( $wvi_corsi_entry( array( 'Lettura', 'Canto', 'Lettura' ) ) );
uguale( 'Più corsi: una riga per corso, senza ripetizioni', array( 'Lettura', 'Canto' ), array_column( $righe, 'Corso' ) );
$righe = wvi_corsi_righe( $wvi_corsi_entry( $wvi_opzioni_1617[2] . ', ' . $wvi_opzioni_1617[1] ) );
uguale( 'Caselle unite in un testo: opzioni del modulo', array( $wvi_opzioni_1617[2], $wvi_opzioni_1617[1] ), array_column( $righe, 'Corso' ) );
uguale( 'Testo unito: opzione contenuta in un\'altra non trovata due volte', array( 'Il gioco del teatro avanzato', 'Dizione' ), wvi_corsi_scelti( 'Il gioco del teatro avanzato, Dizione', array( 'Il gioco del teatro', 'Il gioco del teatro avanzato', 'Dizione' ) ) );
uguale( 'Testo unito senza opzioni note: un corso', array( 'Dizione, Canto' ), wvi_corsi_scelti( 'Dizione, Canto', array() ) );
uguale( 'Nessun corso: una riga con il corso vuoto', array( '' ), array_column( wvi_corsi_righe( $wvi_corsi_entry( '' ) ), 'Corso' ) );
$wvi_opzioni['wvi_corsi_impostazioni']['col_corso'] = 'Laboratorio';
$wvi_opzioni['wvi_generali']['col_rif']             = 'N.';
uguale( 'Nomi delle colonne dal pannello', array( 'F1617-40', 'Lettura' ), array( wvi_corsi_righe( $wvi_corsi_entry( 'Lettura' ) )[0]['N.'], wvi_corsi_righe( $wvi_corsi_entry( 'Lettura' ) )[0]['Laboratorio'] ) );
wvi_configura();

$wvi_mappa = array(
	'mappa' => array(
		$wvi_opzioni_1617[0] => 'lettura-ad-alta-voce-2026',
		$wvi_opzioni_1617[3] => 'gioco-del-teatro-2026',
		'Dizione'            => 'dizione-2027',
	),
	'tab'   => array( 'lettura-ad-alta-voce-2026', 'gioco-del-teatro-2026', 'configurazioni' ),
);
uguale( 'Normalizza: accenti, maiuscole, spazi', 'adulti: lettura e perche', wvi_corsi_normalizza( "  Adulti:  Lettura\tè PERCHÉ " ) );
uguale( 'Normalizza: spazio non separabile, come lo script', 'adulti: lettura', wvi_corsi_normalizza( "Adulti:\u{00A0}Lettura" ) );
uguale( 'Mappa: corso noto', 'lettura-ad-alta-voce-2026', wvi_corsi_tab( $wvi_opzioni_1617[0], $wvi_mappa ) );
uguale( 'Mappa: scritto in altro modo', 'gioco-del-teatro-2026', wvi_corsi_tab( strtoupper( 'Bambini (elementari): Il gioco del teatro,  ottobre-dicembre 2026' ), $wvi_mappa ) );
uguale( 'Mappa: corso assente, tab inesistente, vuoto', array( 'Da smistare', 'Da smistare', 'Da smistare' ), array( wvi_corsi_tab( $wvi_opzioni_1617[1], $wvi_mappa ), wvi_corsi_tab( 'Dizione', $wvi_mappa ), wvi_corsi_tab( '', $wvi_mappa ) ) );
uguale( 'Mappa: il tab di configurazione non è di un corso', 'Da smistare', wvi_corsi_tab( 'Canto', array( 'mappa' => array( 'Canto' => 'configurazioni' ), 'tab' => array( 'configurazioni' ) ) ) );
$wvi_opzioni['wvi_corsi_impostazioni']['tab_riserva'] = 'Altri';
uguale( 'Mappa: tab di riserva dal pannello', 'Altri', wvi_corsi_tab( 'Boh', $wvi_mappa ) );
wvi_configura();

// Regole: corsi riservati ai soci dell'anno, una sola iscrizione a socio per anno
$wvi_socio = static function ( $id, $data, $cf, $extra = array() ) {
	return (object) ( array( 'entry_id' => $id, 'date_created_sql' => $data, 'meta_data' => array( 'text-1' => array( 'value' => $cf ) ) ) + $extra );
};
$wvi_cf_corso  = static fn( $cf ) => wvi_controlla_invio( array(), 1617, array( array( 'name' => 'text-1', 'value' => $cf ) ) );
$wvi_cf_socio  = static fn( $cf ) => wvi_controlla_invio( array(), 274, array( array( 'name' => 'text-1', 'value' => $cf ) ) );
$wvi_msg_socio = 'Questo codice fiscale manca ancora fra i soci 2026-2027: i corsi sono riservati ai soci. Prima occorre iscriversi all\'associazione con il <a href="https://wvoce.it/cosa-puoi-fare/diventa-socio/" target="_blank" rel="noopener">modulo di iscrizione a socio</a>, poi si torna a questa pagina.';
$wvi_msg_doppio = 'Questo codice fiscale risulta già fra i soci 2026-2027: l\'iscrizione di quest\'anno è già registrata. Per domande o per correggere i dati scrivi a <a href="mailto:info@wvoce.it">info@wvoce.it</a>.';
Forminator_API::$entries = array(
	$wvi_socio( 1, '2026-09-03 18:00:00', ' rssmra85t10a562s ' ),
	$wvi_socio( 2, '2026-06-10 18:00:00', 'BNCLRA90A41G702N' ),
	$wvi_socio( 3, '2026-09-20 18:00:00', 'VRDGPP80A01H501U', array( 'is_spam' => 1 ) ),
);
uguale( 'Socio dell\'anno: iscrizione al corso accettata', array(), $wvi_cf_corso( 'RSSMRA85T10A562S' ) );
uguale( 'Socio dell\'anno precedente: errore con il link', array( array( 'text-1' => $wvi_msg_socio ) ), $wvi_cf_corso( 'BNCLRA90A41G702N' ) );
uguale( 'Iscrizione a socio nello spam: errore', array( array( 'text-1' => $wvi_msg_socio ) ), $wvi_cf_corso( 'VRDGPP80A01H501U' ) );
uguale( 'CF sbagliato: solo il messaggio sul formato', 1, count( $wvi_cf_corso( 'RSSMRA85T10A562T' ) ) );
uguale( 'Modulo soci: iscrizione dell\'anno precedente, si rinnova', array(), $wvi_cf_socio( 'BNCLRA90A41G702N' ) );
uguale( 'Modulo soci: già iscritto quest\'anno', array( array( 'text-1' => $wvi_msg_doppio ) ), $wvi_cf_socio( 'rssmra85t10a562s ' ) );
uguale( 'Modulo soci: iscrizione nello spam, si iscrive', array(), $wvi_cf_socio( 'VRDGPP80A01H501U' ) );
$wvi_adesso = '2026-08-20 10:00:00';
uguale( 'Agosto: vale ancora l\'anno precedente', array(), $wvi_cf_corso( 'BNCLRA90A41G702N' ) );
$wvi_adesso = '2026-10-07 10:00:00';
$wvi_opzioni['wvi_corsi_impostazioni']['richiede_socio'] = false;
$wvi_opzioni['wvi_soci_impostazioni']['unico']           = false;
uguale( 'Regole spente: corso e doppia iscrizione accettati', array( array(), array() ), array( $wvi_cf_corso( 'BNCLRA90A41G702N' ), $wvi_cf_socio( 'RSSMRA85T10A562S' ) ) );
$wvi_opzioni['wvi_corsi_impostazioni']['controlla'] = false;
uguale( 'Controllo del CF spento: nessun errore di formato', array(), $wvi_cf_corso( 'RSSMRA85T10A562T' ) );
$wvi_opzioni['wvi_corsi_impostazioni']['form'] = 1800;
uguale( 'Modulo cambiato nel pannello: il vecchio non si controlla più', array(), $wvi_cf_corso( 'xxx' ) );
wvi_configura();
Forminator_API::$entries = array();

// Dal modulo dei soci a quello dei corsi
$wvi_inviati = array(
	array( 'name' => 'name-1', 'value' => 'Mario' ),
	array( 'name' => 'name-2', 'value' => 'Rossi' ),
	array( 'name' => 'text-1', 'value' => 'rssmra85t10a562t' ),
	array( 'name' => 'email-1', 'value' => 'mario@example.com' ),
	array( 'name' => 'phone-2', 'value' => '+39 333 1234567' ),
	array( 'name' => 'address-1', 'value' => array( 'city' => 'Pisa' ) ),
);
$wvi_ok = array( 'success' => true, 'url' => 'https://wvoce.it/cosa-puoi-fare/iscrizione-corso/' );
wvi_passaggio_tieni( $wvi_inviati, 1617 );
uguale( 'Passaggio: modulo dei corsi, campi non tenuti', array(), wvi_passaggio_inviati() );
uguale( 'Passaggio: campi inviati restituiti a Forminator', $wvi_inviati, wvi_passaggio_tieni( $wvi_inviati, 274 ) );
uguale( 'Passaggio: campi scelti nel pannello, CF maiuscolo', array( 'name-1' => 'Mario', 'name-2' => 'Rossi', 'text-1' => 'RSSMRA85T10A562T', 'email-1' => 'mario@example.com', 'phone-2' => '+39 333 1234567' ), wvi_passaggio_inviati() );
uguale( 'Passaggio: rinvio altrove, nessun codice', array( 'success' => true, 'url' => 'https://wvoce.it/grazie/' ), wvi_passaggio_rinvio( array( 'success' => true, 'url' => 'https://wvoce.it/grazie/' ), 274 ) );
$wvi_rinvio = wvi_passaggio_rinvio( $wvi_ok, 274 );
$wvi_codice = preg_match( '/\?wvi=([0-9a-f]{32})$/', $wvi_rinvio['url'], $wvi_m ) ? $wvi_m[1] : '';
uguale( 'Passaggio: solo il codice nell\'indirizzo', array( 32, false ), array( strlen( $wvi_codice ), strpos( $wvi_rinvio['url'], 'RSSMRA' ) ) );
uguale( 'Passaggio: codice valido, dati ripresi', 'RSSMRA85T10A562T', wvi_passaggio_dati( $wvi_codice )['text-1'] ?? '' );
uguale( 'Passaggio: codice sconosciuto o malformato', array( null, null ), array( wvi_passaggio_dati( str_repeat( 'a', 32 ) ), wvi_passaggio_dati( '../x' ) ) );
uguale( 'Passaggio: pagina dei corsi senza la barra finale', true, wvi_stessa_pagina( 'https://wvoce.it/cosa-puoi-fare/iscrizione-corso', 'https://wvoce.it/cosa-puoi-fare/iscrizione-corso/' ) );
uguale( 'Passaggio: dopo il rinvio i campi non restano', $wvi_ok, wvi_passaggio_rinvio( $wvi_ok, 274 ) );
$wvi_opzioni['wvi_corsi_impostazioni']['passaggio'] = false;
wvi_passaggio_tieni( $wvi_inviati, 274 );
uguale( 'Passaggio spento: nessun campo tenuto', array(), wvi_passaggio_inviati() );
wvi_configura();

// Impostazioni: predefiniti neutri, salvataggio dal pannello
$wvi_opzioni = array( 'admin_email' => 'admin@esempio.it' );
$c           = wvi_config( 'soci' );
uguale( 'Predefiniti neutri: nessun modulo, nessun campo, tab "Soci "', array( 0, '', '', 'Soci ', array() ), array( $c['form'], $c['campo_cf'], $c['url'], $c['tab_prefisso'], $c['colonne'] ) );
uguale( 'Predefiniti: anno solare, email dell\'amministratore', array( 1, 'admin@esempio.it' ), array( wvi_generali()['mese_inizio'], wvi_generali()['email'] ) );
uguale( 'Opzioni del plugin', array( 'wvi_soci_impostazioni', 'wvi_soci_ultimo', 'wvi_soci_stato', '' ), array( $c['impostazioni'], $c['ultimo'], $c['stato'], $c['inizio'] ) );
uguale( 'Collegamento senza modulo: spento', false, wvi_configurato( 'soci' ) );

$avvisi = wvi_salva( 'soci', 'collegamenti', array( 'url' => 'https://script.google.com/macros/s/abc/exec', 'token' => ' segreto ', 'form' => '274', 'dal' => '2026-09-15' ) );
$c      = wvi_config( 'soci' );
uguale( 'Salvataggio: nessun avviso', array(), $avvisi );
uguale( 'Salvataggio: valori', array( 'https://script.google.com/macros/s/abc/exec', 'segreto', 274, '2026-09-15' ), array( $c['url'], $c['token'], $c['form'], $c['dal'] ) );
$wvi_opzioni['wvi_soci_ultimo'] = 50;
$avvisi                         = wvi_salva( 'soci', 'collegamenti', array( 'url' => 'https://esempio.it/x', 'token' => '', 'form' => '274', 'dal' => '15/09/2026' ) );
$c                              = wvi_config( 'soci' );
uguale( 'Salvataggio: codice vuoto e data non valida, restano i precedenti', array( 'segreto', '2026-09-15' ), array( $c['token'], $c['dal'] ) );
uguale( 'Salvataggio: due avvisi, punto raggiunto intatto', array( 2, 50 ), array( count( $avvisi ), $wvi_opzioni['wvi_soci_ultimo'] ) );
wvi_salva( 'soci', 'collegamenti', array( 'url' => '', 'form' => '300', 'dal' => '' ) );
uguale( 'Salvataggio: modulo cambiato, si riparte; indirizzo vuoto, spento', array( false, false ), array( isset( $wvi_opzioni['wvi_soci_ultimo'] ), wvi_configurato( 'soci' ) ) );

wvi_salva( 'soci', 'campi', array( 'campo_cf' => 'text-1', 'controlla' => '1', 'tab_prefisso' => 'Tessere ', 'colonne' => "Nome = name-1\nCittà = address-1.city\nAssicurazione =\nsenza uguale\n = vuoto", 'msg_doppio' => 'Già socio {anno}' ) );
wvi_salva( 'soci', 'funzioni', array( 'recupera' => '1', 'recupera_campi' => "name-1, address-1.city\nemail-1", 'recupera_anni' => '0' ) );
$c = wvi_config( 'soci' );
uguale( 'Regole: colonne dal testo del pannello', array( 'Nome' => 'name-1', 'Città' => array( 'address-1', 'city' ), 'Assicurazione' => '' ), $c['colonne'] );
uguale( 'Regole: colonne di nuovo in testo', "Nome = name-1\nCittà = address-1.city\nAssicurazione = ", wvi_colonne_in_testo( $c['colonne'] ) );
uguale( 'Regole: elenco, numeri almeno 1, prefisso con lo spazio', array( array( 'name-1', 'address-1.city', 'email-1' ), 1, 'Tessere ' ), array( $c['recupera_campi'], $c['recupera_anni'], $c['tab_prefisso'] ) );
uguale( 'Regole: casella della scheda non spuntata vuol dire spenta', array( true, false, true ), array( $c['recupera'], $c['unico'], $c['controlla'] ) );
uguale( 'Regole: collegamento intatto', array( 300, '' ), array( $c['form'], $c['url'] ) );
// Ogni scheda salva solo le sue voci: le caselle delle altre restano com'erano
wvi_salva( 'soci', 'campi', array( 'controlla' => '1', 'unico' => '1', 'campo_cf' => 'text-1' ) );
uguale( 'Regole: salvare "Campi e regole" non spegne "Recupera i tuoi dati"', array( true, true, true ), array( wvi_config( 'soci' )['recupera'], wvi_config( 'soci' )['unico'], wvi_config( 'soci' )['controlla'] ) );
wvi_salva( 'soci', 'funzioni', array( 'recupera' => '1', 'recupera_invito' => 'Già stato socio? <a>Recupera i tuoi dati</a>' ) );
uguale( 'Regole: salvare "Passaggio e recupero" non spegne i controlli', array( true, true, true ), array( wvi_config( 'soci' )['recupera'], wvi_config( 'soci' )['unico'], wvi_config( 'soci' )['controlla'] ) );
wvi_salva( 'soci', 'campi', array( 'campo_cf' => 'text-1', 'controlla' => '1', 'unico' => '1', 'tab_prefisso' => 'Tessere ' ) );
wvi_salva( 'soci', 'funzioni', array( 'recupera' => '1', 'tab_prefisso' => 'Altro ' ) );
uguale( 'Regole: una voce di un\'altra scheda inviata per errore non passa', 'Tessere ', wvi_config( 'soci' )['tab_prefisso'] );
$avvisi = wvi_salva( 'generali', 'collegamenti', array( 'mese_inizio' => '9', 'email' => 'non-valida', 'msg_cf' => 'CF sbagliato', 'pagina_soci' => 'https://esempio.it/soci/', 'col_rif' => '', 'proxy' => '1' ) );
wvi_salva( 'generali', 'funzioni', array( 'autocomplete' => "name-1 = given-name\nname-2=family-name\nsenza" ) );
$g = wvi_generali();
uguale( 'Generali: mese, email non valida sostituita, riferimenti obbligatori con avviso', array( 9, 'admin@esempio.it', 'Rif.', 1 ), array( $g['mese_inizio'], $g['email'], $g['col_rif'], count( $avvisi ) ) );
uguale( 'Generali: compilazione automatica', array( 'name-1' => 'given-name', 'name-2' => 'family-name' ), $g['autocomplete'] );
wvi_salva( 'generali', 'collegamenti', array( 'mese_inizio' => '9', 'email' => 'admin@esempio.it', 'msg_cf' => 'CF sbagliato', 'pagina_soci' => 'https://esempio.it/soci/', 'col_rif' => 'Rif.', 'proxy' => '1' ) );
uguale( 'Generali: salvare la scheda Collegamenti non cancella la compilazione automatica', array( 'name-1' => 'given-name', 'name-2' => 'family-name' ), wvi_generali()['autocomplete'] );
wvi_salva( 'generali', 'funzioni', array( 'autocomplete' => "name-1 = given-name\nname-2=family-name" ) );
uguale( 'Generali: salvare la compilazione automatica non spegne il proxy né cambia la pagina', array( true, 'https://esempio.it/soci/' ), array( wvi_generali()['proxy'], wvi_generali()['pagina_soci'] ) );
uguale( 'Testo con i segnaposto', 'VivaVoce: 2026 a admin@esempio.it, https://esempio.it/soci/', wvi_testo( '{associazione}: {anno} a {email}, {link_soci}', array( 'anno' => 2026 ) ) );

wvi_importa(
	array(
		'generali' => array( 'mese_inizio' => 9 ),
		'corsi'    => array( 'form' => 1617, 'dalle_nuove' => true ),
		'stato'    => array( 'corsi' => array( 'ultimo' => 77, 'inizio' => 70, 'sospese' => array( 75 ) ) ),
	)
);
uguale( 'Importazione: impostazioni e stato', array( 1617, 77, 70, array( 75 ), 'wvi_corsi_inizio' ), array( wvi_config( 'corsi' )['form'], $wvi_opzioni['wvi_corsi_ultimo'], $wvi_opzioni['wvi_corsi_inizio'], $wvi_opzioni['wvi_corsi_sospese'], wvi_config( 'corsi' )['inizio'] ) );
uguale( 'Importazione: le altre generali restano', 'https://esempio.it/soci/', wvi_generali()['pagina_soci'] );
uguale( 'ID del foglio dall\'indirizzo, nudo, non valido', array( '1TvSREeUfoeAcA1uI3Hx9qpytOVUOMQQc5664DqwxUOw', '1TvSREeUfoeAcA1uI3Hx9qpytOVUOMQQc5664DqwxUOw', '' ), array( wvi_id_foglio( 'https://docs.google.com/spreadsheets/d/1TvSREeUfoeAcA1uI3Hx9qpytOVUOMQQc5664DqwxUOw/edit#gid=0' ), wvi_id_foglio( ' 1TvSREeUfoeAcA1uI3Hx9qpytOVUOMQQc5664DqwxUOw ' ), wvi_id_foglio( 'Albo Soci' ) ) );

// Invio: partenza dalle nuove, bozze e spam, punto raggiunto
wvi_configura();
Forminator_API::$entries = array( (object) array( 'entry_id' => 7 ), (object) array( 'entry_id' => 31, 'is_spam' => 1 ), (object) array( 'entry_id' => 12 ) );
uguale( 'Partenza dalle nuove: iscrizione più recente, salvata', array( 31, 31 ), array( wvi_ultimo( 'corsi' ), $wvi_opzioni['wvi_corsi_inizio'] ) );
Forminator_API::$entries[] = (object) array( 'entry_id' => 32, 'date_created_sql' => '2026-10-06 09:00:00' );
uguale( 'Partenza dalle nuove: parte solo la nuova', array( 32 ), array_map( static fn( $e ) => $e->entry_id, wvi_iscrizioni( 'corsi' )['da_inviare'] ) );
uguale( 'Soci: si parte da zero', 0, wvi_ultimo( 'soci' ) );

$wvi_opzioni['wvi_soci_ultimo'] = 10;
Forminator_API::$entries        = array(
	(object) array( 'form_id' => 274, 'entry_id' => 8, 'date_created_sql' => '2026-09-10 10:00:00', 'draft_id' => 'abc' ),
	(object) array( 'form_id' => 274, 'entry_id' => 11, 'date_created_sql' => '2026-09-12 10:00:00' ),
);
uguale( 'Bozza sotto il punto raggiunto: annotata, parte solo l\'altra', array( array( 11 ), array( 8 ) ), array( array_map( static fn( $e ) => $e->entry_id, wvi_iscrizioni( 'soci' )['da_inviare'] ), wvi_iscrizioni( 'soci' )['sospese'] ) );
$wvi_opzioni['wvi_soci_sospese']        = array( 8 );
Forminator_API::$entries[0]->draft_id = '';
$wvi_opzioni['wvi_soci_impostazioni'] += array( 'url' => 'https://script.google.com/macros/s/abc/exec', 'token' => 't', 'foglio' => '1TvSREeUfoeAcA1uI3Hx9qpytOVUOMQQc5664DqwxUOw' );
$wvi_risposta                          = array( 'ok' => false, 'errore' => 'foglio occupato' );
wvi_sync( 'soci' );
uguale( 'Invio non riuscito: ripresa annotata, punto raggiunto fermo', array( array( 8 ), 10 ), array( $wvi_opzioni['wvi_soci_sospese'], $wvi_opzioni['wvi_soci_ultimo'] ) );
uguale( 'Richiesta: codice, foglio, nomi per lo script, righe', array( 't', '1TvSREeUfoeAcA1uI3Hx9qpytOVUOMQQc5664DqwxUOw', 'Rif.', 'DomandeAmmissione ', 2 ), array( end( $wvi_richieste )['token'], end( $wvi_richieste )['foglio'], end( $wvi_richieste )['rif'], end( $wvi_richieste )['prefisso'], count( end( $wvi_richieste )['righe'] ) ) );
$wvi_risposta = array( 'ok' => true, 'rif' => array( 'F274-8', 'F274-11' ) );
uguale( 'Invio riuscito: due iscrizioni, punto raggiunto avanti, nessuna sospesa', array( 2, 11, array() ), array( wvi_sync( 'soci' )['inviate'], $wvi_opzioni['wvi_soci_ultimo'], $wvi_opzioni['wvi_soci_sospese'] ) );
Forminator_API::$entries = array();

$wvi_opzioni['wvi_corsi_impostazioni'] += array( 'url' => 'https://script.google.com/macros/s/abc/exec', 'token' => 't' );
$wvi_risposta                           = array( 'ok' => true, 'foglio' => 'PROVA Iscrizione Corsi', 'rif' => array() );
uguale( 'Verifica: nome del foglio raggiunto', 'PROVA Iscrizione Corsi', wvi_verifica( 'corsi' ) );
uguale( 'Corsi: nomi per lo script, senza foglio', array( 'configurazioni', 'Da smistare', 'Corso', false ), array( end( $wvi_richieste )['configurazioni'], end( $wvi_richieste )['riserva'], end( $wvi_richieste )['corso'], array_key_exists( 'foglio', end( $wvi_richieste ) ) ) );
$wvi_http = array( 'code' => 302, 'body' => '' );
uguale( 'Reindirizzamento senza indirizzo: errore chiaro', 'Il foglio ha risposto: risposta inattesa (HTTP 302 senza indirizzo)', wvi_verifica( 'corsi' )->get_error_message() );
$wvi_http     = array( 'code' => 302, 'body' => '', 'headers' => array( 'location' => 'https://script.googleusercontent.com/echo' ) );
$wvi_risposta = array( 'ok' => true, 'foglio' => 'Iscrizione Corsi', 'rif' => array() );
uguale( 'Reindirizzamento seguito con una GET', 'Iscrizione Corsi', wvi_verifica( 'corsi' ) );
$wvi_http     = null;
$wvi_risposta = array( 'ok' => false, 'errore' => 'foglio non raggiungibile' );
uguale( 'Verifica: errore dello script', 'Il foglio ha risposto: foglio non raggiungibile', wvi_verifica( 'corsi' )->get_error_message() );
uguale( 'Script da copiare: soci e corsi', array( true, true ), array( false !== strpos( wvi_script( 'soci' ), 'function impostazioni_(dati)' ), false !== strpos( wvi_script( 'corsi' ), "var CONFIGURAZIONI = 'configurazioni';" ) ) );

// Invio 1.1.1: spam recuperato, abbandonate, attesa delle nuove, blocco, prima configurazione, colonna del corso
wvi_configura();
$wvi_opzioni['wvi_soci_impostazioni'] += array( 'url' => 'https://script.google.com/macros/s/abc/exec', 'token' => 't' );
$wvi_opzioni['wvi_soci_ultimo']        = 10;
$wvi_iscr = static fn( $id, $data, $extra = array() ) => (object) ( array( 'form_id' => 274, 'entry_id' => $id, 'date_created_sql' => $data, 'meta_data' => array( 'name-1' => array( 'value' => 'N' . $id ) ) ) + $extra );
Forminator_API::$entries = array(
	$wvi_iscr( 9, '2026-09-10 10:00:00', array( 'is_spam' => 1 ) ),
	$wvi_iscr( 11, '2026-09-12 10:00:00' ),
	$wvi_iscr( 12, '2026-09-13 10:00:00', array( 'status' => 'abandoned' ) ),
	$wvi_iscr( 13, '2026-10-07 09:59:00' ),
	$wvi_iscr( 14, '2026-10-07 09:50:00' ),
);
$wvi_l = wvi_iscrizioni( 'soci' );
uguale( 'Spam e abbandonata sospese; ci si ferma alla prima appena arrivata (13), anche la 14 aspetta', array( array( 11 ), array( 9, 12 ) ), array( array_map( static fn( $e ) => $e->entry_id, $wvi_l['da_inviare'] ), $wvi_l['sospese'] ) );
$wvi_risposta = array( 'ok' => true, 'rif' => array( 'F274-11' ) );
uguale( 'Invio: una iscrizione, punto raggiunto 11, non oltre la 13 in attesa', array( 1, 11 ), array( wvi_sync( 'soci' )['inviate'], $wvi_opzioni['wvi_soci_ultimo'] ) );
Forminator_API::$entries[0]->is_spam = 0;
uguale( 'Tolta dallo spam con numero superato: parte', array( 9 ), array_map( static fn( $e ) => $e->entry_id, wvi_iscrizioni( 'soci' )['da_inviare'] ) );
$wvi_adesso = '2026-10-07 10:05:00';
uguale( 'Passati due minuti: partono la 9 recuperata, la 13 e la 14', array( 9, 13, 14 ), array_map( static fn( $e ) => $e->entry_id, wvi_iscrizioni( 'soci' )['da_inviare'] ) );
$wvi_opzioni['wvi_soci_invio_in_corso'] = time();
uguale( 'Invio già in corso: fermo, nessuna richiesta', array( 0, 'Un altro invio è in corso: riprova fra qualche minuto.' ), array_values( wvi_sync( 'soci' ) ) );
$wvi_opzioni['wvi_soci_invio_in_corso'] = time() - 3600;
$wvi_risposta                           = array( 'ok' => true, 'rif' => array( 'F274-9', 'F274-13', 'F274-14' ) );
uguale( 'Blocco rimasto da un invio interrotto: si invia e si libera', array( 3, false, 14, array( 12 ) ), array( wvi_sync( 'soci' )['inviate'], isset( $wvi_opzioni['wvi_soci_invio_in_corso'] ), $wvi_opzioni['wvi_soci_ultimo'], $wvi_opzioni['wvi_soci_sospese'] ) );
$wvi_adesso = '2026-10-07 10:00:00';
uguale( 'Numero più alto, spam e abbandonate comprese', 14, wvi_ultimo_id( 274 ) );

$wvi_opzioni = array( 'admin_email' => 'a@esempio.it' );
Forminator_API::$entries = array( $wvi_iscr( 3, '2026-09-01 10:00:00' ), $wvi_iscr( 5, '2026-09-02 10:00:00' ) );
wvi_salva( 'corsi', 'collegamenti', array( 'url' => 'https://script.google.com/macros/s/abc/exec', 'token' => 't', 'form' => '1617', 'dalle_nuove' => '1' ) );
uguale( 'Prima configurazione con "solo le nuove": partenza dall\'ultima già presente', array( 5, array() ), array( wvi_ultimo( 'corsi' ), wvi_iscrizioni( 'corsi' )['da_inviare'] ) );
wvi_salva( 'corsi', 'collegamenti', array( 'url' => 'https://script.google.com/macros/s/abc/exec', 'token' => '', 'form' => '1800', 'dalle_nuove' => '1' ) );
uguale( 'Modulo cambiato: si riparte dalla prima del nuovo', 0, wvi_ultimo( 'corsi' ) );
$wvi_avvisi = wvi_salva( 'corsi', 'campi', array( 'col_corso' => '', 'tab_configurazioni' => 'configurazioni', 'tab_riserva' => 'Da smistare', 'colonne' => "Nome = name-1\ncorso = checkbox-1" ) );
uguale( 'Colonna del corso vuota o ripetuta fra le colonne: corretta con due avvisi', array( 'Corso', array( 'Nome' => 'name-1' ), 2 ), array( wvi_config( 'corsi' )['col_corso'], wvi_config( 'corsi' )['colonne'], count( $wvi_avvisi ) ) );
Forminator_API::$entries = array();

// Recupera i tuoi dati: email all'indirizzo dell'ultima iscrizione, a video sempre lo stesso
wvi_configura();
$wvi_adesso    = '2026-10-07 10:00:00';
$wvi_transient = array();
$wvi_email     = array();
$wvi_iscritto  = static function ( $id, $data, $cf, $email, $extra = array() ) {
	return (object) ( array(
		'entry_id'         => $id,
		'form_id'          => 274,
		'date_created_sql' => $data,
		'meta_data'        => array(
			'text-1'    => array( 'value' => $cf ),
			'email-1'   => array( 'value' => $email ),
			'name-1'    => array( 'value' => 'Laura' ),
			'radio-6'   => array( 'value' => 'individuo' ),
			'address-1' => array( 'value' => array( 'street_address' => 'Via Roma 1', 'city' => 'Pisa', 'zip' => '56125' ) ),
			'upload-1'  => array( 'value' => array( 'file' => array( 'file_url' => 'https://wvoce.it/ricevuta.pdf' ) ) ),
			'consent-1' => array( 'value' => 'checked' ),
		),
	) + $extra );
};
Forminator_API::$entries = array(
	$wvi_iscritto( 40, '2024-10-01 10:00:00', 'BNCLRA90A41G702N', 'vecchia@example.com' ),
	$wvi_iscritto( 41, '2025-09-15 10:00:00', 'bnclra90a41g702n ', 'laura@example.com' ),
	$wvi_iscritto( 42, '2025-11-15 10:00:00', 'BNCLRA90A41G702N', 'spam@example.com', array( 'is_spam' => 1 ) ),
	$wvi_iscritto( 43, '2026-09-10 10:00:00', 'RSSMRA85T10A562S', 'mario@example.com' ),
	$wvi_iscritto( 44, '2022-10-01 10:00:00', 'VRDGPP80A01H501U', 'troppo@example.com' ),
);
uguale( 'Recupera: l\'iscrizione più recente, non spam', 41, wvi_ultima_iscrizione( 'BNCLRA90A41G702N', $wvi_adesso )->entry_id ?? 0 );
uguale( 'Recupera: oltre gli anni del pannello non si cerca', null, wvi_ultima_iscrizione( 'VRDGPP80A01H501U', $wvi_adesso ) );
$wvi_ripresi = wvi_valori_pagina( Forminator_API::$entries[1]->meta_data, wvi_config( 'soci' )['recupera_campi'] );
uguale( 'Recupera: campi del pannello, CF maiuscolo, indirizzo per parti', array( 'radio-6' => 'individuo', 'name-1' => 'Laura', 'text-1' => 'BNCLRA90A41G702N', 'email-1' => 'laura@example.com', 'address-1-street_address' => 'Via Roma 1', 'address-1-city' => 'Pisa', 'address-1-zip' => '56125' ), $wvi_ripresi );
uguale( 'Recupera: CF sbagliato, nessuna email', array( 'formato', array() ), array( wvi_recupera_richiesta( 'BNCLRA90A41G702X', '1.2.3.4', $wvi_adesso ), $wvi_email ) );
uguale( 'Recupera: CF sconosciuto, nessuna email', array( 'nessuno', array() ), array( wvi_recupera_richiesta( 'VRDGPP80A01H501U', '1.2.3.4', $wvi_adesso ), $wvi_email ) );
uguale( 'Recupera: socio dell\'anno scorso, email con il link', 'link', wvi_recupera_richiesta( 'BNCLRA90A41G702N', '1.2.3.4', $wvi_adesso ) );
uguale( 'Recupera: all\'indirizzo dell\'ultima iscrizione, oggetto col nome del sito', array( 'laura@example.com', 'VivaVoce: recupera i dati della tua iscrizione' ), array( $wvi_email[0]['a'] ?? '', $wvi_email[0]['oggetto'] ?? '' ) );
$wvi_codice = preg_match( '/wvi-socio=([0-9a-f]{32})/', $wvi_email[0]['testo'] ?? '', $wvi_m ) ? $wvi_m[1] : '';
uguale( 'Recupera: nel link solo il codice, sulla pagina dei soci', array( 32, false, true ), array( strlen( $wvi_codice ), strpos( $wvi_email[0]['testo'] ?? '', 'Via Roma' ), false !== strpos( $wvi_email[0]['testo'] ?? '', 'https://wvoce.it/cosa-puoi-fare/diventa-socio/?wvi-socio=' ) ) );
uguale( 'Recupera: il link riapre il modulo con i dati', $wvi_ripresi, wvi_recupera_dati( $wvi_codice ) );
uguale( 'Recupera: codice sconosciuto o malformato', array( null, null ), array( wvi_recupera_dati( str_repeat( 'b', 32 ) ), wvi_recupera_dati( '../x' ) ) );
uguale( 'Recupera: seconda richiesta a breve, nessuna email', array( 'attesa', 1 ), array( wvi_recupera_richiesta( 'BNCLRA90A41G702N', '1.2.3.4', $wvi_adesso ), count( $wvi_email ) ) );
uguale( 'Recupera: già iscritto quest\'anno, email senza link', 'iscritto', wvi_recupera_richiesta( 'RSSMRA85T10A562S', '1.2.3.4', $wvi_adesso ) );
uguale( 'Recupera: email "già registrata" con l\'anno, senza link', array( 'mario@example.com', 'VivaVoce: la tua iscrizione 2026-2027 è già registrata', false ), array( $wvi_email[1]['a'] ?? '', $wvi_email[1]['oggetto'] ?? '', strpos( $wvi_email[1]['testo'] ?? '', 'wvi-socio=' ) ) );
Forminator_API::$entries[1]->is_spam = 1;
uguale( 'Recupera: iscrizione spostata nello spam, il link non vale più', null, wvi_recupera_dati( $wvi_codice ) );
uguale( 'Recupera: quinta richiesta dallo stesso IP, ancora ammessa', 'nessuno', wvi_recupera_richiesta( 'VRDGPP80A01H501U', '1.2.3.4', $wvi_adesso ) );
uguale( 'Recupera: sesta richiesta dallo stesso IP, troppe', 'troppe', wvi_recupera_richiesta( 'VRDGPP80A01H501U', '1.2.3.4', $wvi_adesso ) );
uguale( 'Recupera: un altro IP può ancora', 'nessuno', wvi_recupera_richiesta( 'VRDGPP80A01H501U', '5.6.7.8', $wvi_adesso ) );
uguale( 'Recupera: stesso messaggio a video con o senza socio, già iscritto o in attesa', array( true, true, true ), array( wvi_recupera_messaggio( 'link' ) === wvi_recupera_messaggio( 'nessuno' ), wvi_recupera_messaggio( 'link' ) === wvi_recupera_messaggio( 'iscritto' ), wvi_recupera_messaggio( 'link' ) === wvi_recupera_messaggio( 'attesa' ) ) );
uguale( 'Recupera: messaggio con le ore del pannello', true, false !== strpos( wvi_recupera_messaggio( 'link' ), '24 ore' ) );
$wvi_admin = true;
uguale( 'Recupera: agli amministratori l\'esito vero', array( true, true ), array( false !== strpos( wvi_recupera_messaggio( 'attesa' ), '5 minuti' ), wvi_recupera_messaggio( 'link' ) !== wvi_recupera_messaggio( 'nessuno' ) ) );
$wvi_admin = false;
$wvi_opzioni['wvi_soci_impostazioni']['recupera'] = false;
uguale( 'Recupera spento: nessuna ricerca', 'spento', wvi_recupera_richiesta( 'BNCLRA90A41G702N', '9.9.9.9', $wvi_adesso ) );
Forminator_API::$entries = array();

// Messaggio sul codice fiscale dal pannello, pagine con un modulo, campi indicati ma assenti dal modulo
wvi_configura();
$wvi_opzioni['wvi_generali']['msg_cf'] = 'Codice fiscale non valido.';
uguale( 'Messaggio sul CF dal pannello: invio e recupero', array( array( array( 'text-1' => 'Codice fiscale non valido.' ) ), 'Codice fiscale non valido.' ), array( wvi_controlla_invio( array(), 1617, array( array( 'name' => 'text-1', 'value' => 'X' ) ) ), wvi_recupera_messaggio( 'formato' ) ) );
uguale( 'Pagina con lo shortcode, con il blocco, senza modulo', array( true, true, false ), array( wvi_pagina_con_modulo( (object) array( 'post_content' => '[forminator_form id="274"]' ) ), wvi_pagina_con_modulo( (object) array( 'post_content' => '<!-- wp:forminator/forms {"module_id":"274"} /-->' ) ), wvi_pagina_con_modulo( (object) array( 'post_content' => 'Ciao' ) ) ) );
$wvi_singolare = false;
uguale( 'Archivio: niente script anche con il modulo', false, wvi_pagina_con_modulo( (object) array( 'post_content' => '[forminator_form id="274"]' ) ) );
$wvi_singolare = true;
Forminator_API::$campi = array(
	array( 'element_id' => 'name-1', 'field_label' => 'Nome', 'type' => 'name' ),
	array( 'element_id' => 'address-1', 'field_label' => 'Indirizzo', 'type' => 'address' ),
	array( 'element_id' => 'text-1', 'field_label' => 'CF', 'type' => 'text' ),
);
uguale( 'Campi assenti: parti di indirizzo e nomi nella pagina riconosciuti', array( 'nmae-1', 'email-1' ), wvi_campi_assenti( 274, array( 'name-1', 'nmae-1', 'address-1.city', 'address-1-zip', 'text-1', 'email-1', '' ) ) );
Forminator_API::$campi = array();
uguale( 'Campi assenti: modulo che non si legge, nessun avviso', array(), wvi_campi_assenti( 274, array( 'qualsiasi' ) ) );
wvi_configura();

// Indirizzo del visitatore dietro un proxy
$_SERVER['REMOTE_ADDR']    = '172.18.0.2';
$_SERVER['HTTP_X_REAL_IP'] = '93.40.1.2';
uguale( 'Proxy acceso: indirizzo vero da X-Real-IP', '93.40.1.2', wvi_ip_visitatore() );
$wvi_opzioni['wvi_generali']['proxy'] = false;
uguale( 'Proxy spento: intestazioni ignorate', '172.18.0.2', wvi_ip_visitatore() );
$wvi_opzioni['wvi_generali']['proxy'] = true;
$_SERVER['REMOTE_ADDR']               = '151.20.3.4';
uguale( 'Proxy acceso ma richiesta diretta: intestazioni ignorate', '151.20.3.4', wvi_ip_visitatore() );
unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_REAL_IP'] );

// Schema delle impostazioni
$wvi_tipi = array( 'testo', 'prefisso', 'html', 'area', 'casella', 'numero', 'elenco', 'colonne', 'coppie', 'campo', 'modulo', 'url', 'email', 'mese', 'data', 'segreto', 'foglio' );
$wvi_male = array();
$wvi_viste = array();
foreach ( wvi_schema() as $v ) {
	$chiave = "{$v['dove']}.{$v['chiave']}";
	$atteso = array(
		'casella' => 'boolean',
		'numero'  => 'integer',
		'mese'    => 'integer',
		'modulo'  => 'integer',
		'elenco'  => 'array',
		'colonne' => 'array',
		'coppie'  => 'array',
	)[ $v['tipo'] ] ?? 'string';
	if ( isset( $wvi_viste[ $chiave ] ) ) {
		$wvi_male[] = "{$chiave}: ripetuta";
	}
	$wvi_viste[ $chiave ] = true;
	if ( ! in_array( $v['dove'], array( 'generali', 'soci', 'corsi' ), true ) || ! in_array( $v['scheda'], array( 'collegamenti', 'campi', 'funzioni' ), true ) ) {
		$wvi_male[] = "{$chiave}: opzione o scheda sconosciuta";
	}
	if ( ! in_array( $v['tipo'], $wvi_tipi, true ) ) {
		$wvi_male[] = "{$chiave}: tipo {$v['tipo']} sconosciuto";
	}
	if ( '' === trim( $v['etichetta'] ) || ( 'casella' === $v['tipo'] && '' === trim( $v['casella'] ) ) ) {
		$wvi_male[] = "{$chiave}: senza etichetta";
	}
	if ( gettype( $v['predefinito'] ) !== $atteso ) {
		$wvi_male[] = "{$chiave}: predefinito " . gettype( $v['predefinito'] ) . ", serve {$atteso}";
	}
	if ( $v['obbligatorio'] && '' === $v['predefinito'] ) {
		$wvi_male[] = "{$chiave}: obbligatoria ma vuota";
	}
}
uguale( 'Schema: chiavi uniche, tipi noti, etichette, predefiniti del tipo giusto', array(), $wvi_male );
uguale( 'Schema: tutte le chiavi delle impostazioni sono nello schema', array(), array_values( array_diff( array_keys( wvi_config( 'soci' ) ), array_keys( wvi_schema_voci( 'soci' ) ), array( 'id', 'titolo', 'script', 'righe', 'impostazioni', 'ultimo', 'sospese', 'stato', 'inizio' ) ) ) );

/**
 * Valori che il browser invierebbe dal modulo disegnato: caselle solo se spuntate, tendine con
 * l'opzione scelta (o la prima), password vuote.
 *
 * @param string $html HTML del modulo.
 */
function wvi_invio_browser( $html ) {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	$dati = array();
	$nome = static fn( $el ) => preg_match( '/^wvi\[([a-z_]+)\]$/', $el->getAttribute( 'name' ), $m ) ? $m[1] : null;
	foreach ( $doc->getElementsByTagName( 'input' ) as $el ) {
		$k = $nome( $el );
		if ( null !== $k && ( 'checkbox' !== $el->getAttribute( 'type' ) || $el->hasAttribute( 'checked' ) ) ) {
			$dati[ $k ] = $el->getAttribute( 'value' );
		}
	}
	foreach ( $doc->getElementsByTagName( 'textarea' ) as $el ) {
		$k = $nome( $el );
		if ( null !== $k ) {
			$dati[ $k ] = $el->textContent;
		}
	}
	foreach ( $doc->getElementsByTagName( 'select' ) as $el ) {
		$k = $nome( $el );
		if ( null === $k ) {
			continue;
		}
		$scelta = null;
		foreach ( $el->getElementsByTagName( 'option' ) as $o ) {
			if ( null === $scelta || $o->hasAttribute( 'selected' ) ) {
				$scelta = $o->getAttribute( 'value' );
				if ( $o->hasAttribute( 'selected' ) ) {
					break;
				}
			}
		}
		$dati[ $k ] = $scelta;
	}
	return $dati;
}

// Andata e ritorno di ogni modulo del pannello: disegnato, inviato com'è, salvato, nulla cambia
foreach ( array( false, true ) as $wvi_con_campi ) {
	wvi_configura();
	$wvi_opzioni['wvi_generali']['autocomplete']                  = array( 'name-1' => 'given-name', 'luogo-1' => 'off' );
	$wvi_opzioni['wvi_soci_impostazioni']['url']                  = 'https://script.google.com/macros/s/abc/exec';
	$wvi_opzioni['wvi_soci_impostazioni']['token']                = 'segreto-soci';
	$wvi_opzioni['wvi_soci_impostazioni']['recupera_invito']      = 'Già stato socio? <a>Recupera i tuoi dati</a>';
	$wvi_opzioni['wvi_corsi_impostazioni']['foglio']              = '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789';
	$wvi_opzioni['wvi_corsi_impostazioni']['passaggio_avviso']    = "Due righe\ncon <strong>HTML</strong> & accenti è";
	Forminator_API::$campi = $wvi_con_campi ? array(
		array( 'element_id' => 'text-1', 'field_label' => 'CF', 'type' => 'text' ),
		array( 'element_id' => 'email-1', 'field_label' => 'Email', 'type' => 'email' ),
	) : array();
	$wvi_prima = array( wvi_generali(), wvi_config( 'soci' ), wvi_config( 'corsi' ), $wvi_opzioni['wvi_soci_ultimo'] ?? null );
	$wvi_rotti = array();
	foreach ( array( 'generali', 'soci', 'corsi' ) as $dove ) {
		foreach ( array( 'collegamenti', 'campi', 'funzioni' ) as $scheda ) {
			if ( ! wvi_schema_voci( $dove, $scheda ) ) {
				continue;
			}
			ob_start();
			wvi_admin_tabella( $dove, $scheda );
			$avvisi = wvi_salva( $dove, $scheda, wvi_invio_browser( ob_get_clean() ) );
			if ( $avvisi ) {
				$wvi_rotti[] = "{$dove}/{$scheda}: " . implode( ' ', $avvisi );
			}
		}
	}
	$wvi_dopo = array( wvi_generali(), wvi_config( 'soci' ), wvi_config( 'corsi' ), $wvi_opzioni['wvi_soci_ultimo'] ?? null );
	foreach ( $wvi_prima as $n => $valori ) {
		foreach ( (array) $valori as $k => $val ) {
			if ( ( $wvi_dopo[ $n ][ $k ] ?? null ) !== $val ) {
				$wvi_rotti[] = "{$n}.{$k}: " . var_export( $val, true ) . ' → ' . var_export( $wvi_dopo[ $n ][ $k ] ?? null, true );
			}
		}
	}
	uguale( 'Pannello: andata e ritorno di tutti i moduli ' . ( $wvi_con_campi ? 'con le tendine dei campi' : 'con i campi di testo' ), array(), $wvi_rotti );
}
Forminator_API::$campi = array();

// Azioni del pannello
wvi_configura();
$wvi_opzioni['wvi_soci_impostazioni']['url']   = 'https://script.google.com/macros/s/abc/exec';
$wvi_opzioni['wvi_soci_impostazioni']['token'] = 't';
$wvi_opzioni['wvi_soci_ultimo']                = 40;
uguale( 'Azione generali: avviso del campo obbligatorio e conferma', array( array( 'warning', 'Colonna dei riferimenti non può restare vuoto: resta "Rif.".' ), array( 'success', 'Impostazioni generali salvate.' ) ), wvi_azione_generali( '', 'collegamenti', array( 'mese_inizio' => '9', 'msg_cf' => 'x', 'col_rif' => '' ) ) );
uguale( 'Azione regole: conferma con il titolo', array( array( 'success', 'Soci: impostazioni salvate.' ) ), wvi_azione_regole( 'soci', 'campi', array( 'campo_cf' => 'text-1', 'tab_prefisso' => 'X ', 'col_data' => 'Data', 'colonne' => 'Nome = name-1' ) ) );
uguale( 'Azione riparti: punto raggiunto azzerato', array( 'success', false ), array( wvi_azione_riparti( 'soci' )[0][0], isset( $wvi_opzioni['wvi_soci_ultimo'] ) ) );
uguale( 'Azioni su un collegamento spento: niente', array( array(), array() ), array( wvi_azione_riparti( 'corsi' ), wvi_azione_mappa( 'soci' ) ) );
wvi_configura();

echo $wvi_falliti ? "\n{$wvi_falliti} su {$wvi_totale} controlli falliti.\n" : "{$wvi_totale} controlli superati.\n";
exit( $wvi_falliti ? 1 : 0 );
