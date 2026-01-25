<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IC_Scout extends IC_Agent {

    public function run() {
        $this->log( "Starting Scout Run..." );
        
        $feeds = $this->get_feeds();
        if ( empty( $feeds ) ) {
            $this->log( "No feeds found." );
            return;
        }

        foreach ( $feeds as $feed_url ) {
            $this->fetch_feed( $feed_url );
        }
        
        $this->log( "Scout Run Complete." );
    }

    private function get_feeds() {
        $feeds_option = get_option( 'ic_rss_feeds' );
        if ( ! $feeds_option ) return array();
        
        $urls = explode( "\n", $feeds_option );
        return array_filter( array_map( 'trim', $urls ) );
    }

    private function fetch_feed( $url ) {
        if ( ! function_exists( 'fetch_feed' ) ) {
            include_once( ABSPATH . WPINC . '/feed.php' );
        }

        $rss = fetch_feed( $url );
        
        if ( is_wp_error( $rss ) ) {
            $this->log( "Error fetching $url: " . $rss->get_error_message() );
            return;
        }

        // Limit to 5 items per feed for now
        $maxitems = $rss->get_item_quantity( 5 ); 
        $rss_items = $rss->get_items( 0, $maxitems );

        foreach ( $rss_items as $item ) {
            $title = $item->get_title();
            $link = $item->get_permalink();
            $date = $item->get_date( 'Y-m-d H:i:s' );
            $content = $item->get_description();

            // Check if exists (simple check by label for now, or could check metadata url)
            // Ideally we should query by metadata->url, but for MVP we just save.
            // TODO: Implement duplicate check.

            $this->save_node( 
                $title, 
                'raw_data', 
                array(
                    'source' => 'rss',
                    'url'    => $link,
                    'date'   => $date,
                    'content_snippet' => mb_substr( wp_strip_all_tags( $content ), 0, 500 ) . '...'
                )
            );
            
            $this->log( "Saved node: $title" );
        }
    }
}
