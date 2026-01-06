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
 * Handles user profile customizations in the admin.
 */
class User_Profile extends Singleton {

	/**
	 * Display OAuth2 profile data in user profile page.
	 *
	 * @param WP_User $user User object.
	 * @return void
	 */
	public function show_oauth2_profile_fields( $user ) {
		// Check if this user has OAuth2 data.
		$authenticated_by = get_user_meta( $user->ID, 'authenticated_by', true );
		if ( 'oauth2' !== $authenticated_by ) {
			return;
		}

		?>
		<h2><?php esc_html_e( 'Microsoft 365 Profile Data', 'authorizer' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			// Get all OAuth2-related user meta.
			$oauth2_meta = $this->get_oauth2_user_meta( $user->ID );

			if ( empty( $oauth2_meta ) ) {
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'authorizer' ); ?></th>
					<td>
						<p><?php esc_html_e( 'No Microsoft 365 profile data synchronized yet.', 'authorizer' ); ?></p>
					</td>
				</tr>
				<?php
			} else {
				// Display photo if available.
				$photo_url = get_user_meta( $user->ID, 'oauth2_profile_photo_url', true );
				if ( ! empty( $photo_url ) ) {
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Profile Photo', 'authorizer' ); ?></th>
						<td>
							<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php esc_attr_e( 'Profile Photo', 'authorizer' ); ?>" style="max-width: 150px; height: auto; border-radius: 4px;" />
							<p class="description">
								<?php
								$synced_at = get_user_meta( $user->ID, 'oauth2_profile_photo_synced_at', true );
								if ( ! empty( $synced_at ) ) {
									/* translators: %s: formatted date/time */
									printf( esc_html__( 'Last synced: %s', 'authorizer' ), esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $synced_at ) ) );
								}
								?>
							</p>
						</td>
					</tr>
					<?php
				}

				// Display groups if available.
				$groups_json = get_user_meta( $user->ID, 'oauth2_group_names', true );
				if ( ! empty( $groups_json ) ) {
					$groups = json_decode( $groups_json, true );
					if ( is_array( $groups ) && ! empty( $groups ) ) {
						?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Microsoft 365 Groups', 'authorizer' ); ?></th>
							<td>
								<ul style="margin: 0;">
									<?php foreach ( $groups as $group_name ) : ?>
										<li><?php echo esc_html( $group_name ); ?></li>
									<?php endforeach; ?>
								</ul>
								<p class="description">
									<?php
									$synced_at = get_user_meta( $user->ID, 'oauth2_groups_synced_at', true );
									if ( ! empty( $synced_at ) ) {
										/* translators: %s: formatted date/time */
										printf( esc_html__( 'Last synced: %s', 'authorizer' ), esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $synced_at ) ) );
									}
									?>
								</p>
							</td>
						</tr>
						<?php
					}
				}

				// Display profile fields in a collapsible section.
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Profile Fields', 'authorizer' ); ?></th>
					<td>
						<details style="margin-bottom: 10px;">
							<summary style="cursor: pointer; font-weight: 600;">
								<?php
								/* translators: %d: number of fields */
								printf( esc_html__( 'View %d synchronized fields', 'authorizer' ), count( $oauth2_meta ) );
								?>
							</summary>
							<table class="widefat" style="margin-top: 10px;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Field Name', 'authorizer' ); ?></th>
										<th><?php esc_html_e( 'Value', 'authorizer' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $oauth2_meta as $key => $value ) : ?>
										<tr>
											<td><code><?php echo esc_html( $key ); ?></code></td>
											<td><?php echo esc_html( $this->format_meta_value( $value ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</details>
						<p class="description">
							<?php
							$synced_at = get_user_meta( $user->ID, 'oauth2_profile_fields_synced_at', true );
							if ( ! empty( $synced_at ) ) {
								/* translators: %s: formatted date/time */
								printf( esc_html__( 'Last synced: %s', 'authorizer' ), esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $synced_at ) ) );
							}
							?>
						</p>
					</td>
				</tr>
				<?php
			}
			?>
		</table>

		<p>
			<button type="button" id="auth-refresh-ms365-profile" class="button button-secondary" data-user-id="<?php echo esc_attr( $user->ID ); ?>">
				<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
				<?php esc_html_e( 'Refresh Microsoft 365 Profile Data', 'authorizer' ); ?>
			</button>
			<span id="auth-refresh-status" style="margin-left: 10px;"></span>
		</p>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#auth-refresh-ms365-profile').on('click', function() {
				var $button = $(this);
				var $status = $('#auth-refresh-status');
				var userId = $button.data('user-id');

				// Disable button and show loading.
				$button.prop('disabled', true);
				$button.find('.dashicons').addClass('dashicons-update-spin');
				$status.html('<span style="color: #999;"><?php esc_html_e( 'Refreshing profile data...', 'authorizer' ); ?></span>');

				// Send AJAX request.
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'authorizer_refresh_ms365_profile',
						user_id: userId,
						nonce: '<?php echo esc_js( wp_create_nonce( 'authorizer_refresh_profile_' . $user->ID ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$status.html('<span style="color: #46b450;"><span class="dashicons dashicons-yes"></span> ' + response.data.message + '</span>');
							// Reload page after 2 seconds to show updated data.
							setTimeout(function() {
								location.reload();
							}, 2000);
						} else {
							$status.html('<span style="color: #dc3232;"><span class="dashicons dashicons-warning"></span> ' + response.data.message + '</span>');
							$button.prop('disabled', false);
							$button.find('.dashicons').removeClass('dashicons-update-spin');
						}
					},
					error: function() {
						$status.html('<span style="color: #dc3232;"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'An error occurred. Please try again.', 'authorizer' ); ?></span>');
						$button.prop('disabled', false);
						$button.find('.dashicons').removeClass('dashicons-update-spin');
					}
				});
			});
		});
		</script>
		<?php
	}


	/**
	 * Get all OAuth2-related user meta for display.
	 *
	 * @param int $user_id User ID.
	 * @return array Associative array of meta keys and values.
	 */
	private function get_oauth2_user_meta( $user_id ) {
		$all_meta   = get_user_meta( $user_id );
		$oauth2_meta = array();

		// Fields to exclude from display.
		$exclude = array(
			'oauth2_access_token',
			'oauth2_refresh_token',
			'oauth2_token_expires',
			'oauth2_token_saved_at',
			'oauth2_profile_photo_attachment_id',
			'oauth2_profile_photo_url',
			'oauth2_profile_photo_synced_at',
			'oauth2_profile_fields_synced_at',
			'oauth2_groups_synced_at',
			'oauth2_server_id',
			'oauth2_groups',
			'oauth2_group_names',
		);

		foreach ( $all_meta as $key => $values ) {
			// Only include oauth2_ prefixed fields that aren't in exclusion list.
			if ( strpos( $key, 'oauth2_' ) === 0 && ! in_array( $key, $exclude, true ) ) {
				$oauth2_meta[ $key ] = is_array( $values ) && count( $values ) === 1 ? $values[0] : $values;
			}
		}

		return $oauth2_meta;
	}


	/**
	 * Format meta value for display.
	 *
	 * @param mixed $value Meta value.
	 * @return string Formatted value.
	 */
	private function format_meta_value( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', $value );
		}

		// Try to decode JSON.
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return implode( ', ', $decoded );
		}

		// Truncate very long values.
		if ( strlen( $value ) > 100 ) {
			return substr( $value, 0, 100 ) . '...';
		}

		return $value;
	}
}
