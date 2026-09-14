<?php
/** Run in a disposable WordPress install (wp eval-file tests/wordpress.php). */
if ( ! defined( 'ABSPATH' ) ) {
	exit( "Load WordPress before running this integration check.\n" );
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function avs_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo "PASS: $message\n";
}

$plugin = 'awesomux-version-sync/awesomux-version-sync.php';
deactivate_plugins( $plugin );
delete_option( 'awesomux_version_sync_version' );
avs_check( ! is_wp_error( activate_plugin( $plugin ) ), 'plugin activates' );
$live = get_option( 'awesomux_version_sync_version' );
avs_check( is_string( $live ) && 1 === preg_match( '/\A[0-9]+\.[0-9]+\.[0-9]+\z/', $live ), 'activation fetches a real GitHub version' );
avs_check( 'daily' === wp_get_schedule( 'awesomux_version_sync_refresh' ), 'daily cron registered' );
echo "Live version: $live\n";

$mock = static function ( $pre, $args, $url ) {
	if ( 'https://api.github.com/repos/Interactive-Buffoonery/awesomux/releases/latest' === $url ) {
		return array( 'response' => array( 'code' => 200 ), 'body' => '{"tag_name":"v9.8.7","draft":false,"prerelease":false}' );
	}
	return $pre;
};
add_filter( 'pre_http_request', $mock, 10, 3 );
do_action( 'awesomux_version_sync_refresh' );
avs_check( '9.8.7' === get_option( 'awesomux_version_sync_version' ), 'cron callback updates stored version' );
$content = '<!-- wp:html --><p>Terminal → awesoMux [awesomux_version]</p><p>Pouring awesomux-[awesomux_version]… honk.</p><!-- /wp:html -->';
$paragraph = apply_filters( 'the_content', '<!-- wp:paragraph --><p>Terminal → awesoMux [awesomux_version]</p><!-- /wp:paragraph -->' );
avs_check( false !== strpos( $paragraph, 'Terminal → awesoMux 9.8.7' ) && false === strpos( $paragraph, '[awesomux_version]' ), 'inline Paragraph block renders the version' );
$rendered = apply_filters( 'the_content', $content );
avs_check( false === strpos( $rendered, '[awesomux_version]' ) && 2 === substr_count( $rendered, '9.8.7' ), 'both inline Custom HTML mentions render' );
$block = apply_filters( 'the_content', '<!-- wp:shortcode -->[awesomux_version]<!-- /wp:shortcode -->' );
echo 'Shortcode block output: ' . $block . "\n";
avs_check( '9.8.7' === trim( wp_strip_all_tags( $block ) ), 'Shortcode block renders' );
remove_filter( 'pre_http_request', $mock );
$failure = static function () { return new WP_Error( 'http_request_failed', 'Simulated outage' ); };
add_filter( 'pre_http_request', $failure );
avs_check( false === AwesomuxVersionSync\refresh() && '9.8.7' === AwesomuxVersionSync\shortcode(), 'outage preserves displayed value' );
remove_filter( 'pre_http_request', $failure );

deactivate_plugins( $plugin );
avs_check( ! wp_next_scheduled( 'awesomux_version_sync_refresh' ) && '9.8.7' === get_option( 'awesomux_version_sync_version' ), 'deactivation clears cron and preserves version' );
uninstall_plugin( $plugin );
avs_check( false === get_option( 'awesomux_version_sync_version' ), 'uninstall removes saved version' );
avs_check( '&lt;b&gt;fallback&lt;/b&gt;' === do_shortcode( '[awesomux_version fallback="<b>fallback</b>"]' ), 'fallback is escaped' );

activate_plugin( $plugin );
update_option( 'awesomux_version_sync_version', $live, false );
$page = wp_insert_post( array( 'post_title' => 'Version Sync Check', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $content ) );
avs_check( $page > 0, 'demo page created' );
echo 'Demo: ' . get_permalink( $page ) . "\n";
