<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IC_Assets {
    public function __construct() {
        add_action( 'admin_menu', function() {
            add_menu_page('InsightCosmos', 'InsightCosmos', 'manage_options', 'ic-app', array($this, 'render'), 'dashicons-networking');
        });
        add_action( 'admin_enqueue_scripts', array($this, 'enqueue'));
    }

    public function render() { 
        // 這裡直接寫好 HTML 結構，不需要 React 渲染
        ?>
        <div class="wrap ic-admin-wrap">
            <h1><?php esc_html_e( 'Insight Cosmos Dashboard', 'insight-cosmos' ); ?></h1>
            <div id="ic-container">
                <div id="ic-canvas"></div>
                <div id="ic-side-panel">
                    <div id="ic-panel-content">
                        <p><?php esc_html_e( 'Select a node to view details', 'insight-cosmos' ); ?></p>
                    </div>
                </div>
            </div>
            <button id="ic-add-node" class="button button-primary"><?php esc_html_e( '+ Add Node', 'insight-cosmos' ); ?></button>
        </div>
        <?php
    }

    public function enqueue($hook) {
        if ('toplevel_page_ic-app' !== $hook) return;

        // 加載 Cytoscape 庫 (你可以從官網下載 cytoscape.min.js 放到 assets 夾)
        wp_enqueue_script('ic-cytoscape', IC_URL . 'assets/cytoscape.min.js', array(), '3.26.0', true);
        
        // 加載我們的邏輯
        wp_enqueue_script('ic-main-js', IC_URL . 'assets/script.js', array('ic-cytoscape'), IC_VERSION, true);
        wp_enqueue_style('ic-main-css', IC_URL . 'assets/style.css', array(), IC_VERSION);

        // 注入 API 資訊
        wp_localize_script('ic-main-js', 'icConfig', array(
            'root'  => esc_url_raw(rest_url('ic/v1')),
            'nonce' => wp_create_nonce('wp_rest')
        ));
    }
}