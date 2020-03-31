<?php
/**
 * Plugin Name:       WooCommerce Dynamic Pricing Table
 * Plugin URI:        https://github.com/stuartduff/woocommerce-dynamic-pricing-table
 * Description:       Displays a pricing discount table on WooCommerce products, a user role discount message and a simple category discount message when using the WooCommerce Dynamic Pricing plugin.
 * Version:           1.0.7.200228
 * Author:            Stuart Duff
 * Author URI:        http://stuartduff.com
 * Requires at least: 4.6
 * Tested up to:      4.9
 *
 * Text Domain: woocommerce-dynamic-pricing-table
 * Domain Path: /languages/
 *
 * @package WC_Dynamic_Pricing_Table
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Returns the main instance of WC_Dynamic_Pricing_Table to prevent the need to use globals.
 *
 * @since   1.0.0
 * @return  object WC_Dynamic_Pricing_Table
 */
function WC_Dynamic_Pricing_Table() {
  return WC_Dynamic_Pricing_Table::instance();
} // End WC_Dynamic_Pricing_Table()
WC_Dynamic_Pricing_Table();

/**
 * Main WC_Dynamic_Pricing_Table Class
 *
 * @class WC_Dynamic_Pricing_Table
 * @version   1.0.0
 * @since     1.0.0
 * @package   WC_Dynamic_Pricing_Table
 */
final class WC_Dynamic_Pricing_Table {

  /**
   * WC_Dynamic_Pricing_Table The single instance of WC_Dynamic_Pricing_Table.
   * @var     object
   * @access  private
   * @since   1.0.0
   */
  private static $_instance = null;

  /**
   * The token.
   * @var     string
   * @access  public
   * @since   1.0.0
   */
  public $token;

  /**
   * The version number.
   * @var     string
   * @access  public
   * @since   1.0.0
   */
  public $version;

  /**
   * Constructor function.
   * @access  public
   * @since   1.0.0
   * @return  void
   */
  public function __construct() {
    $this->token          = 'woocommerce-dynamic-pricing-table';
    $this->plugin_url     = plugin_dir_url( __FILE__ );
    $this->plugin_path    = plugin_dir_path( __FILE__ );
    $this->version        = '1.0.0';

    register_activation_hook( __FILE__, array( $this, 'install' ) );

    add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

    add_action( 'init', array( $this, 'plugin_setup' ) );

  }

  /**
   * Main WC_Dynamic_Pricing_Table Instance
   *
   * Ensures only one instance of WC_Dynamic_Pricing_Table is loaded or can be loaded.
   *
   * @since   1.0.0
   * @static
   * @see     WC_Dynamic_Pricing_Table()
   * @return  Main WC_Dynamic_Pricing_Table instance
   */
  public static function instance() {
    if ( is_null( self::$_instance ) )
      self::$_instance = new self();
    return self::$_instance;
  } // End instance()

  /**
   * Load the localisation file.
   * @access  public
   * @since   1.0.0
   * @return  void
   */
  public function load_plugin_textdomain() {
    load_plugin_textdomain( 'woocommerce-dynamic-pricing-table', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
  }

  /**
   * Installation.
   * Runs on activation. Logs the version number.
   * @access  public
   * @since   1.0.0
   * @return  void
   */
  public function install() {
    $this->log_plugin_version_number();
  }

  /**
   * Log the plugin version number.
   * @access  private
   * @since   1.0.0
   * @return  void
   */
  private function log_plugin_version_number() {
    // Log the version number.
    update_option( $this->token . '-version', $this->version );
  }

  /**
   * Setup all the things.
   * Only executes if WooCommerce Dynamic Pricing is active.
   * If WooCommerce Dynamic Pricing is inactive an admin notice is displayed.
   * @return void
   */
  public function plugin_setup() {
    if ( class_exists( 'WC_Dynamic_Pricing' ) ) {
      add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'output_dynamic_pricing_table' ) );

      if ( ! is_admin() ) {
        add_action( 'wp', array( $this, 'output_dynamic_pricing_role_message' ) );
        add_action( 'wp', array( $this, 'output_dynamic_pricing_category_message' ) );
      }
    } else {
      add_action( 'admin_notices', array( $this, 'install_wc_dynamic_pricing_notice' ) );
    }
  }

  /**
   * WooCommerce Dynamic Pricing plugin install notice.
   * If the user activates this plugin while not having the WooCommerce Dynamic Pricing plugin installed or activated, prompt them to install WooCommerce Dynamic Pricing.
   * @since   1.0.0
   * @return  void
   */
  public function install_wc_dynamic_pricing_notice() {
    echo '<div class="notice is-dismissible updated">
      <p>' . __( 'The WooCommerce Dynamic Pricing Table extension requires that you have the WooCommerce Dynamic Pricing plugin installed and activated.', 'woocommerce-dynamic-pricing-table' ) . ' <a href="https://www.woocommerce.com/products/dynamic-pricing/">' . __( 'Get WooCommerce Dynamic Pricing now', 'woocommerce-dynamic-pricing-table' ) . '</a></p>
    </div>';
  }

  /**
   * Gets the dynamic pricing rules sets from the post meta.
	 *
	 * @param bool $rules_for_current_user_only
   * @access  public
   * @since   1.0.0
   * @return  get_post_meta()
   */
  public function get_pricing_array_rule_sets($rules_for_current_user_only = true) {
		$rule_sets = get_post_meta( get_the_ID(), '_pricing_rules', true );

		// If required, only return the rules that apply to current user
		// @since 1.0.7.200228
		if($rules_for_current_user_only) {
			$rule_sets = $this->get_discount_rules_for_current_user($rule_sets);
		}
		return $rule_sets;
  }

  /**
   * Gets the current user.
   * @access  public
   * @since   1.0.0
   * @return  wp_get_current_user()
   */
  public function get_current_user() {
    return wp_get_current_user();
  }

  /**
   * Gets the current category.
   * @access  public
   * @since   1.0.0
   * @return  get_queiried object()
   */
  public function pricing_queried_object() {
    return get_queried_object();
  }

  /**
   * Outputs the dynamic bulk pricing table.
   * @access  public
   * @since   1.0.0
   * @return  $output
   */
  public function bulk_pricing_table_output() {

    $array_rule_sets = $this->get_pricing_array_rule_sets();

    $output = '<table class="dynamic-pricing-table">';

    $output .= '<th>' . __( 'Quantity' , 'woocommerce-dynamic-pricing-table' ) . '</th><th>' . __( 'Bulk Purchase Pricing' , 'woocommerce-dynamic-pricing-table' ) . '</th>';

    foreach( $array_rule_sets as $pricing_rule_sets ) {

      foreach ( $pricing_rule_sets['rules'] as $key => $value ) {

        // Checks if a product discount group max quantity field is less than 1.
        if ( $pricing_rule_sets['rules'][$key]['to'] < 1 ){
          $rules_to = __( ' or more', 'woocommerce-dynamic-pricing-table' );
        } else {
          $rules_to = ' - ' . wc_stock_amount( $pricing_rule_sets['rules'][$key]['to'] );
        }

        $output .= '<tr>';

        $output .= '<td><span class="discount-quantity">' . wc_stock_amount( $pricing_rule_sets['rules'][$key]['from'] ) . $rules_to . '</span></td>';

        switch ( $pricing_rule_sets['rules'][$key]['type'] ) {

          case 'price_discount':
            $output .= '<td><span class="discount-amount">' . sprintf( __( '%1$s Discount Per Item', 'woocommerce-dynamic-pricing-table' ), wc_price( self::convert( $pricing_rule_sets['rules'][$key]['amount'] ) ) ) . '</span></td>';
          break;

          case 'percentage_discount':
            $output .= '<td><span class="discount-amount">' . floatval( $pricing_rule_sets['rules'][$key]['amount'] ) . __( '% Discount', 'woocommerce-dynamic-pricing-table' ) . '</span></td>';
          break;

          case 'fixed_price':
            $output .= '<td><span class="discount-amount">' . sprintf( __( '%1$s Per Item', 'woocommerce-dynamic-pricing-table' ), wc_price( self::convert( $pricing_rule_sets['rules'][$key]['amount'] ) ) ) . '</span></td>';
          break;

        }

        $output .= '</tr>';

      }

    }

    $output .= '</table>';

    echo $output;

  }

  /**
   * Outputs the dynamic special offer pricing table.
   * @access  public
   * @since   1.0.0
   * @return  $output
   */
  public function special_offer_pricing_table_output() {

    $array_rule_sets = $this->get_pricing_array_rule_sets();

    $output = '<table class="dynamic-pricing-table">';

    $output .= '<th>' . __( 'Quantity', 'woocommerce-dynamic-pricing-table' ) . '</th><th>' . __( 'Special Offer Pricing', 'woocommerce-dynamic-pricing-table' ) . '</th>';

    foreach( $array_rule_sets as $pricing_rule_sets ) {

      foreach ( $pricing_rule_sets['blockrules'] as $key => $value ) {

        $output .= '<tr>';

        $output .= '<td><span class="discount-quantity">' . sprintf( __( 'Buy %1$s get %2$s more discounted', 'woocommerce-dynamic-pricing-table' ), wc_stock_amount( $pricing_rule_sets['blockrules'][$key]['from'] ), wc_stock_amount( $pricing_rule_sets['blockrules'][$key]['adjust'] ) ) . '</span></td>';

        switch ( $pricing_rule_sets['blockrules'][$key]['type'] ) {

          case 'fixed_adjustment':
            $output .= '<td><span class="discount-amount">' . sprintf( __( '%1$s Discount Per Item', 'woocommerce-dynamic-pricing-table' ), wc_price( self::convert( $pricing_rule_sets['blockrules'][$key]['amount'] ) ) ) . '</span></td>';
          break;

          case 'percent_adjustment':
            $output .= '<td><span class="discount-amount">' . floatval( $pricing_rule_sets['blockrules'][$key]['amount'] ) . __( '% Discount', 'woocommerce-dynamic-pricing-table' ) . '</span></td>';
          break;

          case 'fixed_price':
            $output .= '<td><span class="discount-amount">' . sprintf( __( '%1$s Per Item', 'woocommerce-dynamic-pricing-table' ), wc_price( self::convert( $pricing_rule_sets['blockrules'][$key]['amount'] ) ) ) . '</span></td>';
          break;

        }

        $output .= '</tr>';

      }

    }

    $output .= '</table>';

    echo $output;

	}

	/**
	 * Given the discount rules from a product, it returns the rules that apply to
	 * current user.
	 *
	 * @param array $rule_sets
	 * @return array
	 * @since 1.0.7.200228
	 */
	protected function get_discount_rules_for_current_user($rule_sets) {
		// If there's only one rule set, just return it. The rest of the plugin
		// will automatically check if the dynamic pricing table should be displayed,
		// if the rule sets applies to everyone or current user
		if(count($rule_sets) == 1) {
			return $rule_sets;
		}

		$result = array();

		foreach($rule_sets as $idx => $rule) {
			$conditions = array_shift($rule['conditions']);
			// If the rule set has no conditions, skip it
			if(!is_array($conditions)) {
				continue;
			}

			// Check if the rules apply to current user's role
			if(($conditions['type'] === 'apply_to') && !empty($conditions['args']['applies_to']) && ($conditions['args']['applies_to'] === 'roles')) {
				$conditions_roles = isset($conditions['args']['roles']) && is_array($conditions['args']['roles']) ? $conditions['args']['roles'] : array();

				// If the rule doesn't apply to any role, skip it
				if(empty($conditions_roles)) {
					continue;
				}

				foreach($conditions_roles as $role) {
					// If the rule applies to current user's role, keep it
					if(current_user_can($role)) {
						$result[$idx] = $rule;
						continue;
					}
				}
			}
		}
		// Return matching rules
		return $result;
	}

  /**
   * Outputs the dynamic pricing table.
   * @access  public
   * @since   1.0.0
   */
  public function output_dynamic_pricing_table() {

		$array_rule_sets = $this->get_pricing_array_rule_sets(true);

    if ( $array_rule_sets && is_array( $array_rule_sets ) ) {
      foreach( $array_rule_sets as $pricing_rule_sets ) {
        if ( $pricing_rule_sets['mode'] == 'continuous' ) :
          $this->bulk_pricing_table_output();
        elseif ( $pricing_rule_sets['mode'] == 'block' ) :
          $this->special_offer_pricing_table_output();
        endif;
      }
    }
  }

  /**
   * The role discount notification message.
   * @access  public
   * @since   1.0.0
   * @return  wc_add_notice()
   */
  public function role_discount_notification_message() {

    $role_pricing_rule_sets     = get_option( '_s_membership_pricing_rules', array() );
    $current_user_role          = $this->get_current_user()->roles[0];
		$current_user_display_name  = $this->get_current_user()->display_name;

		$info_message = '';

    foreach( $role_pricing_rule_sets as $role_rules ) {

      // Gets the discount role of the user and the discount amount.
      $user_discount_role   = !empty( $role_rules['conditions'][0]['args']['roles'][0] ) ? $role_rules['conditions'][0]['args']['roles'][0] : '';
      $role_discount_amount = $role_rules['rules'][0]['amount'];

      if ( is_woocommerce() && $current_user_role === $user_discount_role && null !== $user_discount_role ) {

        switch ( $role_rules['rules'][0]['type'] ) {

          case 'percent_product':
            $info_message = sprintf( __( 'Hi %1$s as a %2$s you will receive a %3$s percent discount on all products.', 'woocommerce-dynamic-pricing-table' ), esc_attr( $current_user_display_name ), esc_attr( $current_user_role ), floatval( $role_discount_amount ) );
          break;

          case 'fixed_product':
            $info_message = sprintf( __( 'Hi %1$s as a %2$s you will receive a %3$s discount on all products.', 'woocommerce-dynamic-pricing-table' ), esc_attr( $current_user_display_name ), esc_attr( $current_user_role ), wc_price( self::convert( $role_discount_amount ) ) );
          break;

        }

      }

    }

		if( !empty( $info_message ) ) {
			wc_add_notice( $info_message, 'notice' );
		}
  }

  /**
   * Outputs the role notificaton message.
   * @access  public
   * @since   1.0.0
   */
  public function output_dynamic_pricing_role_message() {
    $this->role_discount_notification_message();
  }

  /**
   * The category discount notification message.
   * @access  public
   * @since   1.0.0
   * @return  wc_add_notice()
   */
  public function category_discount_notification_message() {

    $category_pricing_rule_sets = get_option( '_s_category_pricing_rules', array() );
    $current_product_category   = $this->pricing_queried_object()->term_id;
		$current_category_name      = $this->pricing_queried_object()->name;

		$info_message = '';

    foreach( $category_pricing_rule_sets as $category_rules ) {

      // Gets the discount category and the discount amount set for the category.
      $discount_category        = !empty( $category_rules['collector']['args']['cats'][0] ) ? $category_rules['collector']['args']['cats'][0] : '';
      $category_discount_amount = $category_rules['rules'][0]['amount'];


      if ( is_product_category() && $current_product_category == $discount_category && null != $discount_category ) {

        switch ( $category_rules['rules'][0]['type'] ) {

          case 'percent_product':
            $info_message = sprintf( __( 'You will receive a %1$s percent discount on all products within the %2$s category.', 'woocommerce-dynamic-pricing-table' ), floatval( $category_discount_amount ), esc_attr( $current_category_name ) );
          break;

          case 'fixed_product':
            $info_message = sprintf( __( 'You will receive %1$s discount on all products within the %2$s category.', 'woocommerce-dynamic-pricing-table' ), wc_price( $category_discount_amount ), esc_attr( $current_category_name ) );
          break;

        }

      }

    }

		if( !empty( $info_message ) ) {
			wc_add_notice( $info_message, 'notice' );
		}

  }

  /**
   * Outputs the category notificaton message.
   * @access  public
   * @since   1.0.0
   */
  public function output_dynamic_pricing_category_message() {
    $this->category_discount_notification_message();
	}

	/**
	 * Converts an amount from one currency to another.
	 *
	 * @param float $amount
	 * @param string $to_currency
	 * @param string $from_currency
	 * @return float
	 * @author Aelia <support@aelia.co>
	 * @link https://aelia.co
	 */
	protected static function convert($amount, $to_currency = null, $from_currency = null) {
		// If source currency is not specified, take the shop's base currency as a default
		if(empty($from_currency)) {
			$from_currency = get_option('woocommerce_currency');
		}
		// If target currency is not specified, take the active currency as a default.
		// The Currency Switcher sets this currency automatically, based on the context. Other
		// plugins can also override it, based on their own custom criteria, by implementing
		// a filter for the "woocommerce_currency" hook.
		//
		// For example, a subscription plugin may decide that the active currency is the one
		// taken from a previous subscription, because it's processing a renewal, and such
		// renewal should keep the original amounts, in the original currency.
		if(empty($to_currency)) {
			$to_currency = get_woocommerce_currency();
		}

		// Call the currency conversion filter. Using a filter allows for loose coupling. If the
		// Aelia Currency Switcher is not installed, the filter call will return the original
		// amount, without any conversion being performed. Your plugin won't even need to know if
		// the multi-currency plugin is installed or active
		return apply_filters('wc_aelia_cs_convert', $amount, $from_currency, $to_currency);
	}

} // End Class
