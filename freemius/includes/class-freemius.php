<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}


	final class Freemius {
		/**
		 * @var string
		 */
		public $version = '1.0.2';

		private $_id;
		private $_public_key;
		private $_slug;
		private $_logger;
		private $_plugin_basename;
		private $_plugin_main_file_path;
		private $_plugin_data;

		private static $_instances = array();
		/**
		 * @var FS_User
		 */
		private $_user;
		/**
		 * @var FS_Site
		 */
		private $_site;
		/**
		 * @var FS_Logger
		 */
		private static $_static_logger;

		/**
		 * @var FS_Option_Manager
		 */
		private static $_accounts;

		private function __construct( $slug ) {
			$this->_slug = $slug;

			$this->_logger = FS_Logger::get_logger( WP_FS__SLUG . '_' . $slug, WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

			$bt = debug_backtrace();
			$i  = 1;
			while ($i < count($bt) - 1 && false !== strpos( $bt[ $i ]['file'], '/freemius/' ) ) {
				$i ++;
			}

			$this->_plugin_main_file_path = $bt[ $i ]['file'];
			$this->_plugin_basename       = plugin_basename( $this->_plugin_main_file_path );
			$this->_plugin_data           = get_plugin_data( $this->_plugin_main_file_path );

			$this->_logger->info( 'plugin_basename = ' . $this->_plugin_basename );

			// Hook to plugin activation
			register_activation_hook( $this->_plugin_main_file_path, array( &$this, '_activate_plugin_event' ) );

			// Hook to plugin uninstall.
			register_uninstall_hook( $this->_plugin_main_file_path, array( 'Freemius', '_uninstall_plugin' ) );

			$this->_load_account();
		}

		static function instance( $slug ) {
			$slug = strtolower( $slug );

			if ( ! isset( self::$_instances[ $slug ] ) ) {
				if ( 0 === count( self::$_instances ) ) {
					self::_load_required_static();
				}

				self::$_instances[ $slug ] = new Freemius( $slug );
			}

			return self::$_instances[ $slug ];
		}

		/**
		 * @param $plugin_file
		 *
		 * @return bool|Freemius
		 */
		static function load_instance_by_file($plugin_file) {
			$sites = self::$_accounts->get_option( 'sites' );

			return isset( $sites[ $plugin_file ] ) ? self::instance( $sites[ $plugin_file ]->slug ) : false;
		}

		private static $_statics_loaded = false;
		private static function _load_required_static() {
			if (self::$_statics_loaded)
				return;

			self::$_static_logger = FS_Logger::get_logger( WP_FS__SLUG, WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

			self::$_static_logger->entrance();

			self::$_accounts = FS_Option_Manager::get_manager( WP_FS__ACCOUNTS_OPTION_NAME, true );

			self::$_statics_loaded = true;
		}

		/***
		 * Load account information (user + site).
		 */
		private function _load_account() {
			$this->_logger->entrance();

			eval(base64_decode('CgkJCSRzaXRlcyA9IHNlbGY6OiRfYWNjb3VudHMtPmdldF9vcHRpb24oICdzaXRlcycgKTsKCQkJJHVzZXJzID0gc2VsZjo6JF9hY2NvdW50cy0+Z2V0X29wdGlvbiggJ3VzZXJzJyApOwoKCQkJaWYgKCAhIGlzX2FycmF5KCAkc2l0ZXMgKSApIHsKCQkJCSRzaXRlcyA9IGFycmF5KCk7CgkJCX0KCgkJCWlmICggISBpc19hcnJheSggJHVzZXJzICkgKSB7CgkJCQkkdXNlcnMgPSBhcnJheSgpOwoJCQl9CgoJCQlpZiAoICR0aGlzLT5fbG9nZ2VyLT5pc19vbigpICkgewoJCQkJJHRoaXMtPl9sb2dnZXItPmxvZyggJ3NpdGUgPSAnIC4gdmFyX2V4cG9ydCggJHNpdGVzLCB0cnVlICkgKTsKCQkJfQoKCQkJaWYgKCBpc3NldCggJHNpdGVzWyAkdGhpcy0+X3BsdWdpbl9iYXNlbmFtZSBdICkgJiYgaXNfb2JqZWN0KCAkc2l0ZXNbICR0aGlzLT5fcGx1Z2luX2Jhc2VuYW1lIF0gKSApIHsKCQkJCS8vIExvYWQgc2l0ZS4KCQkJCSR0aGlzLT5fc2l0ZSA9ICRzaXRlc1sgJHRoaXMtPl9wbHVnaW5fYmFzZW5hbWUgXTsKCQkJCS8vIExvYWQgcmVsZXZhbnQgdXNlci4KCQkJCSR0aGlzLT5fdXNlciA9ICR1c2Vyc1sgJHRoaXMtPl9zaXRlLT51c2VyX2lkIF07CgkJCX0gZWxzZSB7CgkJCQlzZWxmOjokX3N0YXRpY19sb2dnZXItPmluZm8oICdUcnlpbmcgdG8gbG9hZCBhY2NvdW50IGZyb20gZXh0ZXJuYWwgc291cmNlIHdpdGggJyAuICdmc19sb2FkX2FjY291bnRfJyAuICR0aGlzLT5fc2x1ZyApOwoKCQkJCSRhY2NvdW50ID0gYXBwbHlfZmlsdGVycyggJ2ZzX2xvYWRfYWNjb3VudF8nIC4gJHRoaXMtPl9zbHVnLCBmYWxzZSApOwoKCQkJCWlmICggZmFsc2UgIT09ICRhY2NvdW50ICkgewoJCQkJCSR0aGlzLT5fc2l0ZSA9ICRhY2NvdW50WydzaXRlJ107CgkJCQkJJHRoaXMtPl91c2VyID0gJGFjY291bnRbJ3VzZXInXTsKCgkJCQkJaWYgKCBpc19vYmplY3QoICR0aGlzLT5fc2l0ZSApICkgewoJCQkJCQlzZWxmOjokX3N0YXRpY19sb2dnZXItPmluZm8oICdBY2NvdW50IGxvYWRlZDogdXNlcl9pZCA9ICcgLiAkdGhpcy0+X3VzZXItPmlkIC4gJzsgc2l0ZV9pZCA9ICcgLiAkdGhpcy0+X3NpdGUtPmlkIC4gJzsnICk7CgoJCQkJCQkkdGhpcy0+X3NpdGUtPnNsdWcgICAgICAgICAgICAgICAgPSAkdGhpcy0+X3NsdWc7CgkJCQkJCSR0aGlzLT5fc2l0ZS0+dXNlcl9pZCAgICAgICAgICAgICA9ICR0aGlzLT5fdXNlci0+aWQ7CgkJCQkJCSR0aGlzLT5fc2l0ZS0+dmVyc2lvbiAgICAgICAgICAgICA9ICR0aGlzLT5nZXRfcGx1Z2luX3ZlcnNpb24oKTsKCQkJCQkJJHNpdGVzWyAkdGhpcy0+X3BsdWdpbl9iYXNlbmFtZSBdID0gJHRoaXMtPl9zaXRlOwoJCQkJCQkkdXNlcnNbICR0aGlzLT5fdXNlci0+aWQgXSAgICAgICAgPSAkdGhpcy0+X3VzZXI7CgoJCQkJCQlzZWxmOjokX2FjY291bnRzLT5zZXRfb3B0aW9uKCAnc2l0ZXMnLCAkc2l0ZXMgKTsKCQkJCQkJc2VsZjo6JF9hY2NvdW50cy0+c2V0X29wdGlvbiggJ3VzZXJzJywgJHVzZXJzICk7CgoJCQkJCQkvLyBTdG9yZSBuZXcgYWNjb3VudCBpbmZvcm1hdGlvbiBhZnRlciBsb2FkaW5nIGZyb20gZXh0ZXJuYWwgc291cmNlLgoJCQkJCQlzZWxmOjokX2FjY291bnRzLT5zdG9yZSgpOwoJCQkJCX0KCQkJCX0KCQkJfQoJCQk='));
		}

		function init( $id, $public_key, $options ) {
			$this->_logger->entrance();

			if ( 'rating-widget' !== $this->_slug && ! is_plugin_active( 'rating-widget/rating-widget.php' ) && file_exists( WP_FS__DIR_INCLUDES . '/_class-dummy-rw-plugin.php' ) ) {
				require_once WP_FS__DIR_INCLUDES . '/class-dummy-rw-plugin.php';
			}


			$this->get_plugin_version();

			$this->_public_key            = $public_key;
			$this->_id                    = $id;


			if ( ! $this->is_registered() ) {
				return;
			}

			if ( is_admin() ) {
				if ( isset( $options['menu'] ) ) // Plugin has menu.
				{
					$this->set_has_menu();
				}

				$this->_init_admin();
			}
		}

		private function _init_admin()
		{
			register_deactivation_hook( $this->_plugin_main_file_path, array( &$this, '_deactivate_plugin_event' ) );

			add_action( 'admin_init', array( &$this, '_add_upgrade_action_link' ) );
			add_action( 'admin_menu', array( &$this, '_add_dashboard_menu' ), WP_FS__LOWEST_PRIORITY );
			add_action( 'init', array( &$this, '_redirect_on_clicked_menu_link' ), WP_FS__LOWEST_PRIORITY );
			add_action( 'fs_after_license_loaded', array( $this, 'add_default_submenu_items' ) );
		}

		/* Events
		------------------------------------------------------------------------------------------------------------------*/
		function _delete_site()
		{
			$sites = self::$_accounts->get_option( 'sites' );
			if ( isset( $sites[ $this->_plugin_basename ] ) ) {
				unset( $sites[ $this->_plugin_basename ] );
			}

			self::$_accounts->set_option( 'sites', $sites, true );
		}

		function _activate_plugin_event() {
			$this->_logger->entrance('slug = ' . $this->_slug);

			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			// Send event.
		}

		function delete_account_event() {
			$this->_logger->entrance('slug = ' . $this->_slug);

			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$this->_delete_site();

			// Send event.
		}

		function _deactivate_plugin_event() {
			$this->_logger->entrance('slug = ' . $this->_slug);

			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			// Send event.
		}

		function _uninstall_plugin_event() {
			$this->_logger->entrance( 'slug = ' . $this->_slug );

			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$this->_delete_site();

			// Send event.

		}

		public static function _uninstall_plugin() {
			self::_load_required_static();

			self::$_static_logger->entrance();

			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$plugin_file = substr(current_filter(), strlen('uninstall_'));

			self::$_static_logger->info('plugin = ' . $plugin_file);

			$fs = self::load_instance_by_file($plugin_file);

			if (is_object($fs))
				$fs->_uninstall_plugin_event();
		}

		/* Account
		------------------------------------------------------------------------------------------------------------------*/
		function is_registered() {
			return is_object( $this->_user );
		}

		/**
		 * @return FS_User
		 */
		function get_user() {
			return $this->_user;
		}

		/**
		 * @return FS_Site
		 */
		function get_site() {
			return $this->_site;
		}

		function get_plan() {

		}

		/* Licensing
		------------------------------------------------------------------------------------------------------------------*/
		function _a87ff679a2f3e71d9181a67b7542122c() {
			$this->_logger->entrance();

			return eval(base64_decode('CgoJCQlyZXR1cm4gcmF0aW5nd2lkZ2V0KCktPl9jZmNkMjA4NDk1ZDU2NWVmNjZlN2RmZjlmOTg3NjRkYSgpOwoJCQk='));
		}

		function _e4da3b7fbbce2345d7772b0674a318d5() {
			$this->_logger->entrance();

			return eval(base64_decode('CgoJCQlyZXR1cm4gcmF0aW5nd2lkZ2V0KCktPl9jNGNhNDIzOGEwYjkyMzgyMGRjYzUwOWE2Zjc1ODQ5YigpOwoJCQk='));
		}

		function _1679091c5a880faf6fb5e6087eb1b2dc( $plan, $exact = false ) {
			$this->_logger->entrance();

			return eval(base64_decode('CgoJCQlyZXR1cm4gZmFsc2U7CgkJCQ=='));
		}

		function _8f14e45fceea167a5a36dedd4bea2543() {
			eval(base64_decode('CgkJCXJldHVybgoJCQkJLy8gQ2hlY2tzIGlmIENsb3VkRmxhcmUncyBIVFRQUyAoRmxleGlibGUgU1NMIHN1cHBvcnQpCgkJCQkoIGlzc2V0KCAkX1NFUlZFUlsnSFRUUF9YX0ZPUldBUkRFRF9QUk9UTyddICkgJiYgJ2h0dHBzJyA9PT0gc3RydG9sb3dlciggJF9TRVJWRVJbJ0hUVFBfWF9GT1JXQVJERURfUFJPVE8nXSApICkgfHwKCQkJCS8vIENoZWNrIGlmIEhUVFBTIHJlcXVlc3QuCgkJCQkoIGlzc2V0KCAkX1NFUlZFUlsnSFRUUFMnXSApICYmICdvbicgPT0gJF9TRVJWRVJbJ0hUVFBTJ10gKSB8fAoJCQkJKCBpc3NldCggJF9TRVJWRVJbJ1NFUlZFUl9QT1JUJ10gKSAmJiA0NDMgPT0gJF9TRVJWRVJbJ1NFUlZFUl9QT1JUJ10gKTsKCQkJ'));
		}

		function _c9f0f895fb98ab9159f51fd0297e236d( $plan, $exact = false ) {
			return ( $this->_8f14e45fceea167a5a36dedd4bea2543() && $this->_1679091c5a880faf6fb5e6087eb1b2dc( $plan, $exact ) );
		}

		function get_upgrade_url( $plan = WP_FS__PLAN_DEFAULT_PAID, $period = WP_FS__PERIOD_ANNUALLY ) {
			$this->_logger->entrance();

			return ratingwidget()->GetUpgradeUrl( false, $period, $plan );
		}

		function get_pricing_url( $period = WP_FS__PERIOD_ANNUALLY ) {
			$this->_logger->entrance();

			return '';
		}

		function get_account_url() {
			return add_query_arg( array( 'page' => $this->_slug . '-account' ), admin_url( 'admin.php', 'admin' ) );
		}

		function get_plugin_folder_name() {
			$this->_logger->entrance();

			$plugin_folder = $this->_plugin_basename;

			while ( '.' !== dirname( $plugin_folder ) ) {
				$plugin_folder = dirname( $plugin_folder );
			}

			$this->_logger->departure('Folder Name = ' . $plugin_folder);

			return $plugin_folder;
		}

		function get_plugin_version() {
			$this->_logger->entrance();

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			$plugins_data = get_plugins( '/' . $this->get_plugin_folder_name() );

			$this->_logger->info('filename = ' . basename( $this->_plugin_main_file_path ));

			$version = $plugins_data[ basename( $this->_plugin_main_file_path ) ]['Version'];

			$this->_logger->departure( 'Version = ' . $version );

			return $version;
		}

		/* Logger
		------------------------------------------------------------------------------------------------------------------*/
		/**
		 * @param string $id
		 * @param bool $prefix_slug
		 *
		 * @return FS_Logger
		 */
		function get_logger( $id = '', $prefix_slug = true ) {
			return FS_Logger::get_logger( ( $prefix_slug ? $this->_slug : '' ) . ( ( ! $prefix_slug || empty( $id ) ) ? '' : '_' ) . $id );
		}

		/**
		 * @param $id
		 * @param bool $load_options
		 * @param bool $prefix_slug
		 *
		 * @return FS_Option_Manager
		 */
		function get_options_manager( $id, $load_options = false, $prefix_slug = true ) {
			return FS_Option_Manager::get_manager( ( $prefix_slug ? $this->_slug : '' ) . ( ( ! $prefix_slug || empty( $id ) ) ? '' : '_' ) . $id, $load_options );
		}

		/* Management Dashboard Menu
		------------------------------------------------------------------------------------------------------------------*/
		private $_has_menu = false;
		private $_menu_items = array();
		private $_menu_link_items = array();

		function _redirect_on_clicked_menu_link() {
			$this->_logger->entrance();

			$page = strtolower( isset( $_REQUEST['page'] ) ? $_REQUEST['page'] : '' );

			$this->_logger->log( 'page = ' . $page );


			foreach ( $this->_menu_link_items as $priority => $items) {
				foreach ( $items as $item ) {
					if ( $page === $item['menu_slug'] ) {
						$this->_logger->log( 'Redirecting to ' . $item['url'] );

						fs_redirect( $item['url'] );
					}
				}
			}
		}

		function _add_dashboard_menu() {
			$this->_logger->entrance();

			// Add user account page.
			$this->add_submenu_item(
				__( 'Account', $this->_slug ),
				array( &$this, '_account_page_render' ),
				$this->_plugin_data['Name'] . ' &ndash; ' . __( 'Account', $this->_slug ),
				'manage_options',
				'account',
				array( &$this, '_account_page_load' )
			);

			foreach ( $this->_menu_items as $item ) {
				$hook = add_submenu_page(
					$this->_slug,
					$item['page_title'],
					$item['menu_title'],
					$item['capability'],
					$item['menu_slug'],
					$item['render_function']
				);

				if ( false !== $item['before_render_function'] ) {
					add_action( "load-$hook", $item['before_render_function'] );
				}
			}

			ksort($this->_menu_link_items);

			foreach ( $this->_menu_link_items as $priority => $items) {
				foreach ( $items as $item ) {
					add_submenu_page(
						$this->_slug,
						$item['page_title'],
						$item['menu_title'],
						$item['capability'],
						$item['menu_slug'],
						array( $this, '' )
					);
				}
			}
		}

		function add_default_submenu_items() {
			if (!$this->_has_menu)
				return;

			$this->add_submenu_link_item( __( 'Support Forum', $this->_slug ), 'https://wordpress.org/support/plugin/' . $this->_slug, 'wp-support-forum', 'read', 50 );

			if ( ! $this->_e4da3b7fbbce2345d7772b0674a318d5() ) {
				$this->add_submenu_link_item( '&#9733; ' . __( 'Upgrade', $this->_slug ) . ' &#9733;', $this->get_upgrade_url(), 'upgrade', 'read', 100 );
			}
		}

		function set_has_menu() {
			$this->_logger->entrance();

			$this->_has_menu = true;
		}

		private function _get_menu_slug( $slug = '' ) {
			return $this->_slug . ( empty( $slug ) ? '' : ( '-' . $slug ) );
		}

		function add_submenu_item( $menu_title, $render_function, $page_title = false, $capability = 'manage_options', $menu_slug = false, $before_render_function = false ) {
			$this->_logger->entrance();

			$this->_menu_items[] = array(
				'page_title'             => is_string( $page_title ) ? $page_title : $menu_title,
				'menu_title'             => $menu_title,
				'capability'             => $capability,
				'menu_slug'              => $this->_get_menu_slug( is_string( $menu_slug ) ? $menu_slug : strtolower( $menu_title ) ),
				'render_function'        => $render_function,
				'before_render_function' => $before_render_function,
			);

			$this->_has_menu = true;
		}

		function add_submenu_link_item( $menu_title, $url, $menu_slug = false, $capability = 'read', $priority = 10 ) {
			$this->_logger->entrance('Title = ' . $menu_title . '; Url = ' . $url);

			if (!isset($this->_menu_link_items[$priority]))
				$this->_menu_link_items[$priority] = array();

			$this->_menu_link_items[$priority][] = array(
				'menu_title'             => $menu_title,
				'capability'             => $capability,
				'menu_slug'              => $this->_get_menu_slug( is_string( $menu_slug ) ? $menu_slug : strtolower( $menu_title ) ),
				'url'                    => $url,
				'page_title'             => $menu_title,
				'render_function'        => 'fs_dummy',
				'before_render_function' => '',
			);

			$this->_has_menu = true;
		}

		/* Actions / Hooks / Filters
		------------------------------------------------------------------------------------------------------------------*/
		function do_action( $tag ) {
			$this->_logger->entrance( $tag );

			do_action( $tag . '_' . $this->_slug );
		}

		function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
			$this->_logger->entrance( $tag );

			add_action( $tag . '_' . $this->_slug, $function_to_add, $priority, $accepted_args );
		}

		/* Account Page
		------------------------------------------------------------------------------------------------------------------*/
		static function _account_details_updated_message() {
			$vars = array(
				"message" => "You have successfully updated your account details.",
				"type"    => "update-nag success"
			);

			fs_require_once_template( "admin-notice.php", $vars );
		}

		private function _store_site()
		{
			$sites = self::$_accounts->get_option( 'sites' );
			$sites[ $this->_plugin_basename ] = $this->get_site();
			self::$_accounts->set_option( 'sites', $sites, true );
		}

		private function _handle_account_edits()
		{
			$properties = array('site_secret_key', 'site_id', 'site_public_key');

			foreach ($properties as $p)
			{
				if ( fs_request_is_action( 'update_' . $p ) ) {
					check_admin_referer( 'update_' . $p );

					$this->_logger->log( 'update_' . $p );

					$site_property = substr($p, strlen('site_'));
					$site_property_value = fs_request_get( 'fs_' . $p . '_' . $this->_slug, '' );
					$this->get_site()->{$site_property} = $site_property_value;

					// Store account after modification.
					$this->_store_site();

					do_action('fs_account_property_edit_' . $this->_slug, 'site', $site_property, $site_property_value);

					// Anonymous functions are only available since PHP 5.3
					add_action( 'all_admin_notices', array('Freemius', '_account_details_updated_message') );

					break;
				}
			}
		}

		function _account_page_load() {
			$this->_logger->entrance();

			$this->_logger->info( var_export( $_REQUEST, true ) );

			fs_enqueue_local_style( 'fs_account', 'account.css' );

			$this->_handle_account_edits();

			$this->do_action( 'fs_account_page_load_before_departure' );
		}

		function _account_page_render() {
			$this->_logger->entrance();

			$vars = array( 'slug' => $this->_slug );
			fs_require_once_template( 'user-account.php', $vars );
		}

		/* Action Links
		------------------------------------------------------------------------------------------------------------------*/
		private $_action_links_hooked = false;
		private $_action_links = array();

		private function is_plugin_action_links_hooked() {
			$this->_logger->entrance( json_encode( $this->_action_links_hooked ) );

			return $this->_action_links_hooked;
		}

		private function hook_plugin_action_links() {
			$this->_logger->entrance();

			$this->_action_links_hooked = true;

			$this->_logger->log( 'Adding action links hooks.' );

			// Add action link to settings page.
			add_filter( 'plugin_action_links_' . $this->_plugin_basename, array(
					&$this,
					'_modify_plugin_action_links'
				), 10, 2 );
			add_filter( 'network_admin_plugin_action_links_' . $this->_plugin_basename, array(
					&$this,
					'_modify_plugin_action_links'
				), 10, 2 );
		}

		function add_plugin_action_link( $label, $url, $external = false, $priority = 10, $key = false ) {
			$this->_logger->entrance();

			if ( ! isset( $this->_action_links[ $priority ] ) ) {
				$this->_action_links[ $priority ] = array();
			}

			if ( false === $key ) {
				$key = preg_replace( "/[^A-Za-z0-9 ]/", '', strtolower( $label ) );
			}

			$this->_action_links[ $priority ][] = array(
				'label'    => $label,
				'href'     => $url,
				'key'      => $key,
				'external' => $external
			);

			if ( ! $this->is_plugin_action_links_hooked() ) {
				$this->hook_plugin_action_links();
			}
		}

		function _add_upgrade_action_link() {
			$this->_logger->entrance();

			if ( ! $this->_e4da3b7fbbce2345d7772b0674a318d5() ) {
				$this->add_plugin_action_link( __( 'Upgrade', $this->_slug ), $this->get_upgrade_url(), true, 20, 'upgrade' );
			}
		}

		function _modify_plugin_action_links( $links, $file ) {
			$this->_logger->entrance();

			ksort( $this->_action_links );

			foreach ( $this->_action_links as $new_links ) {
				foreach ( $new_links as $link ) {
					$links[ $link['key'] ] = '<a href="' . $link['href'] . '"' . ( $link['external'] ? ' target="_blank"' : '' ) . '>' . $link['label'] . '</a>';
				}
			}

			return $links;
		}
	}
