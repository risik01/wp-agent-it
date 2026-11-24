<?php
/**
 * Strumenti che l'agente può usare sul sito.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIA_Tools {

	/** Strumenti in sola lettura: non chiedono mai conferma e non vanno nel registro. */
	const READ_ONLY = array(
		'get_site_info', 'get_option', 'list_options', 'list_plugins', 'search_plugins',
		'list_themes', 'search_themes', 'list_posts', 'get_post', 'list_menus', 'list_widgets',
		'list_users', 'get_theme_mods', 'read_file', 'list_dir', 'fetch_url',
	);

	/* ==================================================================
	 * Definizioni
	 * ================================================================ */

	public static function definitions() {
		$none = new stdClass();
		$any  = array( 'description' => 'Qualsiasi valore JSON' );
		return array(
			// Sito e opzioni.
			self::def( 'get_site_info', 'Panoramica del sito: versioni WordPress/PHP, prefisso tabelle, percorsi, tema attivo, plugin attivi, impostazioni principali, aggiornamenti disponibili.', $none ),
			self::def( 'get_option', 'Legge una opzione di wp_options (anche impostazioni dei plugin).', array( 'name' => array( 'type' => 'string' ) ), array( 'name' ) ),
			self::def( 'list_options', 'Elenca i nomi delle opzioni che contengono un testo (esclusi i transient).', array( 'search' => array( 'type' => 'string' ) ), array( 'search' ) ),
			self::def( 'update_option', 'Imposta una opzione. Valore stringa, numero, booleano, array o oggetto.', array( 'name' => array( 'type' => 'string' ), 'value' => $any ), array( 'name', 'value' ) ),
			self::def( 'delete_option', 'Elimina una opzione.', array( 'name' => array( 'type' => 'string' ) ), array( 'name' ) ),

			// Plugin.
			self::def( 'list_plugins', 'Plugin installati con stato, versione e aggiornamenti disponibili.', $none ),
			self::def( 'search_plugins', 'Cerca plugin su wordpress.org.', array( 'query' => array( 'type' => 'string' ) ), array( 'query' ) ),
			self::def( 'install_plugin', 'Installa un plugin da wordpress.org (slug) oppure da un URL di un file .zip (zip_url). Non lo attiva.', array(
				'slug'    => array( 'type' => 'string' ),
				'zip_url' => array( 'type' => 'string', 'description' => 'URL diretto a uno zip, in alternativa allo slug' ),
			) ),
			self::def( 'activate_plugin', 'Attiva un plugin installato.', array( 'plugin' => array( 'type' => 'string', 'description' => 'File come da list_plugins, es. "akismet/akismet.php", o slug della cartella' ) ), array( 'plugin' ) ),
			self::def( 'deactivate_plugin', 'Disattiva un plugin.', array( 'plugin' => array( 'type' => 'string' ) ), array( 'plugin' ) ),
			self::def( 'update_plugin', 'Aggiorna un plugin all\'ultima versione.', array( 'plugin' => array( 'type' => 'string' ) ), array( 'plugin' ) ),
			self::def( 'delete_plugin', 'Disattiva ed elimina un plugin.', array( 'plugin' => array( 'type' => 'string' ) ), array( 'plugin' ) ),

			// Temi e personalizzazione.
			self::def( 'list_themes', 'Temi installati e tema attivo.', $none ),
			self::def( 'search_themes', 'Cerca temi su wordpress.org.', array( 'query' => array( 'type' => 'string' ) ), array( 'query' ) ),
			self::def( 'install_theme', 'Installa un tema da wordpress.org (slug) o da un URL zip (zip_url). Non lo attiva.', array( 'slug' => array( 'type' => 'string' ), 'zip_url' => array( 'type' => 'string' ) ) ),
			self::def( 'activate_theme', 'Attiva un tema installato.', array( 'stylesheet' => array( 'type' => 'string' ) ), array( 'stylesheet' ) ),
			self::def( 'update_theme', 'Aggiorna un tema.', array( 'stylesheet' => array( 'type' => 'string' ) ), array( 'stylesheet' ) ),
			self::def( 'delete_theme', 'Elimina un tema non attivo.', array( 'stylesheet' => array( 'type' => 'string' ) ), array( 'stylesheet' ) ),
			self::def( 'get_theme_mods', 'Impostazioni del Customizer del tema attivo (theme mods) e CSS aggiuntivo.', $none ),
			self::def( 'set_theme_mod', 'Imposta una impostazione del Customizer (theme mod). Con value null la rimuove.', array( 'name' => array( 'type' => 'string' ), 'value' => $any ), array( 'name' ) ),
			self::def( 'set_custom_css', 'Sostituisce il CSS aggiuntivo del tema attivo (Customizer → CSS aggiuntivo).', array( 'css' => array( 'type' => 'string' ) ), array( 'css' ) ),

			// Menu e widget.
			self::def( 'list_menus', 'Menu classici con voci, posizioni del tema e assegnazioni; per i temi a blocchi elenca anche i menu di navigazione (wp_navigation).', $none ),
			self::def( 'create_menu', 'Crea un menu classico.', array( 'name' => array( 'type' => 'string' ) ), array( 'name' ) ),
			self::def( 'delete_menu', 'Elimina un menu classico.', array( 'menu_id' => array( 'type' => 'integer' ) ), array( 'menu_id' ) ),
			self::def( 'add_menu_item', 'Aggiunge (o modifica, se passi item_id) una voce di menu. Per link a contenuti usa object_type (page, post, category, post_tag) + object_id; per link liberi usa url.', array(
				'menu_id'     => array( 'type' => 'integer' ),
				'item_id'     => array( 'type' => 'integer', 'description' => 'Se presente modifica la voce esistente' ),
				'title'       => array( 'type' => 'string' ),
				'url'         => array( 'type' => 'string' ),
				'object_type' => array( 'type' => 'string' ),
				'object_id'   => array( 'type' => 'integer' ),
				'parent_id'   => array( 'type' => 'integer', 'description' => 'ID della voce padre (sottomenu)' ),
				'position'    => array( 'type' => 'integer' ),
			), array( 'menu_id' ) ),
			self::def( 'delete_menu_item', 'Elimina una voce di menu.', array( 'item_id' => array( 'type' => 'integer' ) ), array( 'item_id' ) ),
			self::def( 'assign_menu_location', 'Assegna un menu a una posizione del tema (menu_id 0 per svuotarla).', array( 'location' => array( 'type' => 'string' ), 'menu_id' => array( 'type' => 'integer' ) ), array( 'location', 'menu_id' ) ),
			self::def( 'list_widgets', 'Aree widget (sidebar) con i widget presenti e le loro impostazioni.', $none ),
			self::def( 'add_widget', 'Aggiunge un widget a una sidebar. Per WordPress moderno usa id_base "block" con settings {"content": "<!-- wp:paragraph --><p>…</p><!-- /wp:paragraph -->"}.', array(
				'sidebar_id' => array( 'type' => 'string' ),
				'id_base'    => array( 'type' => 'string', 'description' => 'Tipo di widget, default "block"' ),
				'settings'   => array( 'type' => 'object' ),
			), array( 'sidebar_id', 'settings' ) ),
			self::def( 'update_widget', 'Modifica le impostazioni di un widget esistente.', array( 'widget_id' => array( 'type' => 'string', 'description' => 'es. "block-3"' ), 'settings' => array( 'type' => 'object' ) ), array( 'widget_id', 'settings' ) ),
			self::def( 'remove_widget', 'Toglie un widget dalle sidebar (lo sposta tra gli inattivi).', array( 'widget_id' => array( 'type' => 'string' ) ), array( 'widget_id' ) ),

			// Contenuti.
			self::def( 'list_posts', 'Elenca articoli, pagine o altri post type.', array(
				'post_type' => array( 'type' => 'string', 'description' => 'Default "post"; "page", "any", o un custom post type' ),
				'status'    => array( 'type' => 'string', 'description' => 'publish, draft, private, trash o any (default)' ),
				'search'    => array( 'type' => 'string' ),
				'number'    => array( 'type' => 'integer', 'description' => 'Default 20, max 500' ),
				'offset'    => array( 'type' => 'integer' ),
			) ),
			self::def( 'get_post', 'Legge un contenuto completo, inclusi i metadati.', array( 'id' => array( 'type' => 'integer' ) ), array( 'id' ) ),
			self::def( 'create_post', 'Crea un contenuto. Stato predefinito: bozza. Contenuto HTML o markup a blocchi.', array(
				'title'     => array( 'type' => 'string' ),
				'content'   => array( 'type' => 'string' ),
				'post_type' => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string' ),
				'excerpt'   => array( 'type' => 'string' ),
				'parent_id' => array( 'type' => 'integer' ),
				'meta'      => array( 'type' => 'object', 'description' => 'Metadati chiave → valore' ),
			), array( 'title' ) ),
			self::def( 'update_post', 'Modifica un contenuto: passa solo i campi da cambiare. meta con valore null elimina la chiave.', array(
				'id'        => array( 'type' => 'integer' ),
				'title'     => array( 'type' => 'string' ),
				'content'   => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string' ),
				'excerpt'   => array( 'type' => 'string' ),
				'slug'      => array( 'type' => 'string' ),
				'parent_id' => array( 'type' => 'integer' ),
				'meta'      => array( 'type' => 'object' ),
			), array( 'id' ) ),
			self::def( 'delete_post', 'Sposta nel cestino un contenuto (o lo elimina definitivamente con force true).', array( 'id' => array( 'type' => 'integer' ), 'force' => array( 'type' => 'boolean' ) ), array( 'id' ) ),

			// Utenti.
			self::def( 'list_users', 'Elenca gli utenti.', array( 'role' => array( 'type' => 'string' ), 'search' => array( 'type' => 'string' ), 'number' => array( 'type' => 'integer' ) ) ),
			self::def( 'create_user', 'Crea un utente. Se manca la password ne genera una e la restituisce.', array(
				'username'          => array( 'type' => 'string' ),
				'email'             => array( 'type' => 'string' ),
				'role'              => array( 'type' => 'string', 'description' => 'Default: ruolo predefinito del sito' ),
				'password'          => array( 'type' => 'string' ),
				'first_name'        => array( 'type' => 'string' ),
				'last_name'         => array( 'type' => 'string' ),
				'display_name'      => array( 'type' => 'string' ),
				'send_notification' => array( 'type' => 'boolean', 'description' => 'Invia all\'utente l\'email con il link per impostare la password' ),
			), array( 'username', 'email' ) ),
			self::def( 'update_user', 'Modifica un utente: email, ruolo, nomi, password.', array(
				'id'           => array( 'type' => 'integer' ),
				'email'        => array( 'type' => 'string' ),
				'role'         => array( 'type' => 'string' ),
				'password'     => array( 'type' => 'string' ),
				'first_name'   => array( 'type' => 'string' ),
				'last_name'    => array( 'type' => 'string' ),
				'display_name' => array( 'type' => 'string' ),
			), array( 'id' ) ),
			self::def( 'delete_user', 'Elimina un utente, assegnando facoltativamente i suoi contenuti a un altro utente.', array( 'id' => array( 'type' => 'integer' ), 'reassign_to' => array( 'type' => 'integer' ) ), array( 'id' ) ),

			// Accesso diretto.
			self::def( 'run_php', 'Esegue codice PHP nel contesto di WordPress (tutte le funzioni WP e $wpdb disponibili, includes di wp-admin già caricati). Non includere "<?php". Usa echo/print_r per l\'output o "return" per restituire un valore. Utile per qualsiasi operazione non coperta dagli altri strumenti.', array( 'code' => array( 'type' => 'string' ) ), array( 'code' ) ),
			self::def( 'db_query', 'Esegue una query SQL sul database di WordPress. Usa {prefix} al posto del prefisso tabelle. SELECT/SHOW/DESCRIBE restituiscono righe (max 500); le altre restituiscono le righe interessate.', array( 'sql' => array( 'type' => 'string' ) ), array( 'sql' ) ),
			self::def( 'list_dir', 'Elenca una cartella. Percorsi relativi alla root di WordPress o assoluti.', array( 'path' => array( 'type' => 'string' ) ) ),
			self::def( 'read_file', 'Legge un file di testo (es. wp-config.php, .htaccess, file del tema).', array( 'path' => array( 'type' => 'string' ), 'offset' => array( 'type' => 'integer', 'description' => 'Carattere da cui partire per file lunghi' ) ), array( 'path' ) ),
			self::def( 'write_file', 'Crea o sovrascrive un file. Il file precedente viene salvato in wp-content/wpaia-backups. I file .php vengono verificati sintatticamente prima della scrittura.', array(
				'path'    => array( 'type' => 'string' ),
				'content' => array( 'type' => 'string' ),
				'append'  => array( 'type' => 'boolean' ),
			), array( 'path', 'content' ) ),
			self::def( 'delete_file', 'Elimina un file (con backup in wp-content/wpaia-backups).', array( 'path' => array( 'type' => 'string' ) ), array( 'path' ) ),
			self::def( 'fetch_url', 'Scarica una pagina web o un\'API (GET) e ne restituisce il contenuto testuale, es. documentazione di un plugin.', array( 'url' => array( 'type' => 'string' ) ), array( 'url' ) ),
			self::def( 'update_core', 'Aggiorna WordPress all\'ultima versione disponibile.', $none ),
		);
	}

	private static function def( $name, $description, $props, $required = array() ) {
		$schema = array( 'type' => 'object', 'properties' => $props );
		if ( $required ) {
			$schema['required'] = $required;
		}
		return array( 'name' => $name, 'description' => $description, 'input_schema' => $schema );
	}

	/* ==================================================================
	 * Esecuzione
	 * ================================================================ */

	public static function is_read_only( $name, $input ) {
		if ( in_array( $name, self::READ_ONLY, true ) ) {
			return true;
		}
		return 'db_query' === $name && self::is_read_sql( $input['sql'] ?? '' );
	}

	private static function is_read_sql( $sql ) {
		return (bool) preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', (string) $sql ) && false === strpos( rtrim( trim( $sql ), ';' ), ';' );
	}

	public static function needs_confirmation( $name, $input ) {
		if ( self::is_read_only( $name, $input ) ) {
			return false;
		}
		$s = wpaia_settings();
		if ( empty( $s['confirm_writes'] ) ) {
			return false;
		}
		if ( 'create_post' === $name && ( empty( $input['status'] ) || 'draft' === $input['status'] ) ) {
			return false;
		}
		return true;
	}

	public static function execute( $name, $input ) {
		$input  = is_array( $input ) ? $input : array();
		$method = 'tool_' . $name;
		if ( ! method_exists( __CLASS__, $method ) ) {
			return array( 'error' => "Strumento sconosciuto: $name" );
		}
		self::load_admin_includes();
		try {
			$result = call_user_func( array( __CLASS__, $method ), $input );
		} catch ( Throwable $e ) {
			$result = array( 'error' => get_class( $e ) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
		}
		if ( is_wp_error( $result ) ) {
			$result = array( 'error' => $result->get_error_message() );
		}
		if ( ! self::is_read_only( $name, $input ) ) {
			self::log( $name, $input, $result );
		}
		return $result;
	}

	private static function log( $name, $input, $result ) {
		$log   = (array) get_option( 'wpaia_log', array() );
		$user  = wp_get_current_user();
		$log[] = array(
			'time'  => current_time( 'mysql' ),
			'user'  => $user ? $user->user_login : '',
			'tool'  => $name,
			'input' => wp_json_encode( $input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'ok'    => empty( $result['error'] ),
			'error' => is_string( $result['error'] ?? null ) ? $result['error'] : '',
		);
		update_option( 'wpaia_log', array_slice( $log, -500 ), false );
	}

	public static function load_admin_includes() {
		foreach ( array( 'plugin', 'plugin-install', 'theme', 'theme-install', 'file', 'misc', 'update', 'user', 'post', 'nav-menu', 'widgets', 'class-wp-upgrader' ) as $f ) {
			if ( file_exists( ABSPATH . "wp-admin/includes/$f.php" ) ) {
				require_once ABSPATH . "wp-admin/includes/$f.php";
			}
		}
	}

	private static function check_file_mods() {
		if ( ! wp_is_file_mod_allowed( 'wpaia' ) ) {
			return array( 'error' => 'Le modifiche ai file sono disabilitate (DISALLOW_FILE_MODS in wp-config.php). Puoi chiedermi di toglierlo con write_file.' );
		}
		return null;
	}

	private static function mask_sensitive( $name, $value ) {
		if ( 'wpaia_settings' === $name ) {
			return '[protetto]';
		}
		if ( preg_match( '/(pass|secret|token|api_?key|license_key|private_key|salt)/i', $name ) ) {
			return '[valore sensibile nascosto]';
		}
		return $value;
	}

	private static function self_file() {
		return plugin_basename( WPAIA_DIR . 'wp-ai-agent.php' );
	}

	/* ==================================================================
	 * Sito e opzioni
	 * ================================================================ */

	private static function tool_get_site_info() {
		global $wp_version, $wpdb;
		$theme  = wp_get_theme();
		$active = array();
		foreach ( (array) get_option( 'active_plugins', array() ) as $f ) {
			$data     = get_plugin_data( WP_PLUGIN_DIR . '/' . $f, false, false );
			$active[] = $f . ' (' . ( $data['Name'] ?? '' ) . ' ' . ( $data['Version'] ?? '' ) . ')';
		}
		$keys = array(
			'blogname', 'blogdescription', 'siteurl', 'home', 'admin_email', 'WPLANG', 'timezone_string', 'gmt_offset',
			'date_format', 'time_format', 'start_of_week', 'permalink_structure', 'show_on_front', 'page_on_front',
			'page_for_posts', 'posts_per_page', 'blog_public', 'users_can_register', 'default_role',
			'default_comment_status', 'comment_moderation', 'comment_registration',
		);
		$opts = array();
		foreach ( $keys as $k ) {
			$opts[ $k ] = get_option( $k );
		}
		$core = get_site_transient( 'update_core' );
		$pl   = get_site_transient( 'update_plugins' );
		return array(
			'wordpress_version' => $wp_version,
			'core_update'       => ( isset( $core->updates[0] ) && 'upgrade' === $core->updates[0]->response ) ? $core->updates[0]->current : null,
			'plugin_updates'    => isset( $pl->response ) ? array_keys( (array) $pl->response ) : array(),
			'php_version'       => PHP_VERSION,
			'mysql_version'     => $wpdb->db_version(),
			'table_prefix'      => $wpdb->prefix,
			'abspath'           => ABSPATH,
			'wp_content_dir'    => WP_CONTENT_DIR,
			'multisite'         => is_multisite(),
			'file_mods_allowed' => wp_is_file_mod_allowed( 'wpaia' ),
			'fs_method'         => get_filesystem_method(),
			'active_theme'      => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ' [' . $theme->get_stylesheet() . ']' . ( $theme->is_block_theme() ? ' (tema a blocchi)' : '' ),
			'active_plugins'    => $active,
			'settings'          => $opts,
		);
	}

	private static function tool_get_option( $in ) {
		$name = (string) ( $in['name'] ?? '' );
		$val  = get_option( $name, null );
		if ( null === $val ) {
			return array( 'name' => $name, 'exists' => false );
		}
		return array( 'name' => $name, 'exists' => true, 'value' => self::mask_sensitive( $name, $val ) );
	}

	private static function tool_list_options( $in ) {
		global $wpdb;
		$search = (string) ( $in['search'] ?? '' );
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$rows   = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s AND option_name NOT LIKE %s ORDER BY option_name LIMIT 300",
			$like, '\_transient%', '\_site\_transient%'
		) );
		return array( 'options' => $rows );
	}

	private static function tool_update_option( $in ) {
		$name = (string) ( $in['name'] ?? '' );
		if ( '' === $name ) {
			return array( 'error' => 'Nome opzione mancante.' );
		}
		$old = get_option( $name, null );
		update_option( $name, $in['value'] ?? null );
		if ( 'permalink_structure' === $name ) {
			flush_rewrite_rules();
		}
		return array( 'ok' => true, 'name' => $name, 'previous' => self::mask_sensitive( $name, $old ), 'current' => self::mask_sensitive( $name, get_option( $name ) ) );
	}

	private static function tool_delete_option( $in ) {
		$name = (string) ( $in['name'] ?? '' );
		return array( 'ok' => delete_option( $name ), 'name' => $name );
	}

	/* ==================================================================
	 * Plugin
	 * ================================================================ */

	private static function tool_list_plugins() {
		$updates = get_site_transient( 'update_plugins' );
		$out     = array();
		foreach ( get_plugins() as $file => $p ) {
			$out[] = array(
				'plugin'           => $file,
				'name'             => $p['Name'],
				'version'          => $p['Version'],
				'active'           => is_plugin_active( $file ),
				'update_available' => isset( $updates->response[ $file ] ) ? $updates->response[ $file ]->new_version : null,
			);
		}
		return array( 'plugins' => $out );
	}

	private static function tool_search_plugins( $in ) {
		$res = plugins_api( 'query_plugins', array(
			'search'   => (string) ( $in['query'] ?? '' ),
			'per_page' => 12,
			'fields'   => array( 'short_description' => true, 'description' => false, 'sections' => false, 'icons' => false, 'banners' => false, 'reviews' => false, 'versions' => false ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$out = array();
		foreach ( $res->plugins as $p ) {
			$p     = (array) $p;
			$out[] = array(
				'slug'            => $p['slug'],
				'name'            => wp_strip_all_tags( $p['name'] ),
				'version'         => $p['version'],
				'rating'          => $p['rating'],
				'num_ratings'     => $p['num_ratings'],
				'active_installs' => $p['active_installs'],
				'last_updated'    => $p['last_updated'],
				'tested_up_to'    => $p['tested'],
				'requires_php'    => $p['requires_php'],
				'description'     => wp_strip_all_tags( $p['short_description'] ),
			);
		}
		return array( 'results' => $out );
	}

	private static function tool_install_plugin( $in ) {
		if ( $e = self::check_file_mods() ) {
			return $e;
		}
		$label = '';
		if ( ! empty( $in['zip_url'] ) ) {
			$package = esc_url_raw( $in['zip_url'] );
			$label   = $package;
		} else {
			$slug = sanitize_key( $in['slug'] ?? '' );
			$api  = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
			if ( is_wp_error( $api ) ) {
				return array( 'error' => "Plugin \"$slug\" non trovato su wordpress.org: " . $api->get_error_message() );
			}
			$package = $api->download_link;
			$label   = $api->name . ' ' . $api->version;
		}
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $package, array( 'overwrite_package' => true ) );
		if ( $err = self::upgrader_error( $result, $skin ) ) {
			return $err;
		}
		return array( 'ok' => true, 'installed' => $label, 'plugin' => $upgrader->plugin_info(), 'note' => 'Installato ma non attivato.' );
	}

	private static function tool_activate_plugin( $in ) {
		$file = self::resolve_plugin_file( $in['plugin'] ?? '' );
		if ( ! $file ) {
			return array( 'error' => 'Plugin non trovato. Usa list_plugins per il nome file corretto.' );
		}
		$r = activate_plugin( $file );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( 'ok' => true, 'plugin' => $file, 'active' => is_plugin_active( $file ) );
	}

	private static function tool_deactivate_plugin( $in ) {
		$file = self::resolve_plugin_file( $in['plugin'] ?? '' );
		if ( ! $file ) {
			return array( 'error' => 'Plugin non trovato.' );
		}
		if ( $file === self::self_file() ) {
			return array( 'error' => 'Disattivarmi interromperebbe questa conversazione: fallo dalla pagina Plugin.' );
		}
		deactivate_plugins( $file );
		return array( 'ok' => true, 'plugin' => $file, 'active' => is_plugin_active( $file ) );
	}

	private static function tool_update_plugin( $in ) {
		if ( $e = self::check_file_mods() ) {
			return $e;
		}
		$file = self::resolve_plugin_file( $in['plugin'] ?? '' );
		if ( ! $file ) {
			return array( 'error' => 'Plugin non trovato.' );
		}
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		$was_active = is_plugin_active( $file );
		$skin       = new WP_Ajax_Upgrader_Skin();
		$upgrader   = new Plugin_Upgrader( $skin );
		$result     = $upgrader->upgrade( $file );
		if ( false === $result && ! $skin->get_errors()->has_errors() ) {
			return array( 'ok' => true, 'note' => 'Nessun aggiornamento disponibile.' );
		}
		if ( $err = self::upgrader_error( $result, $skin ) ) {
			return $err;
		}
		if ( $was_active && ! is_plugin_active( $file ) ) {
			activate_plugin( $file );
		}
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
		return array( 'ok' => true, 'plugin' => $file, 'version' => $data['Version'], 'active' => is_plugin_active( $file ) );
	}

	private static function tool_delete_plugin( $in ) {
		if ( $e = self::check_file_mods() ) {
			return $e;
		}
		$file = self::resolve_plugin_file( $in['plugin'] ?? '' );
		if ( ! $file ) {
			return array( 'error' => 'Plugin non trovato.' );
		}
		if ( $file === self::self_file() ) {
			return array( 'error' => 'Non posso eliminare me stesso da qui: usa la pagina Plugin.' );
		}
		deactivate_plugins( $file, true );
		$r = delete_plugins( array( $file ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( 'ok' => (bool) $r, 'deleted' => $file );
	}

	private static function resolve_plugin_file( $p ) {
		$p       = trim( (string) $p );
		$plugins = get_plugins();
		if ( isset( $plugins[ $p ] ) ) {
			return $p;
		}
		foreach ( $plugins as $file => $data ) {
			if ( strtok( $file, '/' ) === $p || 0 === strcasecmp( $data['Name'], $p ) ) {
				return $file;
			}
		}
		return null;
	}

	private static function upgrader_error( $result, $skin ) {
		if ( is_wp_error( $result ) ) {
			return array( 'error' => $result->get_error_message() );
		}
		if ( is_wp_error( $skin->result ) ) {
			return array( 'error' => $skin->result->get_error_message() );
		}
		if ( $skin->get_errors()->has_errors() ) {
			return array( 'error' => implode( ' ', (array) $skin->get_error_messages() ) );
		}
		if ( is_null( $result ) ) {
			return array( 'error' => 'WordPress non riesce a scrivere sul filesystem: servono permessi di scrittura su wp-content oppure define( \'FS_METHOD\', \'direct\' ) in wp-config.php.' );
		}
		if ( false === $result ) {
			return array( 'error' => 'Operazione non riuscita.' );
		}
		return null;
	}

	/* ==================================================================
	 * Temi e Customizer
	 * ================================================================ */

	private static function tool_list_themes() {
		$active  = get_stylesheet();
		$updates = get_site_transient( 'update_themes' );
		$out     = array();
		foreach ( wp_get_themes() as $slug => $t ) {
			$out[] = array(
				'stylesheet'       => $slug,
				'name'             => $t->get( 'Name' ),
				'version'          => $t->get( 'Version' ),
				'parent'           => $t->parent() ? $t->get_template() : null,
				'active'           => $slug === $active,
				'block_theme'      => $t->is_block_theme(),
				'update_available' => $updates->response[ $slug ]['new_version'] ?? null,
			);
		}
		return array( 'themes' => $out );
	}

	private static function tool_search_themes( $in ) {
		$res = themes_api( 'query_themes', array( 'search' => (string) ( $in['query'] ?? '' ), 'per_page' => 12, 'fields' => array( 'description' => true, 'rating' => true, 'active_installs' => true, 'last_updated' => true ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$out = array();
		foreach ( $res->themes as $t ) {
			$t     = (array) $t;
			$out[] = array(
				'slug'            => $t['slug'],
				'name'            => $t['name'],
				'version'         => $t['version'],
				'rating'          => $t['rating'] ?? null,
				'active_installs' => $t['active_installs'] ?? null,
				'last_updated'    => $t['last_updated'] ?? null,
				'description'     => mb_strimwidth( wp_strip_all_tags( $t['description'] ?? '' ), 0, 300, '…' ),
			);
		}
		return array( 'results' => $out );
	}

	private static function tool_install_theme( $in ) {
		if ( $e = self::check_file_mods() ) {
			return $e;
		}
		if ( ! empty( $in['zip_url'] ) ) {
			$package = esc_url_raw( $in['zip_url'] );
			$label   = $package;
		} else {
			$slug = sanitize_key( $in['slug'] ?? '' );
			$api  = themes_api( 'theme_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
			if ( is_wp_error( $api ) ) {
				return array( 'error' => "Tema \"$slug\" non trovato: " . $api->get_error_message() );
			}
			$package = $api->download_link;
			$label   = $api->name . ' ' . $api->version;
		}
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result   = $upgrader->install( $package, array( 'overwrite_package' => true ) );
		if ( $err = self::upgrader_error( $result, $skin ) ) {
			return $err;
		}
		$info = $upgrader->theme_info();
		return array( 'ok' => true, 'installed' => $label, 'stylesheet' => $info ? $info->get_stylesheet() : null, 'note' => 'Installato ma non attivato.' );
	}

	private static function tool_activate_theme( $in ) {
		$ss    = (string) ( $in['stylesheet'] ?? '' );
		$theme = wp_get_theme( $ss );
		if ( ! $theme->exists() ) {
			return array( 'error' => "Tema \"$ss\" non installato." );
		}
		$prev = get_stylesheet();
		switch_theme( $ss );
		return array( 'ok' => true, 'previous' => $prev, 'active' => get_stylesheet() );
	}

	private static function tool_update_theme( $in ) {
		if ( $e = self::check_file_mods() ) {
			return $e;
		}
		$ss = (string) ( $in['stylesheet'] ?? '' );
		delete_site_transient( 'update_themes' );
		wp_update_themes();
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result   = $upgrader->upgrade( $ss );
		if ( false === $result && ! $skin->get_errors()->has_errors() ) {
			return array( 'ok' => true, 'note' => 'Nessun aggiornamento disponibile.' );
		}
		if ( $err = self::upgrader_error( $result, $skin ) ) {
			return $err;
		}
		return array( 'ok' => true, 'stylesheet' => $ss, 'version' => wp_get_theme( $ss )->get( 'Version' ) );
	}

	private static function tool_delete_theme( $in ) {
		if ( $e = self::check_file_mods() ) {
			return $e;
		}
		$ss = (string) ( $in['stylesheet'] ?? '' );
		if ( $ss === get_stylesheet() || $ss === get_template() ) {
			return array( 'error' => 'Il tema è attivo (o è il genitore di quello attivo): attivane prima un altro.' );
		}
		$r = delete_theme( $ss );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( 'ok' => (bool) $r, 'deleted' => $ss );
	}

	private static function tool_get_theme_mods() {
		return array( 'theme' => get_stylesheet(), 'theme_mods' => get_theme_mods(), 'custom_css' => wp_get_custom_css() );
	}

	private static function tool_set_theme_mod( $in ) {
		$name = (string) ( $in['name'] ?? '' );
		$old  = get_theme_mod( $name );
		if ( ! array_key_exists( 'value', $in ) || null === $in['value'] ) {
			remove_theme_mod( $name );
		} else {
			set_theme_mod( $name, $in['value'] );
		}
		return array( 'ok' => true, 'name' => $name, 'previous' => $old, 'current' => get_theme_mod( $name ) );
	}

	private static function tool_set_custom_css( $in ) {
		$old = wp_get_custom_css();
		$r   = wp_update_custom_css_post( (string) ( $in['css'] ?? '' ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( 'ok' => true, 'previous_length' => strlen( $old ), 'note' => 'Il CSS precedente resta nelle revisioni del Customizer.' );
	}

	/* ==================================================================
	 * Menu e widget
	 * ================================================================ */

	private static function tool_list_menus() {
		$menus = array();
		foreach ( wp_get_nav_menus() as $m ) {
			$items = array();
			foreach ( (array) wp_get_nav_menu_items( $m->term_id, array( 'post_status' => 'any' ) ) as $it ) {
				$items[] = array(
					'item_id'     => $it->ID,
					'title'       => $it->title,
					'url'         => $it->url,
					'parent_id'   => (int) $it->menu_item_parent,
					'object_type' => $it->object,
					'object_id'   => (int) $it->object_id,
					'position'    => (int) $it->menu_order,
				);
			}
			$menus[] = array( 'menu_id' => $m->term_id, 'name' => $m->name, 'items' => $items );
		}
		$nav = array();
		foreach ( get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'any', 'numberposts' => 50 ) ) as $p ) {
			$nav[] = array( 'id' => $p->ID, 'title' => $p->post_title, 'note' => 'Modificabile con update_post (content = markup a blocchi)' );
		}
		return array(
			'menus'              => $menus,
			'theme_locations'    => get_registered_nav_menus(),
			'assigned_locations' => get_nav_menu_locations(),
			'block_navigation'   => $nav,
		);
	}

	private static function tool_create_menu( $in ) {
		$id = wp_create_nav_menu( (string) ( $in['name'] ?? '' ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array( 'ok' => true, 'menu_id' => $id );
	}

	private static function tool_delete_menu( $in ) {
		$r = wp_delete_nav_menu( (int) ( $in['menu_id'] ?? 0 ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( 'ok' => (bool) $r );
	}

	private static function tool_add_menu_item( $in ) {
		$menu_id = (int) ( $in['menu_id'] ?? 0 );
		$item_id = (int) ( $in['item_id'] ?? 0 );
		$args    = array( 'menu-item-status' => 'publish' );
		if ( $item_id ) {
			// Parte dai valori esistenti per non perderli.
			$it = wp_setup_nav_menu_item( get_post( $item_id ) );
			if ( ! $it || empty( $it->ID ) ) {
				return array( 'error' => 'Voce di menu non trovata.' );
			}
			$args += array(
				'menu-item-title'     => $it->title,
				'menu-item-url'       => $it->url,
				'menu-item-type'      => $it->type,
				'menu-item-object'    => $it->object,
				'menu-item-object-id' => $it->object_id,
				'menu-item-parent-id' => $it->menu_item_parent,
				'menu-item-position'  => $it->menu_order,
			);
		}
		if ( isset( $in['title'] ) ) {
			$args['menu-item-title'] = (string) $in['title'];
		}
		if ( ! empty( $in['object_type'] ) && 'custom' !== $in['object_type'] ) {
			$type                       = taxonomy_exists( $in['object_type'] ) ? 'taxonomy' : 'post_type';
			$args['menu-item-type']      = $type;
			$args['menu-item-object']    = sanitize_key( $in['object_type'] );
			$args['menu-item-object-id'] = (int) ( $in['object_id'] ?? 0 );
		} elseif ( isset( $in['url'] ) ) {
			$args['menu-item-type'] = 'custom';
			$args['menu-item-url']  = esc_url_raw( $in['url'] );
		}
		if ( isset( $in['parent_id'] ) ) {
			$args['menu-item-parent-id'] = (int) $in['parent_id'];
		}
		if ( isset( $in['position'] ) ) {
			$args['menu-item-position'] = (int) $in['position'];
		}
		$r = wp_update_nav_menu_item( $menu_id, $item_id, $args );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( 'ok' => true, 'item_id' => $r );
	}

	private static function tool_delete_menu_item( $in ) {
		$id = (int) ( $in['item_id'] ?? 0 );
		if ( 'nav_menu_item' !== get_post_type( $id ) ) {
			return array( 'error' => 'Voce di menu non trovata.' );
		}
		return array( 'ok' => (bool) wp_delete_post( $id, true ) );
	}

	private static function tool_assign_menu_location( $in ) {
		$locs                        = get_theme_mod( 'nav_menu_locations', array() );
		$locs[ (string) $in['location'] ] = (int) $in['menu_id'];
		set_theme_mod( 'nav_menu_locations', $locs );
		return array( 'ok' => true, 'assigned_locations' => get_nav_menu_locations() );
	}

	private static function widget_instance( $widget_id ) {
		if ( ! preg_match( '/^(.+)-(\d+)$/', $widget_id, $m ) ) {
			return array( null, null, null );
		}
		$opt = get_option( 'widget_' . $m[1], array() );
		return array( $m[1], (int) $m[2], is_array( $opt ) ? $opt : array() );
	}

	private static function tool_list_widgets() {
		global $wp_registered_sidebars;
		$out = array();
		foreach ( wp_get_sidebars_widgets() as $sidebar => $ids ) {
			if ( 'array_version' === $sidebar ) {
				continue;
			}
			$widgets = array();
			foreach ( (array) $ids as $wid ) {
				list( $base, $n, $opt ) = self::widget_instance( $wid );
				$widgets[]              = array( 'widget_id' => $wid, 'id_base' => $base, 'settings' => $opt[ $n ] ?? null );
			}
			$out[] = array(
				'sidebar_id' => $sidebar,
				'name'       => $wp_registered_sidebars[ $sidebar ]['name'] ?? ( 'wp_inactive_widgets' === $sidebar ? 'Widget inattivi' : $sidebar ),
				'widgets'    => $widgets,
			);
		}
		return array( 'sidebars' => $out, 'registered_sidebars' => array_keys( (array) $wp_registered_sidebars ) );
	}

	private static function tool_add_widget( $in ) {
		$sidebar = (string) ( $in['sidebar_id'] ?? '' );
		$base    = sanitize_key( $in['id_base'] ?? 'block' );
		$opt     = get_option( 'widget_' . $base, array() );
		$opt     = is_array( $opt ) ? $opt : array();
		$nums    = array_filter( array_keys( $opt ), 'is_int' );
		$n       = $nums ? max( $nums ) + 1 : 2;
		$opt[ $n ]            = (array) ( $in['settings'] ?? array() );
		$opt['_multiwidget'] = 1;
		update_option( 'widget_' . $base, $opt );
		$sw               = wp_get_sidebars_widgets();
		$sw[ $sidebar ]   = isset( $sw[ $sidebar ] ) ? (array) $sw[ $sidebar ] : array();
		$sw[ $sidebar ][] = "$base-$n";
		wp_set_sidebars_widgets( $sw );
		return array( 'ok' => true, 'widget_id' => "$base-$n", 'sidebar_id' => $sidebar );
	}

	private static function tool_update_widget( $in ) {
		list( $base, $n, $opt ) = self::widget_instance( (string) ( $in['widget_id'] ?? '' ) );
		if ( ! $base || ! isset( $opt[ $n ] ) ) {
			return array( 'error' => 'Widget non trovato.' );
		}
		$opt[ $n ] = array_merge( (array) $opt[ $n ], (array) ( $in['settings'] ?? array() ) );
		update_option( 'widget_' . $base, $opt );
		return array( 'ok' => true, 'settings' => $opt[ $n ] );
	}

	private static function tool_remove_widget( $in ) {
		$wid   = (string) ( $in['widget_id'] ?? '' );
		$sw    = wp_get_sidebars_widgets();
		$found = false;
		foreach ( $sw as $k => $ids ) {
			if ( 'array_version' === $k || 'wp_inactive_widgets' === $k || ! is_array( $ids ) ) {
				continue;
			}
			$new = array_values( array_diff( $ids, array( $wid ) ) );
			if ( count( $new ) !== count( $ids ) ) {
				$found    = true;
				$sw[ $k ] = $new;
			}
		}
		if ( ! $found ) {
			return array( 'error' => 'Widget non presente in nessuna sidebar.' );
		}
		$sw['wp_inactive_widgets']   = isset( $sw['wp_inactive_widgets'] ) ? (array) $sw['wp_inactive_widgets'] : array();
		$sw['wp_inactive_widgets'][] = $wid;
		wp_set_sidebars_widgets( $sw );
		return array( 'ok' => true, 'note' => 'Spostato tra i widget inattivi.' );
	}

	/* ==================================================================
	 * Contenuti
	 * ================================================================ */

	private static function clean_content( $html ) {
		return current_user_can( 'unfiltered_html' ) ? (string) $html : wp_kses_post( (string) $html );
	}

	private static function tool_list_posts( $in ) {
		$q   = new WP_Query( array(
			'post_type'      => sanitize_key( $in['post_type'] ?? 'post' ),
			'post_status'    => sanitize_key( $in['status'] ?? 'any' ),
			's'              => (string) ( $in['search'] ?? '' ),
			'posts_per_page' => max( 1, min( 500, (int) ( $in['number'] ?? 20 ) ) ),
			'offset'         => max( 0, (int) ( $in['offset'] ?? 0 ) ),
		) );
		$out = array();
		foreach ( $q->posts as $p ) {
			$out[] = array( 'id' => $p->ID, 'type' => $p->post_type, 'title' => $p->post_title, 'status' => $p->post_status, 'date' => $p->post_date, 'parent' => $p->post_parent, 'link' => get_permalink( $p ) );
		}
		return array( 'total' => (int) $q->found_posts, 'items' => $out );
	}

	private static function tool_get_post( $in ) {
		$p = get_post( (int) ( $in['id'] ?? 0 ) );
		if ( ! $p ) {
			return array( 'error' => 'Contenuto non trovato.' );
		}
		$meta = array();
		foreach ( get_post_meta( $p->ID ) as $k => $v ) {
			$meta[ $k ] = count( $v ) === 1 ? maybe_unserialize( $v[0] ) : array_map( 'maybe_unserialize', $v );
		}
		return array(
			'id'      => $p->ID,
			'type'    => $p->post_type,
			'title'   => $p->post_title,
			'slug'    => $p->post_name,
			'status'  => $p->post_status,
			'parent'  => $p->post_parent,
			'excerpt' => $p->post_excerpt,
			'link'    => get_permalink( $p ),
			'content' => $p->post_content,
			'meta'    => $meta,
		);
	}

	private static function apply_meta( $id, $meta ) {
		foreach ( (array) $meta as $k => $v ) {
			if ( null === $v ) {
				delete_post_meta( $id, $k );
			} else {
				update_post_meta( $id, $k, $v );
			}
		}
	}

	private static function tool_create_post( $in ) {
		$id = wp_insert_post( wp_slash( array(
			'post_title'   => (string) ( $in['title'] ?? '' ),
			'post_content' => self::clean_content( $in['content'] ?? '' ),
			'post_excerpt' => (string) ( $in['excerpt'] ?? '' ),
			'post_type'    => sanitize_key( $in['post_type'] ?? 'post' ),
			'post_status'  => sanitize_key( $in['status'] ?? 'draft' ),
			'post_parent'  => (int) ( $in['parent_id'] ?? 0 ),
			'post_author'  => get_current_user_id(),
		) ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::apply_meta( $id, $in['meta'] ?? array() );
		return array( 'ok' => true, 'id' => $id, 'edit_link' => get_edit_post_link( $id, 'raw' ), 'link' => get_permalink( $id ) );
	}

	private static function tool_update_post( $in ) {
		$id = (int) ( $in['id'] ?? 0 );
		if ( ! get_post( $id ) ) {
			return array( 'error' => 'Contenuto non trovato.' );
		}
		$map  = array( 'title' => 'post_title', 'status' => 'post_status', 'excerpt' => 'post_excerpt', 'slug' => 'post_name', 'parent_id' => 'post_parent' );
		$data = array( 'ID' => $id );
		foreach ( $map as $k => $f ) {
			if ( isset( $in[ $k ] ) ) {
				$data[ $f ] = $in[ $k ];
			}
		}
		if ( isset( $in['content'] ) ) {
			$data['post_content'] = self::clean_content( $in['content'] );
		}
		if ( count( $data ) > 1 ) {
			$r = wp_update_post( wp_slash( $data ), true );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		self::apply_meta( $id, $in['meta'] ?? array() );
		return array( 'ok' => true, 'id' => $id, 'link' => get_permalink( $id ), 'note' => 'Le revisioni precedenti restano disponibili nell\'editor.' );
	}

	private static function tool_delete_post( $in ) {
		$id = (int) ( $in['id'] ?? 0 );
		if ( ! get_post( $id ) ) {
			return array( 'error' => 'Contenuto non trovato.' );
		}
		$r = ! empty( $in['force'] ) ? wp_delete_post( $id, true ) : wp_trash_post( $id );
		return array( 'ok' => (bool) $r, 'id' => $id, 'mode' => ! empty( $in['force'] ) ? 'eliminato definitivamente' : 'cestinato' );
	}

	/* ==================================================================
	 * Utenti
	 * ================================================================ */

	private static function tool_list_users( $in ) {
		$args = array( 'number' => max( 1, min( 500, (int) ( $in['number'] ?? 100 ) ) ) );
		if ( ! empty( $in['role'] ) ) {
			$args['role'] = (string) $in['role'];
		}
		if ( ! empty( $in['search'] ) ) {
			$args['search'] = '*' . $in['search'] . '*';
		}
		$out = array();
		foreach ( get_users( $args ) as $u ) {
			$out[] = array( 'id' => $u->ID, 'login' => $u->user_login, 'email' => $u->user_email, 'display_name' => $u->display_name, 'roles' => $u->roles, 'registered' => $u->user_registered );
		}
		return array( 'users' => $out, 'available_roles' => array_keys( wp_roles()->get_names() ) );
	}

	private static function tool_create_user( $in ) {
		$generated = empty( $in['password'] );
		$pass      = $generated ? wp_generate_password( 20, true ) : (string) $in['password'];
		$id        = wp_insert_user( array(
			'user_login'   => (string) ( $in['username'] ?? '' ),
			'user_email'   => (string) ( $in['email'] ?? '' ),
			'user_pass'    => $pass,
			'role'         => (string) ( $in['role'] ?? get_option( 'default_role' ) ),
			'first_name'   => (string) ( $in['first_name'] ?? '' ),
			'last_name'    => (string) ( $in['last_name'] ?? '' ),
			'display_name' => (string) ( $in['display_name'] ?? $in['username'] ?? '' ),
		) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! empty( $in['send_notification'] ) ) {
			wp_new_user_notification( $id, null, 'user' );
		}
		$out = array( 'ok' => true, 'id' => $id, 'notification_sent' => ! empty( $in['send_notification'] ) );
		if ( $generated ) {
			$out['generated_password'] = $pass;
		}
		return $out;
	}

	private static function tool_update_user( $in ) {
		$id = (int) ( $in['id'] ?? 0 );
		$u  = get_userdata( $id );
		if ( ! $u ) {
			return array( 'error' => 'Utente non trovato.' );
		}
		$data = array( 'ID' => $id );
		foreach ( array( 'email' => 'user_email', 'password' => 'user_pass', 'first_name' => 'first_name', 'last_name' => 'last_name', 'display_name' => 'display_name' ) as $k => $f ) {
			if ( isset( $in[ $k ] ) ) {
				$data[ $f ] = (string) $in[ $k ];
			}
		}
		if ( count( $data ) > 1 ) {
			$r = wp_update_user( $data );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		if ( ! empty( $in['role'] ) ) {
			if ( ! wp_roles()->is_role( $in['role'] ) ) {
				return array( 'error' => 'Ruolo inesistente: ' . $in['role'] );
			}
			$u->set_role( $in['role'] );
		}
		$u = get_userdata( $id );
		return array( 'ok' => true, 'id' => $id, 'email' => $u->user_email, 'roles' => $u->roles );
	}

	private static function tool_delete_user( $in ) {
		$id = (int) ( $in['id'] ?? 0 );
		if ( $id === get_current_user_id() ) {
			return array( 'error' => 'Non posso eliminare l\'utente con cui sei collegato.' );
		}
		if ( ! get_userdata( $id ) ) {
			return array( 'error' => 'Utente non trovato.' );
		}
		$reassign = isset( $in['reassign_to'] ) ? (int) $in['reassign_to'] : null;
		return array( 'ok' => wp_delete_user( $id, $reassign ), 'id' => $id, 'content_reassigned_to' => $reassign );
	}

	/* ==================================================================
	 * Accesso diretto: PHP, SQL, file, web, core
	 * ================================================================ */

	private static function tool_run_php( $in ) {
		$code = (string) ( $in['code'] ?? '' );
		$code = preg_replace( '/^\s*<\?php/i', '', $code );
		$code = preg_replace( '/\?>\s*$/', '', $code );
		global $wpdb;
		ob_start();
		try {
			$ret = eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- funzione voluta, eseguita solo da amministratori e previa conferma.
		} catch ( Throwable $e ) {
			$out = ob_get_clean();
			return array( 'error' => get_class( $e ) . ': ' . $e->getMessage() . ' (riga ' . $e->getLine() . ')', 'output' => $out );
		}
		$out = ob_get_clean();
		return array( 'ok' => true, 'output' => $out, 'return' => $ret );
	}

	private static function tool_db_query( $in ) {
		global $wpdb;
		$sql = str_replace( '{prefix}', $wpdb->prefix, (string) ( $in['sql'] ?? '' ) );
		if ( self::is_read_sql( $sql ) ) {
			$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
			if ( $wpdb->last_error ) {
				return array( 'error' => $wpdb->last_error );
			}
			return array( 'total_rows' => count( $rows ), 'rows' => array_slice( (array) $rows, 0, 500 ) );
		}
		$r = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( false === $r ) {
			return array( 'error' => $wpdb->last_error ?: 'Query non riuscita.' );
		}
		wp_cache_flush();
		return array( 'ok' => true, 'rows_affected' => $r, 'insert_id' => $wpdb->insert_id ?: null );
	}

	private static function resolve_path( $p ) {
		$p = (string) $p;
		if ( '' === $p ) {
			return wp_normalize_path( ABSPATH );
		}
		if ( ! preg_match( '#^(/|[A-Za-z]:[\\\\/])#', $p ) ) {
			$p = ABSPATH . ltrim( $p, '/\\' );
		}
		return wp_normalize_path( $p );
	}

	private static function tool_list_dir( $in ) {
		$dir = self::resolve_path( $in['path'] ?? '' );
		if ( ! is_dir( $dir ) ) {
			return array( 'error' => "Cartella non trovata: $dir" );
		}
		$out = array();
		foreach ( new DirectoryIterator( $dir ) as $f ) {
			if ( $f->isDot() ) {
				continue;
			}
			$out[] = array( 'name' => $f->getFilename(), 'type' => $f->isDir() ? 'dir' : 'file', 'size' => $f->isFile() ? $f->getSize() : null, 'modified' => gmdate( 'Y-m-d H:i', $f->getMTime() ), 'writable' => $f->isWritable() );
			if ( count( $out ) >= 1000 ) {
				break;
			}
		}
		usort( $out, function ( $a, $b ) {
			return strcmp( $a['type'] . $a['name'], $b['type'] . $b['name'] );
		} );
		return array( 'path' => $dir, 'entries' => $out );
	}

	private static function tool_read_file( $in ) {
		$file = self::resolve_path( $in['path'] ?? '' );
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return array( 'error' => "File non trovato o non leggibile: $file" );
		}
		$size   = filesize( $file );
		$offset = max( 0, (int) ( $in['offset'] ?? 0 ) );
		$chunk  = 40000;
		$data   = file_get_contents( $file, false, null, $offset, $chunk );
		$out    = array( 'path' => $file, 'size' => $size, 'offset' => $offset, 'content' => $data );
		if ( $offset + $chunk < $size ) {
			$out['next_offset'] = $offset + $chunk;
		}
		return $out;
	}

	private static function backup_file( $file ) {
		if ( ! file_exists( $file ) ) {
			return null;
		}
		$rel  = ltrim( str_replace( wp_normalize_path( ABSPATH ), '', $file ), '/' );
		$rel  = $rel === $file ? ltrim( str_replace( ':', '', $file ), '/' ) : $rel;
		$dest = wp_normalize_path( WP_CONTENT_DIR ) . '/wpaia-backups/' . gmdate( 'Ymd-His' ) . '/' . $rel;
		wp_mkdir_p( dirname( $dest ) );
		$root = WP_CONTENT_DIR . '/wpaia-backups/';
		if ( ! file_exists( $root . '.htaccess' ) ) {
			file_put_contents( $root . '.htaccess', "Require all denied\nDeny from all\n" );
			file_put_contents( $root . 'index.php', '<?php // Silence.' );
		}
		return copy( $file, $dest ) ? $dest : null;
	}

	private static function tool_write_file( $in ) {
		$file    = self::resolve_path( $in['path'] ?? '' );
		$content = (string) ( $in['content'] ?? '' );
		$append  = ! empty( $in['append'] );
		if ( preg_match( '/\.php$/i', $file ) ) {
			$full = $append && file_exists( $file ) ? file_get_contents( $file ) . $content : $content;
			try {
				token_get_all( $full, TOKEN_PARSE );
			} catch ( ParseError $e ) {
				return array( 'error' => 'Errore di sintassi PHP, file NON scritto: ' . $e->getMessage() . ' (riga ' . $e->getLine() . ')' );
			}
		}
		$backup = self::backup_file( $file );
		wp_mkdir_p( dirname( $file ) );
		$r = file_put_contents( $file, $content, $append ? FILE_APPEND : 0 );
		if ( false === $r ) {
			return array( 'error' => "Impossibile scrivere $file (permessi?)." );
		}
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $file, true ); // phpcs:ignore
		}
		return array( 'ok' => true, 'path' => $file, 'bytes' => $r, 'backup' => $backup );
	}

	private static function tool_delete_file( $in ) {
		$file = self::resolve_path( $in['path'] ?? '' );
		if ( ! is_file( $file ) ) {
			return array( 'error' => "File non trovato: $file" );
		}
		$backup = self::backup_file( $file );
		if ( ! @unlink( $file ) ) { // phpcs:ignore
			return array( 'error' => "Impossibile eliminare $file." );
		}
		return array( 'ok' => true, 'deleted' => $file, 'backup' => $backup );
	}

	private static function tool_fetch_url( $in ) {
		$url = esc_url_raw( (string) ( $in['url'] ?? '' ) );
		$r   = wp_remote_get( $url, array( 'timeout' => 30, 'user-agent' => 'WP-AI-Agent/' . WPAIA_VERSION ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$type = wp_remote_retrieve_header( $r, 'content-type' );
		$body = wp_remote_retrieve_body( $r );
		if ( false !== stripos( $type, 'html' ) ) {
			$body = preg_replace( '#<(script|style|noscript|svg)\b[^>]*>.*?</\1>#is', '', $body );
			$body = trim( preg_replace( "/\n\s*\n+/", "\n\n", html_entity_decode( wp_strip_all_tags( $body ) ) ) );
		}
		return array( 'status' => wp_remote_retrieve_response_code( $r ), 'content_type' => $type, 'content' => mb_strimwidth( $body, 0, 40000, '…[troncato]' ) );
	}

	private static function tool_update_core() {
		if ( $e = self::check_file_mods() ) {
			return $e;
		}
		wp_version_check( array(), true );
		$update = null;
		foreach ( (array) get_core_updates() as $u ) {
			if ( 'upgrade' === $u->response ) {
				$update = $u;
				break;
			}
		}
		if ( ! $update ) {
			global $wp_version;
			return array( 'ok' => true, 'note' => "WordPress è già aggiornato ($wp_version)." );
		}
		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Core_Upgrader( $skin );
		$result   = $upgrader->upgrade( $update );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'ok' => true, 'new_version' => $result );
	}
}
