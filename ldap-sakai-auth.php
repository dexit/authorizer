<?php
/*
Plugin Name: LDAP Sakai Authorization
Plugin URI: http://hawaii.edu/coe/dcdc/
Description: LDAP Sakai Authorization restricts access to students enrolled in university courses, using LDAP for authentication and Sakai for course rosters.
Version: 0.1
Author: Paul Ryan
Author URI: http://www.linkedin.com/in/paulrryan/
License: GPL2
*/
/*
Copyright 2013  Paul Ryan  (email : prar@hawaii.edu)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('WP_Plugin_LDAP_Sakai_Auth')) {
  class WP_Plugin_LDAP_Sakai_Auth {
    
    /**
     * Constructor.
     */
    public function __construct() {
      // Create the options page: Dashboard > Settings > LDAP Sakai Authorization
      //include 'options.php';

      // Register filters.
      add_filter("plugin_action_links_".plugin_basename(__FILE__), array($this, 'plugin_settings_link'));

      // Register actions.
      add_action('admin_menu', array($this, 'add_plugin_page'));
      add_action('admin_init', array($this, 'page_init'));
    } // END __construct()


    /**
     * Plugin activation.
     */
    public function activate() {
      // Do nothing.
    } // END activate()


    /**
     * Plugin deactivation.
     */
    public function deactivate() {
      // Do nothing.
    } // END deactivate()


    /**
     * Add a link to this plugin's settings page from the WordPress Plugins page.
     * Called from "plugin_action_links" filter in __construct() above.
     */
    public function plugin_settings_link($links) {
      $settings_link = '<a href="options-general.php?page=ldap-sakai-auth">Settings</a>';
      array_unshift($links, $settings_link);
      return $links;
    } // END plugin_settings_link()


    /**
     * Create the options page under Dashboard > Settings
     * Run on action hook: admin_menu
     */
    public function add_plugin_page() {
error_log("asdf");
      // @see http://codex.wordpress.org/Function_Reference/add_options_page
      add_options_page(
        'LDAP Sakai Authorization', // Page title
        'LDAP Sakai Auth', // Menu title
        'manage_options', // Capability
        'ldap-sakai-auth', // Menu slug
        array($this, 'create_admin_page') // function
      );
    }


    /**
     * Output the HTML for the options page
     */
    public function create_admin_page() {
      ?>
      <div class="wrap">
        <?php screen_icon(); ?>
        <h2>Settings</h2>
        <form method="post" action="options.php">
          <?php
            // This prints out all hidden settings fields
            // @see http://codex.wordpress.org/Function_Reference/settings_fields
            settings_fields('lsa_settings_group');
            // This prints out all the sections
            // @see http://codex.wordpress.org/Function_Reference/do_settings_sections
            do_settings_sections('ldap-sakai-auth');
          ?>
          <?php submit_button(); ?>
        </form>
      </div>
      <?php
    }


    /**
     * Create sections and options
     * Run on action hook: admin_init
     */
    public function page_init() {
      // Create one setting that holds all the options (array)
      // @see http://codex.wordpress.org/Function_Reference/register_setting
      register_setting(
        'lsa_settings_group', // Option group
        'lsa_settings', // Option name
        array($this, 'sanitize_lsa_settings') // Sanitize callback
      );

      // @see http://codex.wordpress.org/Function_Reference/add_settings_section
      add_settings_section(
        'lsa_settings_ldap', // HTML element ID
        'LDAP Settings', // HTML element Title
        array($this, 'print_section_info_ldap'), // Callback (echos section content)
        'ldap-sakai-auth' // Page this section is shown on (slug)
      );

      // @see http://codex.wordpress.org/Function_Reference/add_settings_field
      add_settings_field(
        'lsa_settings_ldap_host', // HTML element ID
        'LDAP Directory Host', // HTML element Title
        array($this, 'print_text_lsa_ldap_host'), // Callback (echos form element)
        'ldap-sakai-auth', // Page this setting is shown on (slug)
        'lsa_settings_ldap' // Section this setting is shown on
      );
    }


    /**
     * Settings sanitizer callback
     */
    function sanitize_lsa_settings($lsa_settings) {
      // Sanitize LDAP Host setting
      if (filter_var($lsa_settings['ldap_host'], FILTER_VALIDATE_URL) === FALSE) {
        $lsa_settings['ldap_host'] = '';
      }
      // Sanitize ABC setting
      if (false) {
        $lsa_settings['somesetting'] = '';
      }

      if (get_option('lsa_settings') === FALSE) {
        add_option('lsa_settings', $lsa_settings);
      } else {
        update_option('lsa_settings', $lsa_settings);
      }
      return $lsa_settings;
    }


    /**
     * Setting print callbacks
     */
    function print_section_info_ldap() {
      print 'Enter your LDAP server settings below:';
    }
    function print_text_lsa_ldap_host() {
      $lsa = get_option('lsa_settings');
      ?><input type="text" id="lsa_settings_ldap_host" name="lsa_settings[ldap_host]" value="<?php print $lsa['ldap_host']; ?>" /><?php
    }

  } // END class WP_Plugin_LDAP_Sakai_Auth
}

// Installation and uninstallation hooks.
register_activation_hook(__FILE__, array('WP_Plugin_LDAP_Sakai_Auth', 'activate'));
register_deactivation_hook(__FILE__, array('WP_Plugin_LDAP_Sakai_Auth', 'deactivate'));

// Instantiate the plugin class.
$wp_plugin_ldap_sakai_auth = new WP_Plugin_LDAP_Sakai_Auth();
