<?php
/**
 * Plugin Name: EDD Special Discount By RexTheme
 * Description: Applies a 50% discount as a negative fee in Easy Digital Downloads for customers from South Asia (IP-based detection). Removes coupons unless user manually applies one.
 * Version: 1.11.1
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
    error_log('Customer country detected: ' . $country);
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
add_action( 'edd_cart_fees', 'cl_apply_special_discount' );

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
 * Add some CSS to style the discount fee nicely
 */
add_action( 'wp_head', function() {
    if ( edd_is_checkout() ) {
        ?>
        <style type="text/css">
            .edd-cart-fee-special_discount {
                color: #27ae60 !important;
                font-weight: bold;
                background-color: #f8f9fa;
                padding: 5px;
                border-radius: 3px;
            }
            .edd-cart-fee-special_discount .edd-cart-fee-amount {
                color: #e74c3c !important;
            }
        </style>
        <?php
    }
});

/**
 * Optional: Add a notice above checkout cart
 */
add_action( 'edd_before_checkout_cart', function() {
    $country = cl_get_country_from_ip();
    $existing_fees = EDD()->fees->get_fees();
    error_log(print_r($existing_fees, true));
    cl_apply_special_discount();
    error_log('country in notice: ' . $country);
    if ( in_array( $country, cl_get_eligible_countries(), true ) ) {
        if ( isset( $existing_fees['special_discount'] ) ) {
            echo '<div style="background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin-bottom: 20px;">';
            echo '<strong>🎉 Congratulations!</strong> You are eligible for our special 50% regional discount!';
            echo '</div>';
        } elseif ( edd_has_active_discounts() ) {
            echo '<div style="background: #fff3cd; color: #856404; padding: 15px; border: 1px solid #ffeeba; border-radius: 5px; margin-bottom: 20px;">';
            echo '<strong>💡 Note:</strong> Coupon applied, so regional 50% discount has been disabled.';
            echo '</div>';
        }
    }
});
