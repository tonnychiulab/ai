<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IC_REST {
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register' ) );
    }
    public function register() {
        $args = array( 'permission_callback' => function() { return current_user_can('manage_options'); } );
        register_rest_route( 'ic/v1', '/graph', array( 'methods' => 'GET', 'callback' => array( $this, 'get_graph' ), 'permission_callback' => $args['permission_callback'] ) );
        register_rest_route( 'ic/v1', '/node', array( 'methods' => 'POST', 'callback' => array( $this, 'add_node' ), 'permission_callback' => $args['permission_callback'] ) );
        register_rest_route( 'ic/v1', '/node/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete_node' ), 'permission_callback' => $args['permission_callback'] ) );
        register_rest_route( 'ic/v1', '/edge', array( 'methods' => 'POST', 'callback' => array( $this, 'add_edge' ), 'permission_callback' => $args['permission_callback'] ) );
    }

    public function get_graph() {
        global $wpdb;
        return array(
            'nodes' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ic_nodes"),
            'edges' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ic_edges")
        );
    }

    public function add_node($req) {
        global $wpdb;
        $params = $req->get_json_params();
        $wpdb->insert("{$wpdb->prefix}ic_nodes", array('label' => sanitize_text_field($params['label'])));
        return array('id' => $wpdb->insert_id);
    }

    public function delete_node($req) {
        global $wpdb;
        $id = absint($req['id']);
        $wpdb->delete("{$wpdb->prefix}ic_edges", array('source_id' => $id));
        $wpdb->delete("{$wpdb->prefix}ic_edges", array('target_id' => $id));
        $wpdb->delete("{$wpdb->prefix}ic_nodes", array('id' => $id));
        return array('success' => true);
    }

    public function add_edge($req) {
        global $wpdb;
        $params = $req->get_json_params();
        $wpdb->insert("{$wpdb->prefix}ic_edges", array('source_id' => absint($params['source_id']), 'target_id' => absint($params['target_id'])));
        return array('id' => $wpdb->insert_id);
    }
}