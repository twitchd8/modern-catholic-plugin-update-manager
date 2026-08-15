<?php
/**
 * GitHub Releases client.
 *
 * @package ModernCatholicUpdateManager
 */

namespace PowerHouse\ModernCatholic\UpdateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GitHub_Client {
	const CACHE_SECONDS = 21600;
	const OPTION_DIGESTS = 'modern_catholic_updates_package_digests';

	/** @var Credential_File */
	private $credentials;

	public function __construct( ?Credential_File $credentials = null ) {
		$this->credentials = $credentials ? $credentials : new Credential_File();
	}

	/**
	 * Fetch the latest stable GitHub Release for a repository.
	 *
	 * @param array<string,mixed> $repository Repository definition.
	 * @param bool                $force      Bypass cached metadata.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function latest_release( $repository, $force = false ) {
		$key = $this->cache_key( $repository['id'] );
		if ( $force ) {
			delete_transient( $key );
		}

		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			if ( isset( $cached['_error'] ) ) {
				return new \WP_Error( $cached['_error'], isset( $cached['message'] ) ? $cached['message'] : __( 'GitHub release check failed.', 'modern-catholic-plugin-update-manager' ) );
			}
			return $cached;
		}

		$url      = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $repository['owner'] ), rawurlencode( $repository['repo'] ) );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'headers'     => $this->headers(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->cache_error( $key, $response->get_error_code(), $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 404 === $status ) {
			$message = $this->has_token()
				? __( 'No published, non-prerelease GitHub Release was found.', 'modern-catholic-plugin-update-manager' )
				: __( 'No public release was found. If this repository is private, configure a read-only GitHub token.', 'modern-catholic-plugin-update-manager' );
			return $this->cache_error( $key, 'release_not_found', $message );
		}

		if ( 200 !== $status ) {
			$remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );
			$message   = sprintf( __( 'GitHub returned HTTP %d.', 'modern-catholic-plugin-update-manager' ), $status );
			if ( '0' === (string) $remaining ) {
				$message = __( 'GitHub API rate limit reached. Try again after the reset time or configure a read-only token.', 'modern-catholic-plugin-update-manager' );
			}
			return $this->cache_error( $key, 'github_http_error', $message );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return $this->cache_error( $key, 'invalid_release', __( 'GitHub returned invalid or non-stable release metadata.', 'modern-catholic-plugin-update-manager' ) );
		}

		$version = self::normalize_version( $data['tag_name'] );
		if ( ! $version ) {
			return $this->cache_error( $key, 'invalid_version', __( 'The latest GitHub Release tag is not a usable version.', 'modern-catholic-plugin-update-manager' ) );
		}

		$asset_name = str_replace( array( '{slug}', '{version}' ), array( $repository['slug'], $version ), $repository['asset_template'] );
		$asset      = null;
		foreach ( isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : array() as $candidate ) {
			if ( isset( $candidate['name'] ) && hash_equals( $asset_name, $candidate['name'] ) ) {
				$asset = $candidate;
				break;
			}
		}

		if ( ! $asset || empty( $asset['url'] ) ) {
			return $this->cache_error(
				$key,
				'missing_release_asset',
				sprintf( __( 'Release %1$s does not contain the required asset %2$s.', 'modern-catholic-plugin-update-manager' ), $version, $asset_name )
			);
		}

		$release = array(
			'version'      => $version,
			'tag'          => sanitize_text_field( $data['tag_name'] ),
			'name'         => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : $data['tag_name'],
			'notes'        => isset( $data['body'] ) ? wp_kses_post( $data['body'] ) : '',
			'published_at' => isset( $data['published_at'] ) ? sanitize_text_field( $data['published_at'] ) : '',
			'html_url'     => isset( $data['html_url'] ) ? esc_url_raw( $data['html_url'] ) : $repository['repository_url'] . '/releases',
			'package'      => esc_url_raw( $asset['url'] ),
			'asset_name'   => sanitize_file_name( $asset['name'] ),
			'asset_size'   => isset( $asset['size'] ) ? absint( $asset['size'] ) : 0,
			'digest'       => isset( $asset['digest'] ) ? sanitize_text_field( $asset['digest'] ) : '',
		);

		set_transient( $key, $release, self::CACHE_SECONDS );
		$this->remember_digest( $release['package'], $release['digest'] );

		return $release;
	}

	/**
	 * Add GitHub API headers to authenticated release asset downloads.
	 *
	 * @param array<string,mixed> $args Request arguments.
	 * @param string              $url  Request URL.
	 * @return array<string,mixed>
	 */
	public function filter_http_request_args( $args, $url ) {
		if ( ! preg_match( '#^https://api\.github\.com/repos/[^/]+/[^/]+/releases/assets/\d+$#', $url ) ) {
			return $args;
		}

		$args['headers'] = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$args['headers']['Accept'] = 'application/octet-stream';
		$args['headers']['X-GitHub-Api-Version'] = '2022-11-28';
		$args['headers']['User-Agent'] = 'Modern-Catholic-Update-Manager/' . MODERN_CATHOLIC_UPDATE_MANAGER_VERSION;
		$token = $this->token();
		if ( $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}
		return $args;
	}

	/**
	 * Verify a downloaded asset when GitHub provides a SHA-256 digest.
	 *
	 * @param bool|\WP_Error $reply      Existing filter response.
	 * @param string         $package    Package URL.
	 * @param \WP_Upgrader   $upgrader   Upgrader instance.
	 * @param array          $hook_extra Upgrade context.
	 * @return bool|string|\WP_Error
	 */
	public function verify_download( $reply, $package, $upgrader, $hook_extra ) {
		unset( $upgrader, $hook_extra );
		if ( false !== $reply ) {
			return $reply;
		}

		$digests = get_option( self::OPTION_DIGESTS, array() );
		$key     = hash( 'sha256', $package );
		if ( ! is_array( $digests ) || empty( $digests[ $key ] ) || 0 !== strpos( $digests[ $key ], 'sha256:' ) ) {
			return false;
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$file = download_url( $package, 300, false );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$expected = substr( $digests[ $key ], 7 );
		$actual   = hash_file( 'sha256', $file );
		if ( ! $actual || ! hash_equals( strtolower( $expected ), strtolower( $actual ) ) ) {
			wp_delete_file( $file );
			return new \WP_Error( 'package_digest_mismatch', __( 'The downloaded release failed SHA-256 verification.', 'modern-catholic-plugin-update-manager' ) );
		}

		return $file;
	}

	/**
	 * Clear one repository's metadata cache.
	 *
	 * @param string $id Repository owner/name.
	 * @return void
	 */
	public function clear( $id ) {
		delete_transient( $this->cache_key( $id ) );
	}

	/**
	 * Whether a token is available without revealing it.
	 *
	 * @return bool
	 */
	public function has_token() {
		return '' !== $this->token();
	}

	/** Return the active credential source without revealing the credential. */
	public function token_source() {
		if ( defined( 'MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN' ) && '' !== trim( (string) MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN ) ) {
			return 'wp-config.php';
		}
		$environment_token = getenv( 'MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN' );
		if ( false !== $environment_token && '' !== trim( (string) $environment_token ) ) {
			return 'environment';
		}
		if ( $this->credentials->exists() ) {
			return 'credential_file';
		}
		return '' !== trim( (string) apply_filters( 'modern_catholic_updates_github_token', '' ) ) ? 'filter' : '';
	}

	/** Validate candidate access against this plugin's private repository. */
	public function validate_token( $token ) {
		$token    = trim( (string) $token );
		$response = wp_remote_get(
			'https://api.github.com/repos/twitchd8/modern-catholic-plugin-update-manager',
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'headers'     => $this->headers( $token ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'github_token_rejected', __( 'GitHub rejected the token or it cannot read the Update Manager repository.', 'modern-catholic-plugin-update-manager' ) );
		}
		return true;
	}

	/**
	 * Normalize a version tag.
	 *
	 * @param string $tag Git tag.
	 * @return string
	 */
	public static function normalize_version( $tag ) {
		$version = preg_replace( '/^[vV]/', '', trim( (string) $tag ) );
		return preg_match( '/^[0-9]+(?:\.[0-9A-Za-z-]+)+$/', $version ) ? $version : '';
	}

	/**
	 * Request headers.
	 *
	 * @return array<string,string>
	 */
	private function headers( $token_override = null ) {
		$headers = array(
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'Modern-Catholic-Update-Manager/' . MODERN_CATHOLIC_UPDATE_MANAGER_VERSION,
		);
		$token = null === $token_override ? $this->token() : trim( (string) $token_override );
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		return $headers;
	}

	/**
	 * Retrieve a token from wp-config.php, the process environment, the ignored
	 * credential file, or a filter.
	 *
	 * @return string
	 */
	private function token() {
		$token = defined( 'MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN' ) ? (string) MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN : '';
		if ( '' === trim( $token ) ) {
			$environment_token = getenv( 'MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN' );
			$token             = false === $environment_token ? '' : (string) $environment_token;
		}
		if ( '' === trim( $token ) ) {
			$token = $this->credentials->read();
		}
		return trim( (string) apply_filters( 'modern_catholic_updates_github_token', $token ) );
	}

	/**
	 * Cache an error briefly.
	 *
	 * @param string $key     Transient key.
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return \WP_Error
	 */
	private function cache_error( $key, $code, $message ) {
		set_transient( $key, array( '_error' => $code, 'message' => $message ), 600 );
		return new \WP_Error( $code, $message );
	}

	/**
	 * Cache key.
	 *
	 * @param string $id Repository ID.
	 * @return string
	 */
	private function cache_key( $id ) {
		return 'mc_updates_' . substr( hash( 'sha256', strtolower( $id ) ), 0, 32 );
	}

	/**
	 * Persist expected package digests without retaining credentials.
	 *
	 * @param string $package Package URL.
	 * @param string $digest  GitHub digest.
	 * @return void
	 */
	private function remember_digest( $package, $digest ) {
		if ( ! $digest || 0 !== strpos( $digest, 'sha256:' ) ) {
			return;
		}
		$digests = get_option( self::OPTION_DIGESTS, array() );
		$digests = is_array( $digests ) ? $digests : array();
		$digests[ hash( 'sha256', $package ) ] = $digest;
		if ( count( $digests ) > 100 ) {
			$digests = array_slice( $digests, -100, null, true );
		}
		update_option( self::OPTION_DIGESTS, $digests, false );
	}
}
