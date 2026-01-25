<?php
/**
 * Plugin Name: My Tiny Skills
 * Description: A lightweight tool to inspect enabled and disabled wp-config.php constants.
 * Version: 1.0.0
 * Author: Tonny & Antigravity
 * License: GPLv2 or later
 * Text Domain: my-tiny-skills
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class My_Tiny_Skills {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_tools_page() {
		add_management_page(
			__( 'My Tiny Skills', 'my-tiny-skills' ),
			__( 'My Tiny Skills', 'my-tiny-skills' ),
			'manage_options',
			'my-tiny-skills',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'tools_page_my-tiny-skills' !== $hook ) {
			return;
		}

		// Register a virtual handle for inline styles to comply with enqueue standards
		wp_register_style( 'my-tiny-skills-css', false );
		wp_enqueue_style( 'my-tiny-skills-css' );
		
		$css = "
			.mts-container { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px; }
			.mts-column { flex: 1; min-width: 300px; background: #fff; border: 1px solid #ccd0d4; padding: 0; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
			.mts-column h2 { background: #f8f9fa; padding: 15px; margin: 0; border-bottom: 1px solid #ccd0d4; font-size: 1.2em; }
			.mts-list { list-style: none; margin: 0; padding: 0; }
			.mts-list li { padding: 10px 15px; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; }
			.mts-list li:last-child { border-bottom: none; }
			.mts-list li:hover { background: #fafafa; }
			.mts-val { font-family: monospace; background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-size: 0.9em; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
			.mts-enabled h2 { border-left: 4px solid #46b450; color: #46b450; }
			.mts-disabled h2 { border-left: 4px solid #dcdcde; color: #646970; }
			.mts-secure { color: #d63638; font-style: italic; }
		";
		wp_add_inline_style( 'my-tiny-skills-css', $css );
	}

	public function render_page() {
		// Strict Capability Check
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$constants = $this->get_constants_list();
		$enabled   = array();
		$disabled  = array();

		foreach ( $constants as $const ) {
			if ( defined( $const ) ) {
				$val = constant( $const );
				
				// Security: Mask sensitive data
				if ( in_array( $const, array( 'DB_PASSWORD', 'FTP_PASS', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ), true ) ) {
					$val = '********';
				}
				// Format boolean
				elseif ( is_bool( $val ) ) {
					$val = $val ? 'TRUE' : 'FALSE';
				}
				// Format empty/null
				elseif ( '' === $val ) {
					$val = '""';
				}
				
				$enabled[ $const ] = $val;
			} else {
				$disabled[] = $const;
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'My Tiny Skills - Constant Inspector', 'my-tiny-skills' ); ?></h1>
			
			<div class="mts-container">
				<!-- Enabled Column -->
				<div class="mts-column mts-enabled">
					<h2>
						<?php esc_html_e( 'Enabled Constants', 'my-tiny-skills' ); ?> 
						(<?php echo count( $enabled ); ?>)
					</h2>
					<ul class="mts-list">
						<?php foreach ( $enabled as $name => $value ) : ?>
							<li>
								<strong><?php echo esc_html( $name ); ?></strong>
								<span class="mts-val" title="<?php echo esc_attr( is_scalar( $value ) ? $value : gettype( $value ) ); ?>">
									<?php echo esc_html( is_scalar( $value ) ? $value : gettype( $value ) ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<!-- Disabled Column -->
				<div class="mts-column mts-disabled">
					<h2>
						<?php esc_html_e( 'Disabled / Default', 'my-tiny-skills' ); ?>
						(<?php echo count( $disabled ); ?>)
					</h2>
					<ul class="mts-list">
						<?php foreach ( $disabled as $name ) : ?>
							<li>
								<span style="color: #646970;"><?php echo esc_html( $name ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	private function get_constants_list() {
		return array(
			// Debugging
			'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG', 'SAVEQUERIES',
			
			// Database
			'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'DB_COLLATE',
			
			// URLs & Paths (Often not in wp-config but good to check if overridden)
			'WP_HOME', 'WP_SITEURL', 'WP_CONTENT_DIR', 'WP_CONTENT_URL', 
			'WP_PLUGIN_DIR', 'WP_PLUGIN_URL', 'UPLOADS',
			
			// Memory
			'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT',
			
			// Cache
			'WP_CACHE', 'WP_CACHE_KEY_SALT',
			
			// Security & Updates
			'DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS', 'FORCE_SSL_ADMIN',
			'AUTOMATIC_UPDATER_DISABLED', 'WP_AUTO_UPDATE_CORE',
			
			// Content
			'WP_POST_REVISIONS', 'AUTOSAVE_INTERVAL', 'EMPTY_TRASH_DAYS',
			'WP_CRON_LOCK_TIMEOUT', 'DISABLE_WP_CRON', 'ALTERNATE_WP_CRON',
			
			// Multisite
			'WP_ALLOW_MULTISITE', 'MULTISITE', 'SUBDOMAIN_INSTALL', 'DOMAIN_CURRENT_SITE',
			
			// Keys (Existence check mainly)
			'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
		);
	}
}

new My_Tiny_Skills();
