<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MTS_Skill_Options {

	public function render() {
		global $wpdb;
		
		// Query autoload options
		// Note: We use prepare simply to be safe, though no user input here.
		// Note: Direct query used for real-time analysis, caching not desired here.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE autoload = 'yes'" );

		$total_size = 0;
		$options_data = array();

		foreach ( $results as $row ) {
			$length = strlen( $row->option_value );
			$total_size += $length;
			$options_data[] = array(
				'name'   => $row->option_name,
				'length' => $length,
				'value'  => $row->option_value // Keep for potential inspection or short preview
			);
		}

		// Sort by length DESC
		usort( $options_data, function( $a, $b ) {
			return $b['length'] - $a['length'];
		} );
		
		// Limit to top 50 for performance
		$top_options = array_slice( $options_data, 0, 50 );
		?>

		<div class="mts-container">
			<div class="mts-column">
				<h2><?php esc_html_e( 'Autoload Options Analysis', 'my-tiny-skills' ); ?></h2>
				<div style="padding: 15px;">
					<strong><?php esc_html_e( 'Total Autoload Size:', 'my-tiny-skills' ); ?></strong>
					<?php 
					$size_mb = number_format( $total_size / 1024 / 1024, 2 ); 
					$color_style = ( $total_size > 800000 ) ? 'color: #d63638;' : 'color: #46b450;';
					?>
					<span style="font-size: 1.5em; <?php echo esc_attr( $color_style ); ?>"><?php echo esc_html( $size_mb ); ?> MB</span>
					<p class="description">
						<?php esc_html_e( 'WordPress loads these options on EVERY page load. Ideally this should be under 800KB (0.8MB).', 'my-tiny-skills' ); ?>
					</p>
				</div>
				
				<table class="mts-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Option Name', 'my-tiny-skills' ); ?></th>
							<th><?php esc_html_e( 'Size (Bytes)', 'my-tiny-skills' ); ?></th>
							<th><?php esc_html_e( 'Severity', 'my-tiny-skills' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_options as $opt ) : ?>
						<tr>
							<td><?php echo esc_html( $opt['name'] ); ?></td>
							<td><?php echo number_format( $opt['length'] ); ?></td>
							<td>
								<?php 
								if ( $opt['length'] > 100000 ) { // > 100KB
									echo '<span class="mts-badge mts-badge-red">CRITICAL</span>';
								} elseif ( $opt['length'] > 10000 ) { // > 10KB
									echo '<span class="mts-badge mts-badge-gray">Warning</span>';
								} else {
									echo '<span class="mts-badge mts-badge-green">OK</span>';
								}
								?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
