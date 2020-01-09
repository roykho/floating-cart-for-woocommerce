<?php
/**
 * Plugin Name: WooCommerce Bookings
 * Plugin URI: https://woocommerce.com/products/woocommerce-bookings/
 * Description: Setup bookable products such as for reservations, services and hires.
 * Version: 5.4.0
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Text Domain: woocommerce-bookings
 * Domain Path: /languages
 * Tested up to: 1.32
 * WC tested up to: 2.32
 * WC requires at least: 2.6
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Woo: 390890:911c438934af094c2b38d5560b9f50f3
 * Copyright: © 2020 WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Files.FileName

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once 'woo-includes/woo-functions.php';
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '911c438934af094c2b38d5560b9f50f3', '390890' );

/**
 * WooCommerce fallback notice.
 *
 * @since 1.13.0
 */
function woocommerce_bookings_missing_wc_notice() {
	/* translators: %s WC download URL link. */
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Bookings requires WooCommerce to be installed and active. You can download %s here.', 'woocommerce-bookings' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
}

if ( ! defined( 'WC_BOOKINGS_ABSPATH' ) ) {
	define( 'WC_BOOKINGS_ABSPATH', dirname( __FILE__ ) . '/' );
}
// Action scheduler must be included before 'plugins_loaded' priority 0, the recommended way to included here as soon as the plugin file is loaded. https://actionscheduler.org/usage/.
$action_scheduler_exists = include WC_BOOKINGS_ABSPATH . 'vendor/prospress/action-scheduler/action-scheduler.php';

if ( false === $action_scheduler_exists ) {
	throw new Exception( 'vendor/prospress/action-scheduler/action-scheduler.php missing please run `composer install`' );
}

/**
 * Option key name for triggering activation notices.
 *
 * @since 1.14.4
 */
if ( ! defined( 'WC_BOOKINGS_ACTIVATION_NOTICE_KEY' ) ) {
	define( 'WC_BOOKINGS_ACTIVATION_NOTICE_KEY', 'woocommerce_bookings_show_activation_notice' );
}

register_activation_hook( __FILE__, 'woocommerce_bookings_activate' );

/**
 * Activation hook.
 */
function woocommerce_bookings_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_bookings_missing_wc_notice' );
		return;
	}

	// Flag to trigger activation notice after includes have been loaded.
	update_option( WC_BOOKINGS_ACTIVATION_NOTICE_KEY, true );

	// Register the rewrite endpoint before permalinks are flushed.
	add_rewrite_endpoint( apply_filters( 'woocommerce_bookings_account_endpoint', 'bookings' ), EP_PAGES );

	// Flush Permalinks.
	flush_rewrite_rules();
}


if ( ! class_exists( 'WC_Bookings' ) ) :

	define( 'WC_BOOKINGS_VERSION', '2.3.30' );

        define( 'WC_BOOKINGS_MIN_VERSION', '3.0.0' );


	define( 'WC_BOOKINGS_TEMPLATE_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/' );
	define( 'WC_BOOKINGS_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
	define( 'WC_BOOKINGS_MAIN_FILE', __FILE__ );
	define( 'WC_BOOKINGS_GUTENBERG_EXISTS', function_exists( 'register_block_type' ) ? true : false );
	if ( ! defined( 'WC_BOOKINGS_CONNECT_WOOCOMMERCE_URL' ) ) {
		define( 'WC_BOOKINGS_CONNECT_WOOCOMMERCE_URL', 'https://connect.woocommerce.com' );
	}

	if ( ! defined( 'WC_BOOKINGS_DEBUG' ) ) {
		define( 'WC_BOOKINGS_DEBUG', false );
	}

	/**
	 * WC Bookings class
	 */
	class WC_Bookings {
		/**
		 * The single instance of the class.
		 *
		 * @var $_instance
		 * @since 1.13.0
		 */
		protected static $_instance = null;

		/**
		 * Constructor.
		 *
		 * @since 1.13.0
		 */
		public function __construct() {
			$this->includes();
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

			// Do migrations.
			WC_Bookings_Install::init();
			$this->wpdb_table_fix();
			$this->init();

			/*
			 * Show activation notice.
			 *
			 * Large priority ensures this occurs after WooCommerce Admin has loaded.
			 */
			add_action( 'plugins_loaded', array( $this, 'show_activation_notice' ), 100 );
		}

		/**
		 * Show row meta on the plugin screen.
		 *
		 * @access public
		 * @param  mixed $links Plugin Row Meta.
		 * @param  mixed $file  Plugin Base file.
		 * @return array
		 */
		public function plugin_row_meta( $links, $file ) {
			if ( plugin_basename( WC_BOOKINGS_MAIN_FILE ) === $file ) {
				$row_meta = array(
					'docs'    => '<a href="' . esc_url( apply_filters( 'woocommerce_bookings_docs_url', 'https://docs.woocommerce.com/documentation/plugins/woocommerce/woocommerce-extensions/woocommerce-bookings/' ) ) . '" title="' . esc_attr( __( 'View Documentation', 'woocommerce-bookings' ) ) . '">' . __( 'Docs', 'woocommerce-bookings' ) . '</a>',
					'support' => '<a href="' . esc_url( apply_filters( 'woocommerce_bookings_support_url', 'https://woocommerce.com/my-account/tickets/' ) ) . '" title="' . esc_attr( __( 'Visit Premium Customer Support', 'woocommerce-bookings' ) ) . '">' . __( 'Premium Support', 'woocommerce-bookings' ) . '</a>',
				);

				return array_merge( $links, $row_meta );
			}

			return (array) $links;
		}

		/**
		 * Main Bookings Instance.
		 *
		 * Ensures only one instance of Bookings is loaded or can be loaded.
		 *
		 * @since 1.13.0
		 * @return WC_Bookings
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @since 1.13.0
		 */
		public function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'woocommerce-bookings' ), WC_BOOKINGS_VERSION );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @since 1.13.0
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce-bookings' ), WC_BOOKINGS_VERSION );
		}

		/**
		 * Cleanup on plugin deactivation.
		 *
		 * @since 1.11
		 */
		public function deactivate() {
			if ( class_exists( 'WC_Admin_Notes' ) ) {
				WC_Bookings_Inbox_Notice::remove_activity_panel_inbox_notes();
			} else {
				WC_Admin_Notices::remove_notice( 'woocommerce_bookings_activation' );
			}
		}

		/**
		 * Load Classes.
		 *
		 * @throws Exception When composer install hasn't been ran.
		 */
		public function includes() {
			$loader = include_once WC_BOOKINGS_ABSPATH . 'vendor/autoload.php';

			if ( ! $loader ) {
				throw new Exception( 'vendor/autoload.php missing please run `composer install`' );
			}

			require_once WC_BOOKINGS_ABSPATH . 'includes/wc-bookings-functions.php';
		}

		/**
		 * Init all the classes.
		 */
		private function init() {
			// Cache.
			new WC_Bookings_Cache();

			// Initialize.
			new WC_Bookings_Init();
			WC_Bookings_Timezone_Settings::instance();

			// WC AJAX.
			new WC_Bookings_WC_Ajax();

			WC_Booking_Form_Handler::init();
			new WC_Booking_Order_Manager();
			new WC_Product_Booking_Manager();
			new WC_Booking_Cron_Manager();
			WC_Bookings_Google_Calendar_Connection::instance();
			new WC_Booking_Coupon();

			if ( class_exists( 'WC_Product_Addons' ) ) {
				new WC_Bookings_Addons();
			}
			if ( class_exists( 'WC_Deposits' ) ) {
				new WC_Bookings_Deposits();
			}

			if ( class_exists( 'WC_Abstract_Privacy' ) ) {
				new WC_Booking_Privacy();
			}

			new WC_Booking_Email_Manager();
			new WC_Booking_Cart_Manager();
			new WC_Booking_Checkout_Manager();
			new WC_Bookings_REST_API();

			if ( is_admin() ) {
				new WC_Bookings_Menus();
				new WC_Bookings_Report_Dashboard();
				new WC_Bookings_Admin();
				new WC_Bookings_Ajax();
				new WC_Bookings_Admin_Add_Ons();
				new WC_Booking_Products_Export();
				new WC_Booking_Products_Import();
				new WC_Bookings_Tracks();
			}
		}

		/**
		 * Need to correct table names for meta to work.
		 */
		public function wpdb_table_fix() {
			global $wpdb;
			$wpdb->bookings_availabilitymeta = $wpdb->prefix . 'wc_bookings_availabilitymeta';
			$wpdb->tables[]                  = 'wc_bookings_availabilitymeta';
		}

		/**
		 * Shows admin notice after activation.
		 *
		 * Notices are triggered by a flag in options so they can be triggered once on activation
		 * and then actually shown once all necessary resources have been loaded.
		 *
		 * @since 1.14.4
		 */
		public function show_activation_notice() {
			if ( false !== get_option( WC_BOOKINGS_ACTIVATION_NOTICE_KEY ) ) {
				delete_option( WC_BOOKINGS_ACTIVATION_NOTICE_KEY );
				if ( class_exists( 'WC_Admin_Notes' ) ) {
					WC_Bookings_Inbox_Notice::add_activity_panel_inbox_welcome_note();
				} else {
					$notice_html = '<strong>' . esc_html__( 'Bookings has been activated!', 'woocommerce-bookings' ) . '</strong><br><br>';
					/* translators: 1: href link to list of bookings */
					$notice_html .= sprintf( __( '<a href="%s">Add or edit a product</a> to manage bookings in the Product Data section for individual products and then go to the <a href="%s" target="_blank">Bookings page</a> to manage them individually.', 'woocommerce-bookings' ), admin_url( 'post-new.php?post_type=product&bookable_product=1' ), admin_url( 'edit.php?post_type=wc_booking' ) );
					WC_Admin_Notices::add_custom_notice( 'woocommerce_bookings_activation', $notice_html );
				}
			}
		}
	}
endif;

add_action( 'plugins_loaded', 'woocommerce_bookings_init', 10 );

function woocommerce_bookings_init() {
	load_plugin_textdomain( 'woocommerce-bookings', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'woocommerce_bookings_missing_wc_notice' );
		return;
	}

	$GLOBALS['wc_bookings'] = WC_Bookings::instance();
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '2b8029f0d7cdd1118f4d843eb3ab43ff', '184594' );

if ( is_woocommerce_active() ) {
	define( 'WC_BOOKINGS_VERSION', '2.3.17' );


	/**
	 * Updates the plugin version to DB.
	 *
	 * @since 1.5.6
	 * @version 1.5.6
	 */
	function wc_checkout_fields_update_plugin_version() {
		update_option( 'wc_checkout_field_editor_version', WC_CHECKOUT_FIELD_EDITOR_VERSION );
	}

	/**
	 * Performs processes when plugin is activated.
	 *
	 * @since 1.5.6
	 * @version 1.5.6
	 */
	function wc_checkout_fields_activate() {
		wc_checkout_fields_update_plugin_version();
	}

	register_activation_hook( __FILE__, 'wc_checkout_fields_activate' );

	/**
	 * Performs installation processes such as migrations or data update.
	 *
	 * @since 1.5.6
	 * @version 1.5.6
	 */
	function wc_checkout_fields_install() {
		$version = get_option( 'wc_checkout_field_editor_version', WC_CHECKOUT_FIELD_EDITOR_VERSION );

		if ( version_compare( WC_VERSION, '3.0.0', '>=' ) && version_compare( $version, '1.5.6', '<' ) ) {
			wc_checkout_fields_wc30_migrate();
		}
	}

	add_action( 'admin_init', 'wc_checkout_fields_install' );

	/**
	 * Migrates pre WC3.0 data. Pre WC30 checkout field ordering is using
	 * "order" as the key. After WC30, its using "priority" as the key.
	 * This migration will rename the key name and re-set the priority values
	 * to align with WC core.
	 *
	 * @since 1.5.6
	 * @version 1.5.6
	 */
	function wc_checkout_fields_wc30_migrate() {
		$shipping_fields   = get_option( 'wc_fields_shipping', array() );
		$billing_fields    = get_option( 'wc_fields_billing', array() );
		$additional_fields = get_option( 'wc_fields_additional', array() );

		if ( ! empty( $shipping_fields ) ) {
			$migrated_shipping_fields = array();

			foreach ( $shipping_fields as $field => $value_arr ) {
				$migrated_shipping_value_arrs = array();

				foreach( $value_arr as $k => $v ) {
					if ( 'order' === $k ) {
						$migrated_shipping_value_arrs['priority'] = intval( $v ) * 10;
					} else {
						$migrated_shipping_value_arrs[ $k ] = $v;
					}
				}

				$migrated_shipping_fields[ $field ] = $migrated_shipping_value_arrs;
			}

			update_option( 'wc_fields_shipping', $migrated_shipping_fields );
		}

		if ( ! empty( $billing_fields ) ) {
			$migrated_billing_fields = array();

			foreach ( $billing_fields as $field => $value_arr ) {
				$migrated_billing_value_arrs = array();

				foreach( $value_arr as $k => $v ) {
					if ( 'order' === $k ) {
						$migrated_billing_value_arrs['priority'] = intval( $v ) * 10;
					} else {
						$migrated_billing_value_arrs[ $k ] = $v;
					}
				}

				$migrated_billing_fields[ $field ] = $migrated_billing_value_arrs;
			}

			update_option( 'wc_fields_billing', $migrated_billing_fields );
		}

		if ( ! empty( $additional_fields ) ) {
			$migrated_additional_fields = array();

			foreach ( $additional_fields as $field => $value_arr ) {
				$migrated_additional_value_arrs = array();

				foreach( $value_arr as $k => $v ) {
					if ( 'order' === $k ) {
						$migrated_additional_value_arrs['priority'] = intval( $v ) * 10;
					} else {
						$migrated_additional_value_arrs[ $k ] = $v;
					}
				}

				$migrated_additional_fields[ $field ] = $migrated_additional_value_arrs;
			}

			update_option( 'wc_fields_additional', $migrated_additional_fields );
		}

		wc_checkout_fields_update_plugin_version();
	}

	/**
	 * woocommerce_init_checkout_field_editor function.
	 */
	function woocommerce_init_checkout_field_editor() {
		global $supress_field_modification;

		$supress_field_modification = false;

		if ( ! class_exists( 'WC_Checkout_Field_Editor' ) ) {
			require_once( 'includes/class-wc-checkout-field-editor.php' );
		}

		if ( ! class_exists( 'WC_Checkout_Field_Editor_PIP_Integration' ) ) {
			require_once( 'includes/class-wc-checkout-field-editor-pip-integration.php' );
		}
        
        if ( ! class_exists( 'WC_Checkout_Field_Editor_Privacy' ) ) {
			require_once( 'includes/class-wc-checkout-field-editor-privacy.php' );
		}


		/**
		 * Localisation
		 */
		load_plugin_textdomain( 'woocommerce-checkout-field-editor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		new WC_Checkout_Field_Editor_PIP_Integration();

		$GLOBALS['wc_checkout_field_editor'] = new WC_Checkout_Field_Editor();
	}
	add_action( 'init', 'woocommerce_init_checkout_field_editor' );


	/**
	 * Load Export Handler later as init priority 10 is too soon
	 */
	function woocommmerce_init_cfe_export_handler() {

		if ( ! class_exists( 'WC_Checkout_Field_Editor_Export_Handler' ) ) {
			require_once( 'includes/class-wc-checkout-field-editor-export-handler.php' );
			new WC_Checkout_Field_Editor_Export_Handler();
		}
	}
	add_action( 'init', 'woocommmerce_init_cfe_export_handler', 99 );

	/**
	 * Display custom fields in emails
	 *
	 * @param array $fields Current custom fields
	 * @param bool $sent_to_admin Is order being sent to an admin
	 * @param object $order Order object
	 * @return array
	 */
	function wc_checkout_fields_add_custom_fields_to_emails( $fields = array(), $sent_to_admin = false, $order  ) {
		$custom_keys   = array();
		$custom_fields = array_merge(
			WC_Checkout_Field_Editor::get_fields( 'billing' ),
			WC_Checkout_Field_Editor::get_fields( 'shipping' ),
			WC_Checkout_Field_Editor::get_fields( 'additional' )
		);

		// Loop through all custom fields to see if it should be added
		foreach ( $custom_fields as $name => $options ) {
			if ( isset( $options['display_options'] ) ) {
				if ( in_array( 'emails', $options['display_options'] ) ) {
					$custom_keys[ esc_attr( $name ) ] = array(
						'label' => esc_attr( $options[ 'label' ] ),
						'value' => esc_attr( wc_get_checkout_field_value( $order, $name, $options ) )
					);
				}
			}
		}

		return $custom_keys;
	}

	add_filter( 'woocommerce_email_order_meta_fields', 'wc_checkout_fields_add_custom_fields_to_emails', 10, 3 );

	/**
	 * wc_checkout_fields_modify_billing_fields function.
	 *
	 * @param mixed $old
	 */
	function wc_checkout_fields_modify_billing_fields( $old ) {
		global $supress_field_modification;

		if ( $supress_field_modification ) {
			return $old;
		}

		return wc_checkout_fields_modify_fields( get_option( 'wc_fields_billing' ), $old );
	}

	// Use Priority 1 so that the changes from Checkout Field Editor apply first. 3rd party plugins may add extra fields later.
	add_filter( 'woocommerce_billing_fields', 'wc_checkout_fields_modify_billing_fields', 1 );

	/**
	 * wc_checkout_fields_modify_shipping_fields function.
	 *
	 * @param mixed $old
	 */
	function wc_checkout_fields_modify_shipping_fields( $old ) {
		global $supress_field_modification;

		if ( $supress_field_modification ) {
			return $old;
		}

		return wc_checkout_fields_modify_fields( get_option( 'wc_fields_shipping' ), $old );
	}

	// Use Priority 1 so that the changes from Checkout Field Editor apply first. 3rd party plugins may add extra fields later.
	add_filter( 'woocommerce_shipping_fields', 'wc_checkout_fields_modify_shipping_fields', 1 );

	/**
	 * wc_checkout_fields_modify_shipping_fields function.
	 *
	 * @param mixed $old
	 */
	function wc_checkout_fields_modify_order_fields( $fields ) {
		global $supress_field_modification;

		if ( $supress_field_modification ) {
			return $fields;
		}

		if ( $additional_fields = get_option( 'wc_fields_additional' ) ) {
			$fields['order'] = $additional_fields + $fields['order'];

			// check if order_comments is enabled/disabled
			if ( isset( $additional_fields ) && isset( $additional_fields['order_comments'] ) && ! $additional_fields['order_comments']['enabled'] ) {
				unset( $fields['order']['order_comments'] );

				// Remove the additional information header if there are no other additional fields
				if ( 1 === count( $additional_fields ) ) {
					do_action( 'wc_checkout_fields_disable_order_comments' );
				}
			}
		}

		return $fields;
	}

	add_filter( 'woocommerce_checkout_fields', 'wc_checkout_fields_modify_order_fields', 1000 );

	/**
	 * Adding our own action here so that 3rd party plugins can remove this
	 * because they may need to keep the additional information header even
	 * when order comments are disabled
	 *
	 */
	function wc_checkout_fields_maybe_hide_additional_info_header() {
		add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
	}

	add_action( 'wc_checkout_fields_disable_order_comments', 'wc_checkout_fields_maybe_hide_additional_info_header' );

	/**
	 * Modify the array of billing and shipping fields.
	 *
	 * @param mixed $data New checkout fields from this plugin.
	 * @param mixed $old Existing checkout fields from WC.
	 */
	function wc_checkout_fields_modify_fields( $data, $old_fields ) {
		if ( empty( $data ) ) {
			// If we have made no modifications, return the original.
			return $old_fields;
		}
