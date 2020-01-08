<?php
/**
 * Plugin Name: WooCommerce Bookings
 * Plugin URI: https://woocommerce.com/products/woocommerce-bookings/
 * Description: Setup bookable products such as for reservations, services and hires.
 * Version: 2.3.9
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Text Domain: woocommerce-bookings
 * Domain Path: /languages
 * Tested up to: 5.3
 * WC tested up to: 3.8
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

	define( 'WC_BOOKINGS_VERSION', '2.3.9' );
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
	define( 'WC_CHECKOUT_FIELD_EDITOR_VERSION', '1.5.23' );

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

		$fields = $data;

		foreach ( $fields as $name => $values ) {
			if ( false === $values['enabled'] ) {
				unset( $fields[ $name ] );
			}

			// Replace locale field properties so they are unchanged.
			if ( ! in_array( $name, array(
				'billing_address_1',
				'billing_state',
				'billing_city',
				'billing_country',
				'billing_postcode',
				'shipping_address_1',
				'shipping_country',
				'shipping_state',
				'shipping_city',
				'shipping_country',
				'shipping_postcode',
				'order_comments',
			) ) ) {
				continue;
			}


			if ( ! isset( $fields[ $name ] ) ) {
				continue;
			}

			$fields[ $name ]                = $old_fields[ $name ];
			$fields[ $name ]['label']       = ! empty( $data[ $name ]['label'] ) ? $data[ $name ]['label'] : $old_fields[ $name ]['label'];

			if ( ! empty( $data[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $data[ $name ]['placeholder'];

			} elseif ( ! empty( $old_fields[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $old_fields[ $name ]['placeholder'];

			} else {
				$fields[ $name ]['placeholder'] = '';
			}

			$fields[ $name ]['class']       = $data[ $name ]['class'];

			if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
				$fields[ $name ]['clear'] = $data[ $name ]['clear'];
			} else {
				$fields[ $name ]['priority'] = $data[ $name ]['priority'];
			}
		}

		return $fields;
	}

	/**
	 * wc_checkout_fields_scripts function.
	 *
	 */
	function wc_checkout_fields_scripts() {
		global $wp_scripts;

		if ( is_checkout() ) {
			wp_enqueue_script( 'wc-checkout-editor-frontend', plugins_url( '/dist/js/frontend.js' , __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ), WC()->version, true );

			$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';

			wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $jquery_version . '/themes/smoothness/jquery-ui.css' );

			$pattern = array(
				//day
				'd',		//day of the month
				'j',		//3 letter name of the day
				'l',		//full name of the day
				'z',		//day of the year
				'S',

				//month
				'F',		//Month name full
				'M',		//Month name short
				'n',		//numeric month no leading zeros
				'm',		//numeric month leading zeros

				//year
				'Y', 		//full numeric year
				'y',		//numeric year: 2 digit
			);
			$replace = array(
				'dd',
				'd',
				'DD',
				'o',
				'',
				'MM',
				'M',
				'm',
				'mm',
				'yy',
				'y',
			);

			foreach ( $pattern as &$p ) {
				$p = '/' . $p . '/';
			}

			wp_localize_script( 'wc-checkout-editor-frontend', 'wc_checkout_fields', array(
				'date_format' => preg_replace( $pattern, $replace, wc_date_format() ),
			) );
		}
	}

	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_scripts' );

	/**
	 * wc_checkout_fields_date_picker_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_date_picker_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		if ( ! empty( $args['validate'] ) ) {
			foreach ( $args['validate'] as $validate ) {
				$args['class'][] = 'validate-' . $validate;
			}
		}

		$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		if ( $args['label'] ) {
			$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
		}

		$field .= '<input readonly type="text" class="checkout-date-picker input-text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" placeholder="' . $args['placeholder'] . '" ' . $args['maxlength'] . ' value="' . esc_attr( $value ) . '" />
			</p>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_radio_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_radio_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$field = '<div data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		$field .= '<fieldset><legend>' . $args['label'] . $required . '</legend>';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$field .= '<label><input type="radio" ' . checked( $value, esc_attr( $option_text ), false ) . ' name="' . esc_attr( $key ) . '" value="' . esc_attr( $option_text ) . '" /> ' . esc_html( $option_text ) . '</label>';
			}
		}

		$field .= '</fieldset></div>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_multiselect_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_multiselect_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$options = '';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$options .= '<option ' . selected( $value, $option_key, false ) . '>' . esc_attr( $option_text ) . '</option>';
			}

			$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

			if ( $args['label'] ) {
				$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
			}

			$class = '';

			$field .= '<select data-placeholder="' . __( 'Select some options', 'woocommerce-checkout-field-editor' ) . '" multiple="multiple" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $key ) . '" class="checkout_chosen_select select wc-enhanced-select ' . $class . '">
					' . $options . '
				</select>
			</p>' . $after;
		}

		return $field;
	}

	/**
	 * wc_checkout_fields_heading_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_heading_field( $field = '', $key, $args, $value ) {
		$field = '<h3 data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">' . $args['label'] . '</h3>';

		return $field;
	}

	add_filter( 'woocommerce_form_field_radio', 'wc_checkout_fields_radio_field', 10, 4 );
	add_filter( 'woocommerce_form_field_date', 'wc_checkout_fields_date_picker_field', 10, 4 );
	add_filter( 'woocommerce_form_field_multiselect', 'wc_checkout_fields_multiselect_field', 10, 4 );
	add_filter( 'woocommerce_form_field_heading', 'wc_checkout_fields_heading_field', 10, 4 );

	/**
	 * wc_checkout_fields_validation function.
	 *
	 * @param mixed $posted
	 */
	function wc_checkout_fields_validation( $posted ) {
		foreach ( WC()->checkout->checkout_fields as $fieldset_key => $fieldset ) {

			// Skip shipping if its not needed
			if ( 'shipping' === $fieldset_key && ( wc_ship_to_billing_address_only() || ! empty( $posted['shiptobilling'] ) || ( ! WC()->cart->needs_shipping() && 'no' === get_option( 'woocommerce_require_shipping_address' ) ) ) ) {
				continue;
			}

			foreach ( $fieldset as $key => $field ) {

				if ( ! empty( $field['validate'] ) && is_array( $field['validate'] ) ) {

					// ZIP doesn't have field's type. Pass it to avoid notice.
					if ( ! isset( $field['type'] ) ) {
						continue;
					}

					// For non-checkbox fields, `required` validation already
					// handled properly by WC core. However WC core sets unchecked
					// checkbox's value to `0` which then bypass the validation
					// of checking emptiness.
					//
					// @see https://github.com/woocommerce/woocommerce/blob/461ec4da1626b28e2a106a4e4530cb22a19e7d36/includes/class-wc-checkout.php#L449-L450
					if ( 'checkbox' !== $field['type'] && empty( $posted[ $key ] ) ) {
						continue;
					}

					foreach ( $field['validate'] as $rule ) {
						switch ( $rule ) {
							case 'required':

								if ( 'checkbox' === $field['type'] && 0 === $posted[ $key ] ) {
									wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . __( 'is a required field.', 'woocommerce-checkout-field-editor' ), 'error' );
								}

							break;
							case 'number' :

								if ( ! is_numeric( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '>=' ) ) {
										wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ), 'error' );
									} else {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
							case 'email' :

								if ( ! is_email( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '<' ) ) {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid email address.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
						}
					}
				}
			}
		}
	}

	add_action( 'woocommerce_after_checkout_validation', 'wc_checkout_fields_validation' );

	/**
	 * Get custom checkout fields.
	 *
	 * @param  object $order
	 * @param  array $types
	 * @return array $custom_fields
	 */
	function wc_get_custom_checkout_fields( $order, $types = array( 'billing', 'shipping', 'additional' ) ) {
		$order_id      = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$all_fields    = array();
		$custom_fields = array();

		// Get all the fields
		foreach ( $types as $type ) {
			// Skip if an unsupported type
			if ( ! in_array( $type, array( 'billing', 'shipping', 'additional' ) ) ) {
				continue;
			}

			$temp_fields = get_option( 'wc_fields_' . $type );
			if ( false !== $temp_fields ) {
				$all_fields = array_merge( $all_fields, $temp_fields );
			}
		}

		// Loop through each field to see if it is a custom field
		foreach ( $all_fields as $name => $options ) {
			if ( isset( $options['custom'] ) && 1 == $options['custom'] ) {
				$custom_fields[ $name ] = $options;
			}
		}

		return $custom_fields;
	}

	/**
	 * Display custom checkout fields on view order pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields_view_order( $order ) {
		$fields   = wc_get_custom_checkout_fields( $order );
		$found    = false;
		$html     = '';

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			$option_value = wc_get_checkout_field_value( $order, $name, $options );
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== $option_value ) {
				$found = true;
				$html .= '<dt>' . esc_attr( $options['label'] ) . ':</dt>';
				$html .= '<dd>' . $option_value . '</dd>';
			}
		}

		if ( $found ) {
			echo '<dl>';
			echo $html;
			echo '</dl>';
		}
	}

	/**
	 * Add fields to view order/thanks pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields( $order ) {
		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			add_action( 'woocommerce_order_details_after_customer_details', 'wc_display_custom_fields_view_order', 20, 1 );
		} else {
			add_action( 'woocommerce_order_details_after_order_table', 'wc_display_custom_fields_view_order', 20, 1 );
		}
	}

	add_action( 'woocommerce_loaded', 'wc_display_custom_fields', 20, 1 );

	/**
	 * Get custom checkout fields data for admin order area
	 *
	 * @param object $order
	 * @param array $types
	 */
	function wc_get_custom_fields_for_admin_order( $order, $types ) {
		$order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$fields   = wc_get_custom_checkout_fields( $order, $types );
		$html     = '<div class="address custom_checkout_fields">';
		$found    = false;

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== get_post_meta( $order_id, $name, true ) ) {
				$found = true;
				$html .= '<p><strong>' . esc_attr( $options['label'] ) . ':</strong>' . get_post_meta( $order_id, $name, true ) . '</p>';
			}
		}

		$html .= '</div>';

		if ( $found ) {
			echo $html;
		}
	}

	/**
	 * Display custom billing checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_billing_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'billing' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'wc_display_custom_billing_fields_admin_order', 20, 1 );

	/**
	 * Display custom shipping and additional checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_shipping_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'shipping', 'additional' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_shipping_address', 'wc_display_custom_shipping_fields_admin_order', 20, 1 );

	/**
	 * Remove the localization WC core script to ensure
	 * the order remains how it is set in the field editor
	 * settings.
	 */
	function wc_checkout_fields_dequeue_address_i18n() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( apply_filters( 'wc_checkout_fields_dequeue_address_i18n', true ) ) {
			wp_dequeue_script( 'wc-address-i18n' );
			wp_deregister_script( 'wc-address-i18n' );

			wp_register_script( 'wc-address-i18n', plugins_url( '/dist/js/frontend.js', __FILE__ ), array( 'jquery', 'wc-country-select' ), WC_CHECKOUT_FIELD_EDITOR_VERSION, true );
		}
	}
	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_dequeue_address_i18n', 15 );

	/**
	 * Returns the value of an order's checkout field
	 *
	 * @param object $orderd Field's order
	 * @param string $name Field's name
	 * @param array $options Field's properties
	 * @return array
	 */
	function wc_get_checkout_field_value( $order, $name, $options ) {
		$order_id    = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$field_value = get_post_meta( $order_id, $name, true );

		if ( 'checkbox' === $options[ 'type' ] && '1' === $field_value ) {
			$field_value = __( 'yes', 'woocommerce-checkout-field-editor' );
		}

		return $field_value;
	}

} // end is_woocommerce_active() conditional check

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '2b8029f0d7cdd1118f4d843eb3ab43ff', '184594' );

if ( is_woocommerce_active() ) {
	define( 'WC_CHECKOUT_FIELD_EDITOR_VERSION', '1.5.23' );

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

		$fields = $data;

		foreach ( $fields as $name => $values ) {
			if ( false === $values['enabled'] ) {
				unset( $fields[ $name ] );
			}

			// Replace locale field properties so they are unchanged.
			if ( ! in_array( $name, array(
				'billing_address_1',
				'billing_state',
				'billing_city',
				'billing_country',
				'billing_postcode',
				'shipping_address_1',
				'shipping_country',
				'shipping_state',
				'shipping_city',
				'shipping_country',
				'shipping_postcode',
				'order_comments',
			) ) ) {
				continue;
			}


			if ( ! isset( $fields[ $name ] ) ) {
				continue;
			}

			$fields[ $name ]                = $old_fields[ $name ];
			$fields[ $name ]['label']       = ! empty( $data[ $name ]['label'] ) ? $data[ $name ]['label'] : $old_fields[ $name ]['label'];

			if ( ! empty( $data[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $data[ $name ]['placeholder'];

			} elseif ( ! empty( $old_fields[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $old_fields[ $name ]['placeholder'];

			} else {
				$fields[ $name ]['placeholder'] = '';
			}

			$fields[ $name ]['class']       = $data[ $name ]['class'];

			if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
				$fields[ $name ]['clear'] = $data[ $name ]['clear'];
			} else {
				$fields[ $name ]['priority'] = $data[ $name ]['priority'];
			}
		}

		return $fields;
	}

	/**
	 * wc_checkout_fields_scripts function.
	 *
	 */
	function wc_checkout_fields_scripts() {
		global $wp_scripts;

		if ( is_checkout() ) {
			wp_enqueue_script( 'wc-checkout-editor-frontend', plugins_url( '/dist/js/frontend.js' , __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ), WC()->version, true );

			$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';

			wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $jquery_version . '/themes/smoothness/jquery-ui.css' );

			$pattern = array(
				//day
				'd',		//day of the month
				'j',		//3 letter name of the day
				'l',		//full name of the day
				'z',		//day of the year
				'S',

				//month
				'F',		//Month name full
				'M',		//Month name short
				'n',		//numeric month no leading zeros
				'm',		//numeric month leading zeros

				//year
				'Y', 		//full numeric year
				'y',		//numeric year: 2 digit
			);
			$replace = array(
				'dd',
				'd',
				'DD',
				'o',
				'',
				'MM',
				'M',
				'm',
				'mm',
				'yy',
				'y',
			);

			foreach ( $pattern as &$p ) {
				$p = '/' . $p . '/';
			}

			wp_localize_script( 'wc-checkout-editor-frontend', 'wc_checkout_fields', array(
				'date_format' => preg_replace( $pattern, $replace, wc_date_format() ),
			) );
		}
	}

	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_scripts' );

	/**
	 * wc_checkout_fields_date_picker_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_date_picker_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		if ( ! empty( $args['validate'] ) ) {
			foreach ( $args['validate'] as $validate ) {
				$args['class'][] = 'validate-' . $validate;
			}
		}

		$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		if ( $args['label'] ) {
			$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
		}

		$field .= '<input readonly type="text" class="checkout-date-picker input-text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" placeholder="' . $args['placeholder'] . '" ' . $args['maxlength'] . ' value="' . esc_attr( $value ) . '" />
			</p>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_radio_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_radio_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$field = '<div data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		$field .= '<fieldset><legend>' . $args['label'] . $required . '</legend>';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$field .= '<label><input type="radio" ' . checked( $value, esc_attr( $option_text ), false ) . ' name="' . esc_attr( $key ) . '" value="' . esc_attr( $option_text ) . '" /> ' . esc_html( $option_text ) . '</label>';
			}
		}

		$field .= '</fieldset></div>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_multiselect_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_multiselect_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$options = '';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$options .= '<option ' . selected( $value, $option_key, false ) . '>' . esc_attr( $option_text ) . '</option>';
			}

			$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

			if ( $args['label'] ) {
				$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
			}

			$class = '';

			$field .= '<select data-placeholder="' . __( 'Select some options', 'woocommerce-checkout-field-editor' ) . '" multiple="multiple" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $key ) . '" class="checkout_chosen_select select wc-enhanced-select ' . $class . '">
					' . $options . '
				</select>
			</p>' . $after;
		}

		return $field;
	}

	/**
	 * wc_checkout_fields_heading_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_heading_field( $field = '', $key, $args, $value ) {
		$field = '<h3 data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">' . $args['label'] . '</h3>';

		return $field;
	}

	add_filter( 'woocommerce_form_field_radio', 'wc_checkout_fields_radio_field', 10, 4 );
	add_filter( 'woocommerce_form_field_date', 'wc_checkout_fields_date_picker_field', 10, 4 );
	add_filter( 'woocommerce_form_field_multiselect', 'wc_checkout_fields_multiselect_field', 10, 4 );
	add_filter( 'woocommerce_form_field_heading', 'wc_checkout_fields_heading_field', 10, 4 );

	/**
	 * wc_checkout_fields_validation function.
	 *
	 * @param mixed $posted
	 */
	function wc_checkout_fields_validation( $posted ) {
		foreach ( WC()->checkout->checkout_fields as $fieldset_key => $fieldset ) {

			// Skip shipping if its not needed
			if ( 'shipping' === $fieldset_key && ( wc_ship_to_billing_address_only() || ! empty( $posted['shiptobilling'] ) || ( ! WC()->cart->needs_shipping() && 'no' === get_option( 'woocommerce_require_shipping_address' ) ) ) ) {
				continue;
			}

			foreach ( $fieldset as $key => $field ) {

				if ( ! empty( $field['validate'] ) && is_array( $field['validate'] ) ) {

					// ZIP doesn't have field's type. Pass it to avoid notice.
					if ( ! isset( $field['type'] ) ) {
						continue;
					}

					// For non-checkbox fields, `required` validation already
					// handled properly by WC core. However WC core sets unchecked
					// checkbox's value to `0` which then bypass the validation
					// of checking emptiness.
					//
					// @see https://github.com/woocommerce/woocommerce/blob/461ec4da1626b28e2a106a4e4530cb22a19e7d36/includes/class-wc-checkout.php#L449-L450
					if ( 'checkbox' !== $field['type'] && empty( $posted[ $key ] ) ) {
						continue;
					}

					foreach ( $field['validate'] as $rule ) {
						switch ( $rule ) {
							case 'required':

								if ( 'checkbox' === $field['type'] && 0 === $posted[ $key ] ) {
									wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . __( 'is a required field.', 'woocommerce-checkout-field-editor' ), 'error' );
								}

							break;
							case 'number' :

								if ( ! is_numeric( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '>=' ) ) {
										wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ), 'error' );
									} else {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
							case 'email' :

								if ( ! is_email( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '<' ) ) {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid email address.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
						}
					}
				}
			}
		}
	}

	add_action( 'woocommerce_after_checkout_validation', 'wc_checkout_fields_validation' );

	/**
	 * Get custom checkout fields.
	 *
	 * @param  object $order
	 * @param  array $types
	 * @return array $custom_fields
	 */
	function wc_get_custom_checkout_fields( $order, $types = array( 'billing', 'shipping', 'additional' ) ) {
		$order_id      = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$all_fields    = array();
		$custom_fields = array();

		// Get all the fields
		foreach ( $types as $type ) {
			// Skip if an unsupported type
			if ( ! in_array( $type, array( 'billing', 'shipping', 'additional' ) ) ) {
				continue;
			}

			$temp_fields = get_option( 'wc_fields_' . $type );
			if ( false !== $temp_fields ) {
				$all_fields = array_merge( $all_fields, $temp_fields );
			}
		}

		// Loop through each field to see if it is a custom field
		foreach ( $all_fields as $name => $options ) {
			if ( isset( $options['custom'] ) && 1 == $options['custom'] ) {
				$custom_fields[ $name ] = $options;
			}
		}

		return $custom_fields;
	}

	/**
	 * Display custom checkout fields on view order pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields_view_order( $order ) {
		$fields   = wc_get_custom_checkout_fields( $order );
		$found    = false;
		$html     = '';

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			$option_value = wc_get_checkout_field_value( $order, $name, $options );
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== $option_value ) {
				$found = true;
				$html .= '<dt>' . esc_attr( $options['label'] ) . ':</dt>';
				$html .= '<dd>' . $option_value . '</dd>';
			}
		}

		if ( $found ) {
			echo '<dl>';
			echo $html;
			echo '</dl>';
		}
	}

	/**
	 * Add fields to view order/thanks pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields( $order ) {
		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			add_action( 'woocommerce_order_details_after_customer_details', 'wc_display_custom_fields_view_order', 20, 1 );
		} else {
			add_action( 'woocommerce_order_details_after_order_table', 'wc_display_custom_fields_view_order', 20, 1 );
		}
	}

	add_action( 'woocommerce_loaded', 'wc_display_custom_fields', 20, 1 );

	/**
	 * Get custom checkout fields data for admin order area
	 *
	 * @param object $order
	 * @param array $types
	 */
	function wc_get_custom_fields_for_admin_order( $order, $types ) {
		$order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$fields   = wc_get_custom_checkout_fields( $order, $types );
		$html     = '<div class="address custom_checkout_fields">';
		$found    = false;

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== get_post_meta( $order_id, $name, true ) ) {
				$found = true;
				$html .= '<p><strong>' . esc_attr( $options['label'] ) . ':</strong>' . get_post_meta( $order_id, $name, true ) . '</p>';
			}
		}

		$html .= '</div>';

		if ( $found ) {
			echo $html;
		}
	}

	/**
	 * Display custom billing checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_billing_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'billing' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'wc_display_custom_billing_fields_admin_order', 20, 1 );

	/**
	 * Display custom shipping and additional checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_shipping_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'shipping', 'additional' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_shipping_address', 'wc_display_custom_shipping_fields_admin_order', 20, 1 );

	/**
	 * Remove the localization WC core script to ensure
	 * the order remains how it is set in the field editor
	 * settings.
	 */
	function wc_checkout_fields_dequeue_address_i18n() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( apply_filters( 'wc_checkout_fields_dequeue_address_i18n', true ) ) {
			wp_dequeue_script( 'wc-address-i18n' );
			wp_deregister_script( 'wc-address-i18n' );

			wp_register_script( 'wc-address-i18n', plugins_url( '/dist/js/frontend.js', __FILE__ ), array( 'jquery', 'wc-country-select' ), WC_CHECKOUT_FIELD_EDITOR_VERSION, true );
		}
	}
	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_dequeue_address_i18n', 15 );

	/**
	 * Returns the value of an order's checkout field
	 *
	 * @param object $orderd Field's order
	 * @param string $name Field's name
	 * @param array $options Field's properties
	 * @return array
	 */
	function wc_get_checkout_field_value( $order, $name, $options ) {
		$order_id    = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$field_value = get_post_meta( $order_id, $name, true );

		if ( 'checkbox' === $options[ 'type' ] && '1' === $field_value ) {
			$field_value = __( 'yes', 'woocommerce-checkout-field-editor' );
		}

		return $field_value;
	}

} // end is_woocommerce_active() conditional check

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '2b8029f0d7cdd1118f4d843eb3ab43ff', '184594' );

if ( is_woocommerce_active() ) {
	define( 'WC_CHECKOUT_FIELD_EDITOR_VERSION', '1.5.23' );

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

		$fields = $data;

		foreach ( $fields as $name => $values ) {
			if ( false === $values['enabled'] ) {
				unset( $fields[ $name ] );
			}

			// Replace locale field properties so they are unchanged.
			if ( ! in_array( $name, array(
				'billing_address_1',
				'billing_state',
				'billing_city',
				'billing_country',
				'billing_postcode',
				'shipping_address_1',
				'shipping_country',
				'shipping_state',
				'shipping_city',
				'shipping_country',
				'shipping_postcode',
				'order_comments',
			) ) ) {
				continue;
			}


			if ( ! isset( $fields[ $name ] ) ) {
				continue;
			}

			$fields[ $name ]                = $old_fields[ $name ];
			$fields[ $name ]['label']       = ! empty( $data[ $name ]['label'] ) ? $data[ $name ]['label'] : $old_fields[ $name ]['label'];

			if ( ! empty( $data[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $data[ $name ]['placeholder'];

			} elseif ( ! empty( $old_fields[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $old_fields[ $name ]['placeholder'];

			} else {
				$fields[ $name ]['placeholder'] = '';
			}

			$fields[ $name ]['class']       = $data[ $name ]['class'];

			if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
				$fields[ $name ]['clear'] = $data[ $name ]['clear'];
			} else {
				$fields[ $name ]['priority'] = $data[ $name ]['priority'];
			}
		}

		return $fields;
	}

	/**
	 * wc_checkout_fields_scripts function.
	 *
	 */
	function wc_checkout_fields_scripts() {
		global $wp_scripts;

		if ( is_checkout() ) {
			wp_enqueue_script( 'wc-checkout-editor-frontend', plugins_url( '/dist/js/frontend.js' , __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ), WC()->version, true );

			$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';

			wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $jquery_version . '/themes/smoothness/jquery-ui.css' );

			$pattern = array(
				//day
				'd',		//day of the month
				'j',		//3 letter name of the day
				'l',		//full name of the day
				'z',		//day of the year
				'S',

				//month
				'F',		//Month name full
				'M',		//Month name short
				'n',		//numeric month no leading zeros
				'm',		//numeric month leading zeros

				//year
				'Y', 		//full numeric year
				'y',		//numeric year: 2 digit
			);
			$replace = array(
				'dd',
				'd',
				'DD',
				'o',
				'',
				'MM',
				'M',
				'm',
				'mm',
				'yy',
				'y',
			);

			foreach ( $pattern as &$p ) {
				$p = '/' . $p . '/';
			}

			wp_localize_script( 'wc-checkout-editor-frontend', 'wc_checkout_fields', array(
				'date_format' => preg_replace( $pattern, $replace, wc_date_format() ),
			) );
		}
	}

	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_scripts' );

	/**
	 * wc_checkout_fields_date_picker_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_date_picker_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		if ( ! empty( $args['validate'] ) ) {
			foreach ( $args['validate'] as $validate ) {
				$args['class'][] = 'validate-' . $validate;
			}
		}

		$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		if ( $args['label'] ) {
			$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
		}

		$field .= '<input readonly type="text" class="checkout-date-picker input-text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" placeholder="' . $args['placeholder'] . '" ' . $args['maxlength'] . ' value="' . esc_attr( $value ) . '" />
			</p>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_radio_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_radio_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$field = '<div data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		$field .= '<fieldset><legend>' . $args['label'] . $required . '</legend>';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$field .= '<label><input type="radio" ' . checked( $value, esc_attr( $option_text ), false ) . ' name="' . esc_attr( $key ) . '" value="' . esc_attr( $option_text ) . '" /> ' . esc_html( $option_text ) . '</label>';
			}
		}

		$field .= '</fieldset></div>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_multiselect_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_multiselect_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$options = '';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$options .= '<option ' . selected( $value, $option_key, false ) . '>' . esc_attr( $option_text ) . '</option>';
			}

			$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

			if ( $args['label'] ) {
				$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
			}

			$class = '';

			$field .= '<select data-placeholder="' . __( 'Select some options', 'woocommerce-checkout-field-editor' ) . '" multiple="multiple" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $key ) . '" class="checkout_chosen_select select wc-enhanced-select ' . $class . '">
					' . $options . '
				</select>
			</p>' . $after;
		}

		return $field;
	}

	/**
	 * wc_checkout_fields_heading_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_heading_field( $field = '', $key, $args, $value ) {
		$field = '<h3 data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">' . $args['label'] . '</h3>';

		return $field;
	}

	add_filter( 'woocommerce_form_field_radio', 'wc_checkout_fields_radio_field', 10, 4 );
	add_filter( 'woocommerce_form_field_date', 'wc_checkout_fields_date_picker_field', 10, 4 );
	add_filter( 'woocommerce_form_field_multiselect', 'wc_checkout_fields_multiselect_field', 10, 4 );
	add_filter( 'woocommerce_form_field_heading', 'wc_checkout_fields_heading_field', 10, 4 );

	/**
	 * wc_checkout_fields_validation function.
	 *
	 * @param mixed $posted
	 */
	function wc_checkout_fields_validation( $posted ) {
		foreach ( WC()->checkout->checkout_fields as $fieldset_key => $fieldset ) {

			// Skip shipping if its not needed
			if ( 'shipping' === $fieldset_key && ( wc_ship_to_billing_address_only() || ! empty( $posted['shiptobilling'] ) || ( ! WC()->cart->needs_shipping() && 'no' === get_option( 'woocommerce_require_shipping_address' ) ) ) ) {
				continue;
			}

			foreach ( $fieldset as $key => $field ) {

				if ( ! empty( $field['validate'] ) && is_array( $field['validate'] ) ) {

					// ZIP doesn't have field's type. Pass it to avoid notice.
					if ( ! isset( $field['type'] ) ) {
						continue;
					}

					// For non-checkbox fields, `required` validation already
					// handled properly by WC core. However WC core sets unchecked
					// checkbox's value to `0` which then bypass the validation
					// of checking emptiness.
					//
					// @see https://github.com/woocommerce/woocommerce/blob/461ec4da1626b28e2a106a4e4530cb22a19e7d36/includes/class-wc-checkout.php#L449-L450
					if ( 'checkbox' !== $field['type'] && empty( $posted[ $key ] ) ) {
						continue;
					}

					foreach ( $field['validate'] as $rule ) {
						switch ( $rule ) {
							case 'required':

								if ( 'checkbox' === $field['type'] && 0 === $posted[ $key ] ) {
									wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . __( 'is a required field.', 'woocommerce-checkout-field-editor' ), 'error' );
								}

							break;
							case 'number' :

								if ( ! is_numeric( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '>=' ) ) {
										wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ), 'error' );
									} else {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
							case 'email' :

								if ( ! is_email( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '<' ) ) {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid email address.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
						}
					}
				}
			}
		}
	}

	add_action( 'woocommerce_after_checkout_validation', 'wc_checkout_fields_validation' );

	/**
	 * Get custom checkout fields.
	 *
	 * @param  object $order
	 * @param  array $types
	 * @return array $custom_fields
	 */
	function wc_get_custom_checkout_fields( $order, $types = array( 'billing', 'shipping', 'additional' ) ) {
		$order_id      = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$all_fields    = array();
		$custom_fields = array();

		// Get all the fields
		foreach ( $types as $type ) {
			// Skip if an unsupported type
			if ( ! in_array( $type, array( 'billing', 'shipping', 'additional' ) ) ) {
				continue;
			}

			$temp_fields = get_option( 'wc_fields_' . $type );
			if ( false !== $temp_fields ) {
				$all_fields = array_merge( $all_fields, $temp_fields );
			}
		}

		// Loop through each field to see if it is a custom field
		foreach ( $all_fields as $name => $options ) {
			if ( isset( $options['custom'] ) && 1 == $options['custom'] ) {
				$custom_fields[ $name ] = $options;
			}
		}

		return $custom_fields;
	}

	/**
	 * Display custom checkout fields on view order pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields_view_order( $order ) {
		$fields   = wc_get_custom_checkout_fields( $order );
		$found    = false;
		$html     = '';

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			$option_value = wc_get_checkout_field_value( $order, $name, $options );
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== $option_value ) {
				$found = true;
				$html .= '<dt>' . esc_attr( $options['label'] ) . ':</dt>';
				$html .= '<dd>' . $option_value . '</dd>';
			}
		}

		if ( $found ) {
			echo '<dl>';
			echo $html;
			echo '</dl>';
		}
	}

	/**
	 * Add fields to view order/thanks pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields( $order ) {
		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			add_action( 'woocommerce_order_details_after_customer_details', 'wc_display_custom_fields_view_order', 20, 1 );
		} else {
			add_action( 'woocommerce_order_details_after_order_table', 'wc_display_custom_fields_view_order', 20, 1 );
		}
	}

	add_action( 'woocommerce_loaded', 'wc_display_custom_fields', 20, 1 );

	/**
	 * Get custom checkout fields data for admin order area
	 *
	 * @param object $order
	 * @param array $types
	 */
	function wc_get_custom_fields_for_admin_order( $order, $types ) {
		$order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$fields   = wc_get_custom_checkout_fields( $order, $types );
		$html     = '<div class="address custom_checkout_fields">';
		$found    = false;

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== get_post_meta( $order_id, $name, true ) ) {
				$found = true;
				$html .= '<p><strong>' . esc_attr( $options['label'] ) . ':</strong>' . get_post_meta( $order_id, $name, true ) . '</p>';
			}
		}

		$html .= '</div>';

		if ( $found ) {
			echo $html;
		}
	}

	/**
	 * Display custom billing checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_billing_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'billing' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'wc_display_custom_billing_fields_admin_order', 20, 1 );

	/**
	 * Display custom shipping and additional checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_shipping_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'shipping', 'additional' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_shipping_address', 'wc_display_custom_shipping_fields_admin_order', 20, 1 );

	/**
	 * Remove the localization WC core script to ensure
	 * the order remains how it is set in the field editor
	 * settings.
	 */
	function wc_checkout_fields_dequeue_address_i18n() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( apply_filters( 'wc_checkout_fields_dequeue_address_i18n', true ) ) {
			wp_dequeue_script( 'wc-address-i18n' );
			wp_deregister_script( 'wc-address-i18n' );

			wp_register_script( 'wc-address-i18n', plugins_url( '/dist/js/frontend.js', __FILE__ ), array( 'jquery', 'wc-country-select' ), WC_CHECKOUT_FIELD_EDITOR_VERSION, true );
		}
	}
	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_dequeue_address_i18n', 15 );

	/**
	 * Returns the value of an order's checkout field
	 *
	 * @param object $orderd Field's order
	 * @param string $name Field's name
	 * @param array $options Field's properties
	 * @return array
	 */
	function wc_get_checkout_field_value( $order, $name, $options ) {
		$order_id    = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$field_value = get_post_meta( $order_id, $name, true );

		if ( 'checkbox' === $options[ 'type' ] && '1' === $field_value ) {
			$field_value = __( 'yes', 'woocommerce-checkout-field-editor' );
		}

		return $field_value;
	}

} // end is_woocommerce_active() conditional check

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '2b8029f0d7cdd1118f4d843eb3ab43ff', '184594' );

if ( is_woocommerce_active() ) {
	define( 'WC_CHECKOUT_FIELD_EDITOR_VERSION', '1.5.23' );

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

		$fields = $data;

		foreach ( $fields as $name => $values ) {
			if ( false === $values['enabled'] ) {
				unset( $fields[ $name ] );
			}

			// Replace locale field properties so they are unchanged.
			if ( ! in_array( $name, array(
				'billing_address_1',
				'billing_state',
				'billing_city',
				'billing_country',
				'billing_postcode',
				'shipping_address_1',
				'shipping_country',
				'shipping_state',
				'shipping_city',
				'shipping_country',
				'shipping_postcode',
				'order_comments',
			) ) ) {
				continue;
			}


			if ( ! isset( $fields[ $name ] ) ) {
				continue;
			}

			$fields[ $name ]                = $old_fields[ $name ];
			$fields[ $name ]['label']       = ! empty( $data[ $name ]['label'] ) ? $data[ $name ]['label'] : $old_fields[ $name ]['label'];

			if ( ! empty( $data[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $data[ $name ]['placeholder'];

			} elseif ( ! empty( $old_fields[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $old_fields[ $name ]['placeholder'];

			} else {
				$fields[ $name ]['placeholder'] = '';
			}

			$fields[ $name ]['class']       = $data[ $name ]['class'];

			if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
				$fields[ $name ]['clear'] = $data[ $name ]['clear'];
			} else {
				$fields[ $name ]['priority'] = $data[ $name ]['priority'];
			}
		}

		return $fields;
	}

	/**
	 * wc_checkout_fields_scripts function.
	 *
	 */
	function wc_checkout_fields_scripts() {
		global $wp_scripts;

		if ( is_checkout() ) {
			wp_enqueue_script( 'wc-checkout-editor-frontend', plugins_url( '/dist/js/frontend.js' , __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ), WC()->version, true );

			$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';

			wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $jquery_version . '/themes/smoothness/jquery-ui.css' );

			$pattern = array(
				//day
				'd',		//day of the month
				'j',		//3 letter name of the day
				'l',		//full name of the day
				'z',		//day of the year
				'S',

				//month
				'F',		//Month name full
				'M',		//Month name short
				'n',		//numeric month no leading zeros
				'm',		//numeric month leading zeros

				//year
				'Y', 		//full numeric year
				'y',		//numeric year: 2 digit
			);
			$replace = array(
				'dd',
				'd',
				'DD',
				'o',
				'',
				'MM',
				'M',
				'm',
				'mm',
				'yy',
				'y',
			);

			foreach ( $pattern as &$p ) {
				$p = '/' . $p . '/';
			}

			wp_localize_script( 'wc-checkout-editor-frontend', 'wc_checkout_fields', array(
				'date_format' => preg_replace( $pattern, $replace, wc_date_format() ),
			) );
		}
	}

	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_scripts' );

	/**
	 * wc_checkout_fields_date_picker_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_date_picker_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		if ( ! empty( $args['validate'] ) ) {
			foreach ( $args['validate'] as $validate ) {
				$args['class'][] = 'validate-' . $validate;
			}
		}

		$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		if ( $args['label'] ) {
			$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
		}

		$field .= '<input readonly type="text" class="checkout-date-picker input-text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" placeholder="' . $args['placeholder'] . '" ' . $args['maxlength'] . ' value="' . esc_attr( $value ) . '" />
			</p>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_radio_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_radio_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$field = '<div data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		$field .= '<fieldset><legend>' . $args['label'] . $required . '</legend>';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$field .= '<label><input type="radio" ' . checked( $value, esc_attr( $option_text ), false ) . ' name="' . esc_attr( $key ) . '" value="' . esc_attr( $option_text ) . '" /> ' . esc_html( $option_text ) . '</label>';
			}
		}

		$field .= '</fieldset></div>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_multiselect_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_multiselect_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$options = '';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$options .= '<option ' . selected( $value, $option_key, false ) . '>' . esc_attr( $option_text ) . '</option>';
			}

			$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

			if ( $args['label'] ) {
				$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
			}

			$class = '';

			$field .= '<select data-placeholder="' . __( 'Select some options', 'woocommerce-checkout-field-editor' ) . '" multiple="multiple" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $key ) . '" class="checkout_chosen_select select wc-enhanced-select ' . $class . '">
					' . $options . '
				</select>
			</p>' . $after;
		}

		return $field;
	}

	/**
	 * wc_checkout_fields_heading_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_heading_field( $field = '', $key, $args, $value ) {
		$field = '<h3 data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">' . $args['label'] . '</h3>';

		return $field;
	}

	add_filter( 'woocommerce_form_field_radio', 'wc_checkout_fields_radio_field', 10, 4 );
	add_filter( 'woocommerce_form_field_date', 'wc_checkout_fields_date_picker_field', 10, 4 );
	add_filter( 'woocommerce_form_field_multiselect', 'wc_checkout_fields_multiselect_field', 10, 4 );
	add_filter( 'woocommerce_form_field_heading', 'wc_checkout_fields_heading_field', 10, 4 );

	/**
	 * wc_checkout_fields_validation function.
	 *
	 * @param mixed $posted
	 */
	function wc_checkout_fields_validation( $posted ) {
		foreach ( WC()->checkout->checkout_fields as $fieldset_key => $fieldset ) {

			// Skip shipping if its not needed
			if ( 'shipping' === $fieldset_key && ( wc_ship_to_billing_address_only() || ! empty( $posted['shiptobilling'] ) || ( ! WC()->cart->needs_shipping() && 'no' === get_option( 'woocommerce_require_shipping_address' ) ) ) ) {
				continue;
			}

			foreach ( $fieldset as $key => $field ) {

				if ( ! empty( $field['validate'] ) && is_array( $field['validate'] ) ) {

					// ZIP doesn't have field's type. Pass it to avoid notice.
					if ( ! isset( $field['type'] ) ) {
						continue;
					}

					// For non-checkbox fields, `required` validation already
					// handled properly by WC core. However WC core sets unchecked
					// checkbox's value to `0` which then bypass the validation
					// of checking emptiness.
					//
					// @see https://github.com/woocommerce/woocommerce/blob/461ec4da1626b28e2a106a4e4530cb22a19e7d36/includes/class-wc-checkout.php#L449-L450
					if ( 'checkbox' !== $field['type'] && empty( $posted[ $key ] ) ) {
						continue;
					}

					foreach ( $field['validate'] as $rule ) {
						switch ( $rule ) {
							case 'required':

								if ( 'checkbox' === $field['type'] && 0 === $posted[ $key ] ) {
									wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . __( 'is a required field.', 'woocommerce-checkout-field-editor' ), 'error' );
								}

							break;
							case 'number' :

								if ( ! is_numeric( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '>=' ) ) {
										wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ), 'error' );
									} else {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
							case 'email' :

								if ( ! is_email( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '<' ) ) {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid email address.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
						}
					}
				}
			}
		}
	}

	add_action( 'woocommerce_after_checkout_validation', 'wc_checkout_fields_validation' );

	/**
	 * Get custom checkout fields.
	 *
	 * @param  object $order
	 * @param  array $types
	 * @return array $custom_fields
	 */
	function wc_get_custom_checkout_fields( $order, $types = array( 'billing', 'shipping', 'additional' ) ) {
		$order_id      = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$all_fields    = array();
		$custom_fields = array();

		// Get all the fields
		foreach ( $types as $type ) {
			// Skip if an unsupported type
			if ( ! in_array( $type, array( 'billing', 'shipping', 'additional' ) ) ) {
				continue;
			}

			$temp_fields = get_option( 'wc_fields_' . $type );
			if ( false !== $temp_fields ) {
				$all_fields = array_merge( $all_fields, $temp_fields );
			}
		}

		// Loop through each field to see if it is a custom field
		foreach ( $all_fields as $name => $options ) {
			if ( isset( $options['custom'] ) && 1 == $options['custom'] ) {
				$custom_fields[ $name ] = $options;
			}
		}

		return $custom_fields;
	}

	/**
	 * Display custom checkout fields on view order pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields_view_order( $order ) {
		$fields   = wc_get_custom_checkout_fields( $order );
		$found    = false;
		$html     = '';

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			$option_value = wc_get_checkout_field_value( $order, $name, $options );
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== $option_value ) {
				$found = true;
				$html .= '<dt>' . esc_attr( $options['label'] ) . ':</dt>';
				$html .= '<dd>' . $option_value . '</dd>';
			}
		}

		if ( $found ) {
			echo '<dl>';
			echo $html;
			echo '</dl>';
		}
	}

	/**
	 * Add fields to view order/thanks pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields( $order ) {
		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			add_action( 'woocommerce_order_details_after_customer_details', 'wc_display_custom_fields_view_order', 20, 1 );
		} else {
			add_action( 'woocommerce_order_details_after_order_table', 'wc_display_custom_fields_view_order', 20, 1 );
		}
	}

	add_action( 'woocommerce_loaded', 'wc_display_custom_fields', 20, 1 );

	/**
	 * Get custom checkout fields data for admin order area
	 *
	 * @param object $order
	 * @param array $types
	 */
	function wc_get_custom_fields_for_admin_order( $order, $types ) {
		$order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$fields   = wc_get_custom_checkout_fields( $order, $types );
		$html     = '<div class="address custom_checkout_fields">';
		$found    = false;

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== get_post_meta( $order_id, $name, true ) ) {
				$found = true;
				$html .= '<p><strong>' . esc_attr( $options['label'] ) . ':</strong>' . get_post_meta( $order_id, $name, true ) . '</p>';
			}
		}

		$html .= '</div>';

		if ( $found ) {
			echo $html;
		}
	}

	/**
	 * Display custom billing checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_billing_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'billing' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'wc_display_custom_billing_fields_admin_order', 20, 1 );

	/**
	 * Display custom shipping and additional checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_shipping_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'shipping', 'additional' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_shipping_address', 'wc_display_custom_shipping_fields_admin_order', 20, 1 );

	/**
	 * Remove the localization WC core script to ensure
	 * the order remains how it is set in the field editor
	 * settings.
	 */
	function wc_checkout_fields_dequeue_address_i18n() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( apply_filters( 'wc_checkout_fields_dequeue_address_i18n', true ) ) {
			wp_dequeue_script( 'wc-address-i18n' );
			wp_deregister_script( 'wc-address-i18n' );

			wp_register_script( 'wc-address-i18n', plugins_url( '/dist/js/frontend.js', __FILE__ ), array( 'jquery', 'wc-country-select' ), WC_CHECKOUT_FIELD_EDITOR_VERSION, true );
		}
	}
	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_dequeue_address_i18n', 15 );

	/**
	 * Returns the value of an order's checkout field
	 *
	 * @param object $orderd Field's order
	 * @param string $name Field's name
	 * @param array $options Field's properties
	 * @return array
	 */
	function wc_get_checkout_field_value( $order, $name, $options ) {
		$order_id    = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$field_value = get_post_meta( $order_id, $name, true );

		if ( 'checkbox' === $options[ 'type' ] && '1' === $field_value ) {
			$field_value = __( 'yes', 'woocommerce-checkout-field-editor' );
		}

		return $field_value;
	}

} // end is_woocommerce_active() conditional check

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '2b8029f0d7cdd1118f4d843eb3ab43ff', '184594' );

if ( is_woocommerce_active() ) {
	define( 'WC_CHECKOUT_FIELD_EDITOR_VERSION', '1.5.23' );

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

		$fields = $data;

		foreach ( $fields as $name => $values ) {
			if ( false === $values['enabled'] ) {
				unset( $fields[ $name ] );
			}

			// Replace locale field properties so they are unchanged.
			if ( ! in_array( $name, array(
				'billing_address_1',
				'billing_state',
				'billing_city',
				'billing_country',
				'billing_postcode',
				'shipping_address_1',
				'shipping_country',
				'shipping_state',
				'shipping_city',
				'shipping_country',
				'shipping_postcode',
				'order_comments',
			) ) ) {
				continue;
			}


			if ( ! isset( $fields[ $name ] ) ) {
				continue;
			}

			$fields[ $name ]                = $old_fields[ $name ];
			$fields[ $name ]['label']       = ! empty( $data[ $name ]['label'] ) ? $data[ $name ]['label'] : $old_fields[ $name ]['label'];

			if ( ! empty( $data[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $data[ $name ]['placeholder'];

			} elseif ( ! empty( $old_fields[ $name ]['placeholder'] ) ) {
				$fields[ $name ]['placeholder'] = $old_fields[ $name ]['placeholder'];

			} else {
				$fields[ $name ]['placeholder'] = '';
			}

			$fields[ $name ]['class']       = $data[ $name ]['class'];

			if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {
				$fields[ $name ]['clear'] = $data[ $name ]['clear'];
			} else {
				$fields[ $name ]['priority'] = $data[ $name ]['priority'];
			}
		}

		return $fields;
	}

	/**
	 * wc_checkout_fields_scripts function.
	 *
	 */
	function wc_checkout_fields_scripts() {
		global $wp_scripts;

		if ( is_checkout() ) {
			wp_enqueue_script( 'wc-checkout-editor-frontend', plugins_url( '/dist/js/frontend.js' , __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ), WC()->version, true );

			$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';

			wp_enqueue_style( 'jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $jquery_version . '/themes/smoothness/jquery-ui.css' );

			$pattern = array(
				//day
				'd',		//day of the month
				'j',		//3 letter name of the day
				'l',		//full name of the day
				'z',		//day of the year
				'S',

				//month
				'F',		//Month name full
				'M',		//Month name short
				'n',		//numeric month no leading zeros
				'm',		//numeric month leading zeros

				//year
				'Y', 		//full numeric year
				'y',		//numeric year: 2 digit
			);
			$replace = array(
				'dd',
				'd',
				'DD',
				'o',
				'',
				'MM',
				'M',
				'm',
				'mm',
				'yy',
				'y',
			);

			foreach ( $pattern as &$p ) {
				$p = '/' . $p . '/';
			}

			wp_localize_script( 'wc-checkout-editor-frontend', 'wc_checkout_fields', array(
				'date_format' => preg_replace( $pattern, $replace, wc_date_format() ),
			) );
		}
	}

	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_scripts' );

	/**
	 * wc_checkout_fields_date_picker_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_date_picker_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		if ( ! empty( $args['validate'] ) ) {
			foreach ( $args['validate'] as $validate ) {
				$args['class'][] = 'validate-' . $validate;
			}
		}

		$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		if ( $args['label'] ) {
			$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
		}

		$field .= '<input readonly type="text" class="checkout-date-picker input-text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" placeholder="' . $args['placeholder'] . '" ' . $args['maxlength'] . ' value="' . esc_attr( $value ) . '" />
			</p>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_radio_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_radio_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$field = '<div data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

		$field .= '<fieldset><legend>' . $args['label'] . $required . '</legend>';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$field .= '<label><input type="radio" ' . checked( $value, esc_attr( $option_text ), false ) . ' name="' . esc_attr( $key ) . '" value="' . esc_attr( $option_text ) . '" /> ' . esc_html( $option_text ) . '</label>';
			}
		}

		$field .= '</fieldset></div>' . $after;

		return $field;
	}

	/**
	 * wc_checkout_fields_multiselect_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_multiselect_field( $field = '', $key, $args, $value ) {

		if ( ! empty( $args['clear'] ) && version_compare( WC_VERSION, '3.0.0', '<' ) ) {
			$after = '<div class="clear"></div>';
		} else {
			$after = '';
		}

		if ( $args['required'] ) {
			$args['class'][] = 'validate-required';
			$required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce-checkout-field-editor' ) . '">*</abbr>';
		} else {
			$required = '';
		}

		$args['maxlength'] = ( $args['maxlength'] ) ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : '';

		$options = '';

		if ( ! empty( $args['options'] ) ) {
			foreach ( $args['options'] as $option_key => $option_text ) {
				$options .= '<option ' . selected( $value, $option_key, false ) . '>' . esc_attr( $option_text ) . '</option>';
			}

			$field = '<p data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">';

			if ( $args['label'] ) {
				$field .= '<label for="' . esc_attr( $key ) . '" class="' . implode( ' ', $args['label_class'] ) . '">' . $args['label'] . $required . '</label>';
			}

			$class = '';

			$field .= '<select data-placeholder="' . __( 'Select some options', 'woocommerce-checkout-field-editor' ) . '" multiple="multiple" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $key ) . '" class="checkout_chosen_select select wc-enhanced-select ' . $class . '">
					' . $options . '
				</select>
			</p>' . $after;
		}

		return $field;
	}

	/**
	 * wc_checkout_fields_heading_field function.
	 *
	 * @param string $field (default: '')
	 * @param mixed $key
	 * @param mixed $args
	 * @param mixed $value
	 */
	function wc_checkout_fields_heading_field( $field = '', $key, $args, $value ) {
		$field = '<h3 data-priority="' . esc_attr( $args['priority'] ) . '" class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $key ) . '_field">' . $args['label'] . '</h3>';

		return $field;
	}

	add_filter( 'woocommerce_form_field_radio', 'wc_checkout_fields_radio_field', 10, 4 );
	add_filter( 'woocommerce_form_field_date', 'wc_checkout_fields_date_picker_field', 10, 4 );
	add_filter( 'woocommerce_form_field_multiselect', 'wc_checkout_fields_multiselect_field', 10, 4 );
	add_filter( 'woocommerce_form_field_heading', 'wc_checkout_fields_heading_field', 10, 4 );

	/**
	 * wc_checkout_fields_validation function.
	 *
	 * @param mixed $posted
	 */
	function wc_checkout_fields_validation( $posted ) {
		foreach ( WC()->checkout->checkout_fields as $fieldset_key => $fieldset ) {

			// Skip shipping if its not needed
			if ( 'shipping' === $fieldset_key && ( wc_ship_to_billing_address_only() || ! empty( $posted['shiptobilling'] ) || ( ! WC()->cart->needs_shipping() && 'no' === get_option( 'woocommerce_require_shipping_address' ) ) ) ) {
				continue;
			}

			foreach ( $fieldset as $key => $field ) {

				if ( ! empty( $field['validate'] ) && is_array( $field['validate'] ) ) {

					// ZIP doesn't have field's type. Pass it to avoid notice.
					if ( ! isset( $field['type'] ) ) {
						continue;
					}

					// For non-checkbox fields, `required` validation already
					// handled properly by WC core. However WC core sets unchecked
					// checkbox's value to `0` which then bypass the validation
					// of checking emptiness.
					//
					// @see https://github.com/woocommerce/woocommerce/blob/461ec4da1626b28e2a106a4e4530cb22a19e7d36/includes/class-wc-checkout.php#L449-L450
					if ( 'checkbox' !== $field['type'] && empty( $posted[ $key ] ) ) {
						continue;
					}

					foreach ( $field['validate'] as $rule ) {
						switch ( $rule ) {
							case 'required':

								if ( 'checkbox' === $field['type'] && 0 === $posted[ $key ] ) {
									wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . __( 'is a required field.', 'woocommerce-checkout-field-editor' ), 'error' );
								}

							break;
							case 'number' :

								if ( ! is_numeric( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '>=' ) ) {
										wc_add_notice( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ), 'error' );
									} else {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid number.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
							case 'email' :

								if ( ! is_email( $posted[ $key ] ) ) {

									if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3.0', '<' ) ) {
										WC()->add_error( '<strong>' . $field['label'] . '</strong> ' . sprintf( __( '(%s) is not a valid email address.', 'woocommerce-checkout-field-editor' ), $posted[ $key ] ) );
									}
								}

							break;
						}
					}
				}
			}
		}
	}

	add_action( 'woocommerce_after_checkout_validation', 'wc_checkout_fields_validation' );

	/**
	 * Get custom checkout fields.
	 *
	 * @param  object $order
	 * @param  array $types
	 * @return array $custom_fields
	 */
	function wc_get_custom_checkout_fields( $order, $types = array( 'billing', 'shipping', 'additional' ) ) {
		$order_id      = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$all_fields    = array();
		$custom_fields = array();

		// Get all the fields
		foreach ( $types as $type ) {
			// Skip if an unsupported type
			if ( ! in_array( $type, array( 'billing', 'shipping', 'additional' ) ) ) {
				continue;
			}

			$temp_fields = get_option( 'wc_fields_' . $type );
			if ( false !== $temp_fields ) {
				$all_fields = array_merge( $all_fields, $temp_fields );
			}
		}

		// Loop through each field to see if it is a custom field
		foreach ( $all_fields as $name => $options ) {
			if ( isset( $options['custom'] ) && 1 == $options['custom'] ) {
				$custom_fields[ $name ] = $options;
			}
		}

		return $custom_fields;
	}

	/**
	 * Display custom checkout fields on view order pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields_view_order( $order ) {
		$fields   = wc_get_custom_checkout_fields( $order );
		$found    = false;
		$html     = '';

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			$option_value = wc_get_checkout_field_value( $order, $name, $options );
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== $option_value ) {
				$found = true;
				$html .= '<dt>' . esc_attr( $options['label'] ) . ':</dt>';
				$html .= '<dd>' . $option_value . '</dd>';
			}
		}

		if ( $found ) {
			echo '<dl>';
			echo $html;
			echo '</dl>';
		}
	}

	/**
	 * Add fields to view order/thanks pages.
	 *
	 * @param object $order
	 */
	function wc_display_custom_fields( $order ) {
		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			add_action( 'woocommerce_order_details_after_customer_details', 'wc_display_custom_fields_view_order', 20, 1 );
		} else {
			add_action( 'woocommerce_order_details_after_order_table', 'wc_display_custom_fields_view_order', 20, 1 );
		}
	}

	add_action( 'woocommerce_loaded', 'wc_display_custom_fields', 20, 1 );

	/**
	 * Get custom checkout fields data for admin order area
	 *
	 * @param object $order
	 * @param array $types
	 */
	function wc_get_custom_fields_for_admin_order( $order, $types ) {
		$order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$fields   = wc_get_custom_checkout_fields( $order, $types );
		$html     = '<div class="address custom_checkout_fields">';
		$found    = false;

		// Loop through all custom fields to see if it should be added
		foreach ( $fields as $name => $options ) {
			if ( isset( $options['display_options'] ) && in_array( 'view_order', $options['display_options'] ) &&  '' !== get_post_meta( $order_id, $name, true ) ) {
				$found = true;
				$html .= '<p><strong>' . esc_attr( $options['label'] ) . ':</strong>' . get_post_meta( $order_id, $name, true ) . '</p>';
			}
		}

		$html .= '</div>';

		if ( $found ) {
			echo $html;
		}
	}

	/**
	 * Display custom billing checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_billing_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'billing' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'wc_display_custom_billing_fields_admin_order', 20, 1 );

	/**
	 * Display custom shipping and additional checkout fields in admin order area.
	 *
	 * @param object $order
	 */
	function wc_display_custom_shipping_fields_admin_order( $order ) {
		wc_get_custom_fields_for_admin_order( $order, array( 'shipping', 'additional' ) );
	}
	add_action( 'woocommerce_admin_order_data_after_shipping_address', 'wc_display_custom_shipping_fields_admin_order', 20, 1 );

	/**
	 * Remove the localization WC core script to ensure
	 * the order remains how it is set in the field editor
	 * settings.
	 */
	function wc_checkout_fields_dequeue_address_i18n() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( apply_filters( 'wc_checkout_fields_dequeue_address_i18n', true ) ) {
			wp_dequeue_script( 'wc-address-i18n' );
			wp_deregister_script( 'wc-address-i18n' );

			wp_register_script( 'wc-address-i18n', plugins_url( '/dist/js/frontend.js', __FILE__ ), array( 'jquery', 'wc-country-select' ), WC_CHECKOUT_FIELD_EDITOR_VERSION, true );
		}
	}
	add_action( 'wp_enqueue_scripts', 'wc_checkout_fields_dequeue_address_i18n', 15 );

	/**
	 * Returns the value of an order's checkout field
	 *
	 * @param object $orderd Field's order
	 * @param string $name Field's name
	 * @param array $options Field's properties
	 * @return array
	 */
	function wc_get_checkout_field_value( $order, $name, $options ) {
		$order_id    = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
		$field_value = get_post_meta( $order_id, $name, true );

		if ( 'checkbox' === $options[ 'type' ] && '1' === $field_value ) {
			$field_value = __( 'yes', 'woocommerce-checkout-field-editor' );
		}

		return $field_value;
	}

} // end is_woocommerce_active() conditional check
