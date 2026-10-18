<?php
/**
 * Componenti del pannello: il campo di una voce dello schema secondo il suo tipo, la tabella di una
 * scheda generata dallo schema, elenco dei campi di un modulo, avviso sui campi indicati ma assenti.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

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
 * Moduli di Forminator per la tendina: id => nome.
 */
function wvi_admin_moduli() {
	static $moduli = null;
	if ( null !== $moduli ) {
		return $moduli;
	}
	$moduli = array();
	if ( ! class_exists( 'Forminator_API' ) || ! method_exists( 'Forminator_API', 'get_forms' ) ) {
		return $moduli;
	}
	$trovati = Forminator_API::get_forms( null, 1, 200 );
	foreach ( is_array( $trovati ) ? $trovati : array() as $m ) {
		if ( ! is_object( $m ) || empty( $m->id ) ) {
			continue;
		}
		$nome                   = (string) ( $m->settings['formName'] ?? $m->name ?? '' );
		$moduli[ (int) $m->id ] = sprintf( 'n. %d — %s', (int) $m->id, '' !== $nome ? $nome : 'senza nome' );
	}
	ksort( $moduli );
	return $moduli;
}

/**
 * HTML del campo di una voce dello schema, secondo il tipo.
 *
 * @param array $v      Voce dello schema.
 * @param mixed $valore Valore attuale.
 * @param int   $form   Modulo del collegamento (per le tendine dei campi).
 */
function wvi_admin_campo_html( $v, $valore, $form ) {
	$nome       = 'wvi[' . $v['chiave'] . ']';
	$classe     = $v['classe'];
	$testo      = static fn( $tipo, $val, $extra = '' ) => sprintf( '<input type="%s" class="%s" name="%s" value="%s"%s>', $tipo, esc_attr( $classe ), esc_attr( $nome ), esc_attr( (string) $val ), $extra );
	$segnaposto = '' !== $v['segnaposto'] ? ' placeholder="' . esc_attr( $v['segnaposto'] ) . '"' : '';
	switch ( $v['tipo'] ) {
		case 'casella':
			return sprintf( '<label><input type="checkbox" name="%s" value="1"%s> %s</label>', esc_attr( $nome ), checked( (bool) $valore, true, false ), esc_html( $v['casella'] ) );
		case 'numero':
			return sprintf( '<input type="number" min="1" class="small-text" name="%s" value="%d">', esc_attr( $nome ), (int) $valore );
		case 'mese':
			$mesi = array( 1 => 'gennaio (anno solare)', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre' );
			$html = sprintf( '<select name="%s">', esc_attr( $nome ) );
			foreach ( $mesi as $n => $mese ) {
				$html .= sprintf( '<option value="%d"%s>%s</option>', $n, selected( $n, (int) $valore, false ), esc_html( $mese ) );
			}
			return $html . '</select>';
		case 'modulo':
			$moduli = wvi_admin_moduli();
			if ( ! $moduli ) {
				return sprintf( '<input type="number" min="0" class="small-text" name="%s" value="%d">', esc_attr( $nome ), (int) $valore );
			}
			$html = sprintf( '<select name="%s"><option value="0">— nessuno —</option>', esc_attr( $nome ) );
			if ( $valore && ! isset( $moduli[ (int) $valore ] ) ) {
				$html .= sprintf( '<option value="%d" selected>n. %d — modulo assente</option>', (int) $valore, (int) $valore );
			}
			foreach ( $moduli as $id => $titolo ) {
				$html .= sprintf( '<option value="%d"%s>%s</option>', $id, selected( $id, (int) $valore, false ), esc_html( $titolo ) );
			}
			return $html . '</select>';
		case 'campo':
			$campi = wvi_campi( $form );
			if ( ! $campi ) {
				return sprintf( '<input type="text" class="regular-text code" name="%s" value="%s">', esc_attr( $nome ), esc_attr( (string) $valore ) );
			}
			$html = sprintf( '<select name="%s"><option value="">— nessuno —</option>', esc_attr( $nome ) );
			$noto = false;
			foreach ( $campi as $f ) {
				$noto  = $noto || $f['id'] === $valore;
				$html .= sprintf( '<option value="%s"%s>%s — %s</option>', esc_attr( $f['id'] ), selected( $f['id'], $valore, false ), esc_html( $f['id'] ), esc_html( $f['etichetta'] ) );
			}
			if ( '' !== (string) $valore && ! $noto ) {
				$html .= sprintf( '<option value="%s" selected>%s — campo assente dal modulo</option>', esc_attr( $valore ), esc_html( $valore ) );
			}
			return $html . '</select>';
		case 'url':
			return $testo( 'url', $valore, $segnaposto );
		case 'email':
			return $testo( 'email', $valore );
		case 'data':
			return sprintf( '<input type="date" name="%s" value="%s">', esc_attr( $nome ), esc_attr( (string) $valore ) );
		case 'segreto':
			$salvato = '' !== (string) $valore ? 'salvato, termina con …' . substr( (string) $valore, -4 ) : '';
			return sprintf( '<input type="password" class="%s" name="%s" value="" autocomplete="new-password" placeholder="%s">', esc_attr( $classe ), esc_attr( $nome ), esc_attr( $salvato ) );
		case 'foglio':
			return $testo( 'text', $valore, $segnaposto );
		case 'area':
		case 'area_html':
		case 'colonne':
		case 'coppie':
			if ( 'colonne' === $v['tipo'] ) {
				$valore = wvi_colonne_in_testo( $valore );
			} elseif ( 'coppie' === $v['tipo'] ) {
				$righe = array();
				foreach ( (array) $valore as $campo => $val ) {
					$righe[] = "{$campo} = {$val}";
				}
				$valore = implode( "\n", $righe );
			}
			return sprintf( '<textarea class="large-text code" rows="%d" name="%s">%s</textarea>', (int) $v['righe'], esc_attr( $nome ), esc_textarea( (string) $valore ) );
		case 'elenco':
			$valore = implode( ', ', (array) $valore );
			if ( $v['righe'] ) {
				return sprintf( '<textarea class="large-text code" rows="%d" name="%s">%s</textarea>', (int) $v['righe'], esc_attr( $nome ), esc_textarea( $valore ) );
			}
			return $testo( 'text', $valore );
		default: // testo, prefisso, html.
			return $testo( 'text', $valore );
	}
}

/**
 * Righe della tabella di un modulo del pannello: le voci dello schema di quell'opzione e di quella
 * scheda, con i valori attuali. Il modulo si salva con wvi_salva() sulle stesse voci.
 *
 * @param string $dove   generali, soci, corsi.
 * @param string $scheda Scheda del pannello.
 */
function wvi_admin_righe( $dove, $scheda ) {
	$attuali = 'generali' === $dove ? wvi_generali() : wvi_config( $dove );
	$form    = 'generali' === $dove ? 0 : (int) $attuali['form'];
	foreach ( wvi_schema_voci( $dove, $scheda ) as $chiave => $v ) {
		$aiuto = $v['aiuto'];
		if ( 'foglio' === $v['tipo'] && '' !== (string) $attuali[ $chiave ] ) {
			$aiuto .= ' <a href="' . esc_url( 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $attuali[ $chiave ] ) . '/edit' ) . '" target="_blank" rel="noopener">Apri il foglio</a>';
		}
		wvi_admin_riga( $v['etichetta'], wvi_admin_campo_html( $v, $attuali[ $chiave ], $form ), $aiuto );
	}
}

/**
 * Tabella di un modulo del pannello.
 *
 * @param string $dove   generali, soci, corsi.
 * @param string $scheda Scheda del pannello.
 */
function wvi_admin_tabella( $dove, $scheda ) {
	echo '<table class="form-table" role="presentation">';
	wvi_admin_righe( $dove, $scheda );
	echo '</table>';
}

/**
 * Avviso con i campi indicati che il modulo non contiene (wvi_campi_assenti()).
 *
 * @param int      $form   Id del modulo.
 * @param string[] $voci   Campi indicati.
 * @param string   $doveli Dove sono indicati (per il messaggio).
 */
function wvi_admin_avviso_assenti( $form, $voci, $doveli ) {
	$assenti = wvi_campi_assenti( $form, $voci );
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
	<details class="wvi-largo wvi-campi-modulo">
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
