<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

<span class="hljs-keyword">class WPDF_OpenAI </span>{

    private $api_key;

    public <span class="hljs-keyword">function __construct( <span class="hljs-variable">$api_key </span>) </span>{
        $this->api_key = $api_key;
    }

    public <span class="hljs-keyword">function generate_image_for_word( <span class="hljs-variable">$en, $zh = '' </span>) </span>{
        $prompt = sprintf(
            'A detailed, high-quality illustration representing the English word "%s". %s',
            $en,
            $zh ? 'Incorporate the concept of: ' . $zh . '.' : ''
        );

        $body = array(
            'model'  => 'gpt-image-1',
            'prompt' => $prompt,
            'size'   => '512x512',
        );

        $response = wp_remote_post(
            'https://api.openai.com/v1/images/generations',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'openai_request_failed', '無法連線至 OpenAI：' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || empty( $data['data'][0]['url'] ) ) {
            $message = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error from OpenAI.';
            return new WP_Error( 'openai_error', 'OpenAI 回傳錯誤：' . $message );
        }

        return esc_url_raw( $data['data'][0]['url'] );
    }
}