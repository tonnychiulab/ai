<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IC_Settings {

    private $option_group = 'ic_settings_group';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // AJAX Hooks for verification
        add_action( 'wp_ajax_ic_verify_openai', array( $this, 'ajax_verify_openai' ) );
        add_action( 'wp_ajax_ic_verify_search', array( $this, 'ajax_verify_search' ) );

        // AJAX Hooks for Manual Agents
        add_action( 'wp_ajax_ic_run_agent', array( $this, 'ajax_run_agent' ) );
    }

    public function add_settings_page() {
        // Add submenu under InsightCosmos
        add_submenu_page(
            'ic-app',
            __( 'Settings', 'insight-cosmos' ),
            __( 'Settings', 'insight-cosmos' ),
            'manage_options',
            'ic-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( $this->option_group, 'ic_openai_key', array(
            'sanitize_callback' => 'sanitize_text_field' 
        ));
        register_setting( $this->option_group, 'ic_search_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting( $this->option_group, 'ic_rss_feeds', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => "https://feeds.feedburner.com/TechCrunch\nhttps://newsletter.pragmaticengineer.com/feed"
        ));
    }

    public function ajax_verify_openai() {
        check_ajax_referer( 'ic_settings_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'insight-cosmos' ) );
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        if ( empty( $api_key ) ) wp_send_json_error( __( 'Key is empty', 'insight-cosmos' ) );

        $response = wp_remote_get( 'https://api.openai.com/v1/models', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $api_key ),
            'timeout' => 10
        ));

        if ( is_wp_error( $response ) ) {
            /* translators: %s: Error message from OpenAI API */
            wp_send_json_error( sprintf( __( 'Connection failed: %s', 'insight-cosmos' ), $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 200 ) {
            wp_send_json_success( __( 'Valid Key!', 'insight-cosmos' ) );
        } else {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $msg = $body['error']['message'] ?? __( 'Invalid Key', 'insight-cosmos' );
            wp_send_json_error( $msg );
        }
    }

    public function ajax_verify_search() {
        check_ajax_referer( 'ic_settings_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'insight-cosmos' ) );
        
        $api_key = isset($_POST['api_key']) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        if ( empty( $api_key ) ) wp_send_json_error( __( 'Key is empty', 'insight-cosmos' ) );

        // Try Serper first (most likely given the context)
        $response = wp_remote_post( 'https://google.serper.dev/search', array(
            'headers' => array( 
                'X-API-KEY' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode( array( 'q' => 'test' ) ),
            'timeout' => 10
        ));
        
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
             wp_send_json_success( __( 'Valid Serper Key!', 'insight-cosmos' ) );
        }
        
        // If Serper failed, we could try Google API if needed, but error handling is tricky without knowing which one they intend.
        // For now, we return the Serper error or generic.
        
        $msg = __( 'Validation failed. tested against Serper.dev endpoints.', 'insight-cosmos' );
        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            /* translators: %s: HTTP response code */
            $msg .= " (" . sprintf( __( 'Response Code: %s', 'insight-cosmos' ), $code ) . ")";
        }
        
        wp_send_json_error( $msg );
    }

    public function ajax_run_agent() {
        check_ajax_referer( 'ic_settings_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( __( 'Permission denied', 'insight-cosmos' ) );

        $agent_type = isset($_POST['agent']) ? sanitize_text_field( wp_unslash( $_POST['agent'] ) ) : '';

        // Load classes if not already loaded (though they should be via main file)
        if ( ! class_exists( 'IC_Scout' ) ) require_once IC_PATH . 'includes/agents/class-ic-scout.php';
        if ( ! class_exists( 'IC_Analyst' ) ) require_once IC_PATH . 'includes/agents/class-ic-analyst.php';
        if ( ! class_exists( 'IC_Curator' ) ) require_once IC_PATH . 'includes/agents/class-ic-curator.php';

        // Clear previous logs
        IC_Agent::clear_logs();

        try {
            switch ($agent_type) {
                case 'scout':
                    $agent = new IC_Scout();
                    $agent->run();
                    $logs = IC_Agent::get_logs();
                    wp_send_json_success( array(
                        'message' => __( 'Scout run completed successfully.', 'insight-cosmos' ),
                        'logs'    => $logs
                    ));
                    break;
                case 'analyst':
                    $agent = new IC_Analyst();
                    $agent->run();
                    $logs = IC_Agent::get_logs();
                    wp_send_json_success( array(
                        'message' => __( 'Analyst run completed successfully.', 'insight-cosmos' ),
                        'logs'    => $logs
                    ));
                    break;
                case 'curator':
                    $agent = new IC_Curator();
                    $agent->run();
                    $logs = IC_Agent::get_logs();
                    wp_send_json_success( array(
                        'message' => __( 'Curator run completed successfully.', 'insight-cosmos' ),
                        'logs'    => $logs
                    ));
                    break;
                default:
                    wp_send_json_error( __( 'Unknown agent type.', 'insight-cosmos' ) );
            }
        } catch (Exception $e) {
            $logs = IC_Agent::get_logs();
            /* translators: %s: Exception message */
            wp_send_json_error( array(
                'message' => sprintf( __( 'Error: %s', 'insight-cosmos' ), $e->getMessage() ),
                'logs'    => $logs
            ));
        }
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'InsightCosmos Settings', 'insight-cosmos' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( $this->option_group ); ?>
                <?php do_settings_sections( $this->option_group ); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'OpenAI API Key', 'insight-cosmos' ); ?></th>
                        <td>
                            <div style="display:flex; align-items:center; gap: 10px;">
                                <input type="password" id="ic_openai_key" name="ic_openai_key" value="<?php echo esc_attr( get_option('ic_openai_key') ); ?>" class="regular-text" required />
                                <button type="button" class="button button-secondary" id="btn-verify-openai"><?php esc_html_e( 'Test Key', 'insight-cosmos' ); ?></button>
                                <span id="msg-openai" style="font-weight:bold;"></span>
                            </div>
                            <p class="description">
                                <?php esc_html_e( 'Required for Analyst Agent (LLM Analysis).', 'insight-cosmos' ); ?><br/>
                                <a href="https://platform.openai.com/api-keys" target="_blank" class="button button-secondary" style="margin-top:5px;">
                                    <span class="dashicons dashicons-external" style="line-height:28px;"></span> <?php esc_html_e( 'Get OpenAI API Key', 'insight-cosmos' ); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Search API Key (Google/Serper)', 'insight-cosmos' ); ?></th>
                        <td>
                            <div style="display:flex; align-items:center; gap: 10px;">
                                <input type="password" id="ic_search_api_key" name="ic_search_api_key" value="<?php echo esc_attr( get_option('ic_search_api_key') ); ?>" class="regular-text" />
                                <button type="button" class="button button-secondary" id="btn-verify-search"><?php esc_html_e( 'Test Key', 'insight-cosmos' ); ?></button>
                                <span id="msg-search" style="font-weight:bold;"></span>
                            </div>
                            <p class="description">
                                <?php esc_html_e( 'Optional. Used by Scout Agent for web search.', 'insight-cosmos' ); ?><br/>
                                <a href="https://serper.dev/" target="_blank" class="button button-secondary" style="margin-top:5px; margin-right: 5px;">
                                    <span class="dashicons dashicons-external" style="line-height:28px;"></span> <?php esc_html_e( 'Get Serper Key', 'insight-cosmos' ); ?>
                                </a>
                                <a href="https://developers.google.com/custom-search/v1/overview" target="_blank" class="button button-small" style="margin-top:5px; vertical-align:middle;">
                                    <?php esc_html_e( 'Or Google API', 'insight-cosmos' ); ?>
                                </a>
                            </p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'RSS Feeds (One per line)', 'insight-cosmos' ); ?></th>
                        <td>
                            <textarea name="ic_rss_feeds" rows="10" cols="50" class="large-text"><?php echo esc_textarea( get_option('ic_rss_feeds') ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Scout Agent will monitor these feeds daily.', 'insight-cosmos' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr style="margin-top: 30px;">
            
            <h2><?php esc_html_e( 'Manual Operations (Testing)', 'insight-cosmos' ); ?></h2>
            <p><?php esc_html_e( 'Manually trigger agents to test functionality.', 'insight-cosmos' ); ?></p>
            <div style="display:flex; gap: 10px;">
                <button type="button" class="button button-secondary" id="btn-run-scout"><?php esc_html_e( 'Run Scout (Fetch Feeds)', 'insight-cosmos' ); ?></button>
                <button type="button" class="button button-secondary" id="btn-run-analyst" disabled><?php esc_html_e( 'Run Analyst (AI Analysis)', 'insight-cosmos' ); ?></button>
                <button type="button" class="button button-secondary" id="btn-run-curator" disabled><?php esc_html_e( 'Run Curator (Generate Report)', 'insight-cosmos' ); ?></button>
            </div>
            <div id="agent-msg" style="margin-top: 15px; font-weight: bold; min-height: 20px;"></div>

            <h3 style="margin-top: 20px;"><?php esc_html_e( 'Debug Logs', 'insight-cosmos' ); ?></h3>
            <pre id="debug-logs" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto; font-size: 12px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"><?php esc_html_e( 'Click a button above to see debug output...', 'insight-cosmos' ); ?></pre>

            <script>
            jQuery(document).ready(function($) {
                var nonce = '<?php echo esc_js( wp_create_nonce( "ic_settings_nonce" ) ); ?>';

                function verify(btn, input, action, msgSpan) {
                    var key = $(input).val();
                    if(!key) { alert('<?php echo esc_js( __( 'Please enter a key first', 'insight-cosmos' ) ); ?>'); return; }
                    
                    $(btn).text('<?php echo esc_js( __( 'Verifying...', 'insight-cosmos' ) ); ?>').prop('disabled', true);
                    $(msgSpan).text('').css('color', '');
                    
                    $.post(ajaxurl, {
                        action: action,
                        api_key: key,
                        nonce: nonce
                    }, function(res) {
                        $(btn).text('<?php echo esc_js( __( 'Test Key', 'insight-cosmos' ) ); ?>').prop('disabled', false);
                        if(res.success) {
                            $(msgSpan).text('✔ ' + res.data).css('color', 'green');
                        } else {
                            $(msgSpan).text('✘ ' + res.data).css('color', 'red');
                        }
                    });
                }

                function runAgent(btn, agent) {
                    var originalText = $(btn).text();
                    $(btn).text('<?php echo esc_js( __( 'Running...', 'insight-cosmos' ) ); ?>').prop('disabled', true);
                    $('#agent-msg').text('').css('color', '');
                    $('#debug-logs').text('<?php echo esc_js( __( 'Running...', 'insight-cosmos' ) ); ?>');

                    $.post(ajaxurl, {
                        action: 'ic_run_agent',
                        agent: agent,
                        nonce: nonce
                    }, function(res) {
                        $(btn).text(originalText).prop('disabled', false);

                        // Display logs
                        var logs = '';
                        if (res.data && res.data.logs && res.data.logs.length > 0) {
                            logs = res.data.logs.join('\n');
                        } else if (res.data && typeof res.data.logs !== 'undefined') {
                            logs = '<?php echo esc_js( __( 'No logs recorded.', 'insight-cosmos' ) ); ?>';
                        }

                        if(res.success) {
                            var msg = res.data.message || res.data;
                            $('#agent-msg').text('✔ ' + msg).css('color', 'green');
                            $('#debug-logs').text(logs || '<?php echo esc_js( __( 'Completed with no logs.', 'insight-cosmos' ) ); ?>');

                            // Sequential Unlock Logic
                            if (agent === 'scout') {
                                $('#btn-run-analyst').prop('disabled', false);
                            } else if (agent === 'analyst') {
                                $('#btn-run-curator').prop('disabled', false);
                            }
                        } else {
                            var errMsg = res.data.message || res.data;
                            $('#agent-msg').text('✘ ' + errMsg).css('color', 'red');
                            $('#debug-logs').text(logs || '<?php echo esc_js( __( 'Error occurred. Check server logs.', 'insight-cosmos' ) ); ?>');
                        }
                    }).fail(function(xhr, status, error) {
                        $(btn).text(originalText).prop('disabled', false);
                        $('#agent-msg').text('✘ <?php echo esc_js( __( 'Server Error', 'insight-cosmos' ) ); ?>').css('color', 'red');
                        $('#debug-logs').text('AJAX Error: ' + status + ' - ' + error + '\n\nResponse: ' + xhr.responseText);
                    });
                }

                $('#btn-verify-openai').click(function() { verify(this, '#ic_openai_key', 'ic_verify_openai', '#msg-openai'); });
                $('#btn-verify-search').click(function() { verify(this, '#ic_search_api_key', 'ic_verify_search', '#msg-search'); });

                $('#btn-run-scout').click(function() { runAgent(this, 'scout'); });
                $('#btn-run-analyst').click(function() { runAgent(this, 'analyst'); });
                $('#btn-run-curator').click(function() { runAgent(this, 'curator'); });
            });
            </script>
        </div>
        <?php
    }
}
