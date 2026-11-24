<?php
/**
 * Ciclo dell'agente.
 *
 * Ogni richiesta HTTP esegue UN solo passo: o una chiamata all'API, o
 * l'esecuzione di un gruppo di strumenti. Il browser richiama /continue
 * finché il lavoro non è finito: così nessuna richiesta supera i timeout
 * di PHP, del server o di proxy come Cloudflare, per quanto lungo sia il lavoro.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIA_Agent {

	const API_URL    = 'https://api.anthropic.com/v1/messages';
	const META_KEY   = '_wpaia_state';
	const MAX_RESULT = 60000; // caratteri per risultato strumento inviati al modello

	private $user_id;

	public function __construct( $user_id ) {
		$this->user_id = (int) $user_id;
	}

	/* ------------------------------------------------------------------
	 * Stato
	 * ---------------------------------------------------------------- */

	private function load() {
		$s = get_user_meta( $this->user_id, self::META_KEY, true );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		return wp_parse_args( $s, array( 'messages' => array(), 'pending' => null, 'steps' => 0 ) );
	}

	private function save( $state ) {
		$state['messages'] = $this->trim_history( $state['messages'] );
		update_user_meta( $this->user_id, self::META_KEY, wp_slash( $state ) );
	}

	public function reset() {
		delete_user_meta( $this->user_id, self::META_KEY );
	}

	/** Conserva al massimo N messaggi, ripartendo da un messaggio utente testuale. */
	private function trim_history( $messages ) {
		$max = (int) wpaia_settings()['history'];
		while ( count( $messages ) > $max ) {
			array_shift( $messages );
			while ( $messages && ! ( 'user' === $messages[0]['role'] && is_string( $messages[0]['content'] ) ) ) {
				array_shift( $messages );
			}
		}
		return array_values( $messages );
	}

	/** tool_use dell'ultimo messaggio assistente senza ancora un risultato. */
	private function unresolved_tool_uses( $state ) {
		$last = end( $state['messages'] );
		if ( ! $last || 'assistant' !== $last['role'] || ! is_array( $last['content'] ) ) {
			return array();
		}
		return array_values( array_filter( $last['content'], function ( $b ) {
			return 'tool_use' === ( $b['type'] ?? '' );
		} ) );
	}

	/* ------------------------------------------------------------------
	 * Ingressi
	 * ---------------------------------------------------------------- */

	public function handle_message( $text ) {
		$state = $this->load();
		if ( $state['pending'] ) {
			return new WP_Error( 'wpaia_pending', 'C\'è un\'azione in attesa di conferma: approvala o rifiutala prima.' );
		}
		$blocks = array();
		// Lavoro interrotto (pagina chiusa o "Interrompi"): chiudiamo i tool_use rimasti aperti.
		foreach ( $this->unresolved_tool_uses( $state ) as $b ) {
			$blocks[] = $this->tool_result( $b['id'], array( 'error' => 'Interrotto dall\'utente prima dell\'esecuzione.' ) );
		}
		$last = end( $state['messages'] );
		if ( $blocks ) {
			$blocks[]            = array( 'type' => 'text', 'text' => $text );
			$state['messages'][] = array( 'role' => 'user', 'content' => $blocks );
		} elseif ( $last && 'user' === $last['role'] ) {
			// Turno utente precedente rimasto senza risposta (errore API): accodiamo.
			$i = count( $state['messages'] ) - 1;
			if ( is_string( $last['content'] ) ) {
				$state['messages'][ $i ]['content'] .= "\n\n" . $text;
			} else {
				$state['messages'][ $i ]['content'][] = array( 'type' => 'text', 'text' => $text );
			}
		} else {
			$state['messages'][] = array( 'role' => 'user', 'content' => $text );
		}
		$state['steps'] = 0;
		return $this->step( $state );
	}

	public function handle_continue() {
		$state = $this->load();
		if ( $state['pending'] ) {
			return array( 'status' => 'confirm', 'events' => array(), 'pending' => $this->describe_pending( $state['pending']['calls'] ) );
		}
		$last = end( $state['messages'] );
		if ( ! $last ) {
			return array( 'status' => 'done', 'events' => array() );
		}
		if ( 'assistant' === $last['role'] && ! $this->unresolved_tool_uses( $state ) ) {
			return array( 'status' => 'done', 'events' => array() );
		}
		return $this->step( $state );
	}

	public function handle_confirmation( $approve ) {
		$state = $this->load();
		if ( ! $state['pending'] ) {
			return new WP_Error( 'wpaia_nopending', 'Nessuna azione in attesa.' );
		}
		$events  = array();
		$results = $state['pending']['results'];
		foreach ( $state['pending']['calls'] as $call ) {
			if ( $approve ) {
				$r        = WPAIA_Tools::execute( $call['name'], $call['input'] );
				$events[] = $this->tool_event( $call['name'], $call['input'], $r );
			} else {
				$r        = array( 'error' => 'L\'utente ha rifiutato questa azione. Non riprovarla; chiedi come procedere.' );
				$events[] = array( 'type' => 'tool', 'name' => $call['name'], 'input' => $call['input'], 'ok' => false, 'summary' => 'rifiutata' );
			}
			$results[] = $this->tool_result( $call['id'], $r );
		}
		$state['messages'][] = array( 'role' => 'user', 'content' => $results );
		$state['pending']    = null;
		$this->save( $state );
		return array( 'status' => 'continue', 'events' => $events );
	}

	/** Storia semplificata per il frontend. */
	public function history() {
		$state  = $this->load();
		$events = array();
		foreach ( $state['messages'] as $m ) {
			if ( 'user' === $m['role'] ) {
				if ( is_string( $m['content'] ) ) {
					$events[] = array( 'type' => 'user', 'text' => $m['content'] );
				} else {
					foreach ( $m['content'] as $b ) {
						if ( 'text' === ( $b['type'] ?? '' ) ) {
							$events[] = array( 'type' => 'user', 'text' => $b['text'] );
						}
					}
				}
				continue;
			}
			foreach ( (array) $m['content'] as $b ) {
				if ( 'text' === $b['type'] && '' !== trim( $b['text'] ) ) {
					$events[] = array( 'type' => 'text', 'text' => $b['text'] );
				} elseif ( 'tool_use' === $b['type'] ) {
					$events[] = array( 'type' => 'tool', 'name' => $b['name'], 'input' => $b['input'], 'ok' => true, 'summary' => '' );
				}
			}
		}
		$last       = end( $state['messages'] );
		$resumable  = ! $state['pending'] && $last && ( 'user' === $last['role'] || $this->unresolved_tool_uses( $state ) );
		return array(
			'events'    => $events,
			'pending'   => $state['pending'] ? $this->describe_pending( $state['pending']['calls'] ) : null,
			'resumable' => (bool) $resumable,
		);
	}

	/* ------------------------------------------------------------------
	 * Un passo
	 * ---------------------------------------------------------------- */

	private function step( $state ) {
		$events = array();

		// A) L'ultimo messaggio dell'assistente chiede strumenti: eseguiamoli.
		$uses = $this->unresolved_tool_uses( $state );
		if ( $uses ) {
			$results = array();
			$pending = array();
			foreach ( $uses as $b ) {
				$input = is_array( $b['input'] ) ? $b['input'] : array();
				if ( WPAIA_Tools::needs_confirmation( $b['name'], $input ) ) {
					$pending[] = array( 'id' => $b['id'], 'name' => $b['name'], 'input' => $input );
					continue;
				}
				$r         = WPAIA_Tools::execute( $b['name'], $input );
				$events[]  = $this->tool_event( $b['name'], $input, $r );
				$results[] = $this->tool_result( $b['id'], $r );
			}
			if ( $pending ) {
				$state['pending'] = array( 'results' => $results, 'calls' => $pending );
				$this->save( $state );
				return array( 'status' => 'confirm', 'events' => $events, 'pending' => $this->describe_pending( $pending ) );
			}
			$state['messages'][] = array( 'role' => 'user', 'content' => $results );
			$this->save( $state );
			return array( 'status' => 'continue', 'events' => $events );
		}

		// B) Tocca al modello.
		$max = (int) wpaia_settings()['max_steps'];
		if ( $max > 0 && $state['steps'] >= $max ) {
			$state['steps'] = 0;
			$this->save( $state );
			return array( 'status' => 'done', 'events' => array( array( 'type' => 'notice', 'text' => "Raggiunti $max passi (impostazione «Passi massimi»): scrivi «continua» per proseguire." ) ) );
		}

		$resp = $this->call_api( $state['messages'] );
		if ( is_wp_error( $resp ) ) {
			$this->save( $state );
			return $resp;
		}
		$state['steps']++;

		$content             = $resp['content'] ?? array();
		$state['messages'][] = array( 'role' => 'assistant', 'content' => $content );
		foreach ( $content as $b ) {
			if ( 'text' === $b['type'] && '' !== trim( $b['text'] ) ) {
				$events[] = array( 'type' => 'text', 'text' => $b['text'] );
			}
		}
		$this->save( $state );

		$stop = $resp['stop_reason'] ?? '';
		if ( 'tool_use' === $stop ) {
			return array( 'status' => 'continue', 'events' => $events );
		}
		if ( 'max_tokens' === $stop ) {
			$events[] = array( 'type' => 'notice', 'text' => 'Risposta interrotta per il limite di token per risposta (alzalo nelle Impostazioni): scrivi «continua».' );
		}
		return array( 'status' => 'done', 'events' => $events );
	}

	private function call_api( $messages ) {
		$s = wpaia_settings();
		if ( '' === $s['api_key'] ) {
			return new WP_Error( 'wpaia_nokey', 'API key non configurata.' );
		}
		$body = array(
			'model'      => $s['model'],
			'max_tokens' => (int) $s['max_tokens'],
			'system'     => $this->system_prompt(),
			'tools'      => WPAIA_Tools::definitions(),
			'messages'   => $this->prepare_messages( $messages ),
		);
		$res = wp_remote_post( self::API_URL, array(
			'timeout' => 600,
			'headers' => array(
				'x-api-key'         => $s['api_key'],
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'wpaia_http', 'Errore di connessione all\'API: ' . $res->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $res );
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code ) {
			$msg = $data['error']['message'] ?? wp_remote_retrieve_body( $res );
			return new WP_Error( 'wpaia_api', "Errore API ($code): $msg" );
		}
		return $data;
	}

	/** json_decode(..., true) trasforma {} in []: l'API vuole un oggetto per l'input dei tool_use. */
	private function prepare_messages( $messages ) {
		foreach ( $messages as $i => $m ) {
			if ( ! is_array( $m['content'] ) ) {
				continue;
			}
			foreach ( $m['content'] as $j => $b ) {
				if ( 'tool_use' === ( $b['type'] ?? '' ) && empty( $b['input'] ) ) {
					$messages[ $i ]['content'][ $j ]['input'] = new stdClass();
				}
			}
		}
		return $messages;
	}

	private function system_prompt() {
		global $wp_version, $wpdb;
		$user  = wp_get_current_user();
		$extra = trim( (string) wpaia_settings()['extra_prompt'] );
		return sprintf(
			"Sei un agente con pieno accesso amministrativo al sito WordPress \"%s\" (%s). WordPress %s, PHP %s, prefisso tabelle %s, root %s. Oggi è %s.\n" .
			"Lavori per l'amministratore %s, che ti scrive dalla bacheca del sito.\n\n" .
			"Strumenti: hai strumenti dedicati per impostazioni, plugin, temi, Customizer, menu, widget, contenuti e utenti, più run_php (codice PHP arbitrario nel contesto WordPress), db_query (SQL), accesso ai file e fetch_url. " .
			"Preferisci gli strumenti dedicati; usa run_php/db_query/write_file per tutto ciò che non coprono (es. impostazioni di plugin complessi, stili globali dei temi a blocchi, WooCommerce, cron, traduzioni).\n\n" .
			"Come lavori:\n" .
			"- Verifica sempre lo stato reale prima di agire e controlla il risultato dopo.\n" .
			"- Per scegliere un plugin preferisci quelli con molte installazioni attive, aggiornati di recente e compatibili; spiega la scelta in una riga.\n" .
			"- Prima di modificare un'opzione o un file leggine il contenuto attuale e cambia solo ciò che serve.\n" .
			"- Con run_php e write_file sii prudente: un errore fatale può bloccare il sito. Per i file PHP preferisci modifiche piccole e mirate.\n" .
			"- Le azioni che modificano il sito possono richiedere la conferma dell'utente; se ne rifiuta una, non riprovarla e chiedi come procedere.\n" .
			"- Rispondi nella lingua dell'utente, in modo conciso. Alla fine riassumi cosa hai cambiato.%s",
			get_bloginfo( 'name' ),
			home_url(),
			$wp_version,
			PHP_VERSION,
			$wpdb->prefix,
			ABSPATH,
			wp_date( 'j F Y' ),
			$user ? $user->display_name : '',
			$extra ? "\n\nIstruzioni aggiuntive dell'amministratore:\n" . $extra : ''
		);
	}

	/* ------------------------------------------------------------------
	 * Utilità
	 * ---------------------------------------------------------------- */

	private function tool_result( $id, $result ) {
		$json = wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
		if ( false === $json || '' === $json ) {
			$json = print_r( $result, true ); // phpcs:ignore
		}
		if ( strlen( $json ) > self::MAX_RESULT ) {
			$json = mb_strcut( $json, 0, self::MAX_RESULT ) . '…[risultato troncato: chiedi meno dati o usa offset]';
		}
		$block = array( 'type' => 'tool_result', 'tool_use_id' => $id, 'content' => $json );
		if ( is_array( $result ) && ! empty( $result['error'] ) ) {
			$block['is_error'] = true;
		}
		return $block;
	}

	private function tool_event( $name, $input, $result ) {
		$ok = ! ( is_array( $result ) && ! empty( $result['error'] ) );
		return array(
			'type'    => 'tool',
			'name'    => $name,
			'input'   => $input,
			'ok'      => $ok,
			'summary' => $ok ? '' : ( is_string( $result['error'] ) ? $result['error'] : wp_json_encode( $result['error'] ) ),
		);
	}

	private function describe_pending( $calls ) {
		$out = array();
		foreach ( $calls as $c ) {
			$out[] = array( 'name' => $c['name'], 'input' => $c['input'] );
		}
		return $out;
	}
}
