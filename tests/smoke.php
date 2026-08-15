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

$registry = new Repository_Registry();
$github   = new GitHub_Client();
$manager  = new Update_Manager( $registry, $github );
$admin    = new Admin_Page( $registry, $github, $manager );

$test_id = 'twitchd8/modern-catholic-plugin-future-test';
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
mc_updates_smoke_pass( 'Future repositories can be added and removed.' );

$repository = $registry->get( 'twitchd8/modern-catholic-plugin-editorial-sections' );
$release    = $github->latest_release( $repository, true );
if ( is_wp_error( $release ) ) {
	mc_updates_smoke_fail( 'Public GitHub release lookup failed: ' . $release->get_error_message() );
}
if ( '0.2.2' !== $release['version'] || 'modern-catholic-plugin-editorial-sections-0.2.2.zip' !== $release['asset_name'] ) {
	mc_updates_smoke_fail( 'Release version or exact asset selection is incorrect.' );
}
mc_updates_smoke_pass( 'Latest stable public release and exact ZIP asset were selected.' );

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
if ( array( 'modern-catholic-plugin-editorial-sections' ) !== array_keys( $roots ) ) {
	mc_updates_smoke_fail( 'Release ZIP does not have exactly one canonical top-level directory.' );
}
mc_updates_smoke_pass( 'Downloaded ZIP has one canonical installable root.' );

$results = $manager->scan( false );
if ( empty( $results['items']['twitchd8/modern-catholic-plugin-editorial-sections']['release'] ) ) {
	mc_updates_smoke_fail( 'Repository scan omitted the public release.' );
}
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
if ( false === strpos( $page, 'Add a trusted repository' ) || false === strpos( $page, 'Modern Catholic – Editorial Sections' ) || false === strpos( $page, 'Private GitHub repositories require a read-only token.' ) ) {
	mc_updates_smoke_fail( 'Admin page did not render the registry controls and release.' );
}
mc_updates_smoke_pass( 'Admin management page renders repository controls and status.' );

echo 'Smoke test completed successfully.' . PHP_EOL;
