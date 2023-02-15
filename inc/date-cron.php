<?php
/**
 * This file adds a cron job that removes events that have ended and creates new events for recurring events.
 */

// A custom hook for the cron.
add_action( 'reev_cron_hook', 'reev_cron_exec' );

// The actual cron function.
if ( ! wp_next_scheduled( 'reev_cron_hook' ) ) {
	wp_schedule_event( time(), 'daily', 'reev_cron_hook' );
}

register_deactivation_hook( __FILE__, 'reev_deactivate' );

function reev_deactivate() {
	$timestamp = wp_next_scheduled( 'reev_cron_hook' );
	wp_unschedule_event( $timestamp, 'reev_cron_hook' );
}

function reev_cron_exec() {
	$events = get_posts(
		array(
			'post_type'      => 'event',
			'posts_per_page' => -1,
		)
	);

	foreach ( $events as $event ) {
		$event_date = get_field( 'starting_date', $event->ID );
		$event_date = strtotime( $event_date );
		$today      = strtotime( 'today' );
		$diff       = $event_date - $today;
		$days       = floor( $diff / ( 60 * 60 * 24 ) );

		$event_ends = get_field( 'ending_date', $event->ID );
		$event_ends = strtotime( $event_ends );
		$diff_ends  = $event_date - $today;
		$days_ends  = floor( $diff_ends / ( 60 * 60 * 24 ) );

		file_put_contents( WP_PLUGIN_DIR . '/recurring-events/log.txt', $event->post_title . ' is in ' . $days . " days.\n", FILE_APPEND );

		if ( $days_ends < 0 ) {
			file_put_contents( WP_PLUGIN_DIR . '/recurring-events/log.txt', $event->post_title . ' has ended and will be deleted.\n', FILE_APPEND );
			wp_delete_post( $event->ID, true );
		}

		if ( floatval( 0 ) === $days ) {
			$recurring = get_field( 'recurring', $event->ID );

			if ( $recurring ) {
				$frequency = get_field( 'frequency', $event->ID );
				file_put_contents( WP_PLUGIN_DIR . '/recurring-events/log.txt', $event->post_title . " is a recurring event and happens $frequency.\n", FILE_APPEND );

				switch( $frequency ) {
					case 'daily':
						$days = 1;
						break;
					case 'weekly':
						$days = 7;
						break;
					case 'monthly':
						$days = 30;
						break;
				}

				$next_date = strtotime( "+$days days", $event_date );
				$next_ending_date = strtotime( "+$days days", $event_ends );
				file_put_contents( WP_PLUGIN_DIR . '/recurring-events/log.txt', $event->post_title . ' next date is ' . date( 'd m Y', $next_date ) . ".\n", FILE_APPEND );

				wp_insert_post(
					array(
						'post_type'   => 'event',
						'post_title'  => $event->post_title . ' generated by cron',
						'post_status' => 'publish',
						'meta_input'  => array(
							'starting_date' => $next_date,
							'ending_date'   => $next_ending_date,
							'recurring'     => $recurring,
							'frequency'     => $frequency,
						),
					)
				);
			}
		}
	}

	file_put_contents( WP_PLUGIN_DIR . '/recurring-events/log.txt', "End of cron.\n\n", FILE_APPEND );
}
