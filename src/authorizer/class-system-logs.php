<?php
/**
 * Authorizer
 *
 * @license  GPL-2.0+
 * @link     https://github.com/uhm-coe/authorizer
 * @package  authorizer
 */

namespace Authorizer;

/**
 * Handles system logging for OAuth2/MS365 authentication events.
 */
class System_Logs extends Singleton {

	/**
	 * Database table name for storing logs.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Log detail level setting key.
	 *
	 * @var string
	 */
	const LOG_LEVEL_OPTION = 'auth_settings_system_log_level';

	/**
	 * Log detail levels.
	 */
	const LOG_LEVEL_NONE     = 'none';
	const LOG_LEVEL_BASIC    = 'basic';
	const LOG_LEVEL_DETAILED = 'detailed';
	const LOG_LEVEL_DEBUG    = 'debug';

	/**
	 * Initialize the System_Logs class.
	 */
	protected function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'authorizer_system_logs';
	}

	/**
	 * Create the system logs database table.
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			user_id bigint(20) DEFAULT NULL,
			user_email varchar(100) DEFAULT NULL,
			event_type varchar(50) NOT NULL,
			event_status varchar(20) NOT NULL,
			message text DEFAULT NULL,
			details longtext DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY event_time (event_time),
			KEY event_type (event_type),
			KEY event_status (event_status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log an authentication event.
	 *
	 * @param string $event_type   Event type (login_attempt, login_success, login_failure, token_acquired, profile_sync, etc.).
	 * @param string $event_status Event status (success, failure, error).
	 * @param string $message      Event message.
	 * @param array  $details      Additional details (will be JSON encoded).
	 * @param int    $user_id      WordPress user ID (optional).
	 * @param string $user_email   User email (optional).
	 * @return bool  Whether the log was saved.
	 */
	public function log_event( $event_type, $event_status, $message, $details = array(), $user_id = null, $user_email = null ) {
		global $wpdb;

		// Get current log level setting.
		$options   = Options::get_instance();
		$log_level = $options->get( self::LOG_LEVEL_OPTION, Helper::SINGLE_CONTEXT );

		// Check if logging is enabled.
		if ( self::LOG_LEVEL_NONE === $log_level ) {
			return false;
		}

		// Filter details based on log level.
		$filtered_details = $this->filter_details_by_level( $details, $log_level );

		// Get IP address and user agent.
		$ip_address = $this->get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Insert log entry.
		$result = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'      => $user_id,
				'user_email'   => $user_email,
				'event_type'   => $event_type,
				'event_status' => $event_status,
				'message'      => $message,
				'details'      => wp_json_encode( $filtered_details ),
				'ip_address'   => $ip_address,
				'user_agent'   => $user_agent,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Filter log details based on log level.
	 *
	 * @param array  $details   Log details.
	 * @param string $log_level Current log level.
	 * @return array Filtered details.
	 */
	private function filter_details_by_level( $details, $log_level ) {
		if ( self::LOG_LEVEL_DEBUG === $log_level ) {
			// Include everything.
			return $details;
		}

		if ( self::LOG_LEVEL_DETAILED === $log_level ) {
			// Exclude sensitive data but include most info.
			$exclude_keys = array( 'access_token', 'refresh_token', 'password', 'client_secret' );
			return $this->remove_keys( $details, $exclude_keys );
		}

		if ( self::LOG_LEVEL_BASIC === $log_level ) {
			// Only include basic info.
			$include_keys = array(
				'provider',
				'sync_photo',
				'sync_fields',
				'fields_count',
				'groups_count',
				'error_message',
				'error_code',
			);
			return $this->filter_keys( $details, $include_keys );
		}

		return array();
	}

	/**
	 * Remove specific keys from array recursively.
	 *
	 * @param array $data    Data array.
	 * @param array $exclude Keys to exclude.
	 * @return array Filtered array.
	 */
	private function remove_keys( $data, $exclude ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$result = array();
		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, $exclude, true ) ) {
				$result[ $key ] = is_array( $value ) ? $this->remove_keys( $value, $exclude ) : $value;
			}
		}
		return $result;
	}

	/**
	 * Filter array to only include specific keys.
	 *
	 * @param array $data    Data array.
	 * @param array $include Keys to include.
	 * @return array Filtered array.
	 */
	private function filter_keys( $data, $include ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$result = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $include, true ) ) {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle multiple IPs (X-Forwarded-For can contain multiple addresses).
				if ( strpos( $ip, ',' ) !== false ) {
					$ip_array = explode( ',', $ip );
					$ip       = trim( $ip_array[0] );
				}
				// Validate IP address.
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}

	/**
	 * Get logs from database with filtering and pagination.
	 *
	 * @param array $args Query arguments.
	 * @return array Logs.
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'       => 50,
			'offset'      => 0,
			'event_type'  => '',
			'event_status' => '',
			'user_id'     => '',
			'date_from'   => '',
			'date_to'     => '',
			'orderby'     => 'event_time',
			'order'       => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause.
		$where = array( '1=1' );

		if ( ! empty( $args['event_type'] ) ) {
			$where[] = $wpdb->prepare( 'event_type = %s', $args['event_type'] );
		}

		if ( ! empty( $args['event_status'] ) ) {
			$where[] = $wpdb->prepare( 'event_status = %s', $args['event_status'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'event_time >= %s', $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'event_time <= %s', $args['date_to'] );
		}

		$where_sql = implode( ' AND ', $where );

		// Build query.
		$query = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
		$query = $wpdb->prepare( $query, $args['limit'], $args['offset'] ); // phpcs:ignore

		$results = $wpdb->get_results( $query ); // phpcs:ignore

		return $results;
	}

	/**
	 * Get total count of logs matching criteria.
	 *
	 * @param array $args Query arguments.
	 * @return int Total count.
	 */
	public function get_logs_count( $args = array() ) {
		global $wpdb;

		// Build WHERE clause (same as get_logs).
		$where = array( '1=1' );

		if ( ! empty( $args['event_type'] ) ) {
			$where[] = $wpdb->prepare( 'event_type = %s', $args['event_type'] );
		}

		if ( ! empty( $args['event_status'] ) ) {
			$where[] = $wpdb->prepare( 'event_status = %s', $args['event_status'] );
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[] = $wpdb->prepare( 'user_id = %d', $args['user_id'] );
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[] = $wpdb->prepare( 'event_time >= %s', $args['date_from'] );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[] = $wpdb->prepare( 'event_time <= %s', $args['date_to'] );
		}

		$where_sql = implode( ' AND ', $where );

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_sql}" ); // phpcs:ignore

		return (int) $count;
	}

	/**
	 * Delete logs older than specified days.
	 *
	 * @param int $days Number of days to retain logs.
	 * @return int Number of deleted rows.
	 */
	public function delete_old_logs( $days = 30 ) {
		global $wpdb;

		$date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE event_time < %s", $date ) ); // phpcs:ignore

		return $deleted;
	}

	/**
	 * Clear all logs.
	 *
	 * @return bool Success.
	 */
	public function clear_all_logs() {
		global $wpdb;
		return false !== $wpdb->query( "TRUNCATE TABLE {$this->table_name}" ); // phpcs:ignore
	}

	/**
	 * Print the System Logs section info.
	 *
	 * @param array $args Section arguments.
	 * @return void
	 */
	public function print_section_info_system_logs( $args = '' ) {
		?>
		<div id="section_info_system_logs" class="section_info">
			<p><?php esc_html_e( 'View login activity and authentication events. Configure log detail level in Advanced settings.', 'authorizer' ); ?></p>
			<?php $this->render_logs_table(); ?>
		</div>
		<?php
	}

	/**
	 * Render the logs table with filters and pagination.
	 *
	 * @return void
	 */
	private function render_logs_table() {
		// Get filter parameters.
		$event_type   = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : ''; // phpcs:ignore
		$event_status = isset( $_GET['event_status'] ) ? sanitize_text_field( wp_unslash( $_GET['event_status'] ) ) : ''; // phpcs:ignore
		$paged        = isset( $_GET['log_page'] ) ? absint( $_GET['log_page'] ) : 1; // phpcs:ignore
		$per_page     = 20;

		// Get logs.
		$args = array(
			'limit'        => $per_page,
			'offset'       => ( $paged - 1 ) * $per_page,
			'event_type'   => $event_type,
			'event_status' => $event_status,
		);

		$logs        = $this->get_logs( $args );
		$total_logs  = $this->get_logs_count( $args );
		$total_pages = ceil( $total_logs / $per_page );

		?>
		<div class="authorizer-system-logs">
			<div class="logs-filters" style="margin-bottom: 20px;">
				<form method="get" action="">
					<input type="hidden" name="page" value="authorizer" />
					<input type="hidden" name="tab" value="system_logs" />

					<label for="event_type"><?php esc_html_e( 'Event Type:', 'authorizer' ); ?></label>
					<select name="event_type" id="event_type">
						<option value=""><?php esc_html_e( 'All Events', 'authorizer' ); ?></option>
						<option value="login_attempt" <?php selected( $event_type, 'login_attempt' ); ?>><?php esc_html_e( 'Login Attempts', 'authorizer' ); ?></option>
						<option value="login_success" <?php selected( $event_type, 'login_success' ); ?>><?php esc_html_e( 'Login Success', 'authorizer' ); ?></option>
						<option value="login_failure" <?php selected( $event_type, 'login_failure' ); ?>><?php esc_html_e( 'Login Failures', 'authorizer' ); ?></option>
						<option value="token_acquired" <?php selected( $event_type, 'token_acquired' ); ?>><?php esc_html_e( 'Token Acquired', 'authorizer' ); ?></option>
						<option value="profile_sync" <?php selected( $event_type, 'profile_sync' ); ?>><?php esc_html_e( 'Profile Sync', 'authorizer' ); ?></option>
						<option value="photo_sync" <?php selected( $event_type, 'photo_sync' ); ?>><?php esc_html_e( 'Photo Sync', 'authorizer' ); ?></option>
						<option value="groups_sync" <?php selected( $event_type, 'groups_sync' ); ?>><?php esc_html_e( 'Groups Sync', 'authorizer' ); ?></option>
					</select>

					<label for="event_status" style="margin-left: 15px;"><?php esc_html_e( 'Status:', 'authorizer' ); ?></label>
					<select name="event_status" id="event_status">
						<option value=""><?php esc_html_e( 'All Statuses', 'authorizer' ); ?></option>
						<option value="success" <?php selected( $event_status, 'success' ); ?>><?php esc_html_e( 'Success', 'authorizer' ); ?></option>
						<option value="failure" <?php selected( $event_status, 'failure' ); ?>><?php esc_html_e( 'Failure', 'authorizer' ); ?></option>
						<option value="error" <?php selected( $event_status, 'error' ); ?>><?php esc_html_e( 'Error', 'authorizer' ); ?></option>
					</select>

					<button type="submit" class="button" style="margin-left: 10px;"><?php esc_html_e( 'Filter', 'authorizer' ); ?></button>
					<a href="?page=authorizer&tab=system_logs" class="button" style="margin-left: 5px;"><?php esc_html_e( 'Clear Filters', 'authorizer' ); ?></a>
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 140px;"><?php esc_html_e( 'Time', 'authorizer' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Event Type', 'authorizer' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Status', 'authorizer' ); ?></th>
						<th style="width: 200px;"><?php esc_html_e( 'User', 'authorizer' ); ?></th>
						<th><?php esc_html_e( 'Message', 'authorizer' ); ?></th>
						<th style="width: 120px;"><?php esc_html_e( 'IP Address', 'authorizer' ); ?></th>
						<th style="width: 60px;"><?php esc_html_e( 'Details', 'authorizer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="7" style="text-align: center; padding: 20px;">
								<?php esc_html_e( 'No logs found.', 'authorizer' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->event_time ) ) ); ?></td>
								<td><?php echo esc_html( $this->format_event_type( $log->event_type ) ); ?></td>
								<td>
									<span class="log-status log-status-<?php echo esc_attr( $log->event_status ); ?>" style="
										padding: 3px 8px;
										border-radius: 3px;
										font-size: 11px;
										font-weight: 600;
										<?php
										if ( 'success' === $log->event_status ) {
											echo 'background: #d4edda; color: #155724;';
										} elseif ( 'failure' === $log->event_status ) {
											echo 'background: #f8d7da; color: #721c24;';
										} elseif ( 'error' === $log->event_status ) {
											echo 'background: #fff3cd; color: #856404;';
										}
										?>
									">
										<?php echo esc_html( ucfirst( $log->event_status ) ); ?>
									</span>
								</td>
								<td>
									<?php
									if ( ! empty( $log->user_id ) ) {
										$user = get_user_by( 'id', $log->user_id );
										if ( $user ) {
											echo '<a href="' . esc_url( get_edit_user_link( $log->user_id ) ) . '">' . esc_html( $user->user_login ) . '</a>';
										} else {
											echo esc_html( $log->user_email );
										}
									} elseif ( ! empty( $log->user_email ) ) {
										echo esc_html( $log->user_email );
									} else {
										echo '<em>' . esc_html__( 'N/A', 'authorizer' ) . '</em>';
									}
									?>
								</td>
								<td><?php echo esc_html( $log->message ); ?></td>
								<td><?php echo esc_html( $log->ip_address ); ?></td>
								<td>
									<?php if ( ! empty( $log->details ) && '[]' !== $log->details && '{}' !== $log->details ) : ?>
										<button type="button" class="button button-small view-log-details" data-details="<?php echo esc_attr( $log->details ); ?>">
											<?php esc_html_e( 'View', 'authorizer' ); ?>
										</button>
									<?php else : ?>
										<em><?php esc_html_e( 'None', 'authorizer' ); ?></em>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom" style="margin-top: 20px;">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo esc_html( sprintf( _n( '%s item', '%s items', $total_logs, 'authorizer' ), number_format_i18n( $total_logs ) ) ); ?></span>
						<span class="pagination-links">
							<?php
							$base_url = add_query_arg(
								array(
									'page'         => 'authorizer',
									'tab'          => 'system_logs',
									'event_type'   => $event_type,
									'event_status' => $event_status,
								),
								admin_url( 'admin.php' )
							);

							if ( $paged > 1 ) {
								echo '<a class="prev-page button" href="' . esc_url( add_query_arg( 'log_page', $paged - 1, $base_url ) ) . '">&lsaquo;</a>';
							} else {
								echo '<span class="tablenav-pages-navspan button disabled">&lsaquo;</span>';
							}

							echo '<span class="paging-input">';
							echo '<span class="tablenav-paging-text">' . esc_html( sprintf( __( '%1$s of %2$s', 'authorizer' ), number_format_i18n( $paged ), number_format_i18n( $total_pages ) ) ) . '</span>';
							echo '</span>';

							if ( $paged < $total_pages ) {
								echo '<a class="next-page button" href="' . esc_url( add_query_arg( 'log_page', $paged + 1, $base_url ) ) . '">&rsaquo;</a>';
							} else {
								echo '<span class="tablenav-pages-navspan button disabled">&rsaquo;</span>';
							}
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>

			<div id="log-details-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.5); z-index: 100000; max-width: 80%; max-height: 80%; overflow: auto;">
				<h3><?php esc_html_e( 'Log Details', 'authorizer' ); ?></h3>
				<pre id="log-details-content" style="background: #f5f5f5; padding: 15px; border-radius: 4px; overflow: auto; max-height: 400px;"></pre>
				<button type="button" class="button close-log-details"><?php esc_html_e( 'Close', 'authorizer' ); ?></button>
			</div>
			<div id="log-details-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99999;"></div>

			<script>
			jQuery(document).ready(function($) {
				$('.view-log-details').on('click', function() {
					var details = $(this).data('details');
					try {
						var parsed = JSON.parse(details);
						var formatted = JSON.stringify(parsed, null, 2);
						$('#log-details-content').text(formatted);
					} catch (e) {
						$('#log-details-content').text(details);
					}
					$('#log-details-modal').show();
					$('#log-details-overlay').show();
				});

				$('.close-log-details, #log-details-overlay').on('click', function() {
					$('#log-details-modal').hide();
					$('#log-details-overlay').hide();
				});
			});
			</script>
		</div>
		<?php
	}

	/**
	 * Format event type for display.
	 *
	 * @param string $event_type Event type.
	 * @return string Formatted event type.
	 */
	private function format_event_type( $event_type ) {
		$types = array(
			'login_attempt' => __( 'Login Attempt', 'authorizer' ),
			'login_success' => __( 'Login Success', 'authorizer' ),
			'login_failure' => __( 'Login Failure', 'authorizer' ),
			'token_acquired' => __( 'Token Acquired', 'authorizer' ),
			'profile_sync'  => __( 'Profile Sync', 'authorizer' ),
			'photo_sync'    => __( 'Photo Sync', 'authorizer' ),
			'groups_sync'   => __( 'Groups Sync', 'authorizer' ),
		);

		return isset( $types[ $event_type ] ) ? $types[ $event_type ] : ucwords( str_replace( '_', ' ', $event_type ) );
	}

	/**
	 * Print checkbox for enabling system logs.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function print_select_system_log_level( $args = '' ) {
		$options = Options::get_instance();
		$option  = $options->get( self::LOG_LEVEL_OPTION, Helper::get_context( $args ), 'allow override', 'print overlay' );

		if ( is_null( $option ) ) {
			$option = self::LOG_LEVEL_BASIC;
		}

		?>
		<select id="<?php echo esc_attr( self::LOG_LEVEL_OPTION ); ?>" name="auth_settings[<?php echo esc_attr( self::LOG_LEVEL_OPTION ); ?>]">
			<option value="<?php echo esc_attr( self::LOG_LEVEL_NONE ); ?>" <?php selected( $option, self::LOG_LEVEL_NONE ); ?>>
				<?php esc_html_e( 'None - Logging disabled', 'authorizer' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::LOG_LEVEL_BASIC ); ?>" <?php selected( $option, self::LOG_LEVEL_BASIC ); ?>>
				<?php esc_html_e( 'Basic - Login events and sync status', 'authorizer' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::LOG_LEVEL_DETAILED ); ?>" <?php selected( $option, self::LOG_LEVEL_DETAILED ); ?>>
				<?php esc_html_e( 'Detailed - Include profile data (tokens excluded)', 'authorizer' ); ?>
			</option>
			<option value="<?php echo esc_attr( self::LOG_LEVEL_DEBUG ); ?>" <?php selected( $option, self::LOG_LEVEL_DEBUG ); ?>>
				<?php esc_html_e( 'Debug - Full details including tokens (use with caution)', 'authorizer' ); ?>
			</option>
		</select>
		<p class="description">
			<?php
			printf(
				/* translators: %s: system logs tab link */
				esc_html__( 'Choose how detailed the system logs should be. View logs in the %s tab.', 'authorizer' ),
				'<a href="?page=authorizer&tab=system_logs">' . esc_html__( 'System Logs', 'authorizer' ) . '</a>'
			);
			?>
		</p>
		<?php
	}
}
