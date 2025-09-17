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
function fifty_percent_discount_get_eligible_countries() {
    return array( 'BD', 'IN', 'NP', 'LK', 'PK', 'AF', 'MV', 'BT' );
}

/**
 * Get customer country via IP
 */
function fifty_percent_discount_get_country_from_ip() {
    $ip = edd_get_ip();

    if ( ! $ip ) {
        return '';
    }

    // Check cache first
    $cached_country = get_transient( 'fifty_percent_discount_country_' . $ip );
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
        set_transient( 'fifty_percent_discount_country_' . $ip, $data->country, DAY_IN_SECONDS );
        return $data->country;
    }

    return 'BD';
}

/**
 * Apply exclusive 50% discount as negative fee (default for eligible users).
 */
function fifty_percent_discount_apply_special_discount() {
    $country = fifty_percent_discount_get_country_from_ip();

    $existing_fees = EDD()->fees->get_fees();

    // Always remove old discount first
    if ( isset( $existing_fees['special_discount'] ) ) {
        EDD()->fees->remove_fee( 'special_discount' );
    }

    // Only for eligible countries
    if ( ! in_array( $country, fifty_percent_discount_get_eligible_countries(), true ) ) {
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
add_action( 'edd_cart_items_before', 'fifty_percent_discount_apply_special_discount' );

/**
 * When user manually applies a coupon → remove 50% discount and let coupon work.
 */
function fifty_percent_discount_user_applied_coupon( $code ) {
    $existing_fees = EDD()->fees->get_fees();

    // Remove our special discount fee completely
    if ( isset( $existing_fees['special_discount'] ) ) {
        EDD()->fees->remove_fee( 'special_discount' );
    }
}
add_action( 'edd_cart_discount_set', 'fifty_percent_discount_user_applied_coupon' );

/**
 * When coupon removed → reapply our special discount if user eligible.
 */
function fifty_percent_discount_user_removed_coupon( $code ) {
    fifty_percent_discount_apply_special_discount();
}
add_action( 'edd_cart_discount_removed', 'fifty_percent_discount_user_removed_coupon' );

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
    
    $country = fifty_percent_discount_get_country_from_ip();
    if ( in_array( $country, fifty_percent_discount_get_eligible_countries(), true ) && get_option( 'fifty_percent_discount_popup_cancelled' ) !== 'yes' ) {
        ?>
        <div id="fifty-percent-discount-popup-overlay">
            <div id="fifty-percent-discount-popup">
                <h2>Special Discount!</h2>
                <p>For a limited time, customers from your region get a 50% discount on any of our products at RexTheme.</p>
                <button id="fifty-percent-discount-close-popup">Close</button>
            </div>
        </div>
        <style>
            #fifty-percent-discount-popup-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.7);
                z-index: 9999;
                display: none;
                justify-content: center;
                align-items: center;
            }
            #fifty-percent-discount-popup {
                background-color: #fff;
                padding: 30px;
                border-radius: 10px;
                text-align: center;
                animation: fifty-percent-discount-popup-animation 0.5s ease-in-out;
                max-width: 400px;
                box-shadow: 0 0 20px rgba(0,0,0,0.2);
            }
            #fifty-percent-discount-popup h2 {
                color: #005E9E;
                font-size: 28px;
                margin-bottom: 15px;
            }
            #fifty-percent-discount-popup p {
                font-size: 16px;
                margin-bottom: 20px;
            }
            #fifty-percent-discount-close-popup {
                background-color: #005E9E;
                color: #fff;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                transition: background-color 0.3s;
            }
            #fifty-percent-discount-close-popup:hover {
                background-color: #004A7C;
            }
            @keyframes fifty-percent-discount-popup-animation {
                from {
                    transform: scale(0.8);
                    opacity: 0;
                }
                to {
                    transform: scale(1);
                    opacity: 1;
                }
            }
        </style>
        <script>
            setTimeout(function() {
                document.getElementById('fifty-percent-discount-popup-overlay').style.display = 'flex';
            }, 5000);

            document.getElementById('fifty-percent-discount-close-popup').addEventListener('click', function() {
                document.getElementById('fifty-percent-discount-popup-overlay').style.display = 'none';
                // Add ajax call to update option
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo admin_url( 'admin-ajax.php' ); ?>', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;');
                xhr.send('action=fifty_percent_discount_cancel_popup');
            });

            document.getElementById('fifty-percent-discount-popup-overlay').addEventListener('click', function(e) {
                if (e.target.id === 'fifty-percent-discount-popup-overlay') {
                    document.getElementById('fifty-percent-discount-popup-overlay').style.display = 'none';
                }
            });
        </script>
        <?php
    }
});

add_action( 'wp_ajax_fifty_percent_discount_cancel_popup', 'fifty_percent_discount_cancel_popup' );
add_action( 'wp_ajax_nopriv_fifty_percent_discount_cancel_popup', 'fifty_percent_discount_cancel_popup' );

function fifty_percent_discount_cancel_popup() {
    update_option( 'fifty_percent_discount_popup_cancelled', 'yes' );
    wp_die();
}

/**
 * Reset the popup cancelled flag on plugin deactivation.
 */
function fifty_percent_discount_deactivate() {
    delete_option( 'fifty_percent_discount_popup_cancelled' );
}
register_deactivation_hook( __FILE__, 'fifty_percent_discount_deactivate' );

/**
 * Reset the popup cancelled flag on plugin activation.
 */
function fifty_percent_discount_activate() {
    delete_option( 'fifty_percent_discount_popup_cancelled' );
}
register_activation_hook( __FILE__, 'fifty_percent_discount_activate' );
