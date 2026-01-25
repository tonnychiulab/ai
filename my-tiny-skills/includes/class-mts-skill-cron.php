<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MTS_Skill_Cron {

	public function render() {
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			echo '<p>' . esc_html__( 'No cron events found.', 'my-tiny-skills' ) . '</p>';
			return;
		}

		$events = array();
		foreach ( $crons as $time => $cron ) {
			foreach ( $cron as $hook => $dings ) {
				foreach ( $dings as $sig => $data ) {
					$events[] = array(
						'hook'     => $hook,
						'time'     => $time,
						'next_run' => $time - time(), // seconds relative to now
						'schedule' => $data['schedule'],
						'args'     => $data['args'],
					);
				}
			}
		}

		// Sort by time
		usort( $events, function( $a, $b ) {
			return $a['time'] - $b['time'];
		} );
		?>
		
		<div class="mts-column" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Scheduled Cron Events', 'my-tiny-skills' ); ?></h2>
			<table class="mts-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Hook Name', 'my-tiny-skills' ); ?></th>
						<th><?php esc_html_e( 'Next Run', 'my-tiny-skills' ); ?></th>
						<th><?php esc_html_e( 'Recurrence', 'my-tiny-skills' ); ?></th>
						<th><?php esc_html_e( 'Arguments', 'my-tiny-skills' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $events as $event ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $event['hook'] ); ?></strong></td>
						<td>
							<?php 
							if ( $event['next_run'] < 0 ) {
								echo '<span class="mts-badge mts-badge-red">' . esc_html( human_time_diff( $event['time'] ) . ' ago' ) . '</span>';
							} else {
								echo '<span class="mts-badge mts-badge-green">' . esc_html( 'In ' . human_time_diff( $event['time'] ) ) . '</span>';
							}
							?>
							<br/><small><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $event['time'] ) ); ?></small>
						</td>
						<td>
							<?php echo $event['schedule'] ? esc_html( $event['schedule'] ) : '<span class="mts-badge mts-badge-gray">One-off</span>'; ?>
						</td>
						<td>
							<?php 
							if ( ! empty( $event['args'] ) ) {
								echo '<code style="font-size:10px;">' . esc_html( json_encode( $event['args'] ) ) . '</code>';
							} else {
								echo '-';
							}
							?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
