<?php

/**
 * @package WCMultiTieredShipping
 */
/*
 * Plugin Name: WC Multi-Tiered Shipping
 * Description: Add a USPS multi-tiered shipping option to the WooCommerce plugin.
 * Extended Description:
 *  This WordPress plugin adds a multi-tiered shipping option to the WooCommerce 
 *  plugin.  Clothing units of merchandise are fairly uniform in size such that
 *  a predetermined number of units can fit into USPS flat-rate boxes: large, 
 *  medium, and small.
 *  For USPS info: <https://www.usps.com/ship/priority-mail.htm>
 * Version: 1.0.0
 * Author: M&M Hodges <mhodges2@gmail.com>
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
/*
 * This plugin is a derivative work.  Special thanks to:
 *  Joni Halabi, http://www.jhalabi.com, author of the WC Tiered Shipping 
 *  plugin.
 * 
 * The WC Multitiered Shipping plugin was written for Emily, co-owner of 
 * ConfiDANCE, by her parents (lucky girl!).
 */

/**
 * Plugin initialization.
 */
function multi_tiered_shipping_init()
{

  if (!class_exists('WC_Multi_Tiered_Shipping'))
  {

    class WC_Multi_Tiered_Shipping extends WC_Shipping_Method
    {

      /**
       * Constructor for the multi-tier shipping class
       *
       * @access public
       * @return void
       */
      public function __construct()
      {
        $this->id = 'multi_tiered_shipping';
        $this->method_title = __('Multi-Tiered Shipping');  // Admin settings title
        $this->title = __('Multi-Tiered Shipping');   // Shipping method list title

        $this->init();
      }

      /**
       * Initialization.
       *
       * @access public
       * @return void
       */
      function init()
      {
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Save the settings.
        add_action('woocommerce_update_options_shipping_' . $this->id,
              array(&$this, 'process_admin_options'));
      }

      /**
       * Init Settings Form Fields (overriding default settings API)
       */
      function init_form_fields()
      {
        global $woocommerce;

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enabled/Disabled', 'multi_tiered_shipping'),
                'type' => 'checkbox',
                'label' => 'Enable this shipping method'
            ),
            'usertitle' => array(
                'title' => __('Title', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => __('Shipping method label that is visible to the user.',
                      'multi_tiered_shipping'),
                'default' => __('USPS Flat Rates', 'multi_tiered_shipping')
            ),
            'availability' => array(
                'title' => __('Availability', 'multi_tiered_shipping'),
                'type' => 'select',
                'class' => 'wc-enhanced-select availability',
                'options' => array(
                    'all' => 'All allowed countries',
                    'specific' => 'Specific countries'
                ),
                'default' => __('all', 'multi_tiered_shipping')
            ),
            'countries' => array(
                'title' => __('Countries', 'multi_tiered_shipping'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => $woocommerce->countries->countries,
                'default' => __('', 'multi_tiered_shipping')
            ),
            'cfd_additional_cost' => array(
                'title' => __('Per Item Cost', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => 'For large orders the total cost is the largest flat-rate cost plus the addition per-item shipping cost for the addiitonal items.',
                'default' => '1.12'
            ),
            'cfd_tier1_qty' => array(
                'title' => __('Small Qty', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => 'Maximum quantity for USPS small flat-rate box.<br>Take heed: quantities MUST get progressively larger for predictable results!!!',
                'default' => '2'
            ),
            'cfd_tier1_amt' => array(
                'title' => __('Small Amt', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => 'Cost for USPS small flat-rate box',
                'default' => '5.95'
            ),
            'cfd_tier2_qty' => array(
                'title' => __('Medium Qty', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => 'Maximum quantity for USPS medium flat-rate box.',
                'default' => '4'
            ),
            'cfd_tier2_amt' => array(
                'title' => __('Medium Amt', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => 'Cost for USPS medium flat-rate box.',
                'default' => '12.65'
            ),
            'cfd_tier3_qty' => array(
                'title' => __('Large Qty', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => 'Maximum quantity for USPS large flat-rate box.',
                'default' => '10'
            ),
            'cfd_tier3_amt' => array(
                'title' => __('Large Amt', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => 'Cost for USPS large flat-rate box.',
                'default' => '15.90'
            ),
            'cfd_tier4_qty' => array(
                'title' => __('Largest Qty', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => 'Maximum quantity for USPS largest flat-rate box.',
                'default' => '15'
            ),
            'cfd_tier4_amt' => array(
                'title' => __('Largest Amt', 'multi_tiered_shipping'),
                'type' => 'text',
                'description' => 'Cost for USPS largest flat-rate box.',
                'default' => '17.90'
            ),
        );
      }

      /**
       * Calculate shipping cost.
       *
       * @access public
       * @param mixed $package
       * @return void
       */
      public function calculate_shipping($package = array())
      {
        // Only add the shipping rate for this method if the user's country is 
        // included.
        if (!$this->is_tiered_allowed($package))
        {
          return;
        }
        global $woocommerce;

        // Get total item count from cart.
        $cart_item_quantities = $woocommerce->cart->get_cart_item_quantities();
        $cart_total_items = array_sum($cart_item_quantities);

        // Set the shipping cost.
        $rate = array(
            'id' => $this->id,
            'label' => $this->get_option('usertitle'),
            'cost' => $this->cfd_get_shipping_cost($cart_total_items)
        );

        $this->add_rate($rate);
      }

      /**
       * is_tiered_allowed function.
       *
       * @param mixed $package
       * @return true|false
       */
      function is_tiered_allowed($package = array())
      {
        // If plugin availability is set to all countries, just return true.
        $availability = $this->get_option('availability');

        if ($availability == 'all')
        {
          return true;
        }

        // Otherwise, if user's country is not set, return false.
        //    We cannot allow this shipping option if it is not available in all countries 
        //    and we do not know what country the user is in.
        $user_country = $package['destination']['country'];

        if (!$user_country)
        {
          return false;
        }

        // Otherwise, make sure the user's country is in the array of allowed countries.
        $countries = $this->get_option('countries');

        $in_allowed_country = false;

        for ($i = 0; $i < sizeof($countries); $i++)
        {
          if ($user_country == $countries[$i])
          {
            $in_allowed_country = true;
            break;
          }
        }
        return $in_allowed_country;
      }

      /**
       * Shipping calculation based on average number of units of merchandise
       * per package where:       
       *  qty - the maximum quantity of units of merchandise for the tier.
       *  amt - the fixed USPS flat-rate cost for the tier.
       * WARNING: tiers *must* be in ascending order by quantity, 'qty'.
       * 
       * @return mixed
       */
      function cfd_get_shipping_tiers()
      {
        return [
            array(
                'qty' => $this->get_option('cfd_tier1_qty'),
                'amt' => $this->get_option('cfd_tier1_amt')
            ),
            array(
                'qty' => $this->get_option('cfd_tier2_qty'),
                'amt' => $this->get_option('cfd_tier2_amt')
            ),
            array(
                'qty' => $this->get_option('cfd_tier3_qty'),
                'amt' => $this->get_option('cfd_tier3_amt')
            ),
            array(
                'qty' => $this->get_option('cfd_tier4_qty'),
                'amt' => $this->get_option('cfd_tier4_amt')
            ),
        ];
      }

      /**
       * Calculate shipping costs by looking for the appropriate shipping tier 
       * by item quantity.  Prorate shipping cost if there are too many items
       * for the largest tier.
       * 
       * @param type $cart_total_items
       * @return type
       */
      function cfd_get_shipping_cost($cart_total_items)
      {
        $shipping = 0;
        $cfd_shipping_tier = $this->cfd_get_shipping_tiers();

        // Calculate costs by looking for the appropriate shipping tier by item 
        // quantity.
        foreach ($cfd_shipping_tier as $cfd_tier_no => $cfd_tier_value)
        {
          if ($cart_total_items <= $cfd_tier_value['qty'])
          {
            $shipping = $cfd_tier_value['amt'];
            break;
          }
        }
        if ($shipping == 0)
        {
          // If none of the tiers worked, prorate shipping based on the largest 
          // tier as the base cost, plus an additional per-item cost.
          $cfd_extra_per_unit = $this->get_option('cfd_additional_cost');
          $cfd_last_tier = max(array_keys($cfd_shipping_tier));
          $cfd_last_tier_amt = $cfd_shipping_tier[$cfd_last_tier]['amt'];
          $cfd_last_tier_qty = $cfd_shipping_tier[$cfd_last_tier]['qty'];
          $shipping = $cfd_last_tier_amt + ($cart_total_items - $cfd_last_tier_qty) * $cfd_extra_per_unit;
        }
        return $shipping;
      }
    }
  }
}

add_action('woocommerce_shipping_init', 'multi_tiered_shipping_init');

function add_multi_tiered_shipping($methods)
{
  $methods[] = 'WC_Multi_Tiered_Shipping';
  return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_multi_tiered_shipping');

