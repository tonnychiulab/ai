<div class="wrap">
    <h1>AI Plugin Builder</h1>
    
    <?php if ( isset( $_GET['status'] ) && 'success' === $_GET['status'] ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Plugin generation started! (Mock Mode: Slug <?php echo esc_html( $_GET['slug'] ); ?>)</p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="generate_plugin">
        <?php wp_nonce_field( 'ai_plugin_builder_action', 'ai_plugin_builder_nonce' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="plugin_prompt">Describe your Plugin</label></th>
                <td>
                    <textarea name="plugin_prompt" id="plugin_prompt" rows="8" cols="80" class="large-text code" placeholder="Example: Create a testimonial slider plugin with a shortcode [testimonials]..."></textarea>
                    
                    <div style="margin-top: 10px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <button type="button" class="button ai-brainstorm-btn" id="ai-brainstorm-btn">
                            <span class="dashicons dashicons-lightbulb" style="line-height: inherit;"></span> AI Surprise Me!
                        </button>
                        <span class="spinner" id="ai-spinner" style="float: none; margin: 0;"></span>
                    </div>

                    <div style="margin-top: 10px;">
                        <strong>Templates:</strong>
                        <button type="button" class="button button-small template-btn" data-prompt="Create a simple plugin that adds a shortcode [hello_world] which outputs 'Hello World from AI!' in a styled div.">Hello World</button>
                        <button type="button" class="button button-small template-btn" data-prompt="Create an SMTP Debugger plugin. It should have an admin page to enter SMTP credentials (host, port, user, pass) and a button to send a test email to the admin email.">SMTP Debugger</button>
                        <button type="button" class="button button-small template-btn" data-prompt="Create a Portfolio plugin. Register a Custom Post Type 'portfolio' with a taxonomy 'project_type'. Support thumbnail and excerpt.">Portfolio CPT</button>
                        <button type="button" class="button button-small template-btn" data-prompt="Create a floating WhatsApp button plugin. Frontend should load a fixed position button. Admin page to set the phone number and welcome message.">WhatsApp Button</button>
                        <button type="button" class="button button-small template-btn" data-prompt="Create a Dashboard Widget plugin that displays the server's PHP version, Memory Limit, and current Database Size in the WP Admin Dashboard.">System Info Widget</button>
                    </div>

                    <p class="description">The AI will use your local SKILL.md standards to generate the code.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="api_key">OpenAI API Key</label></th>
                <td>
                    <?php if ( defined( 'AI_PLUGIN_BUILDER_KEY' ) ) : ?>
                        <input type="password" value="hiddenkey" class="regular-text" disabled>
                        <p class="description" style="color: green;">✔ API Key loaded securely from wp-config.php</p>
                    <?php else : ?>
                        <input type="password" name="api_key" id="api_key" class="regular-text" placeholder="sk-...">
                        <p class="description">Leave empty to use Mock Mode (or define <code>AI_PLUGIN_BUILDER_KEY</code> in wp-config.php).</p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Generate Plugin' ); ?>
    </form>
    
    <hr>
    
    <h2>📜 Generation History</h2>
    <?php
    $history = get_option( 'ai_plugin_builder_history', [] );
    if ( empty( $history ) ) :
    ?>
        <p>No plugins generated yet.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th scope="col" style="width: 60px;">ID</th>
                    <th scope="col">Plugin Name</th>
                    <th scope="col">Slug</th>
                    <th scope="col" style="width: 180px;">Created At</th>
                    <th scope="col" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Default limit to 5, unless 'show_all' is requested
                $limit = isset( $_GET['show_all'] ) ? count( $history ) : 5;
                $display_items = array_slice( $history, 0, $limit );
                
                foreach ( $display_items as $item ) :
                ?>
                    <tr>
                        <td>#<?php echo esc_html( $item['id'] ); ?></td>
                        <td>
                            <strong><?php echo esc_html( $item['name'] ); ?></strong>
                            <br>
                            <span class="description" title="<?php echo esc_attr( $item['prompt'] ); ?>">
                                <?php echo esc_html( mb_strimwidth( $item['prompt'], 0, 50, '...' ) ); ?>
                            </span>
                        </td>
                        <td><code><?php echo esc_html( $item['slug'] ); ?></code></td>
                        <td><?php echo esc_html( $item['created_at'] ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" class="button button-small">Manage</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ( count( $history ) > 5 && ! isset( $_GET['show_all'] ) ) : ?>
            <p class="description">Showing recent 5 items. <a href="<?php echo esc_url( add_query_arg( 'show_all', '1' ) ); ?>">Show All</a></p>
        <?php endif; ?>
    <?php endif; ?>
    
    <script>
    jQuery(document).ready(function($) {
        // Quick Start Templates
        $('.template-btn').on('click', function() {
            var prompt = $(this).data('prompt');
            $('#plugin_prompt').val(prompt).focus();
        });

        // AI Brainstorm
        $('#ai-brainstorm-btn').on('click', function() {
            var $btn = $(this);
            var $spinner = $('#ai-spinner');
            var apiKey = $('#api_key').val();

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');

            $.post(ajaxurl, {
                action: 'ai_plugin_brainstorm',
                nonce: '<?php echo wp_create_nonce( 'ai_plugin_brainstorm_action' ); ?>',
                api_key: apiKey
            }, function(response) {
                if (response.success) {
                    // Typewriter effect (optional, or just plain val)
                    $('#plugin_prompt').val(response.data).focus();
                } else {
                    alert('Error: ' + response.data);
                }
            }).fail(function() {
                alert('Request failed.');
            }).always(function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            });
        });
        
        // Auto-select API key field if empty and not defined
        if ($('#api_key').length && $('#api_key').val() === '') {
            // Check if not mock mode logic... simplified
        }
    });
    </script>
</div>
