<?php
/**
 * Plugin Name: Copyright & QR Code Standards
 * Plugin URI:  https://github.com/yourusername/copyright-qr-standards
 * Description: Automatically adds copyright notice and QR Code to the bottom of single posts. Fully compliant with WordPress Coding Standards.
 * Version:     1.0.1
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: copyright-qr-standards
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.0
 */

// 防止直接存取
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 主類別
 * 注意：類別名稱與檔案名稱結構對應，通過 FileName 檢查
 */
class Copyright_Qr_Standards {

    /**
     * 建構函式
     */
    public function __construct() {
        // WordPress 4.6+ 自動載入語言包，不需手動呼叫 load_plugin_textdomain
        
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_filter( 'the_content', array( $this, 'add_content_elements' ) );
    }

    /**
     * 註冊並載入 CSS
     * 使用 filemtime() 作為版本號，確保更新後使用者能看到最新樣式
     */
    public function enqueue_styles() {
        if ( is_single() ) {
            $css_file_path = plugin_dir_path( __FILE__ ) . 'assets/css/style.css';
            
            wp_enqueue_style(
                'copyright-qr-style', 
                plugin_dir_url( __FILE__ ) . 'assets/css/style.css', 
                array(), 
                file_exists( $css_file_path ) ? filemtime( $css_file_path ) : '1.0.1', 
                'all'
            );
        }
    }

    /**
     * 在內容後加入 HTML
     * 
     * @param string $content 原始文章內容
     * @return string 修改後的內容
     */
    public function add_content_elements( $content ) {
        // 檢查是否為單篇文章主迴圈
        if ( is_single() && in_the_loop() && is_main_query() ) {
            
            $post_url = get_permalink();
            $site_name = get_bloginfo( 'name' );
            
            // 使用 gmdate() 避免 RestrictedFunctions 警告
            $current_year = gmdate( 'Y' ); 
            
            $html_output = $this->get_html_output( $post_url, $site_name, $current_year );
            $content .= $html_output;
        }

        return $content;
    }

    /**
     * 產生 HTML
     */
    private function get_html_output( $url, $site_name, $year ) {
        // 使用 esc_url 確保 QR 來源安全
        $qr_src = esc_url( 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode( $url ) );
        
        ob_start();
        ?>
        <div class="cqrstd-wrapper">
            <div class="cqrstd-copyright">
                <h3 class="cqrstd-title"><?php echo esc_html__( 'Copyright Notice', 'copyright-qr-standards' ); ?></h3>
                <p>
                    <?php 
                    /* translators: 1: Site Name, 2: Year */
                    printf( 
                        esc_html__( 'This article was originally created by %1$s. Copyright &copy; %2$s %1$s. All rights reserved.', 'copyright-qr-standards' ), 
                        esc_html( $site_name ), 
                        esc_html( $year ) 
                    ); 
                    ?>
                </p>
                <p class="cqrstd-link">
                    <?php esc_html_e( 'Original Link:', 'copyright-qr-standards' ); ?> 
                    <a href="<?php echo esc_url( $url ); ?>" rel="nofollow"><?php echo esc_html( $url ); ?></a>
                </p>
            </div>
            
            <div class="cqrstd-qr-code">
                <p class="cqrstd-qr-label"><?php esc_html_e( 'Scan to read on mobile', 'copyright-qr-standards' ); ?></p>
                <img src="<?php echo esc_url( $qr_src ); ?>" alt="QR Code" class="cqrstd-img" loading="lazy">
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// 初始化
new Copyright_Qr_Standards();