<?php
/**
 * Authorizer
 *
 * @license  GPL-2.0+
 * @link     https://github.com/uhm-coe/authorizer
 * @package  authorizer
 */

namespace Authorizer;

use Authorizer\Helper;
use Authorizer\Options;

/**
 * Implements the authorization (roles and permissions) features of the plugin.
 */
class Authorization extends Singleton {

	/**
	 * This function will fail with a wp_die() message to the user if they
	 * don't have access.
	 *
	 * @param WP_User $user        User to check.
	 * @param array   $user_emails Array of user's plaintext emails (in case current user doesn't have a WP account).
	 * @param array   $user_data   Array of keys for email, username, first_name, last_name, authenticated_by,
	 *                             and any of the following based on authentication method:
	 *                             google_attributes,
	 *                             cas_attributes, cas_server_id,
	 *                             ldap_attributes,
	 *                             oauth2_attributes, oauth2_provider, oauth2_server_id,
	 *                             oidc_attributes, oidc_server_id.
	 * @return WP_Error|WP_User
	 *                             WP_Error if there was an error on user creation / adding user to blog.
	 *                             WP_Error / wp_die() if user does not have access.
	 *                             WP_User if user has access.
	 */
	public function check_user_access( $user, $user_emails, $user_data = array() ) {
		// Grab plugin settings.
		$options                                    = Options::get_instance();
		$auth_settings                              = $options->get_all( Helper::SINGLE_CONTEXT, 'allow override' );
		$auth_settings_access_users_pending         = $options->sanitize_user_list(
			$options->get( 'access_users_pending', Helper::SINGLE_CONTEXT )
		);
		$auth_settings_access_users_approved_single = $options->get( 'access_users_approved', Helper::SINGLE_CONTEXT );
		$auth_settings_access_users_approved_multi  = $options->get( 'access_users_approved', Helper::NETWORK_CONTEXT );
		$auth_settings_access_users_approved        = $options->sanitize_user_list(
			array_merge(
				$auth_settings_access_users_approved_single,
				$auth_settings_access_users_approved_multi
			)
		);

		// If this is an existing user, update which external service authenticated
		// them.
		if ( $user && ! empty( $user_data['authenticated_by'] ) ) {
			update_user_meta( $user->ID, 'authenticated_by', $user_data['authenticated_by'] );
		}

		// Get whether to update first/last name on login from the external service
		// used to authenticate this user.
		$attr_update_on_login = '';
		if ( ! empty( $user_data['authenticated_by'] ) ) {
			$attr_update_on_login_key = '';
			if ( 'cas' === $user_data['authenticated_by'] ) {
				$attr_update_on_login_key = empty( $user_data['cas_server_id'] ) || 1 === intval( $user_data['cas_server_id'] ) ? 'cas_attr_update_on_login' : 'cas_attr_update_on_login_' . $user_data['cas_server_id'];
			} elseif ( 'ldap' === $user_data['authenticated_by'] ) {
				$attr_update_on_login_key = 'ldap_attr_update_on_login';
			} elseif ( 'oauth2' === $user_data['authenticated_by'] ) {
				$attr_update_on_login_key = empty( $user_data['oauth2_server_id'] ) || 1 === intval( $user_data['oauth2_server_id'] ) ? 'oauth2_attr_update_on_login' : 'oauth2_attr_update_on_login_' . $user_data['oauth2_server_id'];
			} elseif ( 'oidc' === $user_data['authenticated_by'] ) {
				$attr_update_on_login_key = empty( $user_data['oidc_server_id'] ) || 1 === intval( $user_data['oidc_server_id'] ) ? 'oidc_attr_update_on_login' : 'oidc_attr_update_on_login_' . $user_data['oidc_server_id'];
			}
			if ( ! empty( $attr_update_on_login_key ) ) {
				$attr_update_on_login = ! empty( $auth_settings[ $attr_update_on_login_key ] ) ? $auth_settings[ $attr_update_on_login_key ] : '';
			}
		}

		// Detect whether this user's first and last name should be updated below
		// (if the external service provides a different value, the option is set to
		// update it, and it's empty if the option to only set it if empty is
		// enabled).
		$should_update_first_name =
			$user && ! empty( $user_data['first_name'] ) && $user_data['first_name'] !== $user->first_name &&
			( '1' === $attr_update_on_login || ( 'update-if-empty' === $attr_update_on_login && empty( $user->first_name ) ) );

		$should_update_last_name =
			$user && ! empty( $user_data['last_name'] ) && $user_data['last_name'] !== $user->last_name &&
			( '1' === $attr_update_on_login || ( 'update-if-empty' === $attr_update_on_login && empty( $user->last_name ) ) );

		/**
		 * Filter whether to block the currently logging in user based on any of
		 * their user attributes.
		 *
		 * @param bool $allow_login Whether to block the currently logging in user.
		 * @param array $user_data User data returned from external service.
		 */
		$allow_login       = apply_filters( 'authorizer_allow_login', true, $user_data );
		$blocked_by_filter = ! $allow_login; // Use this for better readability.

		// Check our externally authenticated user against the block list.
		// If any of their email addresses are blocked, set the relevant user
		// meta field, and show them an error screen.
		foreach ( $user_emails as $user_email ) {
			if ( $blocked_by_filter || $this->is_email_in_list( $user_email, 'blocked' ) ) {

				// Add user to blocked list if it was blocked via the filter.
				if ( $blocked_by_filter && ! $this->is_email_in_list( $user_email, 'blocked' ) ) {
					$auth_settings_access_users_blocked = $options->sanitize_user_list(
						$options->get( 'access_users_blocked', Helper::SINGLE_CONTEXT )
					);
					array_push(
						$auth_settings_access_users_blocked,
						array(
							'email'      => Helper::lowercase( $user_email ),
							'date_added' => wp_date( 'M Y' ),
						)
					);
					update_option( 'auth_settings_access_users_blocked', $auth_settings_access_users_blocked, false );
				}

				// If the blocked external user has a WordPress account, mark it as
				// blocked (enforce block in this->authenticate()).
				if ( $user ) {
					update_user_meta( $user->ID, 'auth_blocked', 'yes' );
				}

				// Allow overriding the message blocked users see after logging in.
				if ( defined( 'AUTHORIZER_LOGIN_MESSAGE_BLOCKED_USERS' ) ) {
					$auth_settings['access_blocked_redirect_to_message'] = \AUTHORIZER_LOGIN_MESSAGE_BLOCKED_USERS;
				}
				/**
				 * Filters the message blocked users see after logging in.
				 *
				 * @since 3.12.0
				 *
				 * @param string $message The message content.
				 */
				$auth_settings['access_blocked_redirect_to_message'] = apply_filters( 'authorizer_login_message_blocked_users', $auth_settings['access_blocked_redirect_to_message'] );

				// Notify user about blocked status and return without authenticating them.
				// phpcs:ignore WordPress.Security.NonceVerification
				$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : home_url();
				$page_title  = sprintf(
					/* TRANSLATORS: %s: Name of blog */
					__( '%s - Access Restricted', 'authorizer' ),
					get_bloginfo( 'name' )
				);
				$error_message =
					apply_filters( 'the_content', $auth_settings['access_blocked_redirect_to_message'] ) .
					'<hr />' .
					'<p style="text-align: center;">' .
					'<a class="button" href="' . wp_logout_url( $redirect_to ) . '">' .
					__( 'Back', 'authorizer' ) .
					'</a></p>';
				update_option( 'auth_settings_advanced_login_error', $error_message, false );
				wp_die( wp_kses( $error_message, Helper::$allowed_html ), esc_html( $page_title ) );
				return new \WP_Error( 'invalid_login', __( 'Invalid login attempted.', 'authorizer' ) );
			}
		}

		// Get the default role for this user (or their current role, if they
		// already have an account).
		$default_role = $user && is_array( $user->roles ) && count( $user->roles ) > 0 ? $user->roles[0] : $auth_settings['access_default_role'];
		/**
		 * Filter the role of the user currently logging in. The role will be
		 * set to the default (specified in Authorizer options) for new users,
		 * or the user's current role for existing users. This filter allows
		 * changing user roles based on custom CAS/LDAP attributes.
		 *
		 * @param string $role                      Role of the user currently logging in.
		 * @param array  $user_data                 User data returned from external service.
		 * @param WP_User|false|null|WP_Error $user User object if logging in user exists.
		 *
		 * @return string|array Role of the user currently logging in, or an array with keys 'default_role' (string), 'roles_to_add' (array), and 'roles_to_remove' (array) if support for multiple roles is desired.
		 */
		$approved_role = apply_filters( 'authorizer_custom_role', $default_role, $user_data, $user );

		// Support for multiple roles if supplied in the filter above. Note: this
		// only has partial support for multisite (it will only add/remove roles
		// to the current blog, not all blogs).
		$roles_to_add    = empty( $approved_role['roles_to_add'] ) ? array() : $approved_role['roles_to_add'];
		$roles_to_remove = empty( $approved_role['roles_to_remove'] ) ? array() : $approved_role['roles_to_remove'];
		if ( ! empty( $approved_role['default_role'] ) ) {
			$approved_role = $approved_role['default_role'];
		}

		/**
		 * Filter whether to automatically approve the currently logging in user
		 * based on any of their user attributes.
		 *
		 * @param bool                        $automatically_approve_login
		 *   Whether to automatically approve the currently logging in user.
		 * @param array                       $user_data User data returned from external service.
		 * @param WP_User|false|null|WP_Error $user      User object if logging in user exists.
		 */
		$automatically_approve_login = apply_filters( 'authorizer_automatically_approve_login', false, $user_data, $user );

		// If this externally-authenticated user is an existing administrator (admin
		// in single site mode, or super admin in network mode), and isn't blocked,
		// let them in. Update their first/last name if needed.
		if ( $user && is_super_admin( $user->ID ) ) {
			if ( $should_update_first_name ) {
				update_user_meta( $user->ID, 'first_name', $user_data['first_name'] );
			}
			if ( $should_update_last_name ) {
				update_user_meta( $user->ID, 'last_name', $user_data['last_name'] );
			}

			return $user;
		}

		// Iterate through each of the email addresses provided by the external
		// service and determine if any of them have access.
		$last_email = end( $user_emails );
		reset( $user_emails );
		foreach ( $user_emails as $user_email ) {
			$is_newly_approved_user = false;

			// If this externally authenticated user isn't in the approved list
			// and login access is set to "All authenticated users," or if they were
			// automatically approved in the "authorizer_approve_login" filter
			// above, then add them to the approved list (they'll get an account
			// created below if they don't have one yet).
			if (
				! $this->is_email_in_list( $user_email, 'approved' ) &&
				( 'external_users' === $auth_settings['access_who_can_login'] || $automatically_approve_login )
			) {
				$is_newly_approved_user = true;

				// If this user happens to be in the pending list (rare),
				// remove them from pending before adding them to approved.
				if ( $this->is_email_in_list( $user_email, 'pending' ) ) {
					foreach ( $auth_settings_access_users_pending as $key => $pending_user ) {
						if ( 0 === strcasecmp( $pending_user['email'], $user_email ) ) {
							unset( $auth_settings_access_users_pending[ $key ] );
							update_option( 'auth_settings_access_users_pending', $auth_settings_access_users_pending, false );
							break;
						}
					}
				}

				// Add this user to the approved list.
				$approved_user = array(
					'email'      => Helper::lowercase( $user_email ),
					'role'       => $approved_role,
					'date_added' => wp_date( 'Y-m-d H:i:s' ),
				);
				array_push( $auth_settings_access_users_approved, $approved_user );
				array_push( $auth_settings_access_users_approved_single, $approved_user );
				update_option( 'auth_settings_access_users_approved', $auth_settings_access_users_approved_single, false );
			}

			// Check our externally authenticated user against the approved
			// list. If they are approved, log them in (and create their account
			// if necessary).
			if ( $is_newly_approved_user || $this->is_email_in_list( $user_email, 'approved' ) ) {
				$user_info = $is_newly_approved_user ? $approved_user : Helper::get_user_info_from_list( $user_email, $auth_settings_access_users_approved );

				// If this user's role was modified above (in the authorizer_custom_role
				// filter), update the role in the approved list and use that role
				// (i.e., if the roles are out of sync, use the authorizer_custom_role
				// value instead of the role in the approved list).
				if ( has_filter( 'authorizer_custom_role' ) ) {
					$user_info['role'] = $approved_role;

					// Find the user in either the single site or multisite approved list
					// and update their role there if different.
					foreach ( $auth_settings_access_users_approved_single as $index => $auth_settings_access_user_approved_single ) {
						if ( $user_info['email'] === $auth_settings_access_user_approved_single['email'] ) {
							if ( $auth_settings_access_users_approved_single[ $index ]['role'] !== $approved_role ) {
								$auth_settings_access_users_approved_single[ $index ]['role'] = $approved_role;
								update_option( 'auth_settings_access_users_approved', $auth_settings_access_users_approved_single, false );
							}
							break;
						}
					}
					if ( is_multisite() ) {
						foreach ( $auth_settings_access_users_approved_multi as $index => $auth_settings_access_user_approved_multi ) {
							if ( $user_info['email'] === $auth_settings_access_user_approved_multi['email'] ) {
								if ( $auth_settings_access_users_approved_multi[ $index ]['role'] !== $approved_role ) {
									$auth_settings_access_users_approved_multi[ $index ]['role'] = $approved_role;
									update_blog_option( get_main_site_id( get_main_network_id() ), 'auth_multisite_settings_access_users_approved', $auth_settings_access_users_approved_multi );
								}
								break;
							}
						}
					}
				}

				// If the approved external user does not have a WordPress account, create it.
				if ( ! $user ) {
					if ( array_key_exists( 'username', $user_data ) ) {
						$username = $user_data['username'];
					} else {
						$username = explode( '@', $user_info['email'] );
						$username = $username[0];
					}
					// If there's already a user with this username (e.g.,
					// johndoe/johndoe@gmail.com exists, and we're trying to add
					// johndoe/johndoe@example.com), use the full email address
					// as the username.
					if ( get_user_by( 'login', $username ) !== false ) {
						$username = $user_info['email'];
					}
					$result = wp_insert_user(
						array(
							'user_login'      => strtolower( $username ),
							'user_pass'       => wp_generate_password(), // random password.
							'first_name'      => array_key_exists( 'first_name', $user_data ) ? $user_data['first_name'] : '',
							'last_name'       => array_key_exists( 'last_name', $user_data ) ? $user_data['last_name'] : '',
							'user_email'      => Helper::lowercase( $user_info['email'] ),
							'user_registered' => wp_date( 'Y-m-d H:i:s' ),
							'role'            => $user_info['role'],
						)
					);

					// Fail with message if error.
					if ( is_wp_error( $result ) || 0 === $result ) {
						return $result;
					}

					// Authenticate as new user.
					$user = new \WP_User( $result );

					/**
					 * Fires after an external user is authenticated for the first time
					 * and a new WordPress account is created for them.
					 *
					 * @since 2.8.0
					 *
					 * @param WP_User $user      User object.
					 * @param array   $user_data User data from external service.
					 *
					 * Example $user_data:
					 * array(
					 *   'email'            => 'user@example.edu',
					 *   'username'         => 'user',
					 *   'first_name'       => 'First',
					 *   'last_name'        => 'Last',
					 *   'authenticated_by' => 'cas',
					 *   'cas_attributes'   => array( ... ),
					 * );
					 */
					do_action( 'authorizer_user_register', $user, $user_data );

					// Save which external service authenticated this new user to user meta.
					if ( $user && ! empty( $user_data['authenticated_by'] ) ) {
						update_user_meta( $user->ID, 'authenticated_by', $user_data['authenticated_by'] );
					}

					// Store OAuth2 token and sync profile data if enabled.
					if ( $user && 'oauth2' === $user_data['authenticated_by'] && 'azure' === $user_data['oauth2_provider'] ) {
						$this->handle_oauth2_token_and_profile_sync( $user, $user_data, $auth_settings );
					}

					// If multisite, iterate through all sites in the network and add the user
					// currently logging in to any of them that have the user on the approved list.
					// Note: this is useful for first-time logins--some users will have access
					// to multiple sites, and this prevents them from having to log into each
					// site individually to get access.
					if ( is_multisite() ) {
						$site_ids_of_user = array_map(
							function ( $site_of_user ) {
								return intval( $site_of_user->userblog_id );
							},
							get_blogs_of_user( $user->ID )
						);

						// phpcs:ignore WordPress.WP.DeprecatedFunctions.wp_get_sitesFound
						$sites = function_exists( 'get_sites' ) ? get_sites() : wp_get_sites( array( 'limit' => PHP_INT_MAX ) );
						foreach ( $sites as $site ) {
							$blog_id = function_exists( 'get_sites' ) ? $site->blog_id : $site['blog_id'];

							// Skip if user is already added to this site.
							if ( in_array( intval( $blog_id ), $site_ids_of_user, true ) ) {
								continue;
							}

							// Check if user is on the approved list of this site they are not added to.
							$other_auth_settings_access_users_approved = get_blog_option( $blog_id, 'auth_settings_access_users_approved', array() );
							if ( Helper::in_multi_array( $user->user_email, $other_auth_settings_access_users_approved ) ) {
								$other_user_info = Helper::get_user_info_from_list( $user->user_email, $other_auth_settings_access_users_approved );
								// Add user to other site.
								add_user_to_blog( $blog_id, $user->ID, $other_user_info['role'] );
							}
						}
					}

					// Check if this new user has any preassigned usermeta
					// values in their approved list entry, and apply them to
					// their new WordPress account.
					if ( array_key_exists( 'usermeta', $user_info ) && is_array( $user_info['usermeta'] ) ) {
						$meta_key = $options->get( 'advanced_usermeta' );

						if ( array_key_exists( 'meta_key', $user_info['usermeta'] ) && array_key_exists( 'meta_value', $user_info['usermeta'] ) ) {
							// Only update the usermeta if the stored value matches
							// the option set in authorizer settings (if they don't
							// match it's probably old data).
							if ( $meta_key === $user_info['usermeta']['meta_key'] ) {
								// Update user's usermeta value for usermeta key stored in authorizer options.
								if ( strpos( $meta_key, 'acf___' ) === 0 && class_exists( 'acf' ) ) {
									// We have an ACF field value, so use the ACF function to update it.
									update_field( str_replace( 'acf___', '', $meta_key ), $user_info['usermeta']['meta_value'], 'user_' . $user->ID );
								} else {
									// We have a normal usermeta value, so just update it via the WordPress function.
									update_user_meta( $user->ID, $meta_key, $user_info['usermeta']['meta_value'] );
								}
							}
						} elseif ( is_multisite() && count( $user_info['usermeta'] ) > 0 ) {
							// Update usermeta for each multisite blog defined for this user.
							foreach ( $user_info['usermeta'] as $blog_id => $usermeta ) {
								if ( array_key_exists( 'meta_key', $usermeta ) && array_key_exists( 'meta_value', $usermeta ) ) {
									// Add this new user to the blog before we create their user meta (this step typically happens below, but we need it to happen early so we can create user meta here).
									if ( ! is_user_member_of_blog( $user->ID, $blog_id ) ) {
										add_user_to_blog( $blog_id, $user->ID, $user_info['role'] );
									}
									switch_to_blog( $blog_id );
									// Update user's usermeta value for usermeta key stored in authorizer options.
									if ( strpos( $meta_key, 'acf___' ) === 0 && class_exists( 'acf' ) ) {
										// We have an ACF field value, so use the ACF function to update it.
										update_field( str_replace( 'acf___', '', $meta_key ), $usermeta['meta_value'], 'user_' . $user->ID );
									} else {
										// We have a normal usermeta value, so just update it via the WordPress function.
										update_user_meta( $user->ID, $meta_key, $usermeta['meta_value'] );
									}
									restore_current_blog();
								}
							}
						}
					}
				} else {
					// Update first/last name from CAS/LDAP if needed.
					if ( $should_update_first_name ) {
						update_user_meta( $user->ID, 'first_name', $user_data['first_name'] );
					}
					if ( $should_update_last_name ) {
						update_user_meta( $user->ID, 'last_name', $user_data['last_name'] );
					}

					// Store OAuth2 token and sync profile data if enabled (for existing users).
					if ( $user && 'oauth2' === $user_data['authenticated_by'] && 'azure' === $user_data['oauth2_provider'] ) {
						$this->handle_oauth2_token_and_profile_sync( $user, $user_data, $auth_settings );
					}
				}

				// If this is multisite, add new user to current blog.
				if ( is_multisite() && ! is_user_member_of_blog( $user->ID ) ) {
					$result = add_user_to_blog( get_current_blog_id(), $user->ID, $user_info['role'] );

					// Fail with message if error.
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}

				// Ensure user has the same role as their entry in the approved list.
				// Note: if any additional roles are defined to be added or removed,
				// use add_role() instead of set_role() so we retain existing roles.
				if ( $user_info && ! in_array( $user_info['role'], $user->roles, true ) ) {
					if ( empty( $roles_to_add ) && empty( $roles_to_remove ) ) {
						$user->set_role( $user_info['role'] );
					} else {
						$user->add_role( $user_info['role'] );
					}
				}

				/**
				 * Filter additional roles to add to the user currently logging in. This
				 * filter allows changing user roles based on custom CAS/LDAP attributes.
				 *
				 * @param array $roles_to_add               Roles to add to the user currently logging in.
				 * @param array $user_data                  User data returned from external service.
				 * @param WP_User|false|null|WP_Error $user User object if logging in user exists.
				 */
				$roles_to_add = apply_filters( 'authorizer_custom_roles_to_add', $roles_to_add, $user_data, $user );

				// Add additional roles to the user. Note: this only has partial support
				// for multisite (it will only add roles to the current blog, not all blogs).
				if ( ! empty( $roles_to_add ) ) {
					foreach ( $roles_to_add as $role_to_add ) {
						$user->add_role( $role_to_add );
					}
				}

				/**
				 * Filter additional roles to remove from the user currently logging in. This
				 * filter allows changing user roles based on custom CAS/LDAP attributes.
				 *
				 * @param array $roles_to_remove            Roles to remove from the user currently logging in.
				 * @param array $user_data                  User data returned from external service.
				 * @param WP_User|false|null|WP_Error $user User object if logging in user exists.
				 */
				$roles_to_remove = apply_filters( 'authorizer_custom_roles_to_remove', $roles_to_remove, $user_data, $user );

				// Remove roles from the user. Note: this only has partial support for
				// multisite (it will only remove roles from the current blog, not all blogs).
				if ( ! empty( $roles_to_remove ) ) {
					foreach ( $roles_to_remove as $role_to_remove ) {
						$user->remove_role( $role_to_remove );
					}
				}

				return $user;

			} elseif ( 0 === strcasecmp( $user_email, $last_email ) ) {
				/**
				 * Note: only do this for the last email address we are checking (we need
				 * to iterate through them all to make sure one of them isn't approved).
				 */

				// User isn't an admin, is not blocked, and is not approved.
				// Add them to the pending list and notify them and their instructor.
				if ( strlen( $user_email ) > 0 && ! $this->is_email_in_list( $user_email, 'pending' ) ) {
					$pending_user               = array();
					$pending_user['email']      = Helper::lowercase( $user_email );
					$pending_user['role']       = $approved_role;
					$pending_user['date_added'] = '';
					array_push( $auth_settings_access_users_pending, $pending_user );
					update_option( 'auth_settings_access_users_pending', $auth_settings_access_users_pending, false );

					// Create strings used in the email notification.
					$site_name              = get_bloginfo( 'name' );
					$site_url               = get_bloginfo( 'url' );
					$authorizer_options_url = 'settings' === $auth_settings['advanced_admin_menu'] ? admin_url( 'options-general.php?page=authorizer' ) : admin_url( '?page=authorizer' );

					// Notify users with the role specified in "Which role should
					// receive email notifications about pending users?" and any
					// individual users specified in "Which users should receive email
					// notifications about pending users?".
					if ( strlen( $auth_settings['access_role_receive_pending_emails'] ) > 0 || ! empty( $auth_settings['access_users_receive_pending_emails'] ) ) {
						$emails_to_notify = array();
						// Add users with specified role (if any).
						if ( strlen( $auth_settings['access_role_receive_pending_emails'] ) > 0 ) {
							foreach ( get_users( array( 'role' => $auth_settings['access_role_receive_pending_emails'] ) ) as $user_recipient ) {
								if ( ! empty( $user_recipient->user_email ) ) {
									$emails_to_notify[] = $user_recipient->user_email;
								}
							}
						}
						// Add individual users (if any).
						if ( ! empty( $auth_settings['access_users_receive_pending_emails'] ) ) {
							foreach ( $auth_settings['access_users_receive_pending_emails'] as $username ) {
								$user_recipient = get_user_by( 'login', $username );
								if ( ! empty( $user_recipient->user_email ) ) {
									$emails_to_notify[] = $user_recipient->user_email;
								}
							}
						}
						// Remove any duplicate email addresses (a user could potentially be
						// added via their role and again via their username).
						$emails_to_notify = array_unique( $emails_to_notify );
						// Email each recipient.
						if ( count( $emails_to_notify ) > 0 ) {
							foreach ( $emails_to_notify as $email ) {
								wp_mail(
									$email,
									sprintf(
										/* TRANSLATORS: 1: User email 2: Name of site */
										__( 'Action required: Pending user %1$s at %2$s', 'authorizer' ),
										$pending_user['email'],
										$site_name
									),
									sprintf(
										/* TRANSLATORS: 1: Name of site 2: URL of site 3: URL of authorizer */
										__( "A new user has tried to access the %1\$s site you manage at:\n%2\$s\n\nPlease log in to approve or deny their request:\n%3\$s\n", 'authorizer' ),
										$site_name,
										$site_url,
										$authorizer_options_url
									)
								);
							}
						}
					}
				}

				// Fetch the external service this user authenticated with, and append
				// it to the logout URL below (so we can fire custom logout routines in
				// custom_logout() based on their external service. This is necessary
				// because a pending user does not have a WP_User, and thus no
				// "authenticated_by" usermeta that is normally used to do this.
				$external_param = isset( $user_data['authenticated_by'] ) ? '&external=' . $user_data['authenticated_by'] : '';

				// Allow overriding the message pending users see after logging in.
				if ( defined( 'AUTHORIZER_LOGIN_MESSAGE_PENDING_USERS' ) ) {
					$auth_settings['access_pending_redirect_to_message'] = \AUTHORIZER_LOGIN_MESSAGE_PENDING_USERS;
				}
				/**
				 * Filters the message pending users see after logging in.
				 *
				 * @since 3.12.0
				 *
				 * @param string $message The message content.
				 */
				$auth_settings['access_pending_redirect_to_message'] = apply_filters( 'authorizer_login_message_pending_users', $auth_settings['access_pending_redirect_to_message'] );

				// Notify user about pending status and return without authenticating them.
				// phpcs:ignore WordPress.Security.NonceVerification
				$redirect_to   = ! empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : home_url();
				$page_title    = get_bloginfo( 'name' ) . ' - Access Pending';
				$error_message =
					apply_filters( 'the_content', $auth_settings['access_pending_redirect_to_message'] ) .
					'<hr />' .
					'<p style="text-align: center;">' .
					'<a class="button" href="' . wp_logout_url( $redirect_to ) . $external_param . '">' .
					__( 'Back', 'authorizer' ) .
					'</a></p>';
				update_option( 'auth_settings_advanced_login_error', $error_message, false );
				wp_die( wp_kses( $error_message, Helper::$allowed_html ), esc_html( $page_title ) );
			}
		}

		// Sanity check: if we made it here without returning, something has gone wrong.
		return new \WP_Error( 'invalid_login', __( 'Invalid login attempted.', 'authorizer' ) );
	}


	/**
	 * Restrict access to WordPress site based on settings (everyone, logged_in_users).
	 *
	 * Action: parse_request
	 *
	 * @param  WP $wp WordPress object.
	 * @return WP|void   WP object when passing through to WordPress authentication, or void.
	 */
	public function restrict_access( $wp ) {
		// Grab plugin settings.
		$options       = Options::get_instance();
		$auth_settings = $options->get_all( Helper::SINGLE_CONTEXT, 'allow override' );

		// Grab current user.
		$current_user = wp_get_current_user();

		$has_access = (
			// Always allow access if WordPress is installing.
			// phpcs:ignore WordPress.Security.NonceVerification
			( defined( 'WP_INSTALLING' ) && isset( $_GET['key'] ) ) ||
			// Always allow access to admins.
			( current_user_can( 'create_users' ) ) ||
			// Allow access if option is set to 'everyone'.
			( 'everyone' === $auth_settings['access_who_can_view'] ) ||
			// Allow access to approved external users and logged in users if option is set to 'logged_in_users'.
			( 'logged_in_users' === $auth_settings['access_who_can_view'] && Helper::is_user_logged_in_and_blog_user() && $this->is_email_in_list( $current_user->user_email, 'approved' ) ) ||
			// Allow REST API requests (access is determined later in the rest_authentication_errors hook).
			// See: https://github.com/WordPress/WordPress/blob/8e41746cb11271d063608a63e3f6091a8685e677/wp-includes/rest-api.php#L131-L133.
			( ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) )
		);

		/**
		 * Developers can use the `authorizer_has_access` filter to override
		 * restricted access on certain pages. Note that the restriction checks
		 * happens before WordPress executes any queries, so use the $wp variable
		 * to investigate what the visitor is trying to load.
		 *
		 * For example, to unblock an RSS feed, place the following PHP code in
		 * the theme's functions.php file or in a simple plug-in:
		 *
		 *   function my_feed_access_override( $has_access, $wp ) {
		 *     // Check query variables to see if this is the feed.
		 *     if ( ! empty( $wp->query_vars['feed'] ) ) {
		 *       $has_access = true;
		 *     }
		 *
		 *     return $has_access;
		 *   }
		 *   add_filter( 'authorizer_has_access', 'my_feed_access_override', 10, 2 );
		 */
		if ( apply_filters( 'authorizer_has_access', $has_access, $wp ) === true ) {
			// Turn off the public notice about browsing anonymously.
			update_option( 'auth_settings_advanced_public_notice', false, true );

			// We've determined that the current user has access, so simply return to grant access.
			return $wp;
		}

		// Allow HEAD requests to the root (usually discovery from a REST client).
		if ( ! empty( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'] && empty( $wp->request ) && empty( $wp->matched_query ) ) {
			return $wp;
		}

		/* We've determined that the current user doesn't have access, so we deal with them now. */

		// Fringe case: In a multisite, a user of a different blog can successfully
		// log in, but they aren't on the 'approved' whitelist for this blog.
		// If that's the case, add them to the pending list for this blog.
		if ( is_multisite() && is_user_logged_in() && ! $has_access ) {
			$current_user = wp_get_current_user();

			// Check user access; block if not, add them to pending list if open, let them through otherwise.
			$result = $this->check_user_access( $current_user, array( $current_user->user_email ) );
		}

		// Check to see if the requested page is public. If so, show it.
		if ( empty( $wp->request ) ) {
			$current_page_id = 'home';
		} else {
			$request_query   = isset( $wp->query_vars ) ? new \WP_Query( $wp->query_vars ) : null;
			$current_page_id = isset( $request_query->post_count ) && $request_query->post_count > 0 ? $request_query->post->ID : '';
		}
		if ( ! array_key_exists( 'access_public_pages', $auth_settings ) || ! is_array( $auth_settings['access_public_pages'] ) ) {
			$auth_settings['access_public_pages'] = array();
		}
		if ( in_array( strval( $current_page_id ), $auth_settings['access_public_pages'], true ) ) {
			if ( 'no_warning' === $auth_settings['access_public_warning'] ) {
				update_option( 'auth_settings_advanced_public_notice', false, true );
			} else {
				update_option( 'auth_settings_advanced_public_notice', true, true );
			}
			return $wp;
		}

		// Check to see if any category assigned to the requested page is public. If so, show it.
		$current_page_categories = wp_get_post_categories( $current_page_id, array( 'fields' => 'slugs' ) );
		foreach ( $current_page_categories as $current_page_category ) {
			if ( in_array( 'cat_' . $current_page_category, $auth_settings['access_public_pages'], true ) ) {
				if ( 'no_warning' === $auth_settings['access_public_warning'] ) {
					update_option( 'auth_settings_advanced_public_notice', false, true );
				} else {
					update_option( 'auth_settings_advanced_public_notice', true, true );
				}
				return $wp;
			}
		}

		// Check to see if this page can't be found. If so, allow showing the 404 page.
		if ( strlen( $current_page_id ) < 1 ) {
			if ( in_array( 'auth_public_404', $auth_settings['access_public_pages'], true ) ) {
				if ( 'no_warning' === $auth_settings['access_public_warning'] ) {
					update_option( 'auth_settings_advanced_public_notice', false, true );
				} else {
					update_option( 'auth_settings_advanced_public_notice', true, true );
				}
				return $wp;
			}
		}

		// Check to see if the requested category is public. If so, show it.
		$current_category_name = property_exists( $wp, 'query_vars' ) && array_key_exists( 'category_name', $wp->query_vars ) && strlen( $wp->query_vars['category_name'] ) > 0 ? $wp->query_vars['category_name'] : '';
		if ( $current_category_name ) {
			$current_category_name_pieces = explode( '/', $current_category_name );
			$current_category_name        = end( $current_category_name_pieces );
			if ( in_array( 'cat_' . $current_category_name, $auth_settings['access_public_pages'], true ) ) {
				if ( 'no_warning' === $auth_settings['access_public_warning'] ) {
					update_option( 'auth_settings_advanced_public_notice', false, true );
				} else {
					update_option( 'auth_settings_advanced_public_notice', true, true );
				}
				return $wp;
			}
		}

		// Allow overriding the message anonymous users see.
		if ( defined( 'AUTHORIZER_MESSAGE_ANONYMOUS_USERS' ) ) {
			$auth_settings['access_redirect_to_message'] = \AUTHORIZER_MESSAGE_ANONYMOUS_USERS;
		}
		/**
		 * Filters the message anonymous users see when visiting public pages on a private site.
		 *
		 * @since 3.12.0
		 *
		 * @param string $message The message content.
		 */
		$auth_settings['access_redirect_to_message'] = apply_filters( 'authorizer_message_anonymous_users', $auth_settings['access_redirect_to_message'] );

		// User is denied access, so show them the error message. Render as JSON
		// if this is a REST API call; otherwise, show the error message via
		// wp_die() (rendered html), or redirect to the login URL.
		$current_path = ! empty( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : home_url();
		if ( property_exists( $wp, 'matched_query' ) && stripos( $wp->matched_query, 'rest_route=' ) === 0 && 'GET' === $_SERVER['REQUEST_METHOD'] ) {
			wp_send_json(
				array(
					'code'    => 'rest_cannot_view',
					'message' => wp_strip_all_tags( $auth_settings['access_redirect_to_message'] ),
					'data'    => array(
						'status' => 401,
					),
				)
			);
		} elseif ( 'message' === $auth_settings['access_redirect'] ) {
			$page_title = sprintf(
				/* TRANSLATORS: %s: Name of blog */
				__( '%s - Access Restricted', 'authorizer' ),
				get_bloginfo( 'name' )
			);
			$error_message =
				apply_filters( 'the_content', $auth_settings['access_redirect_to_message'] ) .
				'<hr />' .
				'<p style="text-align: center;margin-bottom: -15px;">' .
				'<a class="button" href="' . wp_login_url( $current_path ) . '">' .
				__( 'Log In', 'authorizer' ) .
				'</a></p>';
			wp_die( wp_kses( $error_message, Helper::$allowed_html ), esc_html( $page_title ) );
		} else {
			wp_safe_redirect( wp_login_url( $current_path ), 302 );
			exit;
		}

		// Sanity check: we should never get here.
		wp_die( '<p>Access denied.</p>', 'Site Access Restricted' );
	}

	/**
	 * If we're showing search results or a post listing (home or archive page) to
	 * an anonymous user, and Authorizer is configured to only allow logged in
	 * users to see the site, filter the query to only posts marked public.
	 *
	 * Action: pre_get_posts
	 *
	 * @param WP_Query $query The WP_Query instance (passed by reference).
	 * @return void
	 */
	public function remove_private_pages_from_search_and_archives( $query ) {
		// It's possible for pre_get_posts to fire before wp-includes/pluggable.php
		// is loaded, so verify before using the is_user_logged_in() function.
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			require ABSPATH . WPINC . '/pluggable.php';
		}

		// Fix for edge case when viewing admin pages in Pressbooks (Undefined
		// constant "SECURE_AUTH_COOKIE").
		if ( ! defined( 'SECURE_AUTH_COOKIE' ) ) {
			wp_cookie_constants();
		}

		// Do nothing if user is logged in, this isn't the main query, or we're not
		// showing search results, home page, or an archive page.
		if (
			is_user_logged_in() || ! $query->is_main_query() ||
			! ( $query->is_search() || $query->is_home() || $query->is_archive() )
		) {
			return;
		}

		$options      = Options::get_instance();
		$who_can_view = $options->get( 'access_who_can_view' );
		$public_pages = $options->get( 'access_public_pages' );
		$public_pages = is_array( $public_pages ) ? $public_pages : array();

		// Do nothing if this site isn't restricted to logged in users only.
		if ( 'logged_in_users' !== $who_can_view ) {
			return;
		}

		// Check for special public types (home, 404, categories).
		$public_category_ids = array();
		foreach ( $public_pages as $index => $public_page ) {
			if ( 'home' === $public_page || 'auth_public_404' === $public_page ) {
				unset( $public_pages[ $index ] );
			} elseif ( 'cat_' === substr( $public_page, 0, 4 ) ) {
				$public_category_name = substr( $public_page, 4 );
				unset( $public_pages[ $index ] );
				$public_category_ids[] = get_cat_ID( $public_category_name );
			}
		}
		if ( ! empty( $public_category_ids ) ) {
			$pages_in_public_categories = get_posts( array(
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'category__in'   => $public_category_ids,
			) );
			$public_pages               = array_merge( $public_pages, $pages_in_public_categories );
		}

		$query->set( 'post__in', $public_pages );
	}

	/**
	 * Prevent REST API access if user isn't authenticated and "only logged in
	 * users can see the site" is enabled.
	 *
	 * Filter: rest_authentication_errors
	 *
	 * @param  WP_Error|null|true $errors WP_Error if authentication error, null if authentication method wasn't used, true if authentication succeeded.
	 * @return WP_Error|null|true         WP_Error if not logged in and "only logged in users can see the site" is enabled.
	 */
	public function restrict_rest_api( $errors ) {
		// If there is already an error, just return that.
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		// If user isn't logged in, check for "only logged in users can see the site".
		if ( ! is_user_logged_in() ) {
			// Grab plugin settings.
			$options       = Options::get_instance();
			$auth_settings = $options->get_all( Helper::SINGLE_CONTEXT, 'allow override' );

			if (
				'logged_in_users' === $auth_settings['access_who_can_view'] &&
				false === apply_filters( 'authorizer_has_access', false, $GLOBALS['wp'] )
			) {
				// Allow overriding the message anonymous users see.
				if ( defined( 'AUTHORIZER_MESSAGE_ANONYMOUS_USERS' ) ) {
					$auth_settings['access_redirect_to_message'] = \AUTHORIZER_MESSAGE_ANONYMOUS_USERS;
				}
				/**
				 * Filters the message anonymous users see when visiting public pages on a private site.
				 *
				 * @since 3.12.0
				 *
				 * @param string $message The message content.
				 */
				$auth_settings['access_redirect_to_message'] = apply_filters( 'authorizer_message_anonymous_users', $auth_settings['access_redirect_to_message'] );

				return new \WP_Error(
					'rest_cannot_view',
					wp_strip_all_tags( $auth_settings['access_redirect_to_message'] ),
					array(
						'status' => 401,
					)
				);
			}
		}

		return $errors;
	}


	/**
	 * Helper function to determine whether a given email is in one of
	 * the lists (pending, approved, blocked). Defaults to the list of
	 * approved users.
	 *
	 * @param  string $email          Email to check existent of.
	 * @param  string $user_list      List to look for email in.
	 * @param  string $multisite_mode Admin context.
	 * @return boolean                Whether email was found.
	 */
	public function is_email_in_list( $email = '', $user_list = 'approved', $multisite_mode = 'single' ) {
		if ( empty( $email ) ) {
			return false;
		}

		$options = Options::get_instance();

		switch ( $user_list ) {
			case 'pending':
				$auth_settings_access_users_pending = $options->get( 'access_users_pending', Helper::SINGLE_CONTEXT );
				return Helper::in_multi_array( $email, $auth_settings_access_users_pending );
			case 'blocked':
				$auth_settings_access_users_blocked = $options->get( 'access_users_blocked', Helper::SINGLE_CONTEXT );
				// Blocked list can have wildcard matches, e.g., @baddomain.com, which
				// should match any email address at that domain. Check if any wildcards
				// exist, and if the email address has that domain.
				$email_in_blocked_domain = false;
				$blocked_domains         = preg_grep(
					'/^@.*/',
					array_map(
						function ( $blocked_item ) {
							return $blocked_item['email']; },
						$auth_settings_access_users_blocked
					)
				);
				foreach ( $blocked_domains as $blocked_domain ) {
					$email_domain = substr( $email, strrpos( $email, '@' ) );
					if ( $email_domain === $blocked_domain ) {
						$email_in_blocked_domain = true;
						break;
					}
				}
				return $email_in_blocked_domain || Helper::in_multi_array( $email, $auth_settings_access_users_blocked );
			case 'approved':
			default:
				if ( 'single' !== $multisite_mode ) {
					// Get multisite users only.
					$auth_settings_access_users_approved = $options->get( 'access_users_approved', Helper::NETWORK_CONTEXT );
				} elseif ( is_multisite() && 1 === intval( $options->get( 'advanced_override_multisite' ) ) && empty( $options->get( 'prevent_override_multisite', Helper::NETWORK_CONTEXT ) ) ) {
					// This site has overridden any multisite settings (and is not prevented from doing so), so only get its users.
					$auth_settings_access_users_approved = $options->get( 'access_users_approved', Helper::SINGLE_CONTEXT );
				} else {
					// Get all site users and all multisite users.
					$auth_settings_access_users_approved = array_merge(
						$options->get( 'access_users_approved', Helper::SINGLE_CONTEXT ),
						$options->get( 'access_users_approved', Helper::NETWORK_CONTEXT )
					);
				}
				return Helper::in_multi_array( $email, $auth_settings_access_users_approved );
		}
	}


	/**
	 * Handle OAuth2 token storage and profile synchronization for Azure/Microsoft 365.
	 * CRITICAL: Store token FIRST, then queue MS Graph API calls for async execution.
	 *
	 * @param WP_User $user          WordPress user object.
	 * @param array   $user_data     User data from OAuth2 provider.
	 * @param array   $auth_settings Plugin settings.
	 * @return void
	 */
	private function handle_oauth2_token_and_profile_sync( $user, $user_data, $auth_settings ) {
		if ( empty( $user ) || empty( $user_data ) || empty( $user_data['oauth2_token'] ) ) {
			return;
		}

		$options          = Options::get_instance();
		$oauth2_server_id = isset( $user_data['oauth2_server_id'] ) ? $user_data['oauth2_server_id'] : 1;
		$suffix           = 1 === $oauth2_server_id ? '' : '_' . $oauth2_server_id;

		// STEP 1: ALWAYS store token FIRST (regardless of checkbox setting).
		// This ensures the token is saved before any MS Graph API calls.
		$this->store_oauth2_token( $user->ID, $user_data['oauth2_token'] );

		// Get access token for API calls.
		$token        = $user_data['oauth2_token'];
		$access_token = method_exists( $token, 'getToken' ) ? $token->getToken() : null;

		if ( empty( $access_token ) ) {
			// Log token acquisition failure.
			System_Logs::get_instance()->log_event(
				'token_acquired',
				'failure',
				'Failed to extract access token from OAuth2 token object',
				array( 'provider' => isset( $user_data['oauth2_provider'] ) ? $user_data['oauth2_provider'] : 'unknown' ),
				$user->ID,
				$user->user_email
			);
			return;
		}

		// Log successful token acquisition.
		System_Logs::get_instance()->log_event(
			'token_acquired',
			'success',
			'OAuth2 access token acquired successfully',
			array(
				'provider' => isset( $user_data['oauth2_provider'] ) ? $user_data['oauth2_provider'] : 'unknown',
			),
			$user->ID,
			$user->user_email
		);

		// STEP 2: Queue MS Graph API data acquisition for async execution.
		// This prevents blocking the login flow with external API calls.
		$this->queue_microsoft_profile_sync( $user->ID, $access_token, $oauth2_server_id );
	}


	/**
	 * Queue Microsoft profile data synchronization to run after login.
	 * This uses WordPress shutdown hook to run after response is sent to user.
	 *
	 * @param int    $user_id         WordPress user ID.
	 * @param string $access_token    OAuth2 access token.
	 * @param int    $oauth2_server_id OAuth2 server ID.
	 * @return void
	 */
	private function queue_microsoft_profile_sync( $user_id, $access_token, $oauth2_server_id = 1 ) {
		$options = Options::get_instance();
		$suffix  = 1 === $oauth2_server_id ? '' : '_' . $oauth2_server_id;

		// Queue profile sync to run on shutdown (after login response sent).
		add_action( 'shutdown', function() use ( $user_id, $access_token, $oauth2_server_id, $suffix, $options ) {
			// Check if profile photo sync is enabled.
			$sync_photo = $options->get( 'oauth2_sync_profile_photo' . $suffix );
			if ( $sync_photo ) {
				$this->sync_microsoft_profile_photo( $user_id, $access_token );
			}

			// Check if profile fields sync is enabled.
			$sync_fields = $options->get( 'oauth2_sync_profile_fields' . $suffix );
			if ( $sync_fields ) {
				$this->sync_microsoft_profile_fields( $user_id, $access_token );
				$this->sync_microsoft_user_groups( $user_id, $access_token );
			}

			// Apply role mappings based on MS365 profile data.
			$this->apply_oauth2_role_mappings( $user_id, $oauth2_server_id );
		}, 999 );
	}


	/**
	 * Store OAuth2 access token and refresh token encrypted in user meta.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param object $token   OAuth2 token object.
	 * @return void
	 */
	private function store_oauth2_token( $user_id, $token ) {
		if ( empty( $user_id ) || empty( $token ) ) {
			return;
		}

		// Extract token data.
		$access_token  = method_exists( $token, 'getToken' ) ? $token->getToken() : null;
		$refresh_token = method_exists( $token, 'getRefreshToken' ) ? $token->getRefreshToken() : null;
		$expires       = method_exists( $token, 'getExpires' ) ? $token->getExpires() : null;

		if ( empty( $access_token ) ) {
			return;
		}

		// Encrypt tokens using WordPress authentication keys.
		$encrypted_access_token = Helper::encrypt_token( $access_token );
		if ( false !== $encrypted_access_token ) {
			update_user_meta( $user_id, 'oauth2_access_token', $encrypted_access_token );
		}

		if ( ! empty( $refresh_token ) ) {
			$encrypted_refresh_token = Helper::encrypt_token( $refresh_token );
			if ( false !== $encrypted_refresh_token ) {
				update_user_meta( $user_id, 'oauth2_refresh_token', $encrypted_refresh_token );
			}
		}

		if ( ! empty( $expires ) ) {
			update_user_meta( $user_id, 'oauth2_token_expires', $expires );
		}

		// Store timestamp of when token was saved.
		update_user_meta( $user_id, 'oauth2_token_saved_at', time() );
	}


	/**
	 * Sync user profile photo from Microsoft 365.
	 *
	 * @param int    $user_id      WordPress user ID.
	 * @param string $access_token OAuth2 access token.
	 * @return void
	 */
	private function sync_microsoft_profile_photo( $user_id, $access_token ) {
		if ( empty( $user_id ) || empty( $access_token ) ) {
			error_log( 'Authorizer: Cannot sync profile photo - missing user_id or access_token' ); // phpcs:ignore
			System_Logs::get_instance()->log_event(
				'photo_sync',
				'error',
				'Cannot sync profile photo - missing user_id or access_token',
				array(),
				$user_id
			);
			return;
		}

		// Fetch profile photo from Microsoft Graph API.
		$photo = Helper::fetch_microsoft_graph_profile_photo( $access_token );
		if ( false === $photo || empty( $photo['data'] ) ) {
			error_log( 'Authorizer: Profile photo not available for user ' . $user_id ); // phpcs:ignore
			System_Logs::get_instance()->log_event(
				'photo_sync',
				'failure',
				'Profile photo not available from Microsoft Graph',
				array(),
				$user_id
			);
			return;
		}

		// Save profile photo to WordPress.
		$attachment_id = Helper::save_user_profile_photo( $user_id, $photo['data'], $photo['type'] );
		if ( false !== $attachment_id ) {
			// Store when photo was last synced.
			update_user_meta( $user_id, 'oauth2_profile_photo_synced_at', time() );
			error_log( 'Authorizer: Successfully synced profile photo for user ' . $user_id . ' (attachment ID: ' . $attachment_id . ')' ); // phpcs:ignore
			System_Logs::get_instance()->log_event(
				'photo_sync',
				'success',
				'Profile photo synced successfully',
				array(
					'attachment_id' => $attachment_id,
					'photo_type'    => $photo['type'],
				),
				$user_id
			);
		} else {
			error_log( 'Authorizer: Failed to save profile photo for user ' . $user_id ); // phpcs:ignore
			System_Logs::get_instance()->log_event(
				'photo_sync',
				'error',
				'Failed to save profile photo to WordPress',
				array(),
				$user_id
			);
		}
	}


	/**
	 * Sync additional profile fields from Microsoft 365.
	 *
	 * @param int    $user_id      WordPress user ID.
	 * @param string $access_token OAuth2 access token.
	 * @return void
	 */
	private function sync_microsoft_profile_fields( $user_id, $access_token ) {
		if ( empty( $user_id ) || empty( $access_token ) ) {
			error_log( 'Authorizer: Cannot sync profile fields - missing user_id or access_token' ); // phpcs:ignore
			System_Logs::get_instance()->log_event(
				'profile_sync',
				'error',
				'Cannot sync profile fields - missing user_id or access_token',
				array(),
				$user_id
			);
			return;
		}

		// Fetch profile fields from Microsoft Graph API.
		$profile_fields = Helper::fetch_microsoft_graph_profile_fields( $access_token );
		if ( false === $profile_fields || ! is_array( $profile_fields ) ) {
			error_log( 'Authorizer: Failed to fetch profile fields from MS Graph for user ' . $user_id ); // phpcs:ignore
			System_Logs::get_instance()->log_event(
				'profile_sync',
				'failure',
				'Failed to fetch profile fields from Microsoft Graph',
				array(),
				$user_id
			);
			return;
		}

		error_log( 'Authorizer: Fetched ' . count( $profile_fields ) . ' profile fields for user ' . $user_id ); // phpcs:ignore

		// Get custom field mappings from settings.
		$options          = Options::get_instance();
		$oauth2_server_id = get_user_meta( $user_id, 'oauth2_server_id', true );
		if ( empty( $oauth2_server_id ) ) {
			$oauth2_server_id = 1;
		}
		$suffix                = 1 === intval( $oauth2_server_id ) ? '' : '_' . $oauth2_server_id;
		$custom_mappings_raw   = $options->get( 'oauth2_custom_field_mappings' . $suffix );
		$custom_mappings       = $this->parse_field_mappings( $custom_mappings_raw );

		// Get default WordPress field mappings.
		$default_mappings = $this->get_default_wordpress_field_mappings();

		// Merge mappings (custom mappings override defaults).
		$all_mappings = array_merge( $default_mappings, $custom_mappings );

		// Store each profile field as user meta.
		foreach ( $profile_fields as $field_name => $field_value ) {
			// Skip empty values.
			if ( empty( $field_value ) && '0' !== $field_value && 0 !== $field_value ) {
				continue;
			}

			// Handle array values (like businessPhones, skills, interests).
			$original_value = $field_value;
			if ( is_array( $field_value ) ) {
				$field_value = wp_json_encode( $field_value );
			}

			// Determine the WordPress user meta key.
			if ( isset( $all_mappings[ $field_name ] ) ) {
				$meta_key = $all_mappings[ $field_name ];

				// Check if this is a standard WordPress field that needs special handling.
				if ( in_array( $meta_key, array( 'first_name', 'last_name', 'description', 'user_url' ), true ) ) {
					// Update WordPress user table fields.
					if ( 'description' === $meta_key ) {
						wp_update_user(
							array(
								'ID'          => $user_id,
								'description' => is_array( $original_value ) ? implode( ', ', $original_value ) : $original_value,
							)
						);
					} elseif ( 'user_url' === $meta_key ) {
						wp_update_user(
							array(
								'ID'       => $user_id,
								'user_url' => is_array( $original_value ) ? '' : $original_value,
							)
						);
					}
					// first_name and last_name are handled separately in authorization flow.
				}

				// Always store in user meta as well for consistency.
				update_user_meta( $user_id, $meta_key, $field_value );
			} else {
				// Use default oauth2_ prefix.
				update_user_meta( $user_id, 'oauth2_' . $field_name, $field_value );
			}
		}

		// Store when fields were last synced.
		update_user_meta( $user_id, 'oauth2_profile_fields_synced_at', time() );

		// Store the server ID for future reference.
		update_user_meta( $user_id, 'oauth2_server_id', $oauth2_server_id );

		error_log( 'Authorizer: Successfully synced profile fields for user ' . $user_id ); // phpcs:ignore

		// Log successful profile sync.
		System_Logs::get_instance()->log_event(
			'profile_sync',
			'success',
			'Profile fields synced successfully',
			array(
				'fields_count'     => count( $profile_fields ),
				'fields_synced'    => array_keys( $profile_fields ),
				'custom_mappings'  => count( $custom_mappings ),
				'default_mappings' => count( $default_mappings ),
			),
			$user_id
		);
	}


	/**
	 * Get default mappings from MS365 fields to WordPress profile fields.
	 *
	 * @return array Associative array of default field mappings.
	 */
	private function get_default_wordpress_field_mappings() {
		return array(
			// WordPress core user fields.
			'givenName'    => 'first_name',
			'surname'      => 'last_name',
			'aboutMe'      => 'description',
			'mySite'       => 'user_url',

			// Common profile meta fields.
			'displayName'  => 'nickname',
			'jobTitle'     => 'job_title',
			'companyName'  => 'company',
			'officeLocation' => 'office',
			'mobilePhone'  => 'phone',
			'city'         => 'billing_city',
			'state'        => 'billing_state',
			'country'      => 'billing_country',
			'postalCode'   => 'billing_postcode',
			'streetAddress' => 'billing_address_1',

			// Keep oauth2_ prefix for these to avoid conflicts.
			'mail'         => 'oauth2_email',
			'userPrincipalName' => 'oauth2_upn',
		);
	}


	/**
	 * Parse custom field mappings from settings.
	 *
	 * @param string $mappings_raw Raw mappings string from settings.
	 * @return array Associative array of ms365_field => wp_meta_key mappings.
	 */
	private function parse_field_mappings( $mappings_raw ) {
		$mappings = array();

		if ( empty( $mappings_raw ) ) {
			return $mappings;
		}

		// Split by newlines.
		$lines = explode( "\n", $mappings_raw );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip empty lines and comments.
			if ( empty( $line ) || strpos( $line, '#' ) === 0 || strpos( $line, '//' ) === 0 ) {
				continue;
			}

			// Parse mapping in format: ms365_field=wp_meta_key.
			if ( strpos( $line, '=' ) !== false ) {
				list( $ms365_field, $wp_meta_key ) = explode( '=', $line, 2 );
				$ms365_field = trim( $ms365_field );
				$wp_meta_key = trim( $wp_meta_key );

				if ( ! empty( $ms365_field ) && ! empty( $wp_meta_key ) ) {
					$mappings[ $ms365_field ] = $wp_meta_key;
				}
			}
		}

		return $mappings;
	}


	/**
	 * Sync user's Microsoft 365 group memberships.
	 *
	 * @param int    $user_id      WordPress user ID.
	 * @param string $access_token OAuth2 access token.
	 * @return void
	 */
	private function sync_microsoft_user_groups( $user_id, $access_token ) {
		if ( empty( $user_id ) || empty( $access_token ) ) {
			System_Logs::get_instance()->log_event(
				'groups_sync',
				'error',
				'Cannot sync groups - missing user_id or access_token',
				array(),
				$user_id
			);
			return;
		}

		// Fetch groups from Microsoft Graph API.
		$groups = Helper::fetch_microsoft_user_groups( $access_token );
		if ( false === $groups || ! is_array( $groups ) ) {
			System_Logs::get_instance()->log_event(
				'groups_sync',
				'failure',
				'Failed to fetch groups from Microsoft Graph',
				array(),
				$user_id
			);
			return;
		}

		// Store groups as JSON-encoded user meta.
		update_user_meta( $user_id, 'oauth2_groups', wp_json_encode( $groups ) );

		// Also store group display names as a simple array for easy access.
		$group_names = array_map(
			function ( $group ) {
				return $group['displayName'];
			},
			$groups
		);
		update_user_meta( $user_id, 'oauth2_group_names', wp_json_encode( $group_names ) );

		// Store when groups were last synced.
		update_user_meta( $user_id, 'oauth2_groups_synced_at', time() );

		// Log successful groups sync.
		System_Logs::get_instance()->log_event(
			'groups_sync',
			'success',
			'Groups synced successfully',
			array(
				'groups_count' => count( $groups ),
				'group_names'  => $group_names,
			),
			$user_id
		);
	}


	/**
	 * Apply role mappings based on OAuth2/MS365 profile data.
	 *
	 * @param int $user_id           WordPress user ID.
	 * @param int $oauth2_server_id  OAuth2 server ID.
	 * @return void
	 */
	private function apply_oauth2_role_mappings( $user_id, $oauth2_server_id = 1 ) {
		if ( empty( $user_id ) ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		// Get settings.
		$options = Options::get_instance();
		$suffix  = 1 === intval( $oauth2_server_id ) ? '' : '_' . $oauth2_server_id;

		// Get default role.
		$default_role = $options->get( 'oauth2_default_role' . $suffix, Helper::SINGLE_CONTEXT );
		if ( empty( $default_role ) ) {
			$default_role = get_option( 'default_role', 'subscriber' );
		}

		// Get role mappings.
		$mappings_raw = $options->get( 'oauth2_role_mappings' . $suffix, Helper::SINGLE_CONTEXT );
		$mappings     = $this->parse_role_mappings( $mappings_raw );

		// Get user data from meta.
		$user_email  = $user->user_email;
		$job_title   = get_user_meta( $user_id, 'oauth2_jobTitle', true );
		if ( empty( $job_title ) ) {
			$job_title = get_user_meta( $user_id, 'job_title', true );
		}
		$department  = get_user_meta( $user_id, 'oauth2_department', true );
		$groups_json = get_user_meta( $user_id, 'oauth2_group_names', true );
		$groups      = ! empty( $groups_json ) ? json_decode( $groups_json, true ) : array();

		// Determine role based on mappings (first match wins).
		$assigned_role = null;

		// Priority 1: Email mappings.
		if ( isset( $mappings['email'] ) && ! empty( $user_email ) ) {
			foreach ( $mappings['email'] as $pattern => $role ) {
				if ( $this->matches_pattern( $user_email, $pattern ) ) {
					$assigned_role = $role;
					break;
				}
			}
		}

		// Priority 2: Group mappings.
		if ( is_null( $assigned_role ) && isset( $mappings['group'] ) && ! empty( $groups ) ) {
			foreach ( $mappings['group'] as $group_pattern => $role ) {
				foreach ( $groups as $group_name ) {
					if ( $this->matches_pattern( $group_name, $group_pattern ) ) {
						$assigned_role = $role;
						break 2;
					}
				}
			}
		}

		// Priority 3: Job title mappings.
		if ( is_null( $assigned_role ) && isset( $mappings['jobtitle'] ) && ! empty( $job_title ) ) {
			foreach ( $mappings['jobtitle'] as $pattern => $role ) {
				if ( $this->matches_pattern( $job_title, $pattern ) ) {
					$assigned_role = $role;
					break;
				}
			}
		}

		// Priority 4: Department mappings.
		if ( is_null( $assigned_role ) && isset( $mappings['department'] ) && ! empty( $department ) ) {
			foreach ( $mappings['department'] as $pattern => $role ) {
				if ( $this->matches_pattern( $department, $pattern ) ) {
					$assigned_role = $role;
					break;
				}
			}
		}

		// Priority 5: Default role.
		if ( is_null( $assigned_role ) ) {
			$assigned_role = $default_role;
		}

		// Apply role if it's valid and different from current role.
		if ( ! empty( $assigned_role ) && ! in_array( $assigned_role, $user->roles, true ) ) {
			$user->set_role( $assigned_role );

			// Log role assignment.
			System_Logs::get_instance()->log_event(
				'role_assigned',
				'success',
				'User role assigned based on OAuth2 profile data',
				array(
					'assigned_role' => $assigned_role,
					'email'         => $user_email,
					'job_title'     => $job_title,
					'department'    => $department,
					'groups'        => $groups,
				),
				$user_id,
				$user_email
			);
		}
	}


	/**
	 * Parse role mappings from settings text.
	 *
	 * @param string $mappings_raw Raw mappings text.
	 * @return array Parsed mappings grouped by type.
	 */
	private function parse_role_mappings( $mappings_raw ) {
		$mappings = array();

		if ( empty( $mappings_raw ) ) {
			return $mappings;
		}

		$lines = explode( "\n", $mappings_raw );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip empty lines and comments.
			if ( empty( $line ) || 0 === strpos( $line, '#' ) || 0 === strpos( $line, '//' ) ) {
				continue;
			}

			// Parse format: type:pattern:role.
			$parts = explode( ':', $line, 3 );
			if ( count( $parts ) !== 3 ) {
				continue;
			}

			$type    = strtolower( trim( $parts[0] ) );
			$pattern = trim( $parts[1] );
			$role    = trim( $parts[2] );

			// Validate type.
			if ( ! in_array( $type, array( 'email', 'jobtitle', 'department', 'group' ), true ) ) {
				continue;
			}

			// Validate role exists in WordPress.
			if ( ! get_role( $role ) ) {
				continue;
			}

			// Store mapping.
			if ( ! isset( $mappings[ $type ] ) ) {
				$mappings[ $type ] = array();
			}
			$mappings[ $type ][ $pattern ] = $role;
		}

		return $mappings;
	}


	/**
	 * Check if a value matches a pattern (supports wildcards).
	 *
	 * @param string $value   Value to check.
	 * @param string $pattern Pattern (supports * and ? wildcards).
	 * @return bool Whether value matches pattern.
	 */
	private function matches_pattern( $value, $pattern ) {
		// Exact match.
		if ( $value === $pattern ) {
			return true;
		}

		// Convert wildcard pattern to regex.
		// Escape special regex characters except * and ?.
		$regex_pattern = preg_quote( $pattern, '/' );
		// Convert * to .* and ? to .
		$regex_pattern = str_replace( array( '\*', '\?' ), array( '.*', '.' ), $regex_pattern );

		// Match (case-insensitive).
		return (bool) preg_match( '/^' . $regex_pattern . '$/i', $value );
	}
}
