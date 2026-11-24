<?php
/**
 * Aggiornamenti del plugin dalle release di un repository GitHub (pubblico o privato).
 *
 * - Il plugin dichiara "Update URI: https://github.com/..." nell'intestazione, quindi
 *   WordPress non lo confonde con plugin omonimi su wordpress.org e chiede a noi
 *   tramite il filtro update_plugins_github.com.
 * - Si usa l'ultima release (non bozza, non pre-release): il tag "v1.2.3" o "1.2.3"
 *   è la versione. Se la release ha un asset .zip lo usa, altrimenti lo zip del sorgente.
 * - Il download è gestito qui (upgrader_pre_download) così funziona anche con
 *   repository privati: il token va solo a GitHub, non al CDN su cui GitHub reindirizza.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIA_Updater {

	const CACHE   = 'wpaia_gh_release';
	const SLUG    = 'wp-ai-agent';

	private $file;
	private $basename;

	public function __construct( $plugin_file ) {
		$this->file     = $plugin_file;
		$this->basename = plugin_basename( $plugin_file );

		add_filter( 'update_plugins_github.com', array( $this, 'check_update' ), 10, 4 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'flush' ), 10, 0 );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	public static function repo() {
		$s    = wpaia_settings();
		$repo = trim( (string) $s['github_repo'] );
		// Accetta anche l'URL completo.
		$repo = preg_replace( '#^https?://github\.com/#i', '', $repo );
		$repo = preg_replace( '#\.git$#', '', trim( $repo, '/' ) );
		return preg_match( '#^[\w.-]+/[\w.-]+$#', $repo ) ? $repo : '';
	}

	private static function token() {
		return trim( (string) wpaia_settings()['github_token'] );
	}

	private static function headers( $accept = 'application/vnd.github+json' ) {
		$h = array(
			'Accept'               => $accept,
			'User-Agent'           => 'WP-AI-Agent-Updater',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( self::token() ) {
			$h['Authorization'] = 'Bearer ' . self::token();
		}
		return $h;
	}

	public function flush() {
		delete_site_transient( self::CACHE );
	}

	/**
	 * Ultima release, in cache per 6 ore.
	 *
	 * @return array|WP_Error
	 */
	public static function get_release( $force = false ) {
		$repo = self::repo();
		if ( ! $repo ) {
			return new WP_Error( 'wpaia_norepo', 'Repository GitHub non configurato.' );
		}
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE );
			if ( is_array( $cached ) && ( $cached['repo'] ?? '' ) === $repo ) {
				return $cached;
			}
		}
		$r = wp_remote_get( "https://api.github.com/repos/$repo/releases/latest", array( 'headers' => self::headers(), 'timeout' => 20 ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$code = wp_remote_retrieve_response_code( $r );
		$data = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( 200 !== $code ) {
			$msg = $data['message'] ?? 'HTTP ' . $code;
			if ( 404 === $code ) {
				$msg = 'Nessuna release trovata (o repository privato senza token valido).';
			}
			$err = array( 'repo' => $repo, 'error' => $msg );
			set_site_transient( self::CACHE, $err, HOUR_IN_SECONDS );
			return new WP_Error( 'wpaia_gh', $msg );
		}
		$package = $data['zipball_url'];
		foreach ( (array) ( $data['assets'] ?? array() ) as $a ) {
			if ( preg_match( '/\.zip$/i', $a['name'] ) ) {
				// Endpoint API dell'asset: funziona anche per i repository privati.
				$package = $a['url'];
				break;
			}
		}
		$rel = array(
			'repo'      => $repo,
			'version'   => ltrim( (string) $data['tag_name'], 'vV' ),
			'tag'       => $data['tag_name'],
			'package'   => $package,
			'url'       => $data['html_url'],
			'changelog' => (string) ( $data['body'] ?? '' ),
			'date'      => $data['published_at'] ?? '',
		);
		set_site_transient( self::CACHE, $rel, 6 * HOUR_IN_SECONDS );
		return $rel;
	}

	private static function cached_release() {
		$rel = self::get_release();
		return ( is_wp_error( $rel ) || ! empty( $rel['error'] ) ) ? null : $rel;
	}

	/** Filtro update_plugins_{hostname} (WP 5.8+). */
	public function check_update( $update, $plugin_data, $plugin_file, $locales ) {
		if ( $plugin_file !== $this->basename ) {
			return $update;
		}
		$rel = self::cached_release();
		if ( ! $rel ) {
			return $update;
		}
		return array(
			'id'           => 'github.com/' . $rel['repo'],
			'slug'         => self::SLUG,
			'plugin'       => $this->basename,
			'version'      => $rel['version'],
			'new_version'  => $rel['version'],
			'url'          => $rel['url'],
			'package'      => $rel['package'],
			'requires_php' => '7.4',
		);
	}

	/** Finestra "Visualizza dettagli" nella pagina Plugin. */
	public function plugin_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== self::SLUG ) {
			return $res;
		}
		$rel = self::cached_release();
		if ( ! $rel ) {
			return $res;
		}
		$data = get_plugin_data( $this->file, false, false );
		return (object) array(
			'name'          => $data['Name'],
			'slug'          => self::SLUG,
			'version'       => $rel['version'],
			'author'        => $data['Author'],
			'homepage'      => $rel['url'],
			'last_updated'  => $rel['date'],
			'download_link' => $rel['package'],
			'sections'      => array(
				'description' => $data['Description'],
				'changelog'   => $rel['changelog'] ? wpautop( esc_html( $rel['changelog'] ) ) : 'Vedi ' . esc_url( $rel['url'] ),
			),
		);
	}

	/** Scarica lo zip da GitHub gestendo token e redirect. */
	public function download( $reply, $package, $upgrader, $hook_extra = array() ) {
		$repo = self::repo();
		if ( false !== $reply || ! $repo ) {
			return $reply;
		}
		$is_ours = 0 === strpos( $package, "https://api.github.com/repos/$repo/" ) || 0 === strpos( $package, "https://github.com/$repo/" );
		if ( ! $is_ours ) {
			return $reply;
		}
		$accept = false !== strpos( $package, '/releases/assets/' ) ? 'application/octet-stream' : 'application/vnd.github+json';
		$tmp    = wp_tempnam( self::SLUG . '.zip' );
		$url    = $package;
		$send   = self::headers( $accept );

		for ( $i = 0; $i < 5; $i++ ) {
			$r = wp_remote_get( $url, array(
				'headers'     => $send,
				'timeout'     => 300,
				'redirection' => 0,
				'stream'      => true,
				'filename'    => $tmp,
			) );
			if ( is_wp_error( $r ) ) {
				@unlink( $tmp ); // phpcs:ignore
				return $r;
			}
			$code = wp_remote_retrieve_response_code( $r );
			if ( $code >= 300 && $code < 400 ) {
				$url  = wp_remote_retrieve_header( $r, 'location' );
				$host = wp_parse_url( $url, PHP_URL_HOST );
				// Il token va solo agli host GitHub, mai ai CDN firmati.
				$send = in_array( $host, array( 'api.github.com', 'github.com' ), true ) ? self::headers( $accept ) : array( 'User-Agent' => 'WP-AI-Agent-Updater' );
				continue;
			}
			if ( 200 !== $code ) {
				@unlink( $tmp ); // phpcs:ignore
				return new WP_Error( 'wpaia_dl', "Download da GitHub non riuscito (HTTP $code)." );
			}
			return $tmp;
		}
		@unlink( $tmp ); // phpcs:ignore
		return new WP_Error( 'wpaia_dl', 'Troppi redirect durante il download da GitHub.' );
	}

	/** Lo zip del sorgente si estrae in "utente-repo-abc123/": lo rinominiamo nella cartella del plugin. */
	public function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		$is_ours = ( $hook_extra['plugin'] ?? '' ) === $this->basename
			|| file_exists( trailingslashit( $source ) . 'wp-ai-agent.php' );
		if ( ! $is_ours || is_wp_error( $source ) ) {
			return $source;
		}
		$wanted = trailingslashit( $remote_source ) . dirname( $this->basename ) . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $wanted ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
			return $wanted;
		}
		return new WP_Error( 'wpaia_rename', 'Impossibile rinominare la cartella del plugin dopo il download.' );
	}

	public function row_meta( $links, $file ) {
		if ( $file === $this->basename && self::repo() ) {
			$links[] = '<a href="https://github.com/' . esc_attr( self::repo() ) . '" target="_blank" rel="noopener">GitHub</a>';
			$links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpaia_check_update' ), 'wpaia_check_update' ) ) . '">Controlla aggiornamenti</a>';
		}
		return $links;
	}
}
