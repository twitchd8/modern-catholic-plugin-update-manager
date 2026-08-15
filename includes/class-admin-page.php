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

	public function __construct( Repository_Registry $registry, GitHub_Client $github, Update_Manager $manager ) {
		$this->registry = $registry;
		$this->github   = $github;
		$this->manager  = $manager;
	}

	/** Register hooks. */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'update_notice' ) );
		add_action( 'admin_post_modern_catholic_updates_check', array( $this, 'handle_check' ) );
		add_action( 'admin_post_modern_catholic_updates_save_repository', array( $this, 'handle_save_repository' ) );
		add_action( 'admin_post_modern_catholic_updates_toggle_repository', array( $this, 'handle_toggle_repository' ) );
		add_action( 'admin_post_modern_catholic_updates_remove_repository', array( $this, 'handle_remove_repository' ) );
		add_action( 'admin_post_modern_catholic_updates_install', array( $this, 'handle_install' ) );
	}

	/** Add the Tools page. */
	public function menu() {
		add_management_page(
			__( 'Modern Catholic Updates', 'modern-catholic-plugin-update-manager' ),
			__( 'Modern Catholic Updates', 'modern-catholic-plugin-update-manager' ),
			'update_plugins',
			'modern-catholic-updates',
			array( $this, 'render' )
		);
	}

	/** Load page styles. */
	public function assets( $hook ) {
		if ( 'tools_page_modern-catholic-updates' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'modern-catholic-update-manager', MODERN_CATHOLIC_UPDATE_MANAGER_URL . 'assets/admin.css', array(), MODERN_CATHOLIC_UPDATE_MANAGER_VERSION );
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
		$url = admin_url( 'tools.php?page=modern-catholic-updates' );
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

		$results = $this->manager->scan( false );
		$message = isset( $_GET['mc_updates_message'] ) ? sanitize_key( wp_unslash( $_GET['mc_updates_message'] ) ) : '';
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

			<table class="widefat striped mc-updates-table">
				<thead><tr>
					<th><?php esc_html_e( 'Component', 'modern-catholic-plugin-update-manager' ); ?></th>
					<th><?php esc_html_e( 'Repository', 'modern-catholic-plugin-update-manager' ); ?></th>
					<th><?php esc_html_e( 'Installed', 'modern-catholic-plugin-update-manager' ); ?></th>
					<th><?php esc_html_e( 'Latest', 'modern-catholic-plugin-update-manager' ); ?></th>
					<th><?php esc_html_e( 'Status', 'modern-catholic-plugin-update-manager' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'modern-catholic-plugin-update-manager' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $results['items'] as $item ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $item['name'] ); ?></strong><br><code><?php echo esc_html( $item['slug'] ); ?></code> <span class="mc-type"><?php echo esc_html( $item['type'] ); ?></span></td>
						<td><a href="<?php echo esc_url( $item['repository_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['id'] ); ?></a><br><small><?php echo esc_html( $item['source'] ); ?></small></td>
						<td><?php echo $item['installed'] ? esc_html( $item['installed_version'] ) : '&mdash;'; ?></td>
						<td><?php echo isset( $item['release']['version'] ) ? esc_html( $item['release']['version'] ) : '&mdash;'; ?></td>
						<td><?php $this->render_status( $item ); ?></td>
						<td><?php $this->render_actions( $item ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Add a trusted repository', 'modern-catholic-plugin-update-manager' ); ?></h2>
			<p><?php esc_html_e( 'Use this for future repositories that are not auto-detected from installed component headers.', 'modern-catholic-plugin-update-manager' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mc-repository-form">
				<input type="hidden" name="action" value="modern_catholic_updates_save_repository">
				<?php wp_nonce_field( 'modern_catholic_updates_save_repository' ); ?>
				<label><?php esc_html_e( 'Display name', 'modern-catholic-plugin-update-manager' ); ?><input required type="text" name="name" placeholder="Modern Catholic – New Component"></label>
				<label><?php esc_html_e( 'GitHub owner/repository', 'modern-catholic-plugin-update-manager' ); ?><input required type="text" name="repository" placeholder="twitchd8/modern-catholic-plugin-example"></label>
				<label><?php esc_html_e( 'Package type', 'modern-catholic-plugin-update-manager' ); ?><select name="type"><option value="plugin"><?php esc_html_e( 'Plugin', 'modern-catholic-plugin-update-manager' ); ?></option><option value="theme"><?php esc_html_e( 'Theme', 'modern-catholic-plugin-update-manager' ); ?></option></select></label>
				<label><?php esc_html_e( 'Installed directory slug', 'modern-catholic-plugin-update-manager' ); ?><input required type="text" name="slug" placeholder="modern-catholic-plugin-example"></label>
				<label><?php esc_html_e( 'Plugin entrypoint (plugins only)', 'modern-catholic-plugin-update-manager' ); ?><input type="text" name="entrypoint" placeholder="example.php"></label>
				<label><?php esc_html_e( 'Release asset template', 'modern-catholic-plugin-update-manager' ); ?><input required type="text" name="asset_template" value="{slug}-{version}.zip"></label>
				<?php submit_button( __( 'Add repository', 'modern-catholic-plugin-update-manager' ) ); ?>
			</form>
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

	/** Remove a manual repository. */
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
		$state = $this->manager->component_state( $repository );
		if ( $state['installed'] || $state['development'] ) {
			$this->redirect( 'already_installed' );
		}
		$release = $this->github->latest_release( $repository, true );
		if ( is_wp_error( $release ) ) {
			$this->redirect( 'install_failed' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$skin = new \Automatic_Upgrader_Skin();
		if ( 'theme' === $repository['type'] ) {
			$upgrader = new \Theme_Upgrader( $skin );
		} else {
			$upgrader = new \Plugin_Upgrader( $skin );
		}
		$result = $upgrader->install( $release['package'] );
		$this->redirect( is_wp_error( $result ) || ! $result ? 'install_failed' : 'installed' );
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

	/** Render row actions. */
	private function render_actions( $item ) {
		$install_capability = 'theme' === $item['type'] ? 'install_themes' : 'install_plugins';
		if ( 'update_available' === $item['status'] ) {
			echo '<a class="button button-primary" href="' . esc_url( network_admin_url( 'update-core.php' ) ) . '">' . esc_html__( 'Open Updates', 'modern-catholic-plugin-update-manager' ) . '</a> ';
		} elseif ( 'not_installed' === $item['status'] && current_user_can( $install_capability ) ) {
			$this->action_form( 'modern_catholic_updates_install', $item['id'], __( 'Install latest', 'modern-catholic-plugin-update-manager' ), true );
		}

		$this->action_form( 'modern_catholic_updates_toggle_repository', $item['id'], $item['enabled'] ? __( 'Disable', 'modern-catholic-plugin-update-manager' ) : __( 'Enable', 'modern-catholic-plugin-update-manager' ), false, array( 'enabled' => $item['enabled'] ? '0' : '1' ) );
		if ( 'manual' === $item['source'] ) {
			$this->action_form( 'modern_catholic_updates_remove_repository', $item['id'], __( 'Remove', 'modern-catholic-plugin-update-manager' ) );
		}
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
	private function redirect( $message ) {
		wp_safe_redirect( add_query_arg( 'mc_updates_message', sanitize_key( $message ), admin_url( 'tools.php?page=modern-catholic-updates' ) ) );
		exit;
	}

	/** Render a result notice. */
	private function render_message( $message ) {
		$messages = array(
			'checked'             => array( 'success', __( 'GitHub releases checked.', 'modern-catholic-plugin-update-manager' ) ),
			'repository_saved'    => array( 'success', __( 'Repository added.', 'modern-catholic-plugin-update-manager' ) ),
			'repository_enabled'  => array( 'success', __( 'Repository monitoring enabled.', 'modern-catholic-plugin-update-manager' ) ),
			'repository_disabled' => array( 'warning', __( 'Repository monitoring disabled.', 'modern-catholic-plugin-update-manager' ) ),
			'repository_removed'  => array( 'success', __( 'Manual repository removed.', 'modern-catholic-plugin-update-manager' ) ),
			'installed'           => array( 'success', __( 'The latest release was installed. Activation remains under administrator control.', 'modern-catholic-plugin-update-manager' ) ),
			'invalid_repository'  => array( 'error', __( 'The repository definition is invalid.', 'modern-catholic-plugin-update-manager' ) ),
			'already_installed'   => array( 'warning', __( 'That component is already installed or is a protected Git checkout.', 'modern-catholic-plugin-update-manager' ) ),
			'install_failed'      => array( 'error', __( 'Installation failed. Review filesystem permissions and release asset packaging.', 'modern-catholic-plugin-update-manager' ) ),
		);
		if ( isset( $messages[ $message ] ) ) {
			printf( '<div class="notice notice-%1$s inline"><p>%2$s</p></div>', esc_attr( $messages[ $message ][0] ), esc_html( $messages[ $message ][1] ) );
		}
	}
}
