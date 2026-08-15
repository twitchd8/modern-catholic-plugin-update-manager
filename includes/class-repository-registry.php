<?php
/**
 * Repository registry.
 *
 * @package ModernCatholicUpdateManager
 */

namespace PowerHouse\ModernCatholic\UpdateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Repository_Registry {
	const OPTION_REPOSITORIES = 'modern_catholic_updates_repositories';
	const OPTION_DISABLED     = 'modern_catholic_updates_disabled';
	const OPTION_OWNERS       = 'modern_catholic_updates_trusted_owners';

	/**
	 * Return all known repositories.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function all() {
		$repositories = $this->defaults();
		$manual       = get_option( self::OPTION_REPOSITORIES, array() );

		if ( is_array( $manual ) ) {
			foreach ( $manual as $repository ) {
				$repository = $this->normalize( $repository, 'manual' );
				if ( $repository ) {
					$repositories[ $repository['id'] ] = $repository;
				}
			}
		}

		foreach ( $this->discover_installed() as $repository ) {
			if ( ! isset( $repositories[ $repository['id'] ] ) ) {
				$repositories[ $repository['id'] ] = $repository;
			}
		}

		$disabled = get_option( self::OPTION_DISABLED, array() );
		$disabled = is_array( $disabled ) ? array_map( 'strtolower', $disabled ) : array();

		foreach ( $repositories as $id => &$repository ) {
			$repository['enabled'] = ! in_array( strtolower( $id ), $disabled, true );
		}
		unset( $repository );

		ksort( $repositories );

		/**
		 * Filters the complete trusted repository registry.
		 *
		 * @param array<string,array<string,mixed>> $repositories Repositories keyed by owner/name.
		 */
		return apply_filters( 'modern_catholic_updates_repositories', $repositories );
	}

	/**
	 * Return a single repository.
	 *
	 * @param string $id Repository owner/name.
	 * @return array<string,mixed>|null
	 */
	public function get( $id ) {
		$id   = strtolower( trim( (string) $id ) );
		$all  = $this->all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Add or replace a manually managed repository.
	 *
	 * @param array<string,mixed> $repository Repository data.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function save( $repository ) {
		$repository = $this->normalize( $repository, 'manual' );
		if ( ! $repository ) {
			return new \WP_Error( 'invalid_repository', __( 'Enter a valid GitHub owner/repository and package slug.', 'modern-catholic-plugin-update-manager' ) );
		}

		$manual = get_option( self::OPTION_REPOSITORIES, array() );
		$manual = is_array( $manual ) ? $manual : array();
		$manual[ $repository['id'] ] = $repository;
		update_option( self::OPTION_REPOSITORIES, $manual, false );

		return $repository;
	}

	/**
	 * Remove a manually managed repository.
	 *
	 * @param string $id Repository owner/name.
	 * @return bool
	 */
	public function remove( $id ) {
		$id     = strtolower( trim( (string) $id ) );
		$manual = get_option( self::OPTION_REPOSITORIES, array() );
		$manual = is_array( $manual ) ? $manual : array();

		if ( ! isset( $manual[ $id ] ) ) {
			return false;
		}

		unset( $manual[ $id ] );
		update_option( self::OPTION_REPOSITORIES, $manual, false );
		return true;
	}

	/**
	 * Enable or disable a repository.
	 *
	 * @param string $id      Repository owner/name.
	 * @param bool   $enabled Desired state.
	 * @return void
	 */
	public function set_enabled( $id, $enabled ) {
		$id       = strtolower( trim( (string) $id ) );
		$disabled = get_option( self::OPTION_DISABLED, array() );
		$disabled = is_array( $disabled ) ? array_map( 'strtolower', $disabled ) : array();

		if ( $enabled ) {
			$disabled = array_values( array_diff( $disabled, array( $id ) ) );
		} elseif ( ! in_array( $id, $disabled, true ) ) {
			$disabled[] = $id;
		}

		update_option( self::OPTION_DISABLED, array_values( array_unique( $disabled ) ), false );
	}

	/**
	 * Built-in Modern Catholic packages.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function defaults() {
		$items = array(
			array( 'name' => 'Modern Catholic', 'repository' => 'twitchd8/modern-catholic-theme', 'type' => 'theme', 'slug' => 'modern-catholic-theme' ),
			array( 'name' => 'Modern Catholic – Parish Alerts', 'repository' => 'twitchd8/modern-catholic-plugin-parish-alerts', 'type' => 'plugin', 'slug' => 'modern-catholic-plugin-parish-alerts', 'entrypoint' => 'parish-alerts.php' ),
			array( 'name' => 'Modern Catholic – Parish Bulletins', 'repository' => 'twitchd8/modern-catholic-plugin-parish-bulletins', 'type' => 'plugin', 'slug' => 'modern-catholic-plugin-parish-bulletins', 'entrypoint' => 'parish-bulletins.php' ),
			array( 'name' => 'Modern Catholic – Parish Events', 'repository' => 'twitchd8/modern-catholic-plugin-parish-events', 'type' => 'plugin', 'slug' => 'modern-catholic-plugin-parish-events', 'entrypoint' => 'parishpress-events.php' ),
			array( 'name' => 'Modern Catholic – Parish Homilies', 'repository' => 'twitchd8/modern-catholic-plugin-parish-homilies', 'type' => 'plugin', 'slug' => 'modern-catholic-plugin-parish-homilies', 'entrypoint' => 'parishpress-homilies.php' ),
			array( 'name' => 'Modern Catholic – Today’s Readings', 'repository' => 'twitchd8/modern-catholic-plugin-todays-readings', 'type' => 'plugin', 'slug' => 'modern-catholic-plugin-todays-readings', 'entrypoint' => 'usccb-todays-readings.php' ),
			array( 'name' => 'Modern Catholic – Editorial Sections', 'repository' => 'twitchd8/modern-catholic-plugin-editorial-sections', 'type' => 'plugin', 'slug' => 'modern-catholic-plugin-editorial-sections', 'entrypoint' => 'editorial-sections.php' ),
			array( 'name' => 'Modern Catholic – Update Manager', 'repository' => 'twitchd8/modern-catholic-plugin-update-manager', 'type' => 'plugin', 'slug' => 'modern-catholic-plugin-update-manager', 'entrypoint' => 'modern-catholic-update-manager.php' ),
		);

		$repositories = array();
		foreach ( $items as $item ) {
			$item = $this->normalize( $item, 'default' );
			if ( $item ) {
				$repositories[ $item['id'] ] = $item;
			}
		}

		return $repositories;
	}

	/**
	 * Discover installed packages that explicitly reference a trusted GitHub owner.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function discover_installed() {
		$found  = array();
		$owners = get_option( self::OPTION_OWNERS, array( 'twitchd8' ) );
		$owners = is_array( $owners ) ? array_map( 'strtolower', $owners ) : array( 'twitchd8' );
		$owners = apply_filters( 'modern_catholic_updates_trusted_owners', $owners );

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $plugin_file => $data ) {
			$path    = WP_PLUGIN_DIR . '/' . $plugin_file;
			$headers = get_file_data(
				$path,
				array(
					'github' => 'GitHub Plugin URI',
					'update' => 'Update URI',
				)
			);
			$url     = ! empty( $headers['github'] ) ? $headers['github'] : ( ! empty( $data['PluginURI'] ) ? $data['PluginURI'] : $headers['update'] );
			$repo    = self::parse_repository( $url );

			if ( ! $repo || ! in_array( strtolower( $repo['owner'] ), $owners, true ) ) {
				continue;
			}

			$slug  = dirname( $plugin_file );
			$slug  = '.' === $slug ? basename( $plugin_file, '.php' ) : $slug;
			$item  = $this->normalize(
				array(
					'name'       => $data['Name'],
					'repository' => $repo['id'],
					'type'       => 'plugin',
					'slug'       => $slug,
					'entrypoint' => basename( $plugin_file ),
				),
				'discovered'
			);
			if ( $item ) {
				$found[] = $item;
			}
		}

		foreach ( wp_get_themes() as $slug => $theme ) {
			$style   = $theme->get_stylesheet_directory() . '/style.css';
			$headers = is_readable( $style ) ? get_file_data( $style, array( 'github' => 'GitHub Theme URI', 'update' => 'Update URI' ) ) : array();
			$url     = ! empty( $headers['github'] ) ? $headers['github'] : ( $theme->get( 'ThemeURI' ) ? $theme->get( 'ThemeURI' ) : ( isset( $headers['update'] ) ? $headers['update'] : '' ) );
			$repo    = self::parse_repository( $url );

			if ( ! $repo || ! in_array( strtolower( $repo['owner'] ), $owners, true ) ) {
				continue;
			}

			$item = $this->normalize(
				array(
					'name'       => $theme->get( 'Name' ),
					'repository' => $repo['id'],
					'type'       => 'theme',
					'slug'       => $slug,
				),
				'discovered'
			);
			if ( $item ) {
				$found[] = $item;
			}
		}

		return $found;
	}

	/**
	 * Normalize repository data.
	 *
	 * @param array<string,mixed> $repository Repository data.
	 * @param string              $source     Registry source.
	 * @return array<string,mixed>|null
	 */
	private function normalize( $repository, $source ) {
		if ( ! is_array( $repository ) ) {
			return null;
		}

		$repository_value = isset( $repository['repository'] ) ? $repository['repository'] : ( isset( $repository['id'] ) ? $repository['id'] : ( isset( $repository['repository_url'] ) ? $repository['repository_url'] : '' ) );
		$parsed = self::parse_repository( $repository_value );
		$type   = isset( $repository['type'] ) && 'theme' === $repository['type'] ? 'theme' : 'plugin';
		$slug   = isset( $repository['slug'] ) ? sanitize_title( $repository['slug'] ) : '';

		if ( ! $parsed || ! $slug ) {
			return null;
		}

		$entrypoint = isset( $repository['entrypoint'] ) ? sanitize_file_name( $repository['entrypoint'] ) : '';
		$template   = isset( $repository['asset_template'] ) ? sanitize_file_name( $repository['asset_template'] ) : '{slug}-{version}.zip';
		if ( false === strpos( $template, '{version}' ) || false === strpos( $template, '.zip' ) ) {
			$template = '{slug}-{version}.zip';
		}

		return array(
			'id'             => $parsed['id'],
			'owner'          => $parsed['owner'],
			'repo'           => $parsed['repo'],
			'repository_url' => 'https://github.com/' . $parsed['id'],
			'name'           => isset( $repository['name'] ) && $repository['name'] ? sanitize_text_field( $repository['name'] ) : $slug,
			'type'           => $type,
			'slug'           => $slug,
			'entrypoint'     => $entrypoint,
			'asset_template' => $template,
			'source'         => $source,
			'enabled'        => true,
		);
	}

	/**
	 * Parse a GitHub repository URL or owner/repository value.
	 *
	 * @param string $value Repository value.
	 * @return array{id:string,owner:string,repo:string}|null
	 */
	public static function parse_repository( $value ) {
		$value = trim( (string) $value );
		$value = preg_replace( '#^git@github\.com:#i', '', $value );
		$value = preg_replace( '#^https?://github\.com/#i', '', $value );
		$value = preg_replace( '#\.git/?$#i', '', $value );
		$value = trim( $value, '/' );

		if ( ! preg_match( '#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$#', $value, $matches ) ) {
			return null;
		}

		$owner = $matches[1];
		$repo  = $matches[2];
		return array(
			'id'    => strtolower( $owner . '/' . $repo ),
			'owner' => $owner,
			'repo'  => $repo,
		);
	}
}
