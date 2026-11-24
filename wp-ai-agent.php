<?php
/**
 * Plugin Name:       Agente AI per WordPress
 * Description:       Un agente conversazionale (API Anthropic) con pieno accesso amministrativo: impostazioni, plugin, temi, Customizer, menu, widget, contenuti, utenti, codice PHP, SQL e file. Si aggiorna dalle release di GitHub.
 * Version:           0.2.0
 * Author:            Riccardo
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * License:           GPLv2 or later
 * Text Domain:       wp-ai-agent
 * Update URI:        https://github.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAIA_FILE', __FILE__ );
define( 'WPAIA_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAIA_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAIA_VERSION', get_file_data( __FILE__, array( 'Version' => 'Version' ) )['Version'] );

require_once WPAIA_DIR . 'includes/class-wpaia-tools.php';
require_once WPAIA_DIR . 'includes/class-wpaia-agent.php';
require_once WPAIA_DIR . 'includes/class-wpaia-updater.php';

new WPAIA_Updater( __FILE__ );

/* -------------------------------------------------------------------------
 * Impostazioni
 * ---------------------------------------------------------------------- */

function wpaia_defaults() {
	return array(
		'api_key'        => '',
		'model'          => 'claude-sonnet-5-5',
		'max_tokens'     => 16000,
		'confirm_writes' => 1,
		'max_steps'      => 0,   // 0 = nessun limite
		'history'        => 200, // messaggi conservati nella conversazione
		'extra_prompt'   => '',
		'github_repo'    => 'risik01/wp-agent-it',
		'github_token'   => '',
	);
}

function wpaia_settings() {
	$s = get_option( 'wpaia_settings', array() );
	$s = wp_parse_args( is_array( $s ) ? $s : array(), wpaia_defaults() );
	// Le costanti in wp-config.php hanno la precedenza.
	foreach ( array( 'api_key' => 'WPAIA_API_KEY', 'github_repo' => 'WPAIA_GITHUB_REPO', 'github_token' => 'WPAIA_GITHUB_TOKEN' ) as $k => $const ) {
		if ( defined( $const ) && constant( $const ) ) {
			$s[ $k ] = constant( $const );
		}
	}
	return $s;
}

add_action( 'admin_init', function () {
	register_setting( 'wpaia', 'wpaia_settings', array(
		'type'              => 'array',
		'sanitize_callback' => 'wpaia_sanitize_settings',
		'default'           => wpaia_defaults(),
	) );
} );

function wpaia_sanitize_settings( $in ) {
	$old = get_option( 'wpaia_settings', array() );
	$out = wpaia_defaults();
	foreach ( array( 'api_key', 'github_token' ) as $secret ) {
		$v              = isset( $in[ $secret ] ) ? trim( (string) $in[ $secret ] ) : '';
		$out[ $secret ] = '' !== $v ? sanitize_text_field( $v ) : ( $old[ $secret ] ?? '' );
		if ( ! empty( $in[ $secret . '_clear' ] ) ) {
			$out[ $secret ] = '';
		}
	}
	$out['model']          = sanitize_text_field( $in['model'] ?? $out['model'] );
	$out['max_tokens']     = max( 1024, min( 128000, (int) ( $in['max_tokens'] ?? $out['max_tokens'] ) ) );
	$out['confirm_writes'] = empty( $in['confirm_writes'] ) ? 0 : 1;
	$out['max_steps']      = max( 0, (int) ( $in['max_steps'] ?? 0 ) );
	$out['history']        = max( 20, (int) ( $in['history'] ?? 200 ) );
	$out['extra_prompt']   = sanitize_textarea_field( $in['extra_prompt'] ?? '' );
	$out['github_repo']    = sanitize_text_field( $in['github_repo'] ?? '' );
	delete_site_transient( WPAIA_Updater::CACHE );
	return $out;
}

/* -------------------------------------------------------------------------
 * Controllo aggiornamenti manuale
 * ---------------------------------------------------------------------- */

add_action( 'admin_post_wpaia_check_update', function () {
	if ( ! current_user_can( 'update_plugins' ) ) {
		wp_die( 'Permesso negato.' );
	}
	check_admin_referer( 'wpaia_check_update' );
	delete_site_transient( WPAIA_Updater::CACHE );
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();
	wp_safe_redirect( admin_url( 'plugins.php?plugin_status=upgrade' ) );
	exit;
} );

/* -------------------------------------------------------------------------
 * Pagina di amministrazione
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	$hook = add_menu_page( 'Agente AI', 'Agente AI', 'manage_options', 'wp-ai-agent', 'wpaia_render_page', 'dashicons-format-chat', 3 );
	add_action( 'admin_enqueue_scripts', function ( $h ) use ( $hook ) {
		if ( $h !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wpaia', WPAIA_URL . 'assets/admin.css', array(), WPAIA_VERSION );
		wp_enqueue_script( 'wpaia', WPAIA_URL . 'assets/admin.js', array(), WPAIA_VERSION, true );
		wp_localize_script( 'wpaia', 'WPAIA', array(
			'restUrl' => esc_url_raw( rest_url( 'wp-ai-agent/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		) );
	} );
} );

function wpaia_secret_field( $name, $label, $const, $placeholder, $help ) {
	$s   = wpaia_settings();
	$has = '' !== $s[ $name ];
	echo '<tr><th scope="row"><label for="wpaia_' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
	if ( defined( $const ) && constant( $const ) ) {
		echo '<p>Definito in <code>wp-config.php</code> (<code>' . esc_html( $const ) . '</code>).</p>';
	} else {
		echo '<input type="password" id="wpaia_' . esc_attr( $name ) . '" name="wpaia_settings[' . esc_attr( $name ) . ']" class="regular-text" autocomplete="off" placeholder="' . esc_attr( $has ? '•••••••• (vuoto = non cambiare)' : $placeholder ) . '">';
		if ( $has ) {
			echo ' <label><input type="checkbox" name="wpaia_settings[' . esc_attr( $name ) . '_clear]" value="1"> rimuovi</label>';
		}
		echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
	}
	echo '</td></tr>';
}

function wpaia_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = wpaia_settings();
	$has_key = '' !== $s['api_key'];
	$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ( $has_key ? 'chat' : 'settings' ); // phpcs:ignore
	$base    = admin_url( 'admin.php?page=wp-ai-agent' );
	?>
	<div class="wrap wpaia-wrap">
		<h1>Agente AI <span class="wpaia-version">v<?php echo esc_html( WPAIA_VERSION ); ?></span></h1>
		<nav class="nav-tab-wrapper">
			<a href="<?php echo esc_url( $base . '&tab=chat' ); ?>" class="nav-tab <?php echo 'chat' === $tab ? 'nav-tab-active' : ''; ?>">Chat</a>
			<a href="<?php echo esc_url( $base . '&tab=log' ); ?>" class="nav-tab <?php echo 'log' === $tab ? 'nav-tab-active' : ''; ?>">Registro azioni</a>
			<a href="<?php echo esc_url( $base . '&tab=settings' ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">Impostazioni</a>
		</nav>

		<?php if ( 'settings' === $tab ) : ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpaia' ); ?>
				<h2>Agente</h2>
				<table class="form-table" role="presentation">
					<?php wpaia_secret_field( 'api_key', 'API key Anthropic', 'WPAIA_API_KEY', 'sk-ant-...', 'Consigliato: in wp-config.php con <code>define( \'WPAIA_API_KEY\', \'sk-ant-...\' );</code>' ); ?>
					<tr>
						<th scope="row"><label for="wpaia_model">Modello</label></th>
						<td><input type="text" id="wpaia_model" name="wpaia_settings[model]" value="<?php echo esc_attr( $s['model'] ); ?>" class="regular-text">
							<p class="description"><code>claude-sonnet-5-5</code> (equilibrato), <code>claude-opus-5-5</code> (più capace), <code>claude-haiku-5-5</code> (economico).</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpaia_mt">Token massimi per risposta</label></th>
						<td><input type="number" id="wpaia_mt" name="wpaia_settings[max_tokens]" value="<?php echo esc_attr( $s['max_tokens'] ); ?>" min="1024" max="128000"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpaia_ms">Passi massimi per richiesta</label></th>
						<td><input type="number" id="wpaia_ms" name="wpaia_settings[max_steps]" value="<?php echo esc_attr( $s['max_steps'] ); ?>" min="0">
							<p class="description">0 = nessun limite. Puoi sempre fermarlo con il pulsante «Interrompi».</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpaia_h">Messaggi in memoria</label></th>
						<td><input type="number" id="wpaia_h" name="wpaia_settings[history]" value="<?php echo esc_attr( $s['history'] ); ?>" min="20">
							<p class="description">Quanti messaggi della conversazione conservare (più sono, più costa ogni passo).</p></td>
					</tr>
					<tr>
						<th scope="row">Conferma azioni</th>
						<td><label><input type="checkbox" name="wpaia_settings[confirm_writes]" value="1" <?php checked( $s['confirm_writes'] ); ?>>
							Chiedi conferma prima di ogni azione che modifica il sito</label>
							<p class="description">Se lo disattivi l'agente esegue tutto da solo, compresi codice PHP, SQL e modifiche ai file.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpaia_ep">Istruzioni aggiuntive</label></th>
						<td><textarea id="wpaia_ep" name="wpaia_settings[extra_prompt]" rows="5" class="large-text" placeholder="Es. Il sito è di una casa vacanze a Volterra; scrivi i testi in italiano e inglese; non toccare il plugin di prenotazione."><?php echo esc_textarea( $s['extra_prompt'] ); ?></textarea></td>
					</tr>
				</table>

				<h2>Aggiornamenti da GitHub</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpaia_repo">Repository</label></th>
						<td>
							<?php if ( defined( 'WPAIA_GITHUB_REPO' ) && WPAIA_GITHUB_REPO ) : ?>
								<p><code><?php echo esc_html( WPAIA_GITHUB_REPO ); ?></code> (da wp-config.php)</p>
							<?php else : ?>
								<input type="text" id="wpaia_repo" name="wpaia_settings[github_repo]" value="<?php echo esc_attr( $s['github_repo'] ); ?>" class="regular-text" placeholder="risik01/wp-agent-it">
							<?php endif; ?>
							<p class="description">Formato <code>utente/repository</code>. Il plugin si aggiorna dall'ultima release pubblicata.</p>
						</td>
					</tr>
					<?php wpaia_secret_field( 'github_token', 'Token GitHub', 'WPAIA_GITHUB_TOKEN', 'github_pat_...', 'Solo per repository <strong>privati</strong>: token «fine-grained» con accesso in sola lettura a <em>Contents</em> di quel repository.' ); ?>
					<tr>
						<th scope="row">Stato</th>
						<td>
							<?php
							if ( WPAIA_Updater::repo() ) {
								$rel = WPAIA_Updater::get_release();
								if ( is_wp_error( $rel ) ) {
									echo '<p>❌ ' . esc_html( $rel->get_error_message() ) . '</p>';
								} elseif ( ! empty( $rel['error'] ) ) {
									echo '<p>❌ ' . esc_html( $rel['error'] ) . '</p>';
								} else {
									$newer = version_compare( $rel['version'], WPAIA_VERSION, '>' );
									echo '<p>Installata: <strong>' . esc_html( WPAIA_VERSION ) . '</strong> — ultima release: <a href="' . esc_url( $rel['url'] ) . '" target="_blank" rel="noopener"><strong>' . esc_html( $rel['version'] ) . '</strong></a> ' . ( $newer ? '🔔 aggiornamento disponibile' : '✅ aggiornato' ) . '</p>';
								}
								echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpaia_check_update' ), 'wpaia_check_update' ) ) . '">Controlla ora</a></p>';
								echo '<p class="description">Puoi attivare gli aggiornamenti automatici dalla pagina Plugin («Abilita aggiornamenti automatici»).</p>';
							} else {
								echo '<p>Imposta il repository per attivare gli aggiornamenti.</p>';
							}
							?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

		<?php elseif ( 'log' === $tab ) : ?>
			<?php $log = array_reverse( (array) get_option( 'wpaia_log', array() ) ); ?>
			<table class="widefat striped">
				<thead><tr><th>Data</th><th>Utente</th><th>Strumento</th><th>Parametri</th><th>Esito</th></tr></thead>
				<tbody>
				<?php if ( ! $log ) : ?>
					<tr><td colspan="5">Nessuna azione registrata.</td></tr>
				<?php endif; ?>
				<?php foreach ( $log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['time'] ); ?></td>
						<td><?php echo esc_html( $row['user'] ); ?></td>
						<td><code><?php echo esc_html( $row['tool'] ); ?></code></td>
						<td><details><summary><code><?php echo esc_html( mb_strimwidth( $row['input'], 0, 120, '…' ) ); ?></code></summary><pre class="wpaia-log-pre"><?php echo esc_html( $row['input'] ); ?></pre></details></td>
						<td><?php echo $row['ok'] ? '✅' : '❌ ' . esc_html( $row['error'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">I file modificati o eliminati dall'agente vengono salvati in <code>wp-content/wpaia-backups/</code>.</p>

		<?php else : ?>
			<?php if ( ! $has_key ) : ?>
				<div class="notice notice-warning"><p>Configura prima la API key nella scheda <a href="<?php echo esc_url( $base . '&tab=settings' ); ?>">Impostazioni</a>.</p></div>
			<?php endif; ?>
			<?php if ( empty( $s['confirm_writes'] ) ) : ?>
				<div class="notice notice-info"><p>Conferma azioni disattivata: l'agente esegue le modifiche senza chiedere.</p></div>
			<?php endif; ?>
			<div id="wpaia-app">
				<div id="wpaia-messages" aria-live="polite"></div>
				<div id="wpaia-pending" hidden></div>
				<form id="wpaia-form">
					<textarea id="wpaia-input" rows="3" placeholder="Es. «Crea un menu principale con Home, Appartamento, Prenota e Contatti» oppure «Installa un plugin di backup e programma un backup settimanale»"></textarea>
					<div class="wpaia-actions">
						<button type="button" class="button" id="wpaia-reset">Nuova conversazione</button>
						<button type="button" class="button" id="wpaia-stop" hidden>Interrompi</button>
						<button type="submit" class="button button-primary" id="wpaia-send">Invia</button>
					</div>
				</form>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * REST API
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	$perm  = function () {
		return current_user_can( 'manage_options' );
	};
	$agent = function () {
		wpaia_prepare_request();
		return new WPAIA_Agent( get_current_user_id() );
	};

	register_rest_route( 'wp-ai-agent/v1', '/chat', array(
		'methods'             => 'POST',
		'permission_callback' => $perm,
		'callback'            => function ( WP_REST_Request $r ) use ( $agent ) {
			$msg = trim( (string) $r->get_param( 'message' ) );
			if ( '' === $msg ) {
				return new WP_Error( 'wpaia_empty', 'Messaggio vuoto', array( 'status' => 400 ) );
			}
			return wpaia_response( $agent()->handle_message( $msg ) );
		},
	) );

	register_rest_route( 'wp-ai-agent/v1', '/continue', array(
		'methods'             => 'POST',
		'permission_callback' => $perm,
		'callback'            => function () use ( $agent ) {
			return wpaia_response( $agent()->handle_continue() );
		},
	) );

	register_rest_route( 'wp-ai-agent/v1', '/confirm', array(
		'methods'             => 'POST',
		'permission_callback' => $perm,
		'callback'            => function ( WP_REST_Request $r ) use ( $agent ) {
			return wpaia_response( $agent()->handle_confirmation( (bool) $r->get_param( 'approve' ) ) );
		},
	) );

	register_rest_route( 'wp-ai-agent/v1', '/history', array(
		'methods'             => 'GET',
		'permission_callback' => $perm,
		'callback'            => function () use ( $agent ) {
			return $agent()->history();
		},
	) );

	register_rest_route( 'wp-ai-agent/v1', '/reset', array(
		'methods'             => 'POST',
		'permission_callback' => $perm,
		'callback'            => function () use ( $agent ) {
			$agent()->reset();
			return array( 'ok' => true );
		},
	) );
} );

function wpaia_prepare_request() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore
	}
	@ignore_user_abort( true ); // phpcs:ignore -- un passo avviato va portato a termine anche se la pagina si chiude.
	wp_raise_memory_limit( 'admin' );
}

function wpaia_response( $result ) {
	if ( is_wp_error( $result ) ) {
		return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 500 ) );
	}
	return $result;
}
