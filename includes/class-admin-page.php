<?php
/**
 * Administration screen.
 *
 * @package ModernCatholicUpdateManager
 */

namespace PowerHouse\ModernCatholic\UpdateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Page {
	/** @var Repository_Registry */
	private $registry;
	/** @var GitHub_Client */
	private $github;
	/** @var Update_Manager */
	private $manager;
	/** @var Credential_File */
	private $credentials;

	public function __construct( Repository_Registry $registry, GitHub_Client $github, Update_Manager $manager, Credential_File $credentials ) {
		$this->registry = $registry;
		$this->github   = $github;
		$this->manager  = $manager;
		$this->credentials = $credentials;
	}

	/** Register hooks. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'update_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MODERN_CATHOLIC_UPDATE_MANAGER_FILE ), array( $this, 'plugin_action_links' ) );
		add_action( 'admin_post_modern_catholic_updates_check', array( $this, 'handle_check' ) );
		add_action( 'admin_post_modern_catholic_updates_save_repository', array( $this, 'handle_save_repository' ) );
		add_action( 'admin_post_modern_catholic_updates_toggle_repository', array( $this, 'handle_toggle_repository' ) );
		add_action( 'admin_post_modern_catholic_updates_remove_repository', array( $this, 'handle_remove_repository' ) );
		add_action( 'admin_post_modern_catholic_updates_install', array( $this, 'handle_install' ) );
		add_action( 'admin_post_modern_catholic_updates_save_token', array( $this, 'handle_save_token' ) );
		add_action( 'admin_post_modern_catholic_updates_remove_token', array( $this, 'handle_remove_token' ) );
		add_action( 'admin_post_modern_catholic_updates_discover', array( $this, 'handle_discover' ) );
		add_action( 'admin_post_modern_catholic_updates_catalog_add', array( $this, 'handle_catalog_add' ) );
		add_action( 'admin_post_modern_catholic_updates_catalog_install', array( $this, 'handle_catalog_install' ) );
	}

	/** Add the management page beneath Plugins. */
	public function menu() {
		add_plugins_page(
			__( 'Modern Catholic Updates', 'modern-catholic-plugin-update-manager' ),
			__( 'Modern Catholic Updates', 'modern-catholic-plugin-update-manager' ),
			'update_plugins',
			'modern-catholic-updates',
			array( $this, 'render' )
		);
	}

	/** Load page styles. */
	public function assets( $hook ) {
		if ( 'plugins_page_modern-catholic-updates' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'modern-catholic-update-manager', MODERN_CATHOLIC_UPDATE_MANAGER_URL . 'assets/admin.css', array(), MODERN_CATHOLIC_UPDATE_MANAGER_VERSION );
	}

	/** Add a direct management link to the Plugins screen. */
	public function plugin_action_links( $links ) {
		$manage = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'plugins.php?page=modern-catholic-updates' ) ),
			esc_html__( 'Manage updates', 'modern-catholic-plugin-update-manager' )
		);
		array_unshift( $links, $manage );
		return $links;
	}

	/** Render update notification. */
	public function update_notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$results = get_option( Update_Manager::OPTION_RESULTS, array() );
		$count   = is_array( $results ) && isset( $results['updates'] ) ? absint( $results['updates'] ) : 0;
		if ( ! $count ) {
			return;
		}
		$url = admin_url( 'plugins.php?page=modern-catholic-updates' );
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			wp_kses_post( sprintf( _n( '%1$d Modern Catholic update is available. <a href="%2$s">Review updates</a>.', '%1$d Modern Catholic updates are available. <a href="%2$s">Review updates</a>.', $count, 'modern-catholic-plugin-update-manager' ), $count, esc_url( $url ) ) )
		);
	}

	/** Render the management screen. */
	public function render() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage updates.', 'modern-catholic-plugin-update-manager' ) );
		}

		$view = isset( $_GET['mc_updates_view'] ) ? sanitize_key( wp_unslash( $_GET['mc_updates_view'] ) ) : '';
		if ( 'install' === $view ) {
			$this->render_installer();
			return;
		}

		$results  = $this->manager->scan( false );
		$add_mode = 'add' === $view;
		$catalog  = $add_mode ? $this->github->discover_catalog( false ) : null;
		$message  = isset( $_GET['mc_updates_message'] ) ? sanitize_key( wp_unslash( $_GET['mc_updates_message'] ) ) : '';
		?>
		<div class="wrap modern-catholic-updates">
			<h1><?php esc_html_e( 'Modern Catholic Updates', 'modern-catholic-plugin-update-manager' ); ?></h1>
			<p><?php esc_html_e( 'Stable, non-prerelease GitHub Releases are matched to exact versioned ZIP assets. Git working copies are reported but protected from replacement.', 'modern-catholic-plugin-update-manager' ); ?></p>
			<?php $this->render_message( $message ); ?>
			<div class="mc-updates-summary">
				<strong><?php echo esc_html( sprintf( __( 'Last checked: %s', 'modern-catholic-plugin-update-manager' ), wp_date( 'M j, Y g:i a', $results['checked_at'] ) ) ); ?></strong>
				<span><?php echo $this->github->has_token() ? esc_html__( 'Private repository access is configured.', 'modern-catholic-plugin-update-manager' ) : esc_html__( 'Public repositories only; no GitHub token is configured.', 'modern-catholic-plugin-update-manager' ); ?></span>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="modern_catholic_updates_check">
					<?php wp_nonce_field( 'modern_catholic_updates_check' ); ?>
					<?php submit_button( __( 'Check now', 'modern-catholic-plugin-update-manager' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
			<?php $this->render_credentials(); ?>
			<?php if ( $add_mode ) : ?>
				<?php $this->render_catalog( $catalog ); ?>
			<?php else : ?>
				<?php $this->render_modules( $results ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Force a repository scan. */
	public function handle_check() {
		$this->authorize( 'modern_catholic_updates_check' );
		$this->manager->scan( true );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		$this->redirect( 'checked' );
	}

	/** Validate and save a private-repository token to the ignored file. */
	public function handle_save_token() {
		$this->authorize( 'modern_catholic_updates_save_token', 'manage_options' );
		$token = isset( $_POST['github_token'] ) ? trim( (string) wp_unslash( $_POST['github_token'] ) ) : '';
		if ( ! $this->credentials->is_valid( $token ) ) {
			$this->redirect( 'token_invalid' );
		}
		$validated = $this->github->validate_token( $token );
		if ( is_wp_error( $validated ) ) {
			$this->redirect( 'token_invalid' );
		}
		$saved = $this->credentials->write( $token );
		if ( is_wp_error( $saved ) ) {
			$this->redirect( 'token_write_failed' );
		}
		$this->manager->scan( true );
		$this->github->discover_catalog( true );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		$this->redirect( 'token_saved' );
	}

	/** Remove only the plugin-directory credential file. */
	public function handle_remove_token() {
		$this->authorize( 'modern_catholic_updates_remove_token', 'manage_options' );
		if ( ! $this->credentials->remove() ) {
			$this->redirect( 'token_write_failed' );
		}
		$this->manager->scan( true );
		$this->github->discover_catalog( true );
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'update_themes' );
		$this->redirect( 'token_removed' );
	}

	/** Refresh the trusted GitHub repository catalog. */
	public function handle_discover() {
		$this->authorize( 'modern_catholic_updates_discover' );
		$catalog = $this->github->discover_catalog( true );
		$this->redirect( is_wp_error( $catalog ) ? 'catalog_failed' : 'catalog_refreshed', array( 'mc_updates_view' => 'add' ) );
	}

	/** Add one server-discovered catalog repository to the managed registry. */
	public function handle_catalog_add() {
		$this->authorize( 'modern_catholic_updates_catalog_add' );
		$repository = $this->catalog_repository_from_request();
		if ( is_wp_error( $repository ) ) {
			$this->redirect( 'catalog_repository_invalid' );
		}
		$result = $this->registry->save( $repository );
		$this->redirect( is_wp_error( $result ) ? 'catalog_repository_invalid' : 'catalog_repository_added' );
	}

	/** Add and install one server-discovered catalog repository. */
	public function handle_catalog_install() {
		$this->authorize( 'modern_catholic_updates_catalog_install' );
		$repository = $this->catalog_repository_from_request();
		if ( is_wp_error( $repository ) || empty( $repository['release'] ) ) {
			$this->redirect( 'catalog_repository_invalid' );
		}
		$install_capability = 'theme' === $repository['type'] ? 'install_themes' : 'install_plugins';
		if ( ! current_user_can( $install_capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'modern-catholic-plugin-update-manager' ) );
		}
		$saved = $this->registry->save( $repository );
		if ( is_wp_error( $saved ) ) {
			$this->redirect( 'catalog_repository_invalid' );
		}
		$this->redirect_to_installer( $repository['id'] );
	}

	/** Save a manually trusted repository. */
	public function handle_save_repository() {
		$this->authorize( 'modern_catholic_updates_save_repository' );
		$result = $this->registry->save(
			array(
				'name'           => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
				'repository'     => isset( $_POST['repository'] ) ? wp_unslash( $_POST['repository'] ) : '',
				'type'           => isset( $_POST['type'] ) ? wp_unslash( $_POST['type'] ) : 'plugin',
				'slug'           => isset( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '',
				'entrypoint'     => isset( $_POST['entrypoint'] ) ? wp_unslash( $_POST['entrypoint'] ) : '',
				'asset_template' => isset( $_POST['asset_template'] ) ? wp_unslash( $_POST['asset_template'] ) : '{slug}-{version}.zip',
			)
		);
		$this->redirect( is_wp_error( $result ) ? 'invalid_repository' : 'repository_saved' );
	}

	/** Enable or disable a repository. */
	public function handle_toggle_repository() {
		$this->authorize( 'modern_catholic_updates_toggle_repository' );
		$id      = isset( $_POST['repository'] ) ? sanitize_text_field( wp_unslash( $_POST['repository'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];
		$this->registry->set_enabled( $id, $enabled );
		$this->redirect( $enabled ? 'repository_enabled' : 'repository_disabled' );
	}

	/** Remove a repository from the managed module list. */
	public function handle_remove_repository() {
		$this->authorize( 'modern_catholic_updates_remove_repository' );
		$id = isset( $_POST['repository'] ) ? sanitize_text_field( wp_unslash( $_POST['repository'] ) ) : '';
		$this->registry->remove( $id );
		$this->redirect( 'repository_removed' );
	}

	/** Install a registered package's latest verified release. */
	public function handle_install() {
		check_admin_referer( 'modern_catholic_updates_install' );
		$id         = isset( $_POST['repository'] ) ? sanitize_text_field( wp_unslash( $_POST['repository'] ) ) : '';
		$repository = $this->registry->get( $id );
		if ( ! $repository || ! $repository['enabled'] ) {
			$this->redirect( 'invalid_repository' );
		}
		$install_capability = 'theme' === $repository['type'] ? 'install_themes' : 'install_plugins';
		if ( ! current_user_can( $install_capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'modern-catholic-plugin-update-manager' ) );
		}
		$this->redirect_to_installer( $repository['id'] );
	}

	/** Redirect an authorized request into WordPress's interactive installer screen. */
	private function redirect_to_installer( $repository_id ) {
		$action = $this->installer_nonce_action( $repository_id );
		$url    = add_query_arg(
			array(
				'page'            => 'modern-catholic-updates',
				'mc_updates_view' => 'install',
				'repository'      => $repository_id,
			),
			admin_url( 'plugins.php' )
		);
		wp_safe_redirect( wp_nonce_url( $url, $action ) );
		exit;
	}

	/** Run one trusted release through WordPress's normal interactive installer UI. */
	private function render_installer() {
		$id         = isset( $_GET['repository'] ) ? sanitize_text_field( wp_unslash( $_GET['repository'] ) ) : '';
		$repository = $this->registry->get( $id );
		if ( ! $repository || ! $repository['enabled'] ) {
			$this->render_installer_error( __( 'That repository is not registered or is not enabled.', 'modern-catholic-plugin-update-manager' ) );
			return;
		}

		check_admin_referer( $this->installer_nonce_action( $repository['id'] ) );
		$install_capability = 'theme' === $repository['type'] ? 'install_themes' : 'install_plugins';
		if ( ! current_user_can( $install_capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'modern-catholic-plugin-update-manager' ) );
		}

		$state = $this->manager->component_state( $repository );
		if ( $state['installed'] || $state['development'] ) {
			$this->render_installer_error( __( 'That component is already installed or is a protected Git checkout.', 'modern-catholic-plugin-update-manager' ) );
			return;
		}
		$release = $this->github->latest_release( $repository, true );
		if ( is_wp_error( $release ) ) {
			$this->render_installer_error( $release->get_error_message() );
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$url       = add_query_arg(
			array(
				'page'            => 'modern-catholic-updates',
				'mc_updates_view' => 'install',
				'repository'      => $repository['id'],
			),
			admin_url( 'plugins.php' )
		);
		$skin_args = array(
			'type'  => 'web',
			'url'   => $url,
			'nonce' => $this->installer_nonce_action( $repository['id'] ),
			'title' => sprintf( __( 'Installing %s', 'modern-catholic-plugin-update-manager' ), $repository['name'] ),
			'api'   => (object) array(
				'name'    => $repository['name'],
				'version' => $release['version'],
			),
		);
		if ( 'theme' === $repository['type'] ) {
			$skin_args['theme'] = $repository['slug'];
			$skin               = new \Theme_Installer_Skin( $skin_args );
			$upgrader           = new \Theme_Upgrader( $skin );
		} else {
			$skin_args['plugin'] = $repository['slug'];
			$skin                = new \Plugin_Installer_Skin( $skin_args );
			$upgrader            = new \Plugin_Upgrader( $skin );
		}
		$upgrader->install( $release['package'] );
		printf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'plugins.php?page=modern-catholic-updates' ) ),
			esc_html__( 'Back to Modern Catholic Updates', 'modern-catholic-plugin-update-manager' )
		);
	}

	/** Render an installer-specific error without hiding its actual cause. */
	private function render_installer_error( $message ) {
		?>
		<div class="wrap modern-catholic-updates">
			<h1><?php esc_html_e( 'Modern Catholic Updates', 'modern-catholic-plugin-update-manager' ); ?></h1>
			<div class="notice notice-error inline"><p><?php echo esc_html( $message ); ?></p></div>
			<p><a href="<?php echo esc_url( admin_url( 'plugins.php?page=modern-catholic-updates' ) ); ?>"><?php esc_html_e( 'Back to modules', 'modern-catholic-plugin-update-manager' ); ?></a></p>
		</div>
		<?php
	}

	/** Return the per-repository nonce action for interactive installer requests. */
	private function installer_nonce_action( $repository_id ) {
		return 'modern_catholic_updates_install_package_' . $repository_id;
	}

	/** Render a status cell. */
	private function render_status( $item ) {
		$labels = array(
			'disabled'            => __( 'Monitoring disabled', 'modern-catholic-plugin-update-manager' ),
			'current'             => __( 'Current', 'modern-catholic-plugin-update-manager' ),
			'update_available'    => __( 'Update available', 'modern-catholic-plugin-update-manager' ),
			'not_installed'       => __( 'Ready to install', 'modern-catholic-plugin-update-manager' ),
			'development_update'  => __( 'New release; Git checkout protected', 'modern-catholic-plugin-update-manager' ),
			'development_current' => __( 'Git checkout protected', 'modern-catholic-plugin-update-manager' ),
			'release_not_found'   => __( 'No published release', 'modern-catholic-plugin-update-manager' ),
			'missing_release_asset' => __( 'Release asset missing', 'modern-catholic-plugin-update-manager' ),
		);
		$status = isset( $labels[ $item['status'] ] ) ? $labels[ $item['status'] ] : __( 'Check failed', 'modern-catholic-plugin-update-manager' );
		echo '<span class="mc-status mc-status-' . esc_attr( $item['status'] ) . '">' . esc_html( $status ) . '</span>';
		if ( ! empty( $item['error'] ) ) {
			echo '<br><small>' . esc_html( $item['error'] ) . '</small>';
		}
	}

	/** Render private GitHub credential controls without revealing the token. */
	private function render_credentials() {
		$source_labels = array(
			'wp-config.php'    => __( 'wp-config.php constant', 'modern-catholic-plugin-update-manager' ),
			'environment'      => __( 'server environment variable', 'modern-catholic-plugin-update-manager' ),
			'credential_file'  => __( 'plugin credential file', 'modern-catholic-plugin-update-manager' ),
			'filter'           => __( 'secure integration filter', 'modern-catholic-plugin-update-manager' ),
		);
		$source       = $this->github->token_source();
		$source_label = isset( $source_labels[ $source ] ) ? $source_labels[ $source ] : __( 'not configured', 'modern-catholic-plugin-update-manager' );
		?>
		<section class="mc-credentials">
			<h2><?php esc_html_e( 'Private GitHub access', 'modern-catholic-plugin-update-manager' ); ?></h2>
			<p><strong><?php esc_html_e( 'Active credential source:', 'modern-catholic-plugin-update-manager' ); ?></strong> <?php echo esc_html( $source_label ); ?></p>
			<p><?php esc_html_e( 'Use a fine-grained personal access token limited to the required repositories with Contents set to Read-only. A saved token is written to the hidden .github-token.php file inside this plugin, never to WordPress options, and its value is never displayed again.', 'modern-catholic-plugin-update-manager' ); ?></p>
			<p><a href="https://github.com/settings/personal-access-tokens/new" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create a fine-grained token on GitHub', 'modern-catholic-plugin-update-manager' ); ?></a></p>
			<?php if ( current_user_can( 'manage_options' ) && $this->credentials->is_writable() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mc-token-form">
					<input type="hidden" name="action" value="modern_catholic_updates_save_token">
					<?php wp_nonce_field( 'modern_catholic_updates_save_token' ); ?>
					<label for="modern-catholic-github-token"><strong><?php esc_html_e( 'GitHub token', 'modern-catholic-plugin-update-manager' ); ?></strong></label>
					<input id="modern-catholic-github-token" name="github_token" type="password" required autocomplete="new-password" spellcheck="false" placeholder="github_pat_…">
					<?php submit_button( $this->credentials->exists() ? __( 'Replace token file', 'modern-catholic-plugin-update-manager' ) : __( 'Save token file', 'modern-catholic-plugin-update-manager' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( $this->credentials->exists() ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mc-token-remove-form">
						<input type="hidden" name="action" value="modern_catholic_updates_remove_token">
						<?php wp_nonce_field( 'modern_catholic_updates_remove_token' ); ?>
						<?php submit_button( __( 'Remove token file', 'modern-catholic-plugin-update-manager' ), 'delete', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			<?php elseif ( current_user_can( 'manage_options' ) ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'The plugin directory is not writable, so the token file cannot be saved from WordPress.', 'modern-catholic-plugin-update-manager' ); ?></p></div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'The file is ignored by Git and excluded from release ZIPs. The updater restores it after a normal self-update; keep a secure copy because a manual folder replacement can remove it.', 'modern-catholic-plugin-update-manager' ); ?></p>
		</section>
		<?php
	}

	/** Render the single managed module list. */
	private function render_modules( $results ) {
		?>
		<section class="mc-modules">
			<div class="mc-section-heading">
				<div>
					<h2><?php esc_html_e( 'Modules', 'modern-catholic-plugin-update-manager' ); ?></h2>
					<p><?php esc_html_e( 'Only modules added to the manager appear here. Removing a module stops monitoring it; the installed plugin or theme is not deleted.', 'modern-catholic-plugin-update-manager' ); ?></p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'mc_updates_view', 'add', admin_url( 'plugins.php?page=modern-catholic-updates' ) ) ); ?>"><?php esc_html_e( 'Add module', 'modern-catholic-plugin-update-manager' ); ?></a>
			</div>
			<?php if ( empty( $results['items'] ) ) : ?>
				<p class="mc-empty-state"><?php esc_html_e( 'No modules are being managed yet. Select Add module to choose one.', 'modern-catholic-plugin-update-manager' ); ?></p>
			<?php else : ?>
				<table class="widefat striped mc-updates-table">
					<thead><tr>
						<th><?php esc_html_e( 'Module', 'modern-catholic-plugin-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Installed', 'modern-catholic-plugin-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Latest', 'modern-catholic-plugin-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Status', 'modern-catholic-plugin-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'modern-catholic-plugin-update-manager' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $results['items'] as $item ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $item['name'] ); ?></strong> <span class="mc-type"><?php echo esc_html( $item['type'] ); ?></span><br><a href="<?php echo esc_url( $item['repository_url'] ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $item['id'] ); ?></code></a></td>
							<td><?php echo $item['installed'] ? esc_html( $item['installed_version'] ) : '&mdash;'; ?></td>
							<td><?php echo isset( $item['release']['version'] ) ? esc_html( $item['release']['version'] ) : '&mdash;'; ?></td>
							<td><?php $this->render_status( $item ); ?></td>
							<td><?php $this->render_actions( $item ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/** Render repositories discovered from the trusted GitHub catalog. */
	private function render_catalog( $catalog ) {
		$managed = $this->registry->all();
		$available = ! is_wp_error( $catalog ) && ! empty( $catalog['items'] ) ? array_diff_key( $catalog['items'], $managed ) : array();
		?>
		<section class="mc-catalog">
			<div class="mc-section-heading">
				<div>
					<h2><?php esc_html_e( 'Add module', 'modern-catholic-plugin-update-manager' ); ?></h2>
					<p><?php esc_html_e( 'Available modules are discovered from trusted Modern Catholic repositories visible to GitHub.', 'modern-catholic-plugin-update-manager' ); ?></p>
				</div>
				<a class="button" href="<?php echo esc_url( admin_url( 'plugins.php?page=modern-catholic-updates' ) ); ?>"><?php esc_html_e( 'Back to modules', 'modern-catholic-plugin-update-manager' ); ?></a>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mc-discover-form">
				<input type="hidden" name="action" value="modern_catholic_updates_discover">
				<?php wp_nonce_field( 'modern_catholic_updates_discover' ); ?>
				<?php submit_button( __( 'Refresh available modules', 'modern-catholic-plugin-update-manager' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( is_wp_error( $catalog ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $catalog->get_error_message() ); ?></p></div>
			<?php elseif ( empty( $available ) ) : ?>
				<p class="mc-empty-state"><?php esc_html_e( 'There are no additional modules available to add.', 'modern-catholic-plugin-update-manager' ); ?></p>
			<?php else : ?>
				<table class="widefat striped mc-catalog-table">
					<thead><tr>
						<th><?php esc_html_e( 'Available module', 'modern-catholic-plugin-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Type', 'modern-catholic-plugin-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Latest release', 'modern-catholic-plugin-update-manager' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'modern-catholic-plugin-update-manager' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $available as $item ) : ?>
						<?php
						$state              = $this->manager->component_state( $item );
						$install_capability = 'theme' === $item['type'] ? 'install_themes' : 'install_plugins';
						?>
						<tr>
							<td><strong><?php echo esc_html( $item['name'] ); ?></strong><br><a href="<?php echo esc_url( $item['repository_url'] ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $item['id'] ); ?></code></a><?php if ( $item['description'] ) : ?><br><small><?php echo esc_html( $item['description'] ); ?></small><?php endif; ?></td>
							<td><?php echo esc_html( ucfirst( $item['type'] ) ); ?></td>
							<td>
								<?php if ( ! empty( $item['release']['version'] ) ) : ?>
									<?php echo esc_html( $item['release']['version'] ); ?><br><small><?php echo esc_html( $item['release']['asset_name'] ); ?></small>
								<?php else : ?>
									&mdash;<br><small><?php echo esc_html( isset( $item['release_error']['message'] ) ? $item['release_error']['message'] : __( 'No installable release.', 'modern-catholic-plugin-update-manager' ) ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php $this->action_form( 'modern_catholic_updates_catalog_add', $item['id'], __( 'Add module', 'modern-catholic-plugin-update-manager' ), $state['installed'] || empty( $item['release'] ) ); ?>
								<?php if ( ! $state['installed'] && ! empty( $item['release'] ) && current_user_can( $install_capability ) ) : ?>
									<?php $this->action_form( 'modern_catholic_updates_catalog_install', $item['id'], __( 'Add and install', 'modern-catholic-plugin-update-manager' ), true ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<details class="mc-custom-repository">
				<summary><?php esc_html_e( 'Add a repository not shown above', 'modern-catholic-plugin-update-manager' ); ?></summary>
				<p><?php esc_html_e( 'Use this only for a trusted GitHub repository that does not follow the automatic Modern Catholic discovery convention.', 'modern-catholic-plugin-update-manager' ); ?></p>
				<?php $this->render_repository_form(); ?>
			</details>
		</section>
		<?php
	}

	/** Render the advanced custom repository form. */
	private function render_repository_form() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mc-repository-form">
			<input type="hidden" name="action" value="modern_catholic_updates_save_repository">
			<?php wp_nonce_field( 'modern_catholic_updates_save_repository' ); ?>
			<label><?php esc_html_e( 'Display name', 'modern-catholic-plugin-update-manager' ); ?><input required type="text" name="name" placeholder="Modern Catholic – New Module"></label>
			<label><?php esc_html_e( 'GitHub owner/repository', 'modern-catholic-plugin-update-manager' ); ?><input required type="text" name="repository" placeholder="twitchd8/modern-catholic-plugin-example"></label>
			<label><?php esc_html_e( 'Package type', 'modern-catholic-plugin-update-manager' ); ?><select name="type"><option value="plugin"><?php esc_html_e( 'Plugin', 'modern-catholic-plugin-update-manager' ); ?></option><option value="theme"><?php esc_html_e( 'Theme', 'modern-catholic-plugin-update-manager' ); ?></option></select></label>
			<label><?php esc_html_e( 'Installed directory slug', 'modern-catholic-plugin-update-manager' ); ?><input required type="text" name="slug" placeholder="modern-catholic-plugin-example"></label>
			<label><?php esc_html_e( 'Plugin entrypoint (plugins only)', 'modern-catholic-plugin-update-manager' ); ?><input type="text" name="entrypoint" placeholder="example.php"></label>
			<label><?php esc_html_e( 'Release asset template', 'modern-catholic-plugin-update-manager' ); ?><input required type="text" name="asset_template" value="{slug}-{version}.zip"></label>
			<?php submit_button( __( 'Add module', 'modern-catholic-plugin-update-manager' ) ); ?>
		</form>
		<?php
	}

	/** Resolve catalog input from a fresh server-side trusted catalog. */
	private function catalog_repository_from_request() {
		$id = isset( $_POST['repository'] ) ? sanitize_text_field( wp_unslash( $_POST['repository'] ) ) : '';
		return $this->github->catalog_item( $id, true );
	}

	/** Render row actions. */
	private function render_actions( $item ) {
		$install_capability = 'theme' === $item['type'] ? 'install_themes' : 'install_plugins';
		if ( 'update_available' === $item['status'] ) {
			echo '<a class="button button-primary" href="' . esc_url( network_admin_url( 'update-core.php' ) ) . '">' . esc_html__( 'Open Updates', 'modern-catholic-plugin-update-manager' ) . '</a> ';
		} elseif ( 'not_installed' === $item['status'] && current_user_can( $install_capability ) ) {
			$this->action_form( 'modern_catholic_updates_install', $item['id'], __( 'Install latest', 'modern-catholic-plugin-update-manager' ), true );
		}

		$this->action_form( 'modern_catholic_updates_remove_repository', $item['id'], __( 'Remove', 'modern-catholic-plugin-update-manager' ) );
	}

	/** Render a compact action form. */
	private function action_form( $action, $repository, $label, $primary = false, $extra = array() ) {
		?>
		<form class="mc-inline-action" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="repository" value="<?php echo esc_attr( $repository ); ?>">
			<?php foreach ( $extra as $key => $value ) : ?><input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php endforeach; ?>
			<?php wp_nonce_field( $action ); ?>
			<button class="button <?php echo $primary ? 'button-primary' : ''; ?>" type="submit"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/** Authorization helper. */
	private function authorize( $action, $capability = 'update_plugins' ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'modern-catholic-plugin-update-manager' ) );
		}
		check_admin_referer( $action );
	}

	/** Redirect to the management page. */
	private function redirect( $message, $args = array() ) {
		$args['mc_updates_message'] = sanitize_key( $message );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'plugins.php?page=modern-catholic-updates' ) ) );
		exit;
	}

	/** Render a result notice. */
	private function render_message( $message ) {
		$messages = array(
			'checked'             => array( 'success', __( 'GitHub releases checked.', 'modern-catholic-plugin-update-manager' ) ),
			'repository_saved'    => array( 'success', __( 'Repository added.', 'modern-catholic-plugin-update-manager' ) ),
			'repository_enabled'  => array( 'success', __( 'Repository monitoring enabled.', 'modern-catholic-plugin-update-manager' ) ),
			'repository_disabled' => array( 'warning', __( 'Repository monitoring disabled.', 'modern-catholic-plugin-update-manager' ) ),
			'repository_removed'  => array( 'success', __( 'Module removed from the manager.', 'modern-catholic-plugin-update-manager' ) ),
			'catalog_refreshed'   => array( 'success', __( 'The Modern Catholic GitHub catalog was refreshed.', 'modern-catholic-plugin-update-manager' ) ),
			'catalog_failed'      => array( 'error', __( 'GitHub catalog discovery failed. Verify the token and try again.', 'modern-catholic-plugin-update-manager' ) ),
			'catalog_repository_added' => array( 'success', __( 'The discovered repository was added to the update manager.', 'modern-catholic-plugin-update-manager' ) ),
			'catalog_repository_invalid' => array( 'error', __( 'That repository is not available in the trusted GitHub catalog.', 'modern-catholic-plugin-update-manager' ) ),
			'token_saved'         => array( 'success', __( 'Private GitHub access was verified and the token file was saved.', 'modern-catholic-plugin-update-manager' ) ),
			'token_removed'       => array( 'success', __( 'The plugin token file was removed.', 'modern-catholic-plugin-update-manager' ) ),
			'token_invalid'       => array( 'error', __( 'GitHub rejected the token or it cannot read the private Update Manager repository.', 'modern-catholic-plugin-update-manager' ) ),
			'token_write_failed'  => array( 'error', __( 'WordPress could not write or remove the plugin token file.', 'modern-catholic-plugin-update-manager' ) ),
			'invalid_repository'  => array( 'error', __( 'The repository definition is invalid.', 'modern-catholic-plugin-update-manager' ) ),
			'already_installed'   => array( 'warning', __( 'That component is already installed or is a protected Git checkout.', 'modern-catholic-plugin-update-manager' ) ),
		);
		if ( isset( $messages[ $message ] ) ) {
			printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $messages[ $message ][0] ), esc_html( $messages[ $message ][1] ) );
		}
	}
}
