<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

<span class="hljs-keyword">class WPDF_Flashcard </span>{

    const USER_META_KEY = 'wpdf_last_seen_date';

    public <span class="hljs-keyword">function __construct() </span>{
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'in_admin_footer', array( $this, 'render_flashcard_modal' ) );

        add_action( 'wp_ajax_wpdf_generate_image', array( $this, 'handle_generate_image' ) );
    }

    public <span class="hljs-keyword">function should_show_today() </span>{
        if ( ! is_admin() || ! is_user_logged_in() ) {
            return false;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'dashboard' !== $screen->base ) {
            return false;
        }

        $user_id    = get_current_user_id();
        $today      = date_i18n( 'Y-m-d' );
        $last_seen  = get_user_meta( $user_id, self::USER_META_KEY, true );

        if ( $last_seen === $today ) {
            return false;
        }

        return true;
    }

    public <span class="hljs-keyword">function mark_seen_today() </span>{
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        $today   = date_i18n( 'Y-m-d' );
        update_user_meta( $user_id, self::USER_META_KEY, $today );
    }

    public <span class="hljs-keyword">function enqueue_assets( <span class="hljs-variable">$hook </span>) </span>{
        if ( 'index.php' !== $hook ) {
            return;
        }

        if ( ! $this->should_show_today() ) {
            return;
        }

        wp_enqueue_style(
            'wpdf-flashcard',
            WPDF_PLUGIN_URL . 'assets/css/flashcard.css',
            array(),
            WPDF_VERSION
        );

        wp_enqueue_script(
            'wpdf-flashcard',
            WPDF_PLUGIN_URL . 'assets/js/flashcard.js',
            array( 'jquery' ),
            WPDF_VERSION,
            true
        );

        $settings = WPDF_Settings::get_settings();
        $word     = $this->get_today_word( $settings );

        wp_localize_script(
            'wpdf-flashcard',
            'WPDF_DATA',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wpdf_generate_image' ),
                'word'     => $word,
                'order'    => $settings['order'],
                'has_api'  => ! empty( $settings['api_key'] ),
            )
        );
    }

    private <span class="hljs-keyword">function get_today_word( <span class="hljs-variable">$settings </span>) </span>{
        $words = ! empty( $settings['words'] ) ? $settings['words'] : array(
            array( 'en' => 'Apple', 'zh' => '蘋果' ),
        );

        // 用日期當作 seed，讓同一天顯示同一個單字
        $day_index = intval( date_i18n( 'z' ) ); // 一年中的第幾天 0-365
        $idx       = $day_index % count( $words );

        return $words[ $idx ];
    }

    public <span class="hljs-keyword">function render_flashcard_modal() </span>{
        if ( ! $this->should_show_today() ) {
            return;
        }

        $this->mark_seen_today();

        ?>
        <div id="wpdf-overlay" <span class="hljs-keyword">class="wpdf-overlay" style="display:none;"></div>
        <div id="wpdf-modal" class="wpdf-modal" style="display:none;">
            <div class="wpdf-card">
                <button type="button" class="wpdf-close" aria-label="<?php esc_attr_e( 'Close', 'wp-daily-flashcard' ); ?>">
                    &times;
                </button>
                <h2 class="wpdf-title"><?php esc_html_e( '今日學習閃卡', 'wp-daily-flashcard' ); ?></h2>
                <div id="wpdf-text-en" class="wpdf-text wpdf-text-en"></div>
                <div id="wpdf-text-zh" class="wpdf-text wpdf-text-zh"></div>

                <div id="wpdf-image-container" class="wpdf-image-container">
                    <div class="wpdf-image-placeholder">
                        <?php esc_html_e( '點擊卡片產生對應圖片（需設定 OpenAI API Key）', 'wp-daily-flashcard' ); ?>
                    </div>
                </div>

                <button id="wpdf-generate" class="wpdf-generate">
                    <?php esc_html_e( '產生圖片', 'wp-daily-flashcard' ); ?>
                </button>

                <div id="wpdf-message" class="wpdf-message"></div>
            </div>
        </div>
        <?php
    }

    public function handle_generate_image() </span>{
        check_ajax_referer( 'wpdf_generate_image', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
        }

        $settings = WPDF_Settings::get_settings();
        $api_key  = $settings['api_key'];

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => '尚未設定 OpenAI API Key。' ), 400 );
        }

        $en = isset( $_POST['en'] ) ? sanitize_text_field( wp_unslash( $_POST['en'] ) ) : '';
        $zh = isset( $_POST['zh'] ) ? sanitize_text_field( wp_unslash( $_POST['zh'] ) ) : '';

        if ( ! $en ) {
            wp_send_json_error( array( 'message' => '缺少英文單字。' ), 400 );
        }

        $openai = new WPDF_OpenAI( $api_key );
        $image  = $openai->generate_image_for_word( $en, $zh );

        if ( is_wp_error( $image ) ) {
            wp_send_json_error(
                array( 'message' => $image->get_error_message() ),
                500
            );
        }

        wp_send_json_success(
            array(
                'url' => $image,
            )
        );
    }
}