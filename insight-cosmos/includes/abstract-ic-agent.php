<?php
if ( ! defined( 'ABSPATH' ) ) exit;

abstract class IC_Agent {
    
    /**
     * Run the agent's main logic.
     * Should be called by WP-Cron or manually.
     */
    abstract public function run();

    /**
     * Helper log function (can be improved to write to a file or DB)
     */
    protected function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            // error_log( '[' . static::class . '] ' . $message );
        }
    }

    /**
     * Store a node in the graph database.
     * 
     * @param string $label The node label (title).
     * @param string $type The node type (e.g., 'raw_data', 'concept', 'report').
     * @param array $metadata Additional data.
     * @return int Node ID.
     */
    protected function save_node( $label, $type, $metadata = array() ) {
        global $wpdb;
        
        // Ensure type is in metadata for now, as we don't have a dedicated column yet
        $metadata['type'] = $type;
        $metadata['created_at'] = current_time( 'mysql' );

        $wpdb->insert(
            "{$wpdb->prefix}ic_nodes",
            array(
                'label'    => sanitize_text_field( $label ),
                'metadata' => json_encode( $metadata, JSON_UNESCAPED_UNICODE )
            )
        );
        return $wpdb->insert_id;
    }

    /**
     * Update node metadata.
     * 
     * @param int $id Node ID.
     * @param array $new_metadata Metadata to merge or overwrite.
     */
    protected function update_node_metadata( $id, $new_metadata ) {
        global $wpdb;
        
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT metadata FROM {$wpdb->prefix}ic_nodes WHERE id = %d", $id ) );
        if ( ! $row ) return;

        $current_metadata = json_decode( $row->metadata, true );
        if ( ! is_array( $current_metadata ) ) $current_metadata = array();

        $merged_metadata = array_merge( $current_metadata, $new_metadata );

        $wpdb->update(
            "{$wpdb->prefix}ic_nodes",
            array( 'metadata' => json_encode( $merged_metadata, JSON_UNESCAPED_UNICODE ) ),
            array( 'id' => $id )
        );
    }
}
