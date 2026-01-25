<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MTS_Admin {

	private $tabs = array();

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->init_tabs();
	}

	private function init_tabs() {
		$this->tabs = array(
			'constants' => array(
				'label' => __( 'Constants', 'my-tiny-skills' ),
				'class' => 'MTS_Skill_Constants',
			),
			'cron'      => array(
				'label' => __( 'WP Cron', 'my-tiny-skills' ),
				'class' => 'MTS_Skill_Cron',
			),
			'options'   => array(
				'label' => __( 'Options Health', 'my-tiny-skills' ),
				'class' => 'MTS_Skill_Options',
			),
			'source'    => array(
				'label' => __( 'The Source 🧠', 'my-tiny-skills' ),
				'class' => 'MTS_Skill_Source',
			),
		);
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

		// Enqueue separated CSS
		wp_enqueue_style( 
			'my-tiny-skills-css', 
			plugins_url( 'assets/css/style.css', dirname( __FILE__ ) ), // pointing to parent of includes
			array(), 
			'1.1.0' 
		);

		wp_enqueue_script(
			'my-tiny-skills-js',
			plugins_url( 'assets/js/mts-script.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			'1.1.0',
			true // in footer
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'constants';
		if ( ! array_key_exists( $current_tab, $this->tabs ) ) {
			$current_tab = 'constants';
		}

		?>
		<div class="wrap mts-wrap">
			<h1><?php esc_html_e( 'My Tiny Skills', 'my-tiny-skills' ); ?></h1>
			
			<nav class="nav-tab-wrapper">
				<?php foreach ( $this->tabs as $id => $data ) : ?>
					<a href="<?php echo esc_url( admin_url( 'tools.php?page=my-tiny-skills&tab=' . $id ) ); ?>" 
					   class="nav-tab <?php echo $current_tab === $id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $data['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="mts-content">
				<?php
				$class_name = $this->tabs[ $current_tab ]['class'];
				
				// Lazy load class file
				$file_name = 'class-mts-skill-' . $current_tab . '.php';
				$file_path = plugin_dir_path( __FILE__ ) . $file_name;
				
				if ( file_exists( $file_path ) ) {
					require_once $file_path;
					if ( class_exists( $class_name ) ) {
						$skill = new $class_name();
						$skill->render();
					} else {
						echo '<p class="mts-error">' . esc_html__( 'Error: Class not found.', 'my-tiny-skills' ) . '</p>';
					}
				} else {
					echo '<p class="mts-error">' . esc_html__( 'Error: Skill file not found.', 'my-tiny-skills' ) . '</p>';
				}
				?>
			</div>
		</div>
		<?php
	}
}
