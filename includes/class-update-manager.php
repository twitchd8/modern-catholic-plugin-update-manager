<?php
/**
 * WordPress update integration.
 *
 * @package ModernCatholicUpdateManager
 */

namespace PowerHouse\ModernCatholic\UpdateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Update_Manager {
	const OPTION_RESULTS = 'modern_catholic_updates_last_results';
	const CRON_HOOK      = 'modern_catholic_updates_check';

	/** @var Repository_Registry */
	private $registry;

	/** @var GitHub_Client */
	private $github;

	public function __construct( Repository_Registry $registry, GitHub_Client $github ) {
		$this->registry = $registry;
		$this->github   = $github;
	}

	/** Register hooks. */
	public function hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_plugin_updates' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'filter_theme_updates' ) );
		add_filter( 'plugins_api', array( $this, 'filter_plugin_information' ), 20, 3 );
		add_action( self::CRON_HOOK, array( $this, 'scheduled_check' ) );
	}

	/**
	 * Scan all registered repositories and save a notification snapshot.
	 *
	 * @param bool $force Bypass GitHub metadata caches.
	 * @return array<string,mixed>
	 */
	public function scan( $force = false ) {
		$results = array(
			'checked_at' => time(),
			'updates'    => 0,
			'items'      => array(),
		);

		foreach ( $this->registry->all() as $repository ) {
			$state = $this->component_state( $repository );
			$item  = array_merge(
				$repository,
				array(
					'installed'         => $state['installed'],
					'installed_version' => $state['version'],
					'development'       => $state['development'],
					'component_file'    => $state['component_file'],
					'status'            => $repository['enabled'] ? 'checking' : 'disabled',
				)
			);

			if ( ! $repository['enabled'] ) {
				$results['items'][ $repository['id'] ] = $item;
				continue;
			}

			$release = $this->github->latest_release( $repository, $force );
			if ( is_wp_error( $release ) ) {
				$item['status'] = $release->get_error_code();
				$item['error']  = $release->get_error_message();
			} else {
				$item['release'] = $release;
				if ( ! $state['installed'] ) {
					$item['status'] = 'not_installed';
				} elseif ( $state['development'] ) {
					$item['status'] = version_compare( $release['version'], $state['version'], '>' ) ? 'development_update' : 'development_current';
				} elseif ( version_compare( $release['version'], $state['version'], '>' ) ) {
					$item['status'] = 'update_available';
					++$results['updates'];
				} else {
					$item['status'] = 'current';
				}
			}

			$results['items'][ $repository['id'] ] = $item;
		}

		update_option( self::OPTION_RESULTS, $results, false );
		return $results;
	}

	/** Run the scheduled release check. */
	public function scheduled_check() {
		$this->scan( true );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
	}

	/**
	 * Add plugin releases to WordPress's native update transient.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function filter_plugin_updates( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		foreach ( $this->registry->all() as $repository ) {
			if ( 'plugin' !== $repository['type'] || ! $repository['enabled'] ) {
				continue;
			}
			$state = $this->component_state( $repository );
			if ( ! $state['installed'] || $state['development'] ) {
				continue;
			}

			$release = $this->github->latest_release( $repository );
			if ( is_wp_error( $release ) ) {
				continue;
			}

			$update = (object) array(
				'id'          => $repository['repository_url'],
				'slug'        => $repository['slug'],
				'plugin'      => $state['component_file'],
				'new_version' => $release['version'],
				'url'         => $release['html_url'],
				'package'     => $release['package'],
				'icons'       => array(),
				'banners'     => array(),
			);

			if ( version_compare( $release['version'], $state['version'], '>' ) ) {
				$transient->response[ $state['component_file'] ] = $update;
			} else {
				$transient->no_update[ $state['component_file'] ] = $update;
			}
		}

		return $transient;
	}

	/**
	 * Add theme releases to WordPress's native update transient.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function filter_theme_updates( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		foreach ( $this->registry->all() as $repository ) {
			if ( 'theme' !== $repository['type'] || ! $repository['enabled'] ) {
				continue;
			}
			$state = $this->component_state( $repository );
			if ( ! $state['installed'] || $state['development'] ) {
				continue;
			}

			$release = $this->github->latest_release( $repository );
			if ( is_wp_error( $release ) ) {
				continue;
			}

			$update = array(
				'theme'       => $repository['slug'],
				'new_version' => $release['version'],
				'url'         => $release['html_url'],
				'package'     => $release['package'],
			);

			if ( version_compare( $release['version'], $state['version'], '>' ) ) {
				$transient->response[ $repository['slug'] ] = $update;
			} else {
				$transient->no_update[ $repository['slug'] ] = $update;
			}
		}

		return $transient;
	}

	/**
	 * Populate the native plugin details modal with GitHub release notes.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action API action.
	 * @param object             $args   API arguments.
	 * @return false|object|array
	 */
	public function filter_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		foreach ( $this->registry->all() as $repository ) {
			if ( 'plugin' !== $repository['type'] || $repository['slug'] !== $args->slug ) {
				continue;
			}
			$release = $this->github->latest_release( $repository );
			if ( is_wp_error( $release ) ) {
				return $result;
			}
			return (object) array(
				'name'          => $repository['name'],
				'slug'          => $repository['slug'],
				'version'       => $release['version'],
				'author'        => '<a href="https://powerhouseil.com">Andrew T. Schmitt</a>',
				'homepage'      => $repository['repository_url'],
				'download_link' => $release['package'],
				'sections'      => array(
					'description' => sprintf( __( 'A Modern Catholic component maintained at %s.', 'modern-catholic-plugin-update-manager' ), esc_html( $repository['repository_url'] ) ),
					'changelog'   => wpautop( $release['notes'] ? $release['notes'] : __( 'See the GitHub Release for details.', 'modern-catholic-plugin-update-manager' ) ),
				),
			);
		}

		return $result;
	}

	/**
	 * Determine installed version, component file, and Git checkout protection.
	 *
	 * @param array<string,mixed> $repository Repository definition.
	 * @return array{installed:bool,version:string,development:bool,component_file:string}
	 */
	public function component_state( $repository ) {
		if ( 'theme' === $repository['type'] ) {
			$theme     = wp_get_theme( $repository['slug'] );
			$installed = $theme->exists();
			$path      = $installed ? $theme->get_stylesheet_directory() : get_theme_root() . '/' . $repository['slug'];
			return array(
				'installed'      => $installed,
				'version'        => $installed ? (string) $theme->get( 'Version' ) : '',
				'development'    => is_dir( $path . '/.git' ),
				'component_file' => $repository['slug'],
			);
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins     = get_plugins();
		$plugin_file = '';
		$data        = array();
		foreach ( $plugins as $candidate => $candidate_data ) {
			if ( 0 === strpos( $candidate, $repository['slug'] . '/' ) ) {
				if ( ! $repository['entrypoint'] || basename( $candidate ) === $repository['entrypoint'] ) {
					$plugin_file = $candidate;
					$data        = $candidate_data;
					break;
				}
			}
		}

		$path = WP_PLUGIN_DIR . '/' . $repository['slug'];
		return array(
			'installed'      => '' !== $plugin_file,
			'version'        => isset( $data['Version'] ) ? (string) $data['Version'] : '',
			'development'    => is_dir( $path . '/.git' ),
			'component_file' => $plugin_file,
		);
	}
}
