<?php
/**
 * Plugin Name: My Tiny Tools Forge (Stateless Edition)
 * Description: An AI-powered forge. API Keys are stored in your Browser (LocalStorage), never in the Database.
 * Version: 1.2.0
 * Author: WP 金魚腦 2號 (No-Goldfish-Brain)
 * Text Domain: my-tiny-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class NGB_Tiny_Tools_Forge {

    private $slug = 'my-tiny-tools';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_mtt_generate_tool', [ $this, 'ajax_handle_generation' ] );
    }

    /**
     * 1. Register Admin Menu
     */
    public function register_menu_page() {
        add_menu_page(
            'My Tiny Tools',
            'Tiny Tools Forge',
            'manage_options',
            $this->slug,
            [ $this, 'render_admin_page' ],
            'dashicons-hammer',
            99
        );
    }

    /**
     * 2. Enqueue Styles (Inline for portability)
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_' . $this->slug !== $hook ) {
            return;
        }

        $css = "
            .mtt-container { display: flex; height: calc(100vh - 140px); gap: 20px; margin-top: 20px; box-sizing: border-box; }
            .mtt-panel { flex: 1; display: flex; flex-direction: column; background: #fff; border: 1px solid #dcdcde; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px; border-radius: 4px; }
            .mtt-panel h2 { margin-top: 0; border-bottom: 2px solid #f0f0f1; padding-bottom: 10px; }
            .mtt-controls textarea { width: 100%; height: 120px; margin-bottom: 15px; font-family: monospace; border: 1px solid #8c8f94; }
            .mtt-preview-frame { width: 100%; height: 100%; border: none; background: #fafafa; }
            .mtt-loading { display: none; color: #d63638; font-weight: bold; margin-top: 10px; align-items: center; gap: 5px; }
            .mtt-code-output { margin-top: 20px; display: none; flex-grow: 1; }
            .mtt-code-output textarea { width: 100%; height: 100%; font-family: monospace; background: #23282d; color: #72aee6; font-size: 13px; padding: 10px; }
            /* Key Input Styling */
            .mtt-key-section { background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-left: 4px solid #2271b1; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
            .mtt-key-section input { width: 400px; }
            .mtt-badge { background: #e5e5e5; color: #50575e; padding: 2px 8px; border-radius: 4px; font-size: 10px; text-transform: uppercase; }
        ";
        wp_add_inline_style( 'common', $css );
    }

    /**
     * 3. Render Admin Page (LocalStorage Logic)
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'My Tiny Tools Forge 🔨', 'my-tiny-tools' ); ?> <span class="mtt-badge">Stateless Edition</span></h1>
            
            <div class="mtt-key-section">
                <label for="mtt_api_key"><strong>OpenAI API Key:</strong></label>
                <input type="password" id="mtt_api_key" class="regular-text" placeholder="sk-..." autocomplete="new-password" />
                <button type="button" id="mtt_save_key_btn" class="button button-secondary">Save to Browser</button>
                <button type="button" id="mtt_clear_key_btn" class="button button-link-delete">Clear</button>
                <span id="mtt_key_status" style="color: #008a20; display: none;">Saved!</span>
            </div>

            <div class="mtt-container">
                <div class="mtt-panel">
                    <h2>The Anvil (Input)</h2>
                    <div class="mtt-controls">
                        <p>What tiny tool shall we forge today?</p>
                        <textarea id="mtt_prompt" placeholder="e.g., A password strength visualizer with a cracking-time estimator..."></textarea>
                        
                        <button id="mtt_generate_btn" class="button button-primary button-hero">Forge It!</button>
                        <div class="mtt-loading">
                            <span class="spinner is-active" style="float:none; margin:0;"></span>
                            <span>The AI is hammering...</span>
                        </div>
                    </div>

                    <div class="mtt-code-output" id="mtt_code_container">
                        <h3>Forged Code</h3>
                        <textarea id="mtt_raw_code" readonly onclick="this.select()"></textarea>
                    </div>
                </div>

                <div class="mtt-panel" style="padding:0; overflow:hidden;">
                    <h2 style="margin: 20px 20px 0 20px;">The Showcase (Preview)</h2>
                    <div style="flex-grow:1; padding: 20px;">
                        <iframe id="mtt_preview" class="mtt-preview-frame" sandbox="allow-scripts allow-same-origin"></iframe>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            const LS_KEY = 'ngb_mtt_openai_key';
            const $keyInput = $('#mtt_api_key');

            // 1. Load Key from LocalStorage on init
            if (localStorage.getItem(LS_KEY)) {
                $keyInput.val(localStorage.getItem(LS_KEY));
            }

            // 2. Save Key
            $('#mtt_save_key_btn').on('click', function() {
                let key = $keyInput.val().trim();
                if(key) {
                    localStorage.setItem(LS_KEY, key);
                    $('#mtt_key_status').text('Saved to LocalStorage!').fadeIn().delay(2000).fadeOut();
                } else {
                    alert('Please enter a key.');
                }
            });

            // 3. Clear Key
            $('#mtt_clear_key_btn').on('click', function() {
                localStorage.removeItem(LS_KEY);
                $keyInput.val('');
                alert('Key removed from browser storage.');
            });

            // 4. Generate Logic
            $('#mtt_generate_btn').on('click', function(e) {
                e.preventDefault();
                let prompt = $('#mtt_prompt').val();
                let apiKey = $keyInput.val().trim(); // Get directly from input
                
                if(!apiKey) {
                    alert('Error: OpenAI API Key is missing. Please enter it above.');
                    return;
                }

                let $btn = $(this);
                let $loading = $('.mtt-loading');
                
                // UI State: Locked
                $btn.prop('disabled', true);
                $loading.css('display', 'flex');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mtt_generate_tool',
                        security: '<?php echo wp_create_nonce( 'mtt_forge_nonce' ); ?>', 
                        user_prompt: prompt,
                        api_key: apiKey // Send key transiently
                    },
                    success: function(response) {
                        if(response.success) {
                            let htmlContent = response.data.html;
                            $('#mtt_code_container').show();
                            $('#mtt_raw_code').val(htmlContent);
                            let blob = new Blob([htmlContent], {type: 'text/html'});
                            $('#mtt_preview').attr('src', URL.createObjectURL(blob));
                        } else {
                            // Detailed Error Alert
                            alert('Forge Failed: ' + JSON.stringify(response.data));
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('System Error: ' + error);
                        console.error(xhr);
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $loading.hide();
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * 4. AJAX Handler (Stateless Proxy)
     */
    public function ajax_handle_generation() {
        check_ajax_referer( 'mtt_forge_nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        // Retrieve Key from POST (Transient)
        $api_key = isset($_POST['api_key']) ? sanitize_text_field( $_POST['api_key'] ) : '';
        
        if ( empty( $api_key ) ) {
            wp_send_json_error( 'API Key is missing in request.' );
        }

        $user_prompt = sanitize_text_field( $_POST['user_prompt'] );
        if ( empty( $user_prompt ) ) {
            $user_prompt = "Create a useful web developer utility tool (HTML/JS/CSS) that WordPress lacks.";
        }

        $system_instruction = "You are a code generator. Output ONLY raw HTML code with inline CSS/JS. 
        NO markdown blocks. NO explanations. The code must be a single file.";

        $body = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $system_instruction],
                ['role' => 'user', 'content' => "Requirement: " . $user_prompt]
            ],
            'temperature' => 0.7
        ];

        // API Call
        $args = [
            'headers' => [ 
                'Authorization' => 'Bearer ' . $api_key, 
                'Content-Type' => 'application/json' 
            ],
            'body'    => json_encode( $body ),
            'timeout' => 60,
            'method'  => 'POST',
            'data_format' => 'body',
        ];

        $url = 'https://api.openai.com/v1/chat/completions';
        $response = wp_remote_post( $url, $args );

        // Robust Error Handling
        if ( is_wp_error( $response ) ) {
            // Return the specific WP Error message (e.g., "Invalid URL" or "cURL error")
            wp_send_json_error( 'WP Remote Error: ' . $response->get_error_message() );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $body_content  = wp_remote_retrieve_body( $response );

        if ( $response_code !== 200 ) {
            $error_data = json_decode( $body_content, true );
            $error_msg = $error_data['error']['message'] ?? 'Unknown OpenAI Error';
            wp_send_json_error( "API Error ($response_code): $error_msg" );
        }

        $data = json_decode( $body_content, true );
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        // Final Clean
        $content = preg_replace( '/^```html|```$/m', '', $content );

        if ( empty( $content ) ) {
            wp_send_json_error( 'AI returned empty content.' );
        }

        wp_send_json_success( [ 'html' => $content ] );
    }
}

new NGB_Tiny_Tools_Forge();