<?php
/**
 * Plugin Name: WP Daily Flashcard
 * Description: 每天第一次登入後台時，顯示一張英文/正體中文對照的學習閃卡，並可透過 OpenAI 產生對應圖片。
 * Version:     0.1.0
 * Author:      Your Name
 * Text Domain: wp-daily-flashcard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPDF_VERSION', '0.1.0' );
define( 'WPDF_PLUGIN_FILE', __FILE__ );
define( 'WPDF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPDF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * 自動載入 includes 下的 class 檔案（簡單版）
 */
<span class="hljs-keyword">function wpdf_autoload_classes() </span>{
    require_once WPDF_PLUGIN_DIR . 'includes/class-wpdf-settings.php';
    require_once WPDF_PLUGIN_DIR . 'includes/class-wpdf-flashcard.php';
    require_once WPDF_PLUGIN_DIR . 'includes/class-wpdf-openai.php';
}
wpdf_autoload_classes();

/**
 * 啟用時初始化預設設定
 */
<span class="hljs-keyword">function wpdf_activate() </span>{
    $defaults = array(
        'api_key' => '',
        'order'   => 'en_zh', // en_zh or zh_en
        'words'   => array(
            array( 'en' => 'Apple', 'zh' => '蘋果' ),
            array( 'en' => 'Ocean', 'zh' => '海洋' ),
        ),
    );

    if ( ! get_option( 'wpdf_settings' ) ) {
        add_option( 'wpdf_settings', $defaults );
    }
}
register_activation_hook( __FILE__, 'wpdf_activate' );

/**
 * 啟動外掛功能
 */
<span class="hljs-keyword">function wpdf_init_plugin() </span>{
    if ( is_admin() ) {
        new WPDF_Settings();
        new WPDF_Flashcard();
    }
}
add_action( 'plugins_loaded', 'wpdf_init_plugin' );