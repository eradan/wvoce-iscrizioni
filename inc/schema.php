<?php
/**
 * Schema delle impostazioni: ogni campo del pannello descritto una volta sola. Da qui vengono i valori
 * predefiniti (wvi_predefiniti(), wvi_generali()), i moduli del pannello (inc/admin/componenti.php) e
 * il salvataggio (wvi_salva()): un campo aggiunto qui compare nella sua scheda e si salva con la pulizia
 * del suo tipo, senza liste da tenere allineate a mano.
 *
 * Voce: dove (generali, soci, corsi: l'opzione), chiave, scheda del pannello (collegamenti, campi,
 * funzioni), tipo, etichetta, predefinito, e a seconda del tipo: aiuto (HTML semplice), casella (testo
 * accanto alla casella), classe e segnaposto (campi di testo), righe (aree di testo), obbligatorio (vuoto
 * non ammesso: resta il valore di prima).
 *
 * Tipi: testo, prefisso (testo che può finire con uno spazio), html (una riga con HTML semplice), area
 * (testo su più righe con HTML semplice), casella,
 * numero (almeno 1), elenco (id separati da virgole), colonne ("Intestazione = campo.parte"), coppie
 * ("campo = valore"), campo (tendina dei campi del modulo), modulo (tendina dei moduli), url, email, mese,
 * data, segreto (vuoto: resta quello salvato), foglio (ID o indirizzo di un foglio Google).
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Una voce dello schema.
 *
 * @param string $dove        generali, soci, corsi.
 * @param string $chiave      Chiave nell'opzione.
 * @param string $scheda      Scheda del pannello.
 * @param string $tipo        Tipo.
 * @param string $etichetta   Etichetta nel pannello.
 * @param mixed  $predefinito Valore predefinito.
 * @param array  $altro       aiuto, casella, classe, segnaposto, righe, obbligatorio.
 */
function wvi_voce( $dove, $chiave, $scheda, $tipo, $etichetta, $predefinito, $altro = array() ) {
	return array_merge(
		array(
			'dove'         => $dove,
			'chiave'       => $chiave,
			'scheda'       => $scheda,
			'tipo'         => $tipo,
			'etichetta'    => $etichetta,
			'predefinito'  => $predefinito,
			'aiuto'        => '',
			'casella'      => '',
			'classe'       => 'regular-text',
			'segnaposto'   => '',
			'righe'        => 0,
			'obbligatorio' => false,
		),
		$altro
	);
}

/**
 * Tutte le voci, nell'ordine in cui compaiono nel pannello.
 *
 * @return array[]
 */
function wvi_schema() {
	static $schema = null;
	if ( null !== $schema ) {
		return $schema;
	}
	$schema = array(
		// Collegamenti → Impostazioni generali
		wvi_voce( 'generali', 'mese_inizio', 'collegamenti', 'mese', 'Inizio dell\'anno sociale', 1, array( 'aiuto' => 'Da settembre: anno sociale "2026-2027" da settembre ad agosto. Gennaio: anno solare, "2026".' ) ),
		wvi_voce( 'generali', 'pagina_soci', 'collegamenti', 'url', 'Pagina del modulo dei soci', '', array( 'classe' => 'large-text', 'aiuto' => 'Indirizzo della pagina con il modulo dei soci: link nei messaggi e nell\'email di "Recupera i tuoi dati".' ) ),
		wvi_voce( 'generali', 'pagina_corsi', 'collegamenti', 'url', 'Pagina del modulo dei corsi', '', array( 'classe' => 'large-text', 'aiuto' => 'Per il passaggio dal modulo dei soci a quello dei corsi.' ) ),
		wvi_voce( 'generali', 'email', 'collegamenti', 'email', 'Email di contatto', '', array( 'aiuto' => 'Segnaposto <code>{email}</code> nei messaggi e nelle email.' ) ),
		wvi_voce( 'generali', 'msg_cf', 'collegamenti', 'testo', 'Messaggio: codice fiscale sbagliato', 'Controlla il codice fiscale: sono 16 lettere e numeri, come sulla tessera sanitaria.', array( 'classe' => 'large-text', 'obbligatorio' => true, 'aiuto' => 'Accanto al campo, all\'uscita dal campo e all\'invio, in tutti i moduli.' ) ),
		wvi_voce( 'generali', 'col_rif', 'collegamenti', 'testo', 'Colonna dei riferimenti', 'Rif.', array( 'classe' => 'small-text', 'obbligatorio' => true, 'aiuto' => 'Intestazione della colonna con il numero dell\'iscrizione ("F274-12"), contro i doppioni. Di solito <code>Rif.</code>.' ) ),
		wvi_voce( 'generali', 'proxy', 'collegamenti', 'casella', 'Sito dietro un proxy', false, array( 'casella' => 'Le richieste arrivano da un proxy (nginx, Docker): l\'indirizzo del visitatore è in X-Real-IP o X-Forwarded-For', 'aiuto' => 'Serve per il limite di richieste di "Recupera i tuoi dati". Senza proxy lasciare spento: le intestazioni si potrebbero falsificare.' ) ),
	);

	foreach ( array( 'soci', 'corsi' ) as $id ) {
		// Collegamenti → un riquadro per foglio
		$schema[] = wvi_voce( $id, 'form', 'collegamenti', 'modulo', 'Modulo Forminator', 0, array( 'aiuto' => 'Modulo da esportare e su cui valgono le regole. Cambiandolo, l\'invio riparte da capo.' ) );
		$schema[] = wvi_voce( $id, 'url', 'collegamenti', 'url', 'Indirizzo dell\'app web', '', array( 'classe' => 'large-text code', 'segnaposto' => 'https://script.google.com/macros/s/…/exec', 'aiuto' => 'Dal deployment dello script. Vuoto: invio ai fogli spento (le regole sui moduli restano).' ) );
		$schema[] = wvi_voce( $id, 'foglio', 'collegamenti', 'foglio', 'Foglio Google', '', array( 'classe' => 'large-text code', 'segnaposto' => 'https://docs.google.com/spreadsheets/d/…/edit', 'aiuto' => 'ID del foglio (fra <code>/d/</code> e <code>/edit</code>) o l\'indirizzo intero. Per passare da una copia di prova al foglio vero basta cambiarlo.' ) );
		$schema[] = wvi_voce( $id, 'token', 'collegamenti', 'segreto', 'Codice segreto', '', array( 'classe' => 'regular-text code', 'aiuto' => 'Prodotto da <code>creaToken</code> nello script. Vuoto: resta quello salvato.' ) );
		$schema[] = wvi_voce( $id, 'dal', 'collegamenti', 'data', 'Iscrizioni dal giorno', '', array( 'aiuto' => 'Facoltativa: le iscrizioni precedenti restano fuori.' ) );
		$schema[] = wvi_voce( $id, 'dalle_nuove', 'collegamenti', 'casella', 'Solo le nuove', false, array( 'casella' => 'Alla prima attivazione parti dalle iscrizioni che arrivano da quel momento' ) );

		// Campi e regole
		$schema[] = wvi_voce( $id, 'campo_cf', 'campi', 'campo', 'Campo del codice fiscale', '', array( 'aiuto' => 'Serve alle regole, a "Recupera i tuoi dati" e alla ricerca dei soci. Nel foglio va in maiuscolo.' ) );
		$schema[] = wvi_voce( $id, 'controlla', 'campi', 'casella', 'Controllo del codice fiscale', true, array( 'casella' => 'Controlla formato e carattere di controllo (codice fiscale italiano di una persona)' ) );
		if ( 'soci' === $id ) {
			$schema[] = wvi_voce( $id, 'campo_email', 'campi', 'campo', 'Campo dell\'email', '', array( 'aiuto' => 'Indirizzo a cui "Recupera i tuoi dati" scrive.' ) );
			$schema[] = wvi_voce( $id, 'unico', 'campi', 'casella', 'Una sola iscrizione per anno', true, array( 'casella' => 'Ferma il modulo se il codice fiscale è già fra i soci dell\'anno sociale' ) );
			$schema[] = wvi_voce( $id, 'msg_doppio', 'campi', 'area', 'Messaggio: già socio', 'Questo codice fiscale risulta già fra i soci {anno}: l\'iscrizione di quest\'anno è già registrata. Per domande o per correggere i dati scrivi a <a href="mailto:{email}">{email}</a>.', array( 'righe' => 3, 'aiuto' => 'Segnaposto: <code>{anno}</code>, <code>{email}</code>, <code>{link_soci}</code>, <code>{associazione}</code>. HTML semplice ammesso.' ) );
			$schema[] = wvi_voce( $id, 'tab_prefisso', 'campi', 'prefisso', 'Prefisso dei tab', 'Soci ', array( 'aiuto' => 'Un tab per anno sociale: prefisso + anno, per esempio "Soci 2026-2027". Lo script crea il tab dell\'anno nuovo copiando le intestazioni del più recente.' ) );
			$schema[] = wvi_voce( $id, 'col_data', 'campi', 'testo', 'Colonna della data', 'Data', array( 'aiuto' => 'Data dell\'iscrizione ("Ott 5, 2026 @ 10:08 AM"). Vuoto: nessuna colonna.' ) );
		} else {
			$schema[] = wvi_voce( $id, 'campo_corsi', 'campi', 'campo', 'Campo dei corsi', '', array( 'aiuto' => 'Caselle o scelta con i corsi: una riga per ogni corso scelto.' ) );
			$schema[] = wvi_voce( $id, 'richiede_socio', 'campi', 'casella', 'Riservato ai soci', true, array( 'casella' => 'Ferma il modulo se il codice fiscale non è fra i soci dell\'anno sociale' ) );
			$schema[] = wvi_voce( $id, 'msg_socio', 'campi', 'area', 'Messaggio: non socio', 'Questo codice fiscale manca ancora fra i soci {anno}: i corsi sono riservati ai soci. Prima occorre iscriversi all\'associazione con il <a href="{link_soci}" target="_blank" rel="noopener">modulo di iscrizione a socio</a>, poi si torna a questa pagina.', array( 'righe' => 3, 'aiuto' => 'Segnaposto: <code>{anno}</code>, <code>{link_soci}</code>, <code>{email}</code>, <code>{associazione}</code>.' ) );
			$schema[] = wvi_voce( $id, 'col_corso', 'campi', 'testo', 'Colonna del corso', 'Corso', array( 'obbligatorio' => true ) );
			$schema[] = wvi_voce( $id, 'tab_configurazioni', 'campi', 'testo', 'Tab di configurazione', 'configurazioni', array( 'obbligatorio' => true, 'aiuto' => 'Tab del foglio con la corrispondenza: colonna A il tab, colonna B il testo del corso.' ) );
			$schema[] = wvi_voce( $id, 'tab_riserva', 'campi', 'testo', 'Tab di riserva', 'Da smistare', array( 'obbligatorio' => true, 'aiuto' => 'Per i corsi che il tab di configurazione non conosce.' ) );
		}
		$schema[] = wvi_voce( $id, 'colonne', 'campi', 'colonne', 'Colonne del foglio', array(), array( 'righe' => 12, 'aiuto' => 'Una per riga: <code>Intestazione = campo</code>, o <code>Intestazione = address-1.city</code> per una parte di indirizzo. Lo script scrive per intestazione (maiuscole e spazi non contano) e lascia intatte le altre colonne del foglio.' ) );
	}

	// Passaggio e recupero → dal modulo dei soci a quello dei corsi
	$schema[] = wvi_voce( 'corsi', 'passaggio', 'funzioni', 'casella', 'Attivo', false, array( 'casella' => 'Ricompila il modulo dei corsi dopo l\'iscrizione a socio' ) );
	$schema[] = wvi_voce( 'corsi', 'passaggio_campi', 'funzioni', 'elenco', 'Campi da riportare', array(), array( 'classe' => 'large-text code', 'aiuto' => 'Id separati da virgole, per esempio <code>name-1, name-2, text-1, email-1</code>.' ) );
	$schema[] = wvi_voce( 'corsi', 'passaggio_caselle', 'funzioni', 'elenco', 'Caselle da spuntare', array(), array( 'classe' => 'large-text code', 'aiuto' => 'Caselle del modulo dei corsi già accettate con l\'iscrizione a socio (regolamento, privacy…).' ) );
	$schema[] = wvi_voce( 'corsi', 'passaggio_avviso', 'funzioni', 'area', 'Avviso sopra il modulo', 'Abbiamo ripreso i tuoi dati dall\'iscrizione a socio: scegli il corso e completa il modulo.', array( 'righe' => 2 ) );

	// Passaggio e recupero → Recupera i tuoi dati
	$schema[] = wvi_voce( 'soci', 'recupera', 'funzioni', 'casella', 'Attivo', false, array( 'casella' => 'Mostra "Recupera i tuoi dati" nel modulo dei soci' ) );
	$schema[] = wvi_voce( 'soci', 'recupera_campi', 'funzioni', 'elenco', 'Campi da recuperare', array(), array( 'righe' => 3, 'aiuto' => 'Id separati da virgole; parti di indirizzo con il punto (<code>address-1.city</code>). Fuori ricevute, accettazioni e consensi, che vanno rinnovati.' ) );
	$schema[] = wvi_voce( 'soci', 'recupera_anni', 'funzioni', 'numero', 'Anni sociali', 3, array( 'aiuto' => 'Quanti anni sociali precedenti si guardano per l\'ultima iscrizione.' ) );
	$schema[] = wvi_voce( 'soci', 'recupera_ore', 'funzioni', 'numero', 'Durata del link (ore)', 24 );
	$schema[] = wvi_voce( 'soci', 'recupera_richieste', 'funzioni', 'numero', 'Richieste all\'ora per connessione', 5 );
	$schema[] = wvi_voce( 'soci', 'recupera_attesa', 'funzioni', 'numero', 'Minuti fra due email', 5, array( 'aiuto' => 'Allo stesso codice fiscale: protegge la casella di chiunque da invii ripetuti.' ) );
	$schema[] = wvi_voce( 'soci', 'recupera_invito', 'funzioni', 'html', 'Link sotto il codice fiscale', 'Già stato socio? <a>Recupera i tuoi dati</a>', array( 'classe' => 'large-text', 'aiuto' => 'Il testo fra <code>&lt;a&gt;</code> e <code>&lt;/a&gt;</code> diventa il link.' ) );
	$schema[] = wvi_voce( 'soci', 'recupera_messaggio', 'funzioni', 'area', 'Messaggio a video', 'Se il codice fiscale è nel nostro archivio, ti abbiamo scritto all\'indirizzo email dell\'ultima iscrizione (guarda anche nello spam). Il link vale {ore} ore.', array( 'righe' => 2, 'aiuto' => 'Uguale per tutti, per non dire chi è socio. Segnaposto: <code>{ore}</code>.' ) );
	$schema[] = wvi_voce( 'soci', 'recupera_avviso', 'funzioni', 'area', 'Avviso sul modulo ricompilato', 'Abbiamo recuperato i dati della tua ultima iscrizione: controlla che siano ancora giusti e completa il resto del modulo.', array( 'righe' => 2 ) );
	$schema[] = wvi_voce( 'soci', 'recupera_link_ogg', 'funzioni', 'testo', 'Email con il link: oggetto', '{associazione}: recupera i dati della tua iscrizione', array( 'classe' => 'large-text' ) );
	$schema[] = wvi_voce( 'soci', 'recupera_link_testo', 'funzioni', 'area', 'Email con il link: testo', "Ciao,\n\nhai chiesto di recuperare i dati della tua ultima iscrizione a {associazione}. Apri il link qui sotto entro {ore} ore: troverai il modulo di iscrizione già compilato con i tuoi dati.\n\n{link}\n\nControlla che i dati siano ancora giusti e completa il resto del modulo.\n\nSe non sei stato tu, ignora questa email: senza il link nessuno vede i tuoi dati.\n\n{associazione}", array( 'righe' => 9, 'aiuto' => 'Segnaposto: <code>{link}</code> (obbligatorio), <code>{ore}</code>, <code>{associazione}</code>, <code>{email}</code>.' ) );
	$schema[] = wvi_voce( 'soci', 'recupera_gia_ogg', 'funzioni', 'testo', 'Email "già iscritto": oggetto', '{associazione}: la tua iscrizione {anno} è già registrata', array( 'classe' => 'large-text' ) );
	$schema[] = wvi_voce( 'soci', 'recupera_gia_testo', 'funzioni', 'area', 'Email "già iscritto": testo', "Ciao,\n\nqualcuno, probabilmente tu, ha chiesto di recuperare i dati della tua iscrizione a {associazione} dal modulo sul sito.\n\nLa tua iscrizione per l'anno sociale {anno} è già registrata: non serve compilare di nuovo il modulo. Per correggere i dati scrivi a {email}.\n\nSe non sei stato tu, ignora questa email.\n\n{associazione}", array( 'righe' => 7, 'aiuto' => 'Segnaposto: <code>{anno}</code>, <code>{associazione}</code>, <code>{email}</code>.' ) );

	// Passaggio e recupero → compilazione automatica del browser
	$schema[] = wvi_voce( 'generali', 'autocomplete', 'funzioni', 'coppie', 'Campi', array(), array( 'righe' => 6, 'aiuto' => 'Una per riga: <code>name-1 = given-name</code>. Per le parti di indirizzo il nome nella pagina: <code>address-1-city = address-level2</code>.' ) );

	return $schema;
}

/**
 * Voci di un'opzione, eventualmente di una sola scheda.
 *
 * @param string      $dove   generali, soci, corsi.
 * @param string|null $scheda Scheda (null: tutte).
 * @return array[] Chiave => voce.
 */
function wvi_schema_voci( $dove, $scheda = null ) {
	$voci = array();
	foreach ( wvi_schema() as $v ) {
		if ( $v['dove'] === $dove && ( null === $scheda || $v['scheda'] === $scheda ) ) {
			$voci[ $v['chiave'] ] = $v;
		}
	}
	return $voci;
}

/**
 * Valori predefiniti di un'opzione.
 *
 * @param string $dove generali, soci, corsi.
 */
function wvi_schema_predefiniti( $dove ) {
	return array_map( static fn( $v ) => $v['predefinito'], wvi_schema_voci( $dove ) );
}
