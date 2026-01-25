<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IC_Curator extends IC_Agent {

    public function run() {
        $this->log( "Starting Curator Run..." );

        // 1. Get high-scoring nodes from the last 24 hours (or just all un-reported ones for MVP)
        // We look for nodes with 'analysis' and score >= 7
        // Similar simplified query for MVP
        global $wpdb;
        $results = $wpdb->get_results( 
            "SELECT * FROM {$wpdb->prefix}ic_nodes 
             WHERE metadata LIKE '%\"analysis\"%' 
             ORDER BY id DESC LIMIT 50" 
        );

        $candidates = array();
        
        foreach ( $results as $node ) {
            $meta = json_decode( $node->metadata, true );
            
            // Safety check
            if ( ! isset( $meta['analysis']['score'] ) ) continue;

            // Threshold: Score >= 7
            if ( intval( $meta['analysis']['score'] ) < 7 ) continue;
            
            // Check if already used in a report (TODO: implement robust tracking)
            $candidates[] = array(
                'id'       => $node->id,
                'title'    => $node->label,
                'summary'  => $meta['analysis']['summary'],
                'score'    => $meta['analysis']['score'],
                'url'      => $meta['url'] ?? '#',
                'tags'     => isset($meta['analysis']['tags']) ? implode(', ', $meta['analysis']['tags']) : ''
            );
        }

        if ( empty( $candidates ) ) {
            $this->log( "No high-value intelligence found to curate." );
            return;
        }

        $this->generate_report( $candidates );
    }

    private function generate_report( $items ) {
        $date_str = date_i18n( get_option( 'date_format' ) );
        $title = "Daily Intelligence Digest - $date_str";
        
        // Check if report already exists for today to avoid spamming 
        // (Simple check by title for MVP)
        if ( post_exists( $title, '', '', 'ic_report' ) ) {
            $this->log( "Report '$title' already exists. Skipping." );
            return;
        }

        $content = "<!-- wp:paragraph --><p>Here is your daily intelligence briefing served by InsightCosmos Curator.</p><!-- /wp:paragraph -->";
        
        $content .= "<!-- wp:list --><ul>";
        foreach ( $items as $item ) {
            $score_emoji = $item['score'] >= 9 ? '🔥' : '⭐';
            $content .= "<li><strong>{$score_emoji} [{$item['score']}] <a href='{$item['url']}' target='_blank'>{$item['title']}</a></strong><br/>";
            $content .= "<em>{$item['summary']}</em><br/>";
            $content .= "<small>Tags: {$item['tags']}</small></li>";
        }
        $content .= "</ul><!-- /wp:list -->";

        $post_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'ic_report',
            'post_author'  => get_current_user_id() ?: 1
        ));

        if ( $post_id ) {
            $this->log( "Created Report ID: $post_id" );
        } else {
            $this->log( "Failed to create report." );
        }
    }
}
