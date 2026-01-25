<?php
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Delete Options
delete_option( 'ic_openai_key' );
delete_option( 'ic_search_api_key' );
delete_option( 'ic_rss_feeds' );

// 2. Drop Custom Tables
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ic_nodes" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ic_edges" );

// 3. Delete Custom Post Type Posts (ic_report) - Optional
// Some users might want to keep reports even if plugin is deleted. 
// But strictly strictly speaking, "clean up" means removing them.
// Let's remove them to be thorough as requested.
$reports = get_posts( array( 
    'post_type' => 'ic_report', 
    'numberposts' => -1, 
    'post_status' => 'any' 
) );

foreach ( $reports as $report ) {
    wp_delete_post( $report->ID, true ); // Force delete
}
