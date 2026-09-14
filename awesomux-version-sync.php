<?php
/**
 * Plugin Name: awesoMux Version Sync
 * Description: Sync the latest stable GitHub release with [awesomux_version].
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Interactive Buffoonery
 * License: GPL-2.0-or-later
 * Text Domain: awesomux-version-sync
 */

namespace AwesomuxVersionSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION = 'awesomux_version_sync_version';
const HOOK   = 'awesomux_version_sync_refresh';

/** Schedule once; also repairs a missing event after an update. */
function schedule() {
	if ( ! wp_next_scheduled( HOOK ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', HOOK );
	}
}

/** Fetch immediately so the first page can show a version. */
function activate( $network_wide ) {
	if ( $network_wide ) {
		wp_die( esc_html__( 'Activate awesoMux Version Sync on each site individually.', 'awesomux-version-sync' ) );
	}
	schedule();
	refresh();
}

function deactivate() {
	wp_clear_scheduled_hook( HOOK );
}

/** A failed check must never erase the last usable version. */
function refresh() {
	$response = wp_remote_get(
		'https://api.github.com/repos/Interactive-Buffoonery/awesomux/releases/latest',
		array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => 65536,
			'headers'             => array( 'Accept' => 'application/vnd.github+json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return false;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $release ) || ! isset( $release['tag_name'], $release['draft'], $release['prerelease'] )
		|| false !== $release['draft'] || false !== $release['prerelease']
		|| ! is_string( $release['tag_name'] ) ) {
		return false;
	}

	// Accept this project's stable version format, never arbitrary release text.
	if ( ! preg_match( '/\Av?([0-9]+\.[0-9]+\.[0-9]+)\z/', $release['tag_name'], $matches ) ) {
		return false;
	}

	$version = $matches[1];
	$old     = get_option( OPTION, '' );
	if ( $version !== $old ) {
		if ( ! update_option( OPTION, $version, false ) ) {
			return false;
		}
		/** Cache integrations can purge rendered pages when the version changes. */
		do_action( 'awesomux_version_sync_updated', $version, $old );
	}
	return true;
}

/** Plain inline text, with no network access during rendering. */
function shortcode( $attributes = array() ) {
	$attributes = shortcode_atts( array( 'fallback' => '' ), $attributes, 'awesomux_version' );
	$version    = get_option( OPTION, '' );
	return esc_html( is_string( $version ) && '' !== $version ? $version : $attributes['fallback'] );
}

add_action( 'init', __NAMESPACE__ . '\\schedule' );
add_action( HOOK, __NAMESPACE__ . '\\refresh' );
add_shortcode( 'awesomux_version', __NAMESPACE__ . '\\shortcode' );
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
