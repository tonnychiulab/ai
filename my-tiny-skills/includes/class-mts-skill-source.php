<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MTS_Skill_Source {

	public function render() {
		?>
		<div class="mts-container">
			<div class="mts-column mts-tribute-card">
				
				<div class="mts-tribute-header">
					<span class="dashicons dashicons-superhero" style="font-size: 64px; width: 64px; height: 64px; color: #2271b1;"></span>
					<h2><?php esc_html_e( 'The Agentic Origin', 'my-tiny-skills' ); ?></h2>
					<p class="mts-tribute-subtitle">
						<?php esc_html_e( 'Empowered by elvis / claude-wordpress-skills', 'my-tiny-skills' ); ?>
					</p>
				</div>

				<div class="mts-tribute-content">
					<p>
						<?php esc_html_e( 'This plugin was built using the advanced agentic coding standards pioneered in the "Claude WordPress Skills" project. It serves as a blueprint for high-quality, AI-assisted WordPress development.', 'my-tiny-skills' ); ?>
					</p>
					
					<a href="https://github.com/elvismdev/claude-wordpress-skills" target="_blank" class="button button-primary button-hero">
						<?php esc_html_e( 'View The Project on GitHub', 'my-tiny-skills' ); ?> 
						<span class="dashicons dashicons-external" style="line-height: 1.5;"></span>
					</a>
				</div>

				<hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

				<h3><?php esc_html_e( 'Core Skills Matrix', 'my-tiny-skills' ); ?></h3>
				
				<div class="mts-skills-grid">
					
					<div class="mts-skill-item">
						<span class="dashicons dashicons-shield-alt mts-icon"></span>
						<h4><?php esc_html_e( 'Security First', 'my-tiny-skills' ); ?></h4>
						<p><?php esc_html_e( 'Strict nonce, capability checks, and escaping.', 'my-tiny-skills' ); ?></p>
					</div>

					<div class="mts-skill-item">
						<span class="dashicons dashicons-yes-alt mts-icon"></span>
						<h4><?php esc_html_e( 'Code Quality', 'my-tiny-skills' ); ?></h4>
						<p><?php esc_html_e( 'Adherence to WordPress Coding Standards (WPCS).', 'my-tiny-skills' ); ?></p>
					</div>

					<div class="mts-skill-item">
						<span class="dashicons dashicons-performance mts-icon"></span>
						<h4><?php esc_html_e( 'Performance', 'my-tiny-skills' ); ?></h4>
						<p><?php esc_html_e( 'Optimized queries and asset loading.', 'my-tiny-skills' ); ?></p>
					</div>

					<div class="mts-skill-item">
						<span class="dashicons dashicons-layout mts-icon"></span>
						<h4><?php esc_html_e( 'Architecture', 'my-tiny-skills' ); ?></h4>
						<p><?php esc_html_e( 'Modular, Class-based, and Object-Oriented.', 'my-tiny-skills' ); ?></p>
					</div>

				</div>

			</div>
		</div>
		<?php
	}
}
