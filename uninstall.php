<?php
/** Remove saved versions and scheduled checks on deletion. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$offset = 0;
	do {
		$sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $offset ) );
		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );
			wp_clear_scheduled_hook( 'awesomux_version_sync_refresh' );
			delete_option( 'awesomux_version_sync_version' );
			restore_current_blog();
		}
		$offset += 100;
	} while ( 100 === count( $sites ) );
} else {
	wp_clear_scheduled_hook( 'awesomux_version_sync_refresh' );
	delete_option( 'awesomux_version_sync_version' );
}
