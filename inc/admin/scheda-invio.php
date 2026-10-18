<?php
/**
 * Schede Invio soci e Invio corsi: stato dell'invio, prova a secco, colonne, invio subito, ripartenza.
 * Per i corsi anche la corrispondenza corso → tab letta dal foglio, solo su richiesta: aprire la
 * scheda non chiama Google.
 *
 * @package WvoceIscrizioni
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contenuto della scheda d'invio di un collegamento.
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
	$mappa    = 'corsi' === $id ? wvi_corsi_mappa_copia() : null;
	$gruppo   = null;
	$partenza = $c['inizio']
		? sprintf( 'dopo l\'iscrizione n. %d (la più recente quando il collegamento è stato attivato)', $inizio )
		: ( $c['dal'] ? 'dal ' . wp_date( 'j F Y', strtotime( $c['dal'] ) ) : 'dalla prima' );

	if ( 'corsi' === $id ) {
		$gruppo = $mappa
			? static fn( $riga ) => wvi_corsi_tab( $riga[ $c['col_corso'] ] ?? '', $mappa )
			: static fn( $riga ) => 'corso: ' . ( '' !== ( $riga[ $c['col_corso'] ] ?? '' ) ? $riga[ $c['col_corso'] ] : '(nessuno)' );
	}
	$prova = $attivo ? wvi_prova( $id, $gruppo ) : null;
	?>
	<h2><?php echo esc_html( $c['titolo'] ); ?></h2>
	<p>Modulo Forminator n. <?php echo (int) $c['form']; ?> → foglio Google<?php echo $attivo ? esc_html( ', iscrizioni ' . $partenza ) : ''; ?>.</p>
	<?php if ( ! $attivo ) : ?>
		<div class="notice notice-warning inline"><p>Spento: servono modulo, indirizzo e codice segreto nella scheda <a href="<?php echo esc_url( wvi_admin_url() ); ?>">Collegamenti</a> e Forminator attivo.</p></div>
	<?php endif; ?>

	<?php if ( $attivo ) : ?>
		<table class="widefat striped wvi-medio">
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
	<table class="widefat striped wvi-medio">
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
			<p class="wvi-staccato">
				<button class="button button-primary" name="wvi_azione" value="invia">Invia adesso</button>
				<button class="button" name="wvi_azione" value="riparti" data-wvi-conferma="<?php echo esc_attr( "Ricominciare {$partenza}? Le righe già nel foglio non vengono duplicate." ); ?>">Ricomincia dal punto di partenza</button>
			</p>
		</form>
	<?php endif; ?>
	<?php
}

/**
 * Corsi: opzioni del modulo e tab in cui finiscono, secondo il tab di configurazione del foglio letto
 * l'ultima volta; senza una lettura, solo le opzioni e il pulsante per leggerlo.
 *
 * @param array|null $mappa Copia della corrispondenza (wvi_corsi_mappa_copia()).
 */
function wvi_admin_mappa( $mappa ) {
	$c       = wvi_config( 'corsi' );
	$opzioni = wvi_corsi_opzioni();
	?>
	<h3>Corsi e tab del foglio</h3>
	<p class="wvi-medio">Opzioni del campo <code><?php echo esc_html( $c['campo_corsi'] ); ?></code> del modulo e tab in cui il foglio le scrive, secondo il suo tab <code><?php echo esc_html( $c['tab_configurazioni'] ); ?></code> (colonna A: tab, colonna B: testo del corso). Per spostare un corso in un altro tab, o aggiungerne uno, basta modificare quel tab nel foglio.</p>
	<table class="widefat striped wvi-medio">
		<thead><tr><th>Corso (testo dell'opzione, da copiare in colonna B)</th><th>Tab</th></tr></thead>
		<tbody>
			<?php foreach ( $opzioni as $corso ) : ?>
				<?php $tab = $mappa ? wvi_corsi_tab( $corso, $mappa ) : ''; ?>
				<tr>
					<td><?php echo esc_html( $corso ); ?></td>
					<td>
						<?php
						if ( ! $mappa ) {
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
	<?php if ( $mappa ) : ?>
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
		<p>
			<button class="button" name="wvi_azione" value="mappa"><?php echo $mappa ? 'Rileggi il tab di configurazione' : 'Leggi il tab di configurazione dal foglio'; ?></button>
			<span class="description"><?php echo $mappa ? esc_html( 'Letto il ' . wp_date( 'j F Y, H:i', (int) $mappa['letta'] ) . '; la prova a secco usa questa lettura.' ) : 'Finché non lo leggi, la prova a secco raggruppa le righe per corso.'; ?></span>
		</p>
	</form>
	<?php
}
