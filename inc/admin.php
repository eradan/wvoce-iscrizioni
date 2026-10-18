<?php
/**
 * Strumenti → Iscrizioni: collegamenti con i fogli, campi e regole dei moduli, passaggio ai corsi e
 * "Recupera i tuoi dati", stato e invio delle iscrizioni, script Apps Script da incollare.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schede della pagina.
 */
function wvi_admin_schede() {
	return array(
		'collegamenti' => 'Collegamenti',
		'campi'        => 'Campi e regole',
		'funzioni'     => 'Passaggio e recupero',
		'soci'         => 'Invio soci',
		'corsi'        => 'Invio corsi',
		'script'       => 'Script Apps Script',
	);
}

/**
 * Scheda aperta (dal parametro "tab" dell'indirizzo).
 */
function wvi_admin_scheda() {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( wvi_admin_schede()[ $tab ] ) ? $tab : 'collegamenti';
}

/**
 * Indirizzo della pagina, su una scheda.
 *
 * @param string $tab Scheda.
 */
function wvi_admin_url( $tab = 'collegamenti' ) {
	return admin_url( 'tools.php?page=wvi-iscrizioni&tab=' . rawurlencode( $tab ) );
}

/**
 * Voce di menu in Strumenti.
 */
function wvi_admin_menu() {
	$hook = add_management_page( 'Iscrizioni', 'Iscrizioni', 'manage_options', 'wvi-iscrizioni', 'wvi_admin_pagina' );
	if ( $hook ) {
		add_action( "load-{$hook}", 'wvi_admin_azioni' );
	}
}
add_action( 'admin_menu', 'wvi_admin_menu' );

/**
 * Collegamento "Impostazioni" nell'elenco dei plugin.
 *
 * @param array $link Collegamenti del plugin.
 */
function wvi_admin_link_plugin( $link ) {
	array_unshift( $link, '<a href="' . esc_url( wvi_admin_url() ) . '">Impostazioni</a>' );
	return $link;
}
add_filter( 'plugin_action_links_' . plugin_basename( WVI_DIR . 'wvoce-iscrizioni.php' ), 'wvi_admin_link_plugin' );

/**
 * Messaggio da mostrare dopo il reindirizzamento che segue un'azione.
 *
 * @param string $tipo  success, warning, error.
 * @param string $testo Messaggio.
 */
function wvi_admin_avviso( $tipo, $testo ) {
	$chiave   = 'wvi_avvisi_' . get_current_user_id();
	$avvisi   = (array) get_transient( $chiave );
	$avvisi[] = array( $tipo, $testo );
	set_transient( $chiave, array_filter( $avvisi ), MINUTE_IN_SECONDS );
}

/**
 * Azioni dei pulsanti, prima che la pagina parta: poi si torna sulla scheda con una GET, così un
 * aggiornamento della pagina non ripete l'azione.
 */
function wvi_admin_azioni() {
	if ( empty( $_POST['wvi_azione'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'wvi_iscrizioni' );
	$azione = sanitize_key( wp_unslash( $_POST['wvi_azione'] ) );
	$id     = isset( $_POST['wvi_id'] ) ? sanitize_key( wp_unslash( $_POST['wvi_id'] ) ) : '';
	$tab    = isset( $_POST['wvi_tab'] ) ? sanitize_key( wp_unslash( $_POST['wvi_tab'] ) ) : 'collegamenti';
	// Ogni voce è ripulita dalla funzione che la salva (testi con HTML e a capo, elenchi, numeri).
	$dati = isset( $_POST['wvi'] ) && is_array( $_POST['wvi'] ) ? wp_unslash( $_POST['wvi'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( 'generali' === $azione ) {
		wvi_salva_generali( $dati );
		wvi_admin_avviso( 'success', 'Impostazioni generali salvate.' );
		wp_safe_redirect( wvi_admin_url( $tab ) );
		exit;
	}
	if ( ! isset( wvi_collegamenti()[ $id ] ) ) {
		wp_safe_redirect( wvi_admin_url( $tab ) );
		exit;
	}
	$c = wvi_config( $id );

	if ( 'salva' === $azione || 'verifica' === $azione ) {
		foreach ( wvi_salva_collegamento( $id, $dati ) as $avviso ) {
			wvi_admin_avviso( 'warning', $avviso );
		}
		wvi_admin_avviso( 'success', "{$c['titolo']}: collegamento salvato." );
		wvi_schedule();
		delete_transient( 'wvi_corsi_mappa' );
		if ( 'verifica' === $azione ) {
			$esito = wvi_verifica( $id );
			wvi_admin_avviso(
				is_wp_error( $esito ) ? 'error' : 'success',
				is_wp_error( $esito )
					? "{$c['titolo']}, verifica non riuscita: " . $esito->get_error_message()
					: "{$c['titolo']}: risponde il foglio «{$esito}», indirizzo, codice e foglio sono giusti."
			);
		}
	} elseif ( 'regole' === $azione ) {
		foreach ( wvi_salva_regole( $id, $dati ) as $avviso ) {
			wvi_admin_avviso( 'warning', $avviso );
		}
		delete_transient( 'wvi_corsi_mappa' );
		wvi_admin_avviso( 'success', "{$c['titolo']}: impostazioni salvate." );
	} elseif ( 'invia' === $azione && wvi_configurato( $id ) ) {
		$esito = wvi_sync( $id );
		wvi_admin_avviso(
			$esito['errore'] ? 'error' : 'success',
			sprintf( 'Inviate %d iscrizioni.', $esito['inviate'] ) . ( $esito['errore'] ? ' Errore: ' . $esito['errore'] : '' )
		);
	} elseif ( 'riparti' === $azione && wvi_configurato( $id ) ) {
		delete_option( $c['ultimo'] );
		wvi_admin_avviso( 'success', 'Si riparte dal punto di partenza: le righe già nel foglio restano senza doppioni.' );
	} elseif ( 'mappa' === $azione && 'corsi' === $id ) {
		$mappa = wvi_corsi_mappa( true );
		wvi_admin_avviso(
			is_wp_error( $mappa ) ? 'error' : 'success',
			is_wp_error( $mappa ) ? $mappa->get_error_message() : sprintf( 'Tab "%s" riletto: %d corsi.', $c['tab_configurazioni'], count( $mappa['mappa'] ) )
		);
	}
	wp_safe_redirect( wvi_admin_url( $tab ) );
	exit;
}

/**
 * Apertura di un modulo del pannello: nonce, collegamento, scheda a cui tornare.
 *
 * @param string $id  Collegamento ('' per le impostazioni generali).
 * @param string $tab Scheda.
 */
function wvi_admin_apri( $id, $tab ) {
	echo '<form method="post" action="' . esc_url( wvi_admin_url( $tab ) ) . '">';
	wp_nonce_field( 'wvi_iscrizioni' );
	echo '<input type="hidden" name="wvi_id" value="' . esc_attr( $id ) . '">';
	echo '<input type="hidden" name="wvi_tab" value="' . esc_attr( $tab ) . '">';
}

/**
 * Pagina del pannello.
 */
function wvi_admin_pagina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$attivo = wvi_admin_scheda();
	$chiave = 'wvi_avvisi_' . get_current_user_id();
	$avvisi = (array) get_transient( $chiave );
	delete_transient( $chiave );
	?>
	<div class="wrap">
		<h1>Iscrizioni</h1>
		<?php if ( ! class_exists( 'Forminator_API' ) ) : ?>
			<div class="notice notice-error inline"><p>Forminator non è attivo: il plugin lavora sui suoi moduli.</p></div>
		<?php endif; ?>
		<nav class="nav-tab-wrapper" style="margin-bottom:1em">
			<?php foreach ( wvi_admin_schede() as $tab => $titolo ) : ?>
				<a href="<?php echo esc_url( wvi_admin_url( $tab ) ); ?>" class="nav-tab<?php echo $tab === $attivo ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $titolo ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php foreach ( array_filter( $avvisi ) as $avviso ) : ?>
			<div class="notice notice-<?php echo esc_attr( $avviso[0] ); ?> is-dismissible"><p><?php echo esc_html( $avviso[1] ); ?></p></div>
		<?php endforeach; ?>
		<?php
		if ( 'collegamenti' === $attivo ) {
			wvi_admin_collegamenti();
		} elseif ( 'campi' === $attivo ) {
			wvi_admin_campi();
		} elseif ( 'funzioni' === $attivo ) {
			wvi_admin_funzioni();
		} elseif ( 'script' === $attivo ) {
			wvi_admin_script();
		} else {
			wvi_admin_invio( $attivo );
		}
		?>
	</div>
	<?php
}

/**
 * Moduli di Forminator per la tendina: id => nome.
 */
function wvi_admin_moduli() {
	if ( ! class_exists( 'Forminator_API' ) || ! method_exists( 'Forminator_API', 'get_forms' ) ) {
		return array();
	}
	$moduli = Forminator_API::get_forms( null, 1, 200 );
	$out    = array();
	foreach ( is_array( $moduli ) ? $moduli : array() as $m ) {
		if ( ! is_object( $m ) || empty( $m->id ) ) {
			continue;
		}
		$nome                = (string) ( $m->settings['formName'] ?? $m->name ?? '' );
		$out[ (int) $m->id ] = sprintf( 'n. %d — %s', (int) $m->id, '' !== $nome ? $nome : 'senza nome' );
	}
	ksort( $out );
	return $out;
}

/**
 * Riga di una tabella del pannello.
 *
 * @param string $etichetta Etichetta.
 * @param string $campo     HTML del campo (già protetto).
 * @param string $aiuto     Testo d'aiuto (HTML semplice).
 */
function wvi_admin_riga( $etichetta, $campo, $aiuto = '' ) {
	echo '<tr><th scope="row">' . esc_html( $etichetta ) . '</th><td>' . $campo . ( $aiuto ? '<p class="description">' . wp_kses_post( $aiuto ) . '</p>' : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- campo costruito con esc_*.
}

/**
 * Campo di testo.
 *
 * @param string $nome   Nome nel modulo (dentro wvi[…]).
 * @param string $valore Valore.
 * @param string $classe Classe CSS.
 */
function wvi_admin_testo( $nome, $valore, $classe = 'regular-text' ) {
	return sprintf( '<input type="text" class="%s" name="wvi[%s]" value="%s">', esc_attr( $classe ), esc_attr( $nome ), esc_attr( (string) $valore ) );
}

/**
 * Area di testo.
 *
 * @param string $nome   Nome nel modulo.
 * @param string $valore Valore.
 * @param int    $righe  Righe.
 */
function wvi_admin_area( $nome, $valore, $righe = 4 ) {
	return sprintf( '<textarea class="large-text code" rows="%d" name="wvi[%s]">%s</textarea>', (int) $righe, esc_attr( $nome ), esc_textarea( (string) $valore ) );
}

/**
 * Casella sì/no.
 *
 * @param string $nome      Nome nel modulo.
 * @param bool   $valore    Valore.
 * @param string $etichetta Testo accanto.
 */
function wvi_admin_casella( $nome, $valore, $etichetta ) {
	// Il campo nascosto dice che la casella era nel modulo: non spuntata vuol dire spenta (wvi_casella()).
	return sprintf( '<input type="hidden" name="wvi[_caselle][]" value="%1$s"><label><input type="checkbox" name="wvi[%1$s]" value="1"%2$s> %3$s</label>', esc_attr( $nome ), checked( (bool) $valore, true, false ), esc_html( $etichetta ) );
}

/**
 * Tendina dei campi di un modulo (o campo di testo se il modulo non si legge).
 *
 * @param string $nome   Nome nel modulo.
 * @param string $valore Id del campo scelto.
 * @param array  $campi  Campi del modulo (wvi_campi()).
 */
function wvi_admin_campo( $nome, $valore, $campi ) {
	if ( ! $campi ) {
		return wvi_admin_testo( $nome, $valore, 'regular-text code' );
	}
	$html = sprintf( '<select name="wvi[%s]"><option value="">— nessuno —</option>', esc_attr( $nome ) );
	$noto = false;
	foreach ( $campi as $f ) {
		$noto  = $noto || $f['id'] === $valore;
		$html .= sprintf( '<option value="%s"%s>%s — %s</option>', esc_attr( $f['id'] ), selected( $f['id'], $valore, false ), esc_html( $f['id'] ), esc_html( $f['etichetta'] ) );
	}
	if ( '' !== (string) $valore && ! $noto ) {
		$html .= sprintf( '<option value="%s" selected>%s — campo assente dal modulo</option>', esc_attr( $valore ), esc_html( $valore ) );
	}
	return $html . '</select>';
}

/**
 * Campi indicati nel pannello che il modulo non contiene: un errore di battitura darebbe solo una
 * colonna vuota o un campo non ricompilato. Per le parti di indirizzo conta il campo
 * ("address-1.city", nella pagina "address-1-city").
 *
 * @param int      $form Id del modulo.
 * @param string[] $voci Campi indicati.
 * @return string[] Voci senza un campo nel modulo ([] se il modulo non si legge).
 */
function wvi_admin_assenti( $form, $voci ) {
	$ids = array_column( wvi_campi( $form ), 'id' );
	if ( ! $ids ) {
		return array();
	}
	$assenti = array();
	foreach ( array_filter( array_map( 'strval', $voci ), 'strlen' ) as $voce ) {
		$campo = wvi_campo_parte( $voce )[0];
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

/**
 * Avviso con i campi indicati che il modulo non contiene.
 *
 * @param int      $form   Id del modulo.
 * @param string[] $voci   Campi indicati.
 * @param string   $doveli Dove sono indicati (per il messaggio).
 */
function wvi_admin_avviso_assenti( $form, $voci, $doveli ) {
	$assenti = wvi_admin_assenti( $form, $voci );
	if ( $assenti ) {
		printf( '<div class="notice notice-warning inline"><p>%s: il modulo n. %d non ha questi campi: <code>%s</code>. Controlla gli id nell\'elenco dei campi del modulo.</p></div>', esc_html( $doveli ), (int) $form, esc_html( implode( ', ', $assenti ) ) );
	}
}

/**
 * Elenco dei campi di un modulo, per scrivere colonne ed elenchi.
 *
 * @param int $form Id del modulo.
 */
function wvi_admin_elenco_campi( $form ) {
	$campi = wvi_campi( $form );
	if ( ! $campi ) {
		return;
	}
	?>
	<details style="margin:.5em 0 1em;max-width:820px">
		<summary>Campi del modulo n. <?php echo (int) $form; ?></summary>
		<p class="description">Solo la struttura del modulo, nessun dato delle iscrizioni. Per gli indirizzi si indica la parte dopo un punto: <code>address-1.street_address</code>, <code>.city</code>, <code>.state</code>, <code>.zip</code>, <code>.country</code>.</p>
		<table class="widefat striped">
			<thead><tr><th>Id</th><th>Etichetta</th><th>Tipo</th><th>Opzioni</th></tr></thead>
			<tbody>
				<?php foreach ( $campi as $f ) : ?>
					<tr>
						<td><code><?php echo esc_html( $f['id'] ); ?></code></td>
						<td><?php echo esc_html( $f['etichetta'] ); ?></td>
						<td><?php echo esc_html( $f['tipo'] ); ?></td>
						<td>
							<?php foreach ( $f['opzioni'] as $o ) : ?>
								<?php echo esc_html( $o['etichetta'] === $o['valore'] ? $o['valore'] : "{$o['etichetta']} = {$o['valore']}" ); ?><br>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</details>
	<?php
}

/**
 * Scheda Collegamenti: impostazioni generali e, per ogni foglio, indirizzo, codice, modulo e data,
 * con la guida.
 */
function wvi_admin_collegamenti() {
	$g      = wvi_generali();
	$moduli = wvi_admin_moduli();
	$mesi   = array( 1 => 'gennaio (anno solare)', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre' );
	wvi_admin_apri( '', 'collegamenti' );
	?>
	<h2>Impostazioni generali</h2>
	<table class="form-table" role="presentation">
		<?php
		$scelta = '<select name="wvi[mese_inizio]">';
		foreach ( $mesi as $n => $nome ) {
			$scelta .= sprintf( '<option value="%d"%s>%s</option>', $n, selected( $n, (int) $g['mese_inizio'], false ), esc_html( $nome ) );
		}
		$scelta .= '</select>';
		wvi_admin_riga( 'Inizio dell\'anno sociale', $scelta, 'Da settembre: anno sociale "2026-2027" da settembre ad agosto. Gennaio: anno solare, "2026".' );
		wvi_admin_riga( 'Pagina del modulo dei soci', sprintf( '<input type="url" class="large-text" name="wvi[pagina_soci]" value="%s">', esc_attr( $g['pagina_soci'] ) ), 'Indirizzo della pagina con il modulo dei soci: link nei messaggi e nell\'email di "Recupera i tuoi dati".' );
		wvi_admin_riga( 'Pagina del modulo dei corsi', sprintf( '<input type="url" class="large-text" name="wvi[pagina_corsi]" value="%s">', esc_attr( $g['pagina_corsi'] ) ), 'Per il passaggio dal modulo dei soci a quello dei corsi.' );
		wvi_admin_riga( 'Email di contatto', sprintf( '<input type="email" class="regular-text" name="wvi[email]" value="%s">', esc_attr( $g['email'] ) ), 'Segnaposto <code>{email}</code> nei messaggi e nelle email.' );
		wvi_admin_riga( 'Messaggio: codice fiscale sbagliato', wvi_admin_testo( 'msg_cf', $g['msg_cf'], 'large-text' ), 'Accanto al campo, all\'uscita dal campo e all\'invio, in tutti i moduli.' );
		wvi_admin_riga( 'Colonna dei riferimenti', wvi_admin_testo( 'col_rif', $g['col_rif'], 'small-text' ), 'Intestazione della colonna con il numero dell\'iscrizione ("F274-12"), contro i doppioni. Di solito <code>Rif.</code>.' );
		wvi_admin_riga( 'Sito dietro un proxy', wvi_admin_casella( 'proxy', $g['proxy'], 'Le richieste arrivano da un proxy (nginx, Docker): l\'indirizzo del visitatore è in X-Real-IP o X-Forwarded-For' ), 'Serve per il limite di richieste di "Recupera i tuoi dati". Senza proxy lasciare spento: le intestazioni si potrebbero falsificare.' );
		?>
	</table>
	<p><button class="button button-primary" name="wvi_azione" value="generali">Salva le impostazioni generali</button></p>
	</form>

	<p style="max-width:820px;margin-top:2em">Le iscrizioni dei moduli Forminator arrivano da sole nei fogli Google, ogni 15 minuti, attraverso un'app web di Apps Script pubblicata dal foglio. Per ogni foglio servono l'ID del foglio, l'indirizzo dell'app web e il suo codice segreto. La guida è in fondo alla pagina.</p>
	<?php
	foreach ( array_keys( wvi_collegamenti() ) as $id ) :
		$c = wvi_config( $id );
		wvi_admin_apri( $id, 'collegamenti' );
		?>
		<h2 style="margin-top:1.5em"><?php echo esc_html( $c['titolo'] ); ?> <span style="font-weight:normal;font-size:13px">— <?php echo wvi_configurato( $id ) ? '<strong>attivo</strong>' : 'spento'; ?></span></h2>
		<table class="form-table" role="presentation">
			<?php
			if ( $moduli ) {
				$scelta = '<select name="wvi[form]"><option value="0">— nessuno —</option>';
				if ( $c['form'] && ! isset( $moduli[ $c['form'] ] ) ) {
					$scelta .= sprintf( '<option value="%d" selected>n. %d — modulo assente</option>', $c['form'], $c['form'] );
				}
				foreach ( $moduli as $form => $nome ) {
					$scelta .= sprintf( '<option value="%d"%s>%s</option>', $form, selected( $form, $c['form'], false ), esc_html( $nome ) );
				}
				$scelta .= '</select>';
			} else {
				$scelta = sprintf( '<input type="number" min="0" class="small-text" name="wvi[form]" value="%d">', $c['form'] );
			}
			wvi_admin_riga( 'Modulo Forminator', $scelta, 'Modulo da esportare e su cui valgono le regole. Cambiandolo, l\'invio riparte da capo.' );
			wvi_admin_riga( 'Indirizzo dell\'app web', sprintf( '<input type="url" class="large-text code" name="wvi[url]" value="%s" placeholder="https://script.google.com/macros/s/…/exec">', esc_attr( $c['url'] ) ), 'Dal deployment dello script. Vuoto: invio ai fogli spento (le regole sui moduli restano).' );
			$apri = '' !== $c['foglio'] ? ' <a href="' . esc_url( 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $c['foglio'] ) . '/edit' ) . '" target="_blank" rel="noopener">Apri il foglio</a>' : '';
			wvi_admin_riga( 'Foglio Google', sprintf( '<input type="text" class="large-text code" name="wvi[foglio]" value="%s" placeholder="https://docs.google.com/spreadsheets/d/…/edit">', esc_attr( $c['foglio'] ) ), 'ID del foglio (fra <code>/d/</code> e <code>/edit</code>) o l\'indirizzo intero. Per passare da una copia di prova al foglio vero basta cambiarlo.' . $apri );
			wvi_admin_riga( 'Codice segreto', sprintf( '<input type="password" class="regular-text code" name="wvi[token]" value="" autocomplete="new-password" placeholder="%s">', esc_attr( $c['token'] ? 'salvato, termina con …' . substr( $c['token'], -4 ) : '' ) ), 'Prodotto da <code>creaToken</code> nello script. Vuoto: resta quello salvato.' );
			wvi_admin_riga( 'Iscrizioni dal giorno', sprintf( '<input type="date" name="wvi[dal]" value="%s">', esc_attr( $c['dal'] ) ), 'Facoltativa: le iscrizioni precedenti restano fuori.' );
			wvi_admin_riga( 'Solo le nuove', wvi_admin_casella( 'dalle_nuove', $c['dalle_nuove'], 'Alla prima attivazione parti dalle iscrizioni che arrivano da quel momento' ) );
			?>
		</table>
		<p>
			<button class="button button-primary" name="wvi_azione" value="salva">Salva</button>
			<button class="button" name="wvi_azione" value="verifica">Salva e verifica il collegamento</button>
		</p>
		</form>
	<?php endforeach; ?>

	<hr style="margin:2em 0">
	<h2>Guida</h2>
	<ol style="max-width:820px">
		<li><strong>Script nel Drive.</strong> In Google Drive: Nuovo → Altro → Google Apps Script (progetto autonomo, consigliato per i fogli in un Drive condiviso). In alternativa, dal foglio: Estensioni → Apps Script. Incollare al posto del codice esistente il testo della scheda <a href="<?php echo esc_url( wvi_admin_url( 'script' ) ); ?>">Script Apps Script</a>. Lo script è lo stesso per ogni sito: nomi dei tab e delle colonne speciali li manda il sito a ogni chiamata.</li>
		<li><strong>Foglio.</strong> L'indirizzo del foglio, copiato dalla barra del browser, o il solo ID. Basta che l'account che pubblica lo script possa modificarlo.</li>
		<li><strong>Codice segreto.</strong> Nell'editor, scegliere la funzione <code>creaToken</code> e premere Esegui. La prima volta Google chiede le autorizzazioni (Avanzate → Vai a … → Consenti). Nel registro di esecuzione compare il codice di 64 caratteri da incollare qui.</li>
		<li><strong>Indirizzo.</strong> Esegui il deployment → Nuovo deployment → tipo App web; Esegui come: Me; Chi ha accesso: Chiunque. L'URL termina con <code>/exec</code>.</li>
		<li><strong>Campi e colonne.</strong> Nella scheda <a href="<?php echo esc_url( wvi_admin_url( 'campi' ) ); ?>">Campi e regole</a>: quale campo è il codice fiscale, quali colonne scrivere nel foglio e come si chiamano i tab.</li>
		<li><strong>Verifica.</strong> "Salva e verifica il collegamento" manda al foglio una richiesta vuota: la risposta riporta il nome del foglio raggiunto senza scrivere nulla. Nelle schede d'invio la prova a secco mostra cosa partirebbe.</li>
		<li><strong>Script modificato.</strong> Dopo aver incollato una versione nuova: Esegui il deployment → Gestisci deployment → matita → Versione: Nuova versione. URL, codice e foglio restano gli stessi.</li>
	</ol>
	<h3>Se il deployment risponde "Non disponi dell'autorizzazione necessaria"</h3>
	<ul style="max-width:820px;list-style:disc;padding-left:1.5em">
		<li>Script collegato a un foglio in un Drive condiviso: in Dettagli progetto compare "Contenitore". Rimedio: progetto autonomo.</li>
		<li>Più account Google aperti nel browser (l'indirizzo dell'editor contiene <code>/u/1/</code>): una finestra anonima con un solo account.</li>
		<li>Nel dominio Workspace manca la voce "Chiunque" in "Chi ha accesso": serve l'amministratore del dominio.</li>
	</ul>
	<?php
}

/**
 * Scheda Campi e regole: per ogni collegamento campi del modulo, colonne del foglio, tab, regole e
 * messaggi d'errore.
 */
function wvi_admin_campi() {
	foreach ( array_keys( wvi_collegamenti() ) as $id ) :
		$c     = wvi_config( $id );
		$campi = wvi_campi( $c['form'] );
		wvi_admin_apri( $id, 'campi' );
		?>
		<h2 style="margin-top:1.5em"><?php echo esc_html( $c['titolo'] ); ?> <span style="font-weight:normal;font-size:13px">— modulo n. <?php echo (int) $c['form']; ?></span></h2>
		<?php if ( ! $c['form'] ) : ?>
			<p>Prima scegli il modulo nella scheda <a href="<?php echo esc_url( wvi_admin_url() ); ?>">Collegamenti</a>.</p>
			</form>
			<?php
			continue;
		endif;
		$voci = array( $c['campo_cf'], 'soci' === $id ? $c['campo_email'] : $c['campo_corsi'] );
		foreach ( (array) $c['colonne'] as $campo ) {
			$voci[] = is_array( $campo ) ? (string) $campo[0] : (string) $campo;
		}
		wvi_admin_avviso_assenti( $c['form'], $voci, 'Campi e colonne' );
		wvi_admin_elenco_campi( $c['form'] );
		?>
		<table class="form-table" role="presentation">
			<?php
			wvi_admin_riga( 'Campo del codice fiscale', wvi_admin_campo( 'campo_cf', $c['campo_cf'], $campi ), 'Serve alle regole, a "Recupera i tuoi dati" e alla ricerca dei soci. Nel foglio va in maiuscolo.' );
			wvi_admin_riga( 'Controllo del codice fiscale', wvi_admin_casella( 'controlla', $c['controlla'], 'Controlla formato e carattere di controllo (codice fiscale italiano di una persona)' ) );
			if ( 'soci' === $id ) {
				wvi_admin_riga( 'Campo dell\'email', wvi_admin_campo( 'campo_email', $c['campo_email'], $campi ), 'Indirizzo a cui "Recupera i tuoi dati" scrive.' );
				wvi_admin_riga( 'Una sola iscrizione per anno', wvi_admin_casella( 'unico', $c['unico'], 'Ferma il modulo se il codice fiscale è già fra i soci dell\'anno sociale' ) );
				wvi_admin_riga( 'Messaggio: già socio', wvi_admin_area( 'msg_doppio', $c['msg_doppio'], 3 ), 'Segnaposto: <code>{anno}</code>, <code>{email}</code>, <code>{link_soci}</code>, <code>{associazione}</code>. HTML semplice ammesso.' );
				wvi_admin_riga( 'Prefisso dei tab', wvi_admin_testo( 'tab_prefisso', $c['tab_prefisso'] ), 'Un tab per anno sociale: prefisso + anno, per esempio "Soci 2026-2027". Lo script crea il tab dell\'anno nuovo copiando le intestazioni del più recente.' );
				wvi_admin_riga( 'Colonna della data', wvi_admin_testo( 'col_data', $c['col_data'] ), 'Data dell\'iscrizione ("Ott 5, 2026 @ 10:08 AM"). Vuoto: nessuna colonna.' );
			} else {
				wvi_admin_riga( 'Campo dei corsi', wvi_admin_campo( 'campo_corsi', $c['campo_corsi'], $campi ), 'Caselle o scelta con i corsi: una riga per ogni corso scelto.' );
				wvi_admin_riga( 'Riservato ai soci', wvi_admin_casella( 'richiede_socio', $c['richiede_socio'], 'Ferma il modulo se il codice fiscale non è fra i soci dell\'anno sociale' ) );
				wvi_admin_riga( 'Messaggio: non socio', wvi_admin_area( 'msg_socio', $c['msg_socio'], 3 ), 'Segnaposto: <code>{anno}</code>, <code>{link_soci}</code>, <code>{email}</code>, <code>{associazione}</code>.' );
				wvi_admin_riga( 'Colonna del corso', wvi_admin_testo( 'col_corso', $c['col_corso'] ) );
				wvi_admin_riga( 'Tab di configurazione', wvi_admin_testo( 'tab_configurazioni', $c['tab_configurazioni'] ), 'Tab del foglio con la corrispondenza: colonna A il tab, colonna B il testo del corso.' );
				wvi_admin_riga( 'Tab di riserva', wvi_admin_testo( 'tab_riserva', $c['tab_riserva'] ), 'Per i corsi che il tab di configurazione non conosce.' );
			}
			wvi_admin_riga( 'Colonne del foglio', wvi_admin_area( 'colonne', wvi_colonne_in_testo( $c['colonne'] ), 12 ), 'Una per riga: <code>Intestazione = campo</code>, o <code>Intestazione = address-1.city</code> per una parte di indirizzo. Lo script scrive per intestazione (maiuscole e spazi non contano) e lascia intatte le altre colonne del foglio.' );
			?>
		</table>
		<p><button class="button button-primary" name="wvi_azione" value="regole">Salva</button></p>
		</form>
	<?php endforeach; ?>
	<?php
}

/**
 * Scheda Passaggio e recupero: passaggio dal modulo dei soci a quello dei corsi, "Recupera i tuoi dati",
 * compilazione automatica del browser.
 */
function wvi_admin_funzioni() {
	$soci  = wvi_config( 'soci' );
	$corsi = wvi_config( 'corsi' );
	$g     = wvi_generali();

	wvi_admin_apri( 'corsi', 'funzioni' );
	?>
	<h2>Dal modulo dei soci a quello dei corsi</h2>
	<p style="max-width:820px">Chi nel modulo dei soci sceglie di iscriversi anche a un corso arriva alla pagina dei corsi con il modulo già compilato. In Forminator, nel modulo dei soci: un comportamento dopo l'invio "Reindirizza" verso la pagina dei corsi (Collegamenti → Impostazioni generali), con la condizione voluta. I campi ripresi devono avere lo stesso id nei due moduli.</p>
	<table class="form-table" role="presentation">
		<?php
		wvi_admin_riga( 'Attivo', wvi_admin_casella( 'passaggio', $corsi['passaggio'], 'Ricompila il modulo dei corsi dopo l\'iscrizione a socio' ) );
		wvi_admin_riga( 'Campi da riportare', wvi_admin_testo( 'passaggio_campi', implode( ', ', $corsi['passaggio_campi'] ), 'large-text code' ), 'Id separati da virgole, per esempio <code>name-1, name-2, text-1, email-1</code>.' );
		wvi_admin_riga( 'Caselle da spuntare', wvi_admin_testo( 'passaggio_caselle', implode( ', ', $corsi['passaggio_caselle'] ), 'large-text code' ), 'Caselle del modulo dei corsi già accettate con l\'iscrizione a socio (regolamento, privacy…).' );
		wvi_admin_riga( 'Avviso sopra il modulo', wvi_admin_area( 'passaggio_avviso', $corsi['passaggio_avviso'], 2 ) );
		?>
	</table>
	<?php
	if ( $corsi['passaggio'] ) {
		wvi_admin_avviso_assenti( $soci['form'], $corsi['passaggio_campi'], 'Campi da riportare, nel modulo dei soci' );
		wvi_admin_avviso_assenti( $corsi['form'], array_merge( $corsi['passaggio_campi'], $corsi['passaggio_caselle'] ), 'Campi e caselle da riportare, nel modulo dei corsi' );
	}
	?>
	<?php wvi_admin_elenco_campi( $corsi['form'] ); ?>
	<p><button class="button button-primary" name="wvi_azione" value="regole">Salva</button></p>
	</form>

	<?php wvi_admin_apri( 'soci', 'funzioni' ); ?>
	<h2>Recupera i tuoi dati</h2>
	<p style="max-width:820px">Sotto il codice fiscale del modulo dei soci, un link manda all'indirizzo email dell'ultima iscrizione un link personale che riapre il modulo già compilato. A video il messaggio è sempre lo stesso, che il codice fiscale sia in archivio o no; agli amministratori loggati compare l'esito vero. Servono il campo del codice fiscale e quello dell'email (scheda <a href="<?php echo esc_url( wvi_admin_url( 'campi' ) ); ?>">Campi e regole</a>) e la pagina del modulo dei soci. Anteprima per gli amministratori: <code>?wvi-socio-prova=</code> + numero dell'iscrizione, sulla pagina del modulo.</p>
	<table class="form-table" role="presentation">
		<?php
		wvi_admin_riga( 'Attivo', wvi_admin_casella( 'recupera', $soci['recupera'], 'Mostra "Recupera i tuoi dati" nel modulo dei soci' ) );
		wvi_admin_riga( 'Campi da recuperare', wvi_admin_area( 'recupera_campi', implode( ', ', $soci['recupera_campi'] ), 3 ), 'Id separati da virgole; parti di indirizzo con il punto (<code>address-1.city</code>). Fuori ricevute, accettazioni e consensi, che vanno rinnovati.' );
		wvi_admin_riga( 'Anni sociali', sprintf( '<input type="number" min="1" class="small-text" name="wvi[recupera_anni]" value="%d">', (int) $soci['recupera_anni'] ), 'Quanti anni sociali precedenti si guardano per l\'ultima iscrizione.' );
		wvi_admin_riga( 'Durata del link (ore)', sprintf( '<input type="number" min="1" class="small-text" name="wvi[recupera_ore]" value="%d">', (int) $soci['recupera_ore'] ) );
		wvi_admin_riga( 'Richieste all\'ora per connessione', sprintf( '<input type="number" min="1" class="small-text" name="wvi[recupera_richieste]" value="%d">', (int) $soci['recupera_richieste'] ) );
		wvi_admin_riga( 'Minuti fra due email', sprintf( '<input type="number" min="1" class="small-text" name="wvi[recupera_attesa]" value="%d">', (int) $soci['recupera_attesa'] ), 'Allo stesso codice fiscale: protegge la casella di chiunque da invii ripetuti.' );
		wvi_admin_riga( 'Link sotto il codice fiscale', wvi_admin_testo( 'recupera_invito', $soci['recupera_invito'], 'large-text' ), 'Il testo fra <code>&lt;a&gt;</code> e <code>&lt;/a&gt;</code> diventa il link.' );
		wvi_admin_riga( 'Messaggio a video', wvi_admin_area( 'recupera_messaggio', $soci['recupera_messaggio'], 2 ), 'Uguale per tutti, per non dire chi è socio. Segnaposto: <code>{ore}</code>.' );
		wvi_admin_riga( 'Avviso sul modulo ricompilato', wvi_admin_area( 'recupera_avviso', $soci['recupera_avviso'], 2 ) );
		wvi_admin_riga( 'Email con il link: oggetto', wvi_admin_testo( 'recupera_link_ogg', $soci['recupera_link_ogg'], 'large-text' ) );
		wvi_admin_riga( 'Email con il link: testo', wvi_admin_area( 'recupera_link_testo', $soci['recupera_link_testo'], 9 ), 'Segnaposto: <code>{link}</code> (obbligatorio), <code>{ore}</code>, <code>{associazione}</code>, <code>{email}</code>.' );
		wvi_admin_riga( 'Email "già iscritto": oggetto', wvi_admin_testo( 'recupera_gia_ogg', $soci['recupera_gia_ogg'], 'large-text' ) );
		wvi_admin_riga( 'Email "già iscritto": testo', wvi_admin_area( 'recupera_gia_testo', $soci['recupera_gia_testo'], 7 ), 'Segnaposto: <code>{anno}</code>, <code>{associazione}</code>, <code>{email}</code>.' );
		?>
	</table>
	<?php
	if ( $soci['recupera'] ) {
		wvi_admin_avviso_assenti( $soci['form'], $soci['recupera_campi'], 'Campi da recuperare' );
	}
	?>
	<?php wvi_admin_elenco_campi( $soci['form'] ); ?>
	<p><button class="button button-primary" name="wvi_azione" value="regole">Salva</button></p>
	</form>

	<?php wvi_admin_apri( '', 'funzioni' ); ?>
	<h2>Compilazione automatica del browser</h2>
	<p style="max-width:820px">Valori dell'attributo <code>autocomplete</code> per i campi dei moduli dei soci e dei corsi, così il browser propone i dati salvati: <code>given-name</code>, <code>family-name</code>, <code>email</code>, <code>tel</code>, <code>street-address</code>, <code>postal-code</code>… e <code>off</code> per i campi da non riempire (per esempio il luogo di nascita, che altrimenti riceverebbe la città di residenza).</p>
	<?php
	$coppie = array();
	foreach ( $g['autocomplete'] as $campo => $valore ) {
		$coppie[] = "{$campo} = {$valore}";
	}
	?>
	<table class="form-table" role="presentation">
		<?php wvi_admin_riga( 'Campi', wvi_admin_area( 'autocomplete', implode( "\n", $coppie ), 6 ), 'Una per riga: <code>name-1 = given-name</code>. Per le parti di indirizzo il nome nella pagina: <code>address-1-city = address-level2</code>.' ); ?>
	</table>
	<p><button class="button button-primary" name="wvi_azione" value="generali">Salva</button></p>
	</form>
	<?php
}

/**
 * Scheda Script Apps Script: il testo da incollare nell'editor, con il pulsante per copiarlo.
 */
function wvi_admin_script() {
	?>
	<p style="max-width:820px">Testo completo degli script da incollare nell'editor di Apps Script, al posto del codice esistente. Istruzioni nella scheda <a href="<?php echo esc_url( wvi_admin_url() ); ?>">Collegamenti</a>. Dopo un aggiornamento del plugin conviene confrontare lo script pubblicato con quello qui sotto e, se diverso, pubblicare una Nuova versione del deployment.</p>
	<?php foreach ( array_keys( wvi_collegamenti() ) as $id ) : ?>
		<?php
		$c     = wvi_config( $id );
		$testo = wvi_script( $id );
		if ( '' === $testo ) {
			continue;
		}
		?>
		<h2 style="margin-top:1.5em"><?php echo esc_html( $c['titolo'] ); ?> <span style="font-weight:normal;font-size:13px">— <code><?php echo esc_html( $c['script'] ); ?></code></span></h2>
		<p>
			<button type="button" class="button button-primary" data-wvi-copia="wvi-script-<?php echo esc_attr( $id ); ?>">Copia lo script</button>
			<span class="wvi-copiato" aria-live="polite" style="margin-left:.5em"></span>
		</p>
		<textarea id="wvi-script-<?php echo esc_attr( $id ); ?>" class="large-text code" rows="18" readonly spellcheck="false" style="font-size:12px"><?php echo esc_textarea( $testo ); ?></textarea>
	<?php endforeach; ?>
	<script>
	document.querySelectorAll( '[data-wvi-copia]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var area = document.getElementById( b.getAttribute( 'data-wvi-copia' ) );
			var nota = b.parentNode.querySelector( '.wvi-copiato' );
			var fatto = function () { nota.textContent = 'Copiato.'; };
			var aMano = function () {
				area.focus();
				area.select();
				nota.textContent = document.execCommand( 'copy' ) ? 'Copiato.' : 'Testo selezionato: Ctrl+C per copiarlo.';
			};
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( area.value ).then( fatto, aMano );
			} else {
				aMano();
			}
		} );
	} );
	</script>
	<?php
}

/**
 * Scheda d'invio di un collegamento: stato, prova a secco, colonne, invio subito, ripartenza. Per i
 * corsi anche la corrispondenza corso → tab letta dal foglio.
 *
 * @param string $id Collegamento.
 */
function wvi_admin_invio( $id ) {
	$c        = wvi_config( $id );
	$quando   = static fn( $t ) => $t ? wp_date( 'j F Y, H:i', (int) $t ) : '—';
	$attivo   = wvi_configurato( $id );
	$ultimo   = $attivo ? wvi_ultimo( $id ) : 0;
	$inizio   = $c['inizio'] ? (int) get_option( $c['inizio'], 0 ) : 0;
	$stato    = (array) get_option( $c['stato'], array() );
	$mappa    = null;
	$gruppo   = null;
	$partenza = $c['inizio']
		? sprintf( 'dopo l\'iscrizione n. %d (la più recente quando il collegamento è stato attivato)', $inizio )
		: ( $c['dal'] ? 'dal ' . wp_date( 'j F Y', strtotime( $c['dal'] ) ) : 'dalla prima' );

	if ( 'corsi' === $id && $attivo ) {
		$mappa  = wvi_corsi_mappa();
		$gruppo = is_wp_error( $mappa )
			? static fn( $riga ) => 'corso: ' . ( '' !== ( $riga[ $c['col_corso'] ] ?? '' ) ? $riga[ $c['col_corso'] ] : '(nessuno)' )
			: static fn( $riga ) => wvi_corsi_tab( $riga[ $c['col_corso'] ] ?? '', $mappa );
	}
	$prova = $attivo ? wvi_prova( $id, $gruppo ) : null;
	?>
	<h2><?php echo esc_html( $c['titolo'] ); ?></h2>
	<p>Modulo Forminator n. <?php echo (int) $c['form']; ?> → foglio Google<?php echo $attivo ? esc_html( ', iscrizioni ' . $partenza ) : ''; ?>.</p>
	<?php if ( ! $attivo ) : ?>
		<div class="notice notice-warning inline"><p>Spento: servono modulo, indirizzo e codice segreto nella scheda <a href="<?php echo esc_url( wvi_admin_url() ); ?>">Collegamenti</a> e Forminator attivo.</p></div>
	<?php endif; ?>
	<?php if ( is_wp_error( $mappa ) ) : ?>
		<div class="notice notice-warning inline"><p>Tab "<?php echo esc_html( $c['tab_configurazioni'] ); ?>" del foglio non letto: <?php echo esc_html( $mappa->get_error_message() ); ?> La prova a secco raggruppa le righe per corso.</p></div>
	<?php endif; ?>

	<?php if ( $attivo ) : ?>
		<table class="widefat striped" style="max-width:720px">
			<tbody>
				<tr><th>Ultima iscrizione confermata dal foglio</th><td>n. <?php echo (int) $ultimo; ?></td></tr>
				<tr><th>Ultimo invio riuscito</th><td><?php echo esc_html( $quando( $stato['ultimo_invio'] ?? 0 ) ); ?></td></tr>
				<tr><th>Ultimo controllo</th><td><?php echo esc_html( $quando( $stato['controllo'] ?? 0 ) ); ?></td></tr>
				<tr><th>Prossimo controllo</th><td><?php echo esc_html( $quando( wp_next_scheduled( 'wvi_sync' ) ) ); ?></td></tr>
				<tr><th>Errore</th><td><?php echo esc_html( ( $stato['errore'] ?? '' ) ? $stato['errore'] . ' (dal ' . $quando( $stato['errore_dal'] ) . ')' : 'nessuno' ); ?></td></tr>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( $prova ) : ?>
		<h3>Prova a secco</h3>
		<p><?php echo esc_html( sprintf( '%d iscrizioni da inviare, %d righe.', $prova['totale'], $prova['righe'] ) ); ?>
			<?php foreach ( $prova['tab'] as $nome => $n ) : ?>
				<br><?php echo esc_html( "{$nome}: {$n}" ); ?>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>

	<h3>Colonne</h3>
	<p>Si cambiano nella scheda <a href="<?php echo esc_url( wvi_admin_url( 'campi' ) ); ?>">Campi e regole</a>.</p>
	<table class="widefat striped" style="max-width:720px">
		<thead><tr><th>Colonna del foglio</th><th>Campo del modulo</th><?php echo $prova ? '<th>Righe con la colonna piena</th>' : ''; ?></tr></thead>
		<tbody>
			<?php foreach ( (array) $c['colonne'] as $col => $campo ) : ?>
				<tr>
					<td><?php echo esc_html( $col ); ?></td>
					<td><code><?php echo esc_html( implode( ' → ', (array) $campo ) ); ?></code></td>
					<?php if ( $prova ) : ?>
						<td><?php echo (int) ( $prova['piene'][ $col ] ?? 0 ); ?> su <?php echo (int) $prova['righe']; ?></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $c['colonne'] ) : ?>
				<tr><td colspan="3"><em>Nessuna colonna: il foglio riceve solo i riferimenti.</em></td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( 'corsi' === $id && $attivo ) : ?>
		<?php wvi_admin_mappa( $mappa ); ?>
	<?php endif; ?>

	<?php if ( $attivo ) : ?>
		<?php wvi_admin_apri( $id, $id ); ?>
			<p style="margin-top:1.5em">
				<button class="button button-primary" name="wvi_azione" value="invia">Invia adesso</button>
				<button class="button" name="wvi_azione" value="riparti" onclick="return confirm('Ricominciare <?php echo esc_js( $partenza ); ?>? Le righe già nel foglio non vengono duplicate.');">Ricomincia dal punto di partenza</button>
			</p>
		</form>
	<?php endif; ?>
	<?php
}

/**
 * Corsi: opzioni del modulo e tab in cui finiscono, secondo il tab di configurazione del foglio.
 *
 * @param array|WP_Error $mappa Risultato di wvi_corsi_mappa().
 */
function wvi_admin_mappa( $mappa ) {
	$c       = wvi_config( 'corsi' );
	$opzioni = wvi_corsi_opzioni();
	$ok      = ! is_wp_error( $mappa );
	?>
	<h3>Corsi e tab del foglio</h3>
	<p style="max-width:720px">Opzioni del campo <code><?php echo esc_html( $c['campo_corsi'] ); ?></code> del modulo e tab in cui il foglio le scrive, secondo il suo tab <code><?php echo esc_html( $c['tab_configurazioni'] ); ?></code> (colonna A: tab, colonna B: testo del corso). Per spostare un corso in un altro tab, o aggiungerne uno, basta modificare quel tab nel foglio.</p>
	<table class="widefat striped" style="max-width:720px">
		<thead><tr><th>Corso (testo dell'opzione, da copiare in colonna B)</th><th>Tab</th></tr></thead>
		<tbody>
			<?php foreach ( $opzioni as $corso ) : ?>
				<?php $tab = $ok ? wvi_corsi_tab( $corso, $mappa ) : ''; ?>
				<tr>
					<td><?php echo esc_html( $corso ); ?></td>
					<td>
						<?php
						if ( ! $ok ) {
							echo '—';
						} elseif ( $c['tab_riserva'] === $tab ) {
							echo '<strong>' . esc_html( $tab ) . '</strong>';
						} else {
							echo '<code>' . esc_html( $tab ) . '</code>';
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $opzioni ) : ?>
				<tr><td colspan="2"><em>Nessuna opzione letta dal campo dei corsi.</em></td></tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php if ( $ok ) : ?>
		<?php
		$senza = array();
		foreach ( $mappa['mappa'] as $corso => $tab ) {
			if ( ! in_array( $tab, $mappa['tab'], true ) ) {
				$senza[] = "{$tab} ({$corso})";
			}
		}
		?>
		<?php if ( $senza ) : ?>
			<p>Nel tab di configurazione ci sono tab che il foglio non contiene: <?php echo esc_html( implode( '; ', $senza ) ); ?>.</p>
		<?php endif; ?>
	<?php endif; ?>
	<?php wvi_admin_apri( 'corsi', 'corsi' ); ?>
		<p><button class="button" name="wvi_azione" value="mappa">Rileggi il tab di configurazione</button> <span class="description">Il sito ne tiene una copia per 5 minuti.</span></p>
	</form>
	<?php
}
