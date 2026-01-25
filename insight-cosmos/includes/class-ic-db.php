<?php
class IC_DB {
    public static function create_tables() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$wpdb->prefix}ic_nodes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            label varchar(255) NOT NULL,
            metadata json DEFAULT NULL,
            PRIMARY KEY (id)
        ) $collate;
        CREATE TABLE {$wpdb->prefix}ic_edges (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_id bigint(20) NOT NULL,
            target_id bigint(20) NOT NULL,
            PRIMARY KEY (id)
        ) $collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}