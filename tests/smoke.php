<?php
/**
 * CLI smoke test. Run only against a disposable development site.
 *
 * @package ModernCatholicUpdateManager
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$wordpress = dirname( __DIR__, 4 );
chdir( $wordpress );
require 'wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

use PowerHouse\ModernCatholic\UpdateManager\Admin_Page;
use PowerHouse\ModernCatholic\UpdateManager\Credential_File;
use PowerHouse\ModernCatholic\UpdateManager\GitHub_Client;
use PowerHouse\ModernCatholic\UpdateManager\Repository_Registry;
use PowerHouse\ModernCatholic\UpdateManager\Update_Manager;

/** Fail the smoke test. */
function mc_updates_smoke_fail( $message ) {
	fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
	exit( 1 );
}

/** Record a passing assertion. */
function mc_updates_smoke_pass( $message ) {
	echo 'PASS: ' . $message . PHP_EOL;
}

$plugin_file = 'modern-catholic-plugin-update-manager/modern-catholic-update-manager.php';
if ( ! is_plugin_active( $plugin_file ) ) {
	mc_updates_smoke_fail( 'Plugin is not active.' );
}
mc_updates_smoke_pass( 'Plugin is active.' );

if ( ! wp_next_scheduled( Update_Manager::CRON_HOOK ) ) {
	mc_updates_smoke_fail( 'Scheduled release check is missing.' );
}
mc_updates_smoke_pass( 'Twice-daily release check is scheduled.' );

$parsed = Repository_Registry::parse_repository( 'https://github.com/twitchd8/modern-catholic-plugin-editorial-sections.git' );
if ( ! $parsed || 'twitchd8/modern-catholic-plugin-editorial-sections' !== $parsed['id'] ) {
	mc_updates_smoke_fail( 'Repository URL parsing failed.' );
}
mc_updates_smoke_pass( 'Repository URL parsing is canonical.' );

$credential_test_path = MODERN_CATHOLIC_UPDATE_MANAGER_DIR . 'tests/.github-token-smoke.php';
if ( file_exists( $credential_test_path ) ) {
	wp_delete_file( $credential_test_path );
}
$credential_test = new Credential_File( $credential_test_path );
$fake_token       = 'github_pat_' . str_repeat( 'a', 32 );
$saved_token      = $credential_test->write( $fake_token );
if ( is_wp_error( $saved_token ) || $fake_token !== $credential_test->read() ) {
	mc_updates_smoke_fail( 'Filesystem credential could not be written and read.' );
}
wp_delete_file( $credential_test_path );
$credential_test->restore_after_update(
	null,
	array(
		'action' => 'update',
		'type'   => 'plugin',
		'plugin' => $plugin_file,
	)
);
if ( $fake_token !== $credential_test->read() ) {
	mc_updates_smoke_fail( 'Filesystem credential was not restored after a simulated self-update.' );
}
$credential_test->remove();
if ( file_exists( $credential_test_path ) ) {
	mc_updates_smoke_fail( 'Filesystem credential test cleanup failed.' );
}
mc_updates_smoke_pass( 'Ignored credential file writes, reads, removes, and survives self-update replacement.' );

$credentials = new Credential_File();
$registry    = new Repository_Registry();
$github      = new GitHub_Client( $credentials );
$manager     = new Update_Manager( $registry, $github );
$admin       = new Admin_Page( $registry, $github, $manager, $credentials );

$runtime_token = getenv( 'MODERN_CATHOLIC_UPDATES_GITHUB_TOKEN' );
if ( false !== $runtime_token && '' !== trim( (string) $runtime_token ) ) {
	$validated_token = $github->validate_token( $runtime_token );
	if ( is_wp_error( $validated_token ) ) {
		mc_updates_smoke_fail( 'Configured private GitHub credential was rejected.' );
	}
	mc_updates_smoke_pass( 'Configured credential can read the private Update Manager repository.' );
}

$catalog = $github->discover_catalog( true );
if ( is_wp_error( $catalog ) ) {
	mc_updates_smoke_fail( 'GitHub catalog discovery failed: ' . $catalog->get_error_message() );
}
if ( ! empty( $catalog['items']['twitchd8/modern-catholic-plugin-parish-blog'] ) ) {
	mc_updates_smoke_fail( 'GitHub catalog discovery included an archived component.' );
}
if ( false !== $runtime_token && '' !== trim( (string) $runtime_token ) && empty( $catalog['items']['twitchd8/modern-catholic-plugin-update-manager']['release'] ) ) {
	mc_updates_smoke_fail( 'Authenticated catalog discovery omitted the private Update Manager release.' );
}
if ( ! is_wp_error( $github->catalog_item( 'untrusted/example', false ) ) ) {
	mc_updates_smoke_fail( 'Catalog lookup accepted an undiscovered repository.' );
}
mc_updates_smoke_pass( 'GitHub catalog filters trusted Modern Catholic repositories and verifies exact releases.' );

$test_id               = 'twitchd8/modern-catholic-plugin-future-test';
$original_repositories = get_option( Repository_Registry::OPTION_REPOSITORIES, null );
$original_removed      = get_option( Repository_Registry::OPTION_REMOVED, null );
$original_disabled     = get_option( Repository_Registry::OPTION_DISABLED, null );
if ( is_array( $original_repositories ) ) {
	unset( $original_repositories[ $test_id ] );
}
if ( is_array( $original_removed ) ) {
	$original_removed = array_values( array_diff( $original_removed, array( $test_id ) ) );
}
$removable_id          = 'twitchd8/modern-catholic-plugin-parish-events';
$removable             = $registry->get( $removable_id );
$module_registry_error = '';
if ( ! $removable || ! $registry->remove( $removable_id ) || $registry->get( $removable_id ) ) {
	$module_registry_error = 'A built-in module could not be removed from the managed list.';
}
if ( ! $module_registry_error ) {
	$restored = $registry->save( $removable );
	if ( is_wp_error( $restored ) || ! $registry->get( $removable_id ) ) {
		$module_registry_error = 'A removed module could not be added back to the managed list.';
	}
}
if ( null === $original_repositories ) {
	delete_option( Repository_Registry::OPTION_REPOSITORIES );
} else {
	update_option( Repository_Registry::OPTION_REPOSITORIES, $original_repositories, false );
}
if ( null === $original_removed ) {
	delete_option( Repository_Registry::OPTION_REMOVED );
} else {
	update_option( Repository_Registry::OPTION_REMOVED, $original_removed, false );
}
if ( null === $original_disabled ) {
	delete_option( Repository_Registry::OPTION_DISABLED );
} else {
	update_option( Repository_Registry::OPTION_DISABLED, $original_disabled, false );
}
if ( $module_registry_error ) {
	mc_updates_smoke_fail( $module_registry_error );
}
mc_updates_smoke_pass( 'Built-in and discovered modules can be removed and added back.' );

$saved   = $registry->save(
	array(
		'name'       => 'Future Test',
		'repository' => $test_id,
		'type'       => 'plugin',
		'slug'       => 'modern-catholic-plugin-future-test',
		'entrypoint' => 'future-test.php',
	)
);
if ( is_wp_error( $saved ) || ! $registry->get( $test_id ) ) {
	mc_updates_smoke_fail( 'Manual future repository registration failed.' );
}
if ( ! $registry->remove( $test_id ) || $registry->get( $test_id ) ) {
	mc_updates_smoke_fail( 'Manual future repository cleanup failed.' );
}
if ( null === $original_removed ) {
	delete_option( Repository_Registry::OPTION_REMOVED );
} else {
	update_option( Repository_Registry::OPTION_REMOVED, $original_removed, false );
}
mc_updates_smoke_pass( 'Future repositories can be added and removed.' );

$catalog_repository = null;
foreach ( $catalog['items'] as $catalog_item ) {
	if ( ! empty( $catalog_item['release'] ) ) {
		$catalog_repository = $catalog_item;
		break;
	}
}
if ( $catalog_repository ) {
	$release = $github->latest_release( $catalog_repository, true );
	if ( is_wp_error( $release ) ) {
		mc_updates_smoke_fail( 'GitHub release lookup failed: ' . $release->get_error_message() );
	}
	$expected_asset = $catalog_repository['slug'] . '-' . $release['version'] . '.zip';
	if ( $expected_asset !== $release['asset_name'] ) {
		mc_updates_smoke_fail( 'Release version or exact asset selection is incorrect.' );
	}
	mc_updates_smoke_pass( 'Latest stable release and exact ZIP asset were selected.' );

	$temporary = download_url( $release['package'], 60, false );
	if ( is_wp_error( $temporary ) ) {
		mc_updates_smoke_fail( 'Release asset download failed: ' . $temporary->get_error_message() );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $temporary ) ) {
		wp_delete_file( $temporary );
		mc_updates_smoke_fail( 'Downloaded release asset is not a readable ZIP.' );
	}
	$roots = array();
	for ( $index = 0; $index < $zip->numFiles; $index++ ) {
		$name = $zip->getNameIndex( $index );
		$root = strtok( $name, '/' );
		if ( $root ) {
			$roots[ $root ] = true;
		}
	}
	$zip->close();
	wp_delete_file( $temporary );
	if ( array( $catalog_repository['slug'] ) !== array_keys( $roots ) ) {
		mc_updates_smoke_fail( 'Release ZIP does not have exactly one canonical top-level directory.' );
	}
	mc_updates_smoke_pass( 'Downloaded ZIP has one canonical installable root.' );
} else {
	mc_updates_smoke_pass( 'No catalog release was visible without a private credential; package test skipped.' );
}

$results = $manager->scan( false );
if ( empty( $results['items']['twitchd8/modern-catholic-theme']['development'] ) ) {
	mc_updates_smoke_fail( 'Theme Git checkout was not protected.' );
}
mc_updates_smoke_pass( 'Registry scan and Git checkout protection are active.' );

wp_set_current_user( 1 );
$links = $admin->plugin_action_links( array() );
if ( empty( $links[0] ) || false === strpos( $links[0], 'plugins.php?page=modern-catholic-updates' ) ) {
	mc_updates_smoke_fail( 'Plugin-row management link does not target the Plugins submenu.' );
}
$admin->menu();
global $submenu;
$plugin_menu_found = false;
foreach ( isset( $submenu['plugins.php'] ) ? $submenu['plugins.php'] : array() as $menu_item ) {
	if ( isset( $menu_item[2] ) && 'modern-catholic-updates' === $menu_item[2] ) {
		$plugin_menu_found = true;
		break;
	}
}
if ( ! $plugin_menu_found ) {
	mc_updates_smoke_fail( 'Management page was not registered beneath Plugins.' );
}
mc_updates_smoke_pass( 'Management page and direct link are registered beneath Plugins.' );

ob_start();
$admin->render();
$page = ob_get_clean();
if ( false === strpos( $page, '>Modules<' ) || false === strpos( $page, 'Add module' ) || false === strpos( $page, 'Private GitHub access' ) || false === strpos( $page, 'github_token' ) || false === strpos( $page, 'modern_catholic_updates_remove_repository' ) ) {
	mc_updates_smoke_fail( 'Primary admin page did not render the single managed module list.' );
}
if ( false !== strpos( $page, 'Available module' ) || false !== strpos( $page, 'mc-repository-form' ) ) {
	mc_updates_smoke_fail( 'Primary admin page exposed available or custom repository controls before Add module was selected.' );
}
mc_updates_smoke_pass( 'Primary admin page renders only the managed module list.' );

$_GET['mc_updates_view'] = 'add';
ob_start();
$admin->render();
$add_page = ob_get_clean();
unset( $_GET['mc_updates_view'] );
if ( false === strpos( $add_page, '>Add module<' ) || false === strpos( $add_page, 'Available module' ) || false === strpos( $add_page, 'Refresh available modules' ) || false === strpos( $add_page, 'Add a repository not shown above' ) || false === strpos( $add_page, 'modern_catholic_updates_catalog_add' ) ) {
	mc_updates_smoke_fail( 'Add module view did not render available and custom repository controls.' );
}
if ( false !== strpos( $add_page, 'mc-updates-table' ) ) {
	mc_updates_smoke_fail( 'Add module view rendered the managed module table at the same time.' );
}
mc_updates_smoke_pass( 'Available modules render only after Add module is selected.' );

echo 'Smoke test completed successfully.' . PHP_EOL;
