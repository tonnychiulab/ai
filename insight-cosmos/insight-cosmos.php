<?php
/**
 * Plugin Name: InsightCosmos
 * Description: An AI agent-powered WordPress plugin for automated intelligence gathering and analysis.
 * Version: 1.0.9
 * Author: WP 金魚腦 2號
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: insight-cosmos
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'IC_PATH', plugin_dir_path( __FILE__ ) );
define( 'IC_URL', plugin_dir_url( __FILE__ ) );
define( 'IC_VERSION', '1.0.9' );

require_once IC_PATH . 'includes/class-ic-db.php';
require_once IC_PATH . 'includes/class-ic-rest.php';
require_once IC_PATH . 'includes/class-ic-assets.php';
require_once IC_PATH . 'includes/class-ic-settings.php';
require_once IC_PATH . 'includes/abstract-ic-agent.php';
require_once IC_PATH . 'includes/agents/class-ic-scout.php';
require_once IC_PATH . 'includes/agents/class-ic-analyst.php';
require_once IC_PATH . 'includes/agents/class-ic-curator.php';

final class InsightCosmos {
    public function __construct() {
        register_activation_hook( __FILE__, array( 'IC_DB', 'create_tables' ) );
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'init', array( $this, 'register_cpt' ) );
    }

    public function init() {
        new IC_REST();
        if ( is_admin() ) {
            new IC_Assets();
            new IC_Settings();
            
            // Open "View" link in new tab for Reports
            add_filter( 'post_row_actions', array( $this, 'open_view_in_new_tab' ), 10, 2 );
        }
    }

    public function open_view_in_new_tab( $actions, $post ) {
        if ( $post->post_type === 'ic_report' && isset( $actions['view'] ) ) {
            $actions['view'] = str_replace( '<a href=', '<a target="_blank" href=', $actions['view'] );
        }
        return $actions;
    }

    public function register_cpt() {
        register_post_type( 'ic_report', array(
            'labels' => array(
                'name' => __( 'Intelligence Reports', 'insight-cosmos' ),
                'singular_name' => __( 'Report', 'insight-cosmos' )
            ),
            'public' => true,
            'show_in_menu' => 'ic-app', // Show under our main menu
            'supports' => array( 'title', 'editor', 'author' ),
            'has_archive' => true
        ) );
    }
}
new InsightCosmos();