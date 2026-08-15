<?php
/**
 * Plugin bootstrap.
 *
 * @package ModernCatholicUpdateManager
 */

namespace PowerHouse\ModernCatholic\UpdateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	/** @var self|null */
	private static $instance;
	/** @var bool */
	private $booted = false;

	/** Return the singleton. */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register plugin services. */
	public function boot() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$registry = new Repository_Registry();
		$github   = new GitHub_Client();
		$manager  = new Update_Manager( $registry, $github );
		$admin    = new Admin_Page( $registry, $github, $manager );

		$manager->hooks();
		$admin->hooks();
		add_filter( 'http_request_args', array( $github, 'filter_http_request_args' ), 20, 2 );
		add_filter( 'upgrader_pre_download', array( $github, 'verify_download' ), 10, 4 );
	}

	/** Schedule release checks. */
	public static function activate() {
		if ( ! wp_next_scheduled( Update_Manager::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'twicedaily', Update_Manager::CRON_HOOK );
		}
	}

	/** Remove the scheduled event without deleting repository configuration. */
	public static function deactivate() {
		wp_clear_scheduled_hook( Update_Manager::CRON_HOOK );
	}

	/** Prevent direct construction. */
	private function __construct() {}
}
