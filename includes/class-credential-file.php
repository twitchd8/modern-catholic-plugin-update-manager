<?php
/**
 * Filesystem-backed GitHub credential storage.
 *
 * @package ModernCatholicUpdateManager
 */

namespace PowerHouse\ModernCatholic\UpdateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Credential_File {
	const FILENAME        = '.github-token.php';
	const LEGACY_FILENAME = 'github-token.php';

	/** @var string */
	private $path;

	/** @var string */
	private $preserved_token = '';

	/**
	 * @param string $path Optional alternate path for tests.
	 */
	public function __construct( $path = '' ) {
		$this->path = $path ? $path : MODERN_CATHOLIC_UPDATE_MANAGER_DIR . self::FILENAME;
		if ( ! $path ) {
			$this->migrate_legacy_file();
		}
		$this->preserved_token = $this->read();
	}

	/** Register self-update preservation. */
	public function hooks() {
		add_action( 'upgrader_process_complete', array( $this, 'restore_after_update' ), 10, 2 );
	}

	/** Return the credential file path. */
	public function path() {
		return $this->path;
	}

	/** Whether a usable file-backed token exists. */
	public function exists() {
		return '' !== $this->read();
	}

	/** Whether the credential file can be created or replaced. */
	public function is_writable() {
		return file_exists( $this->path ) ? is_writable( $this->path ) : is_writable( dirname( $this->path ) );
	}

	/** Read the token without exposing it to WordPress options. */
	public function read() {
		if ( ! is_readable( $this->path ) ) {
			return '';
		}

		$value = include $this->path;
		$value = is_string( $value ) ? trim( $value ) : '';
		return $this->is_valid( $value ) ? $value : '';
	}

	/** Create an empty protected credential file when possible. */
	public function ensure_placeholder() {
		if ( file_exists( $this->path ) ) {
			return true;
		}
		return $this->write_contents( $this->contents( '' ) );
	}

	/** Save a validated token to the protected PHP credential file. */
	public function write( $token ) {
		$token = trim( (string) $token );
		if ( ! $this->is_valid( $token ) ) {
			return new \WP_Error( 'invalid_github_token', __( 'Enter a valid GitHub token without spaces.', 'modern-catholic-plugin-update-manager' ) );
		}

		$result = $this->write_contents( $this->contents( $token ) );
		if ( true === $result ) {
			$this->preserved_token = $token;
		}
		return $result;
	}

	/** Remove the file-backed credential. */
	public function remove() {
		$this->preserved_token = '';
		if ( ! file_exists( $this->path ) ) {
			return true;
		}
		wp_delete_file( $this->path );
		return ! file_exists( $this->path );
	}

	/** Restore the ignored credential file after this plugin replaces itself. */
	public function restore_after_update( $upgrader, $hook_extra ) {
		unset( $upgrader );
		if ( ! $this->preserved_token || ! $this->is_our_update( $hook_extra ) || $this->exists() ) {
			return;
		}
		$this->write( $this->preserved_token );
	}

	/** Validate the token's storage-safe format. */
	public function is_valid( $token ) {
		return '' !== $token && strlen( $token ) >= 20 && strlen( $token ) <= 512 && 1 === preg_match( '/^[A-Za-z0-9_]+$/', $token );
	}

	/** Build a non-rendering PHP credential file. */
	private function contents( $token ) {
		return "<?php\nif ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\nreturn " . var_export( $token, true ) . ";\n";
	}

	/** Write credential contents with an exclusive lock and restrictive permissions. */
	private function write_contents( $contents ) {
		if ( ! $this->is_writable() ) {
			return new \WP_Error( 'credential_file_not_writable', __( 'The plugin credential file is not writable.', 'modern-catholic-plugin-update-manager' ) );
		}

		$written = file_put_contents( $this->path, $contents, LOCK_EX );
		if ( false === $written || strlen( $contents ) !== $written ) {
			return new \WP_Error( 'credential_file_write_failed', __( 'WordPress could not save the plugin credential file.', 'modern-catholic-plugin-update-manager' ) );
		}
		@chmod( $this->path, 0600 );
		clearstatcache( true, $this->path );
		return true;
	}

	/** Migrate the original non-hidden development filename without exposing it. */
	private function migrate_legacy_file() {
		$legacy_path = MODERN_CATHOLIC_UPDATE_MANAGER_DIR . self::LEGACY_FILENAME;
		if ( file_exists( $this->path ) || ! is_readable( $legacy_path ) ) {
			return;
		}
		$value = include $legacy_path;
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $this->is_valid( $value ) ) {
			$result = $this->write( $value );
			if ( is_wp_error( $result ) ) {
				return;
			}
		}
		wp_delete_file( $legacy_path );
	}

	/** Determine whether an upgrader completion belongs to this plugin. */
	private function is_our_update( $hook_extra ) {
		if ( ! is_array( $hook_extra ) || 'update' !== ( isset( $hook_extra['action'] ) ? $hook_extra['action'] : '' ) || 'plugin' !== ( isset( $hook_extra['type'] ) ? $hook_extra['type'] : '' ) ) {
			return false;
		}

		$plugins = isset( $hook_extra['plugins'] ) ? (array) $hook_extra['plugins'] : array();
		if ( ! empty( $hook_extra['plugin'] ) ) {
			$plugins[] = $hook_extra['plugin'];
		}
		return in_array( plugin_basename( MODERN_CATHOLIC_UPDATE_MANAGER_FILE ), $plugins, true );
	}
}
