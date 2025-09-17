<?php
/**
 * Plugin Name: EDD Special Discount By RexTheme
 * Description: Applies a 50% discount as a negative fee for eligible South Asian customers (IP-based). If a coupon is applied, the 50% discount is removed; when coupons are removed, it’s re-applied.
 * Version: 1.1.23
 * Author: RexTheme
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Eligible South Asian countries
function cl_get_eligible_countries() {
    return array( 'BD', 'IN', 'NP', 'LK', 'PK', 'AF', 'MV', 'BT' );
}

/**
 * Get customer country via IP
 */
function cl_get_country_from_ip() {
    $ip = edd_get_ip();

    if ( ! $ip ) {
        return '';
    }

    // Check cache first
    $cached_country = get_transient( 'cl_country_' . $ip );
    if ( $cached_country ) {
        return $cached_country;
    }

    // API call
    $response = wp_remote_get( "https://ipinfo.io/{$ip}/json", array( 'timeout' => 5 ) );

    if ( is_wp_error( $response ) ) {
        return '';
    }

    $data = json_decode( wp_remote_retrieve_body( $response ) );

    if ( isset( $data->country ) ) {
        set_transient( 'cl_country_' . $ip, $data->country, DAY_IN_SECONDS );
        return $data->country;
    }

    return 'BD';
}

/**
 * Apply exclusive 50% discount as negative fee (default for eligible users).
 */
function cl_apply_special_discount() {
    $country = cl_get_country_from_ip();

    $existing_fees = EDD()->fees->get_fees();

    // Always remove old discount first
    if ( isset( $existing_fees['special_discount'] ) ) {
        EDD()->fees->remove_fee( 'special_discount' );
    }

    // Only for eligible countries
    if ( ! in_array( $country, cl_get_eligible_countries(), true ) ) {
        return;
    }

    // If coupon(s) exist → by default, remove them and keep our discount
    if ( edd_has_active_discounts() ) {
        edd_unset_all_cart_discounts();
    }

    // Apply our 50% discount
    $cart_total = edd_get_cart_subtotal();
    if ( $cart_total > 0 ) {
        $discount_amount = $cart_total * 0.5;
        EDD()->fees->add_fee( array(
                'amount' => -$discount_amount,
                'label'  => 'Special Discount (50%)',
                'id'     => 'special_discount',
                'type'   => 'discount',
                'no_tax' => true
        ));
    }
}
add_action( 'edd_cart_items_before', 'cl_apply_special_discount' );

/**
 * When user manually applies a coupon → remove 50% discount and let coupon work.
 */
function cl_user_applied_coupon( $code ) {
    $existing_fees = EDD()->fees->get_fees();

    // Remove our special discount fee completely
    if ( isset( $existing_fees['special_discount'] ) ) {
        EDD()->fees->remove_fee( 'special_discount' );
    }
}
add_action( 'edd_cart_discount_set', 'cl_user_applied_coupon' );

/**
 * When coupon removed → reapply our special discount if user eligible.
 */
function cl_user_removed_coupon( $code ) {
    cl_apply_special_discount();
}
add_action( 'edd_cart_discount_removed', 'cl_user_removed_coupon' );

/**
 * Vanilla JS: remove 50% fee row when a coupon is applied on the frontend
 */
add_action( 'wp_footer', function() {
    if ( function_exists( 'edd_is_checkout' ) && edd_is_checkout() ) {
        ?>
        <script>
        (function() {
          function removeSpecialDiscountRow() {
            var row = document.getElementById('edd_cart_fee_special_discount');
            if (row && row.parentNode) { row.parentNode.removeChild(row); }
          }

          // Listen for native custom event (if fired by EDD or other scripts)
          document.addEventListener('edd_coupon_applied', removeSpecialDiscountRow, true);

          // Try to react after clicking common "apply coupon" triggers
          document.addEventListener('click', function(e) {
            var t = e.target;
            if (!t) return;
            if (t.matches('#edd-apply-discount, [name="edd-apply-discount"], .edd-apply-discount, button[data-edd-action="apply_discount"], input[type="submit"][value*="coupon" i], #apply_discount')) {
              setTimeout(removeSpecialDiscountRow, 250);
            }
          }, true);

        }());
        </script>
        <?php
    }
});
