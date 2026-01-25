<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IC_Analyst extends IC_Agent {

    public function run() {
        $this->log( "Starting Analyst Run..." );

        $api_key = get_option( 'ic_openai_key' );
        if ( ! $api_key ) {
            $this->log( "OpenAI API Key missing." );
            return;
        }

        // 1. Find un-analyzed raw_data nodes
        // Since we don't have JSON_CONTAINS in basic WPDB easily without verifying MySQL version, 
        // we fetch recent raw_data and filter in PHP for MVP safety.
        // Ideally: SELECT * FROM ic_nodes WHERE metadata LIKE '%"type":"raw_data"%' AND metadata NOT LIKE '%"analysis"%'
        
        global $wpdb;
        $results = $wpdb->get_results( 
            "SELECT * FROM {$wpdb->prefix}ic_nodes 
             WHERE metadata LIKE '%\"type\":\"raw_data\"%' 
             ORDER BY id DESC LIMIT 20" 
        );

        $count = 0;
        foreach ( $results as $node ) {
            $meta = json_decode( $node->metadata, true );
            
            // Skip if already analyzed
            if ( isset( $meta['analysis'] ) ) continue;

            $this->log( "Analyzing Node #{$node->id}: {$node->label}" );
            
            // 2. Call OpenAI
            $analysis = $this->analyze_content( $node->label, $meta['content_snippet'] ?? '', $api_key );
            
            if ( $analysis ) {
                // 3. Update Node
                $this->update_node_metadata( $node->id, array(
                    'analysis' => $analysis,
                    'analyzed_at' => current_time( 'mysql' )
                ));
                $count++;
            }
        }

        $this->log( "Analyst Run Complete. Analyzed $count nodes." );
    }

    private function analyze_content( $title, $content, $api_key ) {
        $prompt = "You are an Intelligence Analyst. Analyze the following content item.\n\n";
        $prompt .= "Title: $title\n";
        $prompt .= "Content Snippet: $content\n\n";
        $prompt .= "Provide a JSON response with the following keys:\n";
        $prompt .= "- summary: A concise 1-sentence summary.\n";
        $prompt .= "- score: A relevance score from 1-10 (10 being critical tech/AI news).\n";
        $prompt .= "- tags: An array of 3-5 keywords.\n";
        $prompt .= "- language: The language code of the content (en, zh-tw, etc).\n";
        $prompt .= "Response must be valid JSON only.";

        $args = array(
            'body'        => json_encode( array(
                'model'       => 'gpt-4o-mini', // or gpt-3.5-turbo
                'messages'    => array(
                    array( 'role' => 'system', 'content' => 'You serve JSON only.' ),
                    array( 'role' => 'user', 'content' => $prompt )
                ),
                'temperature' => 0.5,
            ) ),
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'timeout'     => 30,
        );

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $args );

        if ( is_wp_error( $response ) ) {
            $this->log( "OpenAI API Error: " . $response->get_error_message() );
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            $content_str = trim( $data['choices'][0]['message']['content'] );
            // Clean up markdown code blocks if present
            $content_str = str_replace( array( '```json', '```' ), '', $content_str );
            return json_decode( $content_str, true );
        }

        return null;
    }
}
