<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

<span class="hljs-keyword">class WPDF_Settings </span>{

    const OPTION_KEY = 'wpdf_settings';

    public <span class="hljs-keyword">function __construct() </span>{
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public static <span class="hljs-keyword">function get_settings() </span>{
        $settings = get_option( self::OPTION_KEY, array() );

        // 簡單填預設
        $settings = wp_parse_args(
            $settings,
            array(
                'api_key' => '',
                'order'   => 'en_zh',
                'words'   => array(),
            )
        );

        return $settings;
    }

    public <span class="hljs-keyword">function add_menu() </span>{
        add_options_page(
            __( '每日學習閃卡', 'wp-daily-flashcard' ),
            __( '每日學習閃卡', 'wp-daily-flashcard' ),
            'manage_options',
            'wp-daily-flashcard',
            array( $this, 'render_page' )
        );
    }

    public <span class="hljs-keyword">function register_settings() </span>{
        register_setting(
            'wpdf_settings_group',
            self::OPTION_KEY,
            array( $this, 'sanitize_settings' )
        );
    }

    public <span class="hljs-keyword">function sanitize_settings( <span class="hljs-variable">$input </span>) </span>{
        $output = array();

        $output['api_key'] = isset( $input['api_key'] ) ? trim( $input['api_key'] ) : '';

        $order = isset( $input['order'] ) ? $input['order'] : 'en_zh';
        $output['order'] = in_array( $order, array( 'en_zh', 'zh_en' ), true ) ? $order : 'en_zh';

        // 處理單字清單
        $words_raw = isset( $input['words_raw'] ) ? $input['words_raw'] : '';
        $lines     = preg_split( '/\r\n|\r|\n/', $words_raw );
        $words     = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( '' === $line ) {
                continue;
            }

            // 格式: 英文|中文
            $parts = array_map( 'trim', explode( '|', $line, 2 ) );
            if ( count( $parts ) === 2 ) {
                $words[] = array(
                    'en' => $parts[0],
                    'zh' => $parts[1],
                );
            }
        }

        $output['words'] = $words;

        return $output;
    }

    public <span class="hljs-keyword">function render_page() </span>{
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings = self::get_settings();

        // 轉回 textarea 用的格式
        $words_raw_lines = array();
        foreach ( $settings['words'] as $word ) {
            $words_raw_lines[] = $word['en'] . '|' . $word['zh'];
        }
        $words_raw = implode( "\n", $words_raw_lines );
        ?>
        <div <span class="hljs-keyword">class="wrap">
            <h1><?php esc_html_e( '每日學習閃卡設定', 'wp-daily-flashcard' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'wpdf_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="wpdf_api_key"><?php esc_html_e( 'OpenAI API Key', 'wp-daily-flashcard' ); ?></label>
                        </th>
                        <td>
                            <input
                                name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]"
                                type="password"
                                id="wpdf_api_key"
                                value="<?php echo esc_attr( $settings['api_key'] ); ?>"
                                class="regular-text"
                            />
                            <p class="description">
                                <?php esc_html_e( '用於產生圖片的 OpenAI API 金鑰。', 'wp-daily-flashcard' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wpdf_order"><?php esc_html_e( '文字排序', 'wp-daily-flashcard' ); ?></label>
                        </th>
                        <td>
                            <select
                                name="<?php echo esc_attr( self::OPTION_KEY ); ?>[order]"
                                id="wpdf_order"
                            >
                                <option value="en_zh" <?php selected( $settings['order'], 'en_zh' ); ?>>
                                    <?php esc_html_e( '英文在上，中文在下', 'wp-daily-flashcard' ); ?>
                                </option>
                                <option value="zh_en" <?php selected( $settings['order'], 'zh_en' ); ?>>
                                    <?php esc_html_e( '中文在上，英文在下', 'wp-daily-flashcard' ); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="wpdf_words_raw"><?php esc_html_e( '單字清單', 'wp-daily-flashcard' ); ?></label>
                        </th>
                        <td>
                            <textarea
                                name="<?php echo esc_attr( self::OPTION_KEY ); ?>[words_raw]"
                                id="wpdf_words_raw"
                                rows="10"
                                cols="50"
                                class="large-text code"
                            ><?php echo esc_textarea( $words_raw ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( '每行一組，格式為「英文|中文」。例如：Apple|蘋果', 'wp-daily-flashcard' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
</span>