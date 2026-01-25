<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MTS_Skill_Constants {

	public function render() {
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
		<?php
	}

	private function get_constants_list() {
		return array(
			// Debugging
			'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG', 'SAVEQUERIES',
			
			// Database
			'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'DB_COLLATE',
			
			// URLs & Paths
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
			
			// Keys
			'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
		);
	}
}
