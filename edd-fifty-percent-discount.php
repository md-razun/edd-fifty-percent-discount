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
        <div id="fifty-percent-discount-popup" role="dialog" aria-live="polite" aria-label="Special Discount">
            <button type="button" id="fifty-percent-discount-close-popup" aria-label="Close">&times;</button>
            <div class="fifty-percent-discount-header-image">
            </div>
            <h2>Celebrating South Asia With 50% OFF</h2>
            <p>It looks like you are from an awesome region in South Asia. So today, we have something special for you - an exclusive 50% OFF on any plugin you want.</p>
            <a href="https://rextheme.com/products/#plugins" target="_blank" class="fifty-percent-discount-cta-button">
                <span>Check Out Our Plugins</span>
            </a>
        </div>
        <style>
            /* Standalone popup (no overlay) */
            #fifty-percent-discount-popup {
                position: fixed;
                bottom: 20px;
                left: 20px;
                z-index: 9999;
                background-color: #fff;
                padding: 0; /* Adjusted for image */
                border-radius: 10px;
                text-align: left;
                animation: fifty-percent-discount-popup-animation 0.5s ease-in-out;
                max-width: 400px;
                box-shadow: 0 0 20px rgba(0,0,0,0.2);
                margin: 0;
                display: none; /* shown via JS */
                overflow: hidden; /* Ensures rounded corners cut off image */
            }

            .fifty-percent-discount-header-image {
                background-image: url('https://i.imgur.com/Qk26d7J.png'); /* YOUR PROVIDED IMAGE URL */
                background-size: cover;
                background-position: center;
                height: 180px; /* Height of the header section */
                position: relative;
                margin-bottom: 20px; /* Space between header and text */
                background-color: #0d0d4e; /* Fallback for image loading, matching a dark blue in your image */
            }

            /* Removed .fifty-percent-discount-off-text and its ::before as it's in the image */

            #fifty-percent-discount-popup h2 {
                color: #212529; /* Darker text for better contrast */
                font-size: 24px;
                margin: 0 24px 12px 24px; /* Padding for text content */
                font-weight: 700;
                line-height: 1.3;
            }
            #fifty-percent-discount-popup p {
                font-size: 15px;
                margin: 0 24px 20px 24px; /* Padding for text content */
                color: #495057;
                line-height: 1.5;
            }

            /* Call to Action Button */
            .fifty-percent-discount-cta-button {
                display: flex;
                align-items: center;
                justify-content: center;
                width: calc(100% - 48px); /* Full width minus padding */
                margin: 0 24px 24px 24px; /* Centered with padding */
                padding: 14px 20px;
                border-radius: 8px;
                /* Updated gradient to match the design's blue/teal button */
                background: linear-gradient(to right, #00CFFF, #007DFF);
                color: #fff;
                font-size: 17px;
                font-weight: 600;
                text-decoration: none;
                transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
                box-shadow: 0 4px 15px rgba(0, 114, 255, 0.4);
            }
            .fifty-percent-discount-cta-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 114, 255, 0.6);
            }
            .fifty-percent-discount-cta-button span {
                position: relative;
                padding-left: 25px; /* Space for the arrow icon */
            }
            .fifty-percent-discount-cta-button span::before {
                content: '';
                position: absolute;
                left: 0;
                top: 50%;
                transform: translateY(-50%);
                width: 16px; /* Size of the arrow */
                height: 16px;
                background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%23ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>'); /* White arrow SVG */
                background-size: contain;
                background-repeat: no-repeat;
            }


            /* Cross button in top-right */
            #fifty-percent-discount-close-popup {
                position: absolute;
                top: 10px;
                right: 10px;
                width: 32px;
                height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: rgba(255, 255, 255, 0.2); /* Semi-transparent white for contrast on header */
                color: #fff; /* White cross */
                border: none;
                border-radius: 50%; /* Circular close button */
                cursor: pointer;
                font-size: 24px;
                line-height: 1;
                padding: 0;
                z-index: 10000; /* Ensure it's above the header image */
            }
            #fifty-percent-discount-close-popup:hover {
                background: rgba(255, 255, 255, 0.4);
            }

            @keyframes fifty-percent-discount-popup-animation {
                from {
                    transform: scale(0.95);
                    opacity: 0;
                }
                to {
                    transform: scale(1);
                    opacity: 1;
                }
            }
            @media (max-width: 480px) {
                #fifty-percent-discount-popup { max-width: calc(100% - 40px); left: 20px; right: 20px; }
            }
        </style>
        <script>
            // Show the popup (no overlay)
            setTimeout(function() {
                var pop = document.getElementById('fifty-percent-discount-popup');
                if (pop) pop.style.display = 'block';
            }, 5000);

            // Close handler for the cross button with AJAX flag persist
            (function(){
                var closeBtn = document.getElementById('fifty-percent-discount-close-popup');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        var pop = document.getElementById('fifty-percent-discount-popup');
                        if (pop) pop.style.display = 'none';
                        // Add ajax call to update option
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', '<?php echo admin_url( 'admin-ajax.php' ); ?>', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;');
                        xhr.send('action=fifty_percent_discount_cancel_popup');
                    });
                }
            })();
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
