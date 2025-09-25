<?php
/**
 * Plugin Name: EDD Special Discount By RexTheme - Improved
 * Description: Applies a 50% discount as a negative fee for eligible South Asian customers (IP-based). Enhanced with better error handling and user experience.
 * Version: 1.2.0
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


        <div 
            id="fifty-percent-discount-popup" 
            class="fifty-percent-discount-popup" 
            role="dialog" 
            aria-modal="true" 
            aria-labelledby="fifty-percent-discount-heading" 
            aria-describedby="fifty-percent-discount-description" 
        >

           
            <div class="fifty-percent-discount-popup__content">
                 <!-- Close Button -->
                <button 
                    type="button" 
                    id="fifty-percent-discount-close-popup" 
                    class="fifty-percent-discount-popup__close" 
                    aria-label="<?php esc_attr_e('Close popup', 'rextheme'); ?>"
                >
                   
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15.8569 2.14258L2.14258 15.8569" stroke="#666666" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2.14258 2.14258L15.8569 15.8569" stroke="#666666" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>

                </button>

                <!-- Logo Image -->
                <img 
                    src="<?php echo esc_url( plugin_dir_url(__FILE__) . 'image/celebrating-south-asia.webp' ); ?>" 
                    alt="<?php esc_attr_e('Celebrating South Asia', 'rextheme'); ?>" 
                    class="fifty-percent-discount-popup__logo"
                    loading="lazy"
                >

                <!-- Heading -->
                <h2 id="fifty-percent-discount-heading" class="fifty-percent-discount-popup__heading">
                    <?php esc_html_e('Celebrating South Asia With ', 'rextheme'); ?> <span class="fifty-percent-discount-popup__heading-highlight">50% OFF</span> 
                </h2>

                <!-- Description -->
                <p id="fifty-percent-discount-description" class="fifty-percent-discount-popup__text">
                    <?php esc_html_e('It looks like you are from an awesome region in South Asia. Today, we have something special for you – an exclusive 50% OFF on any plugin you want.', 'rextheme'); ?>
                </p>

                <!-- CTA Button -->

                <div class="fifty-percent-discount-popup__cta-button">
                    <a 
                        href="https://rextheme.com/products/#plugins" 
                        target="_blank" 
                        rel="noopener noreferrer" 
                        class="fifty-percent-discount-popup__cta"
                    >

                        <span class="cta-btn-inner">
                            <span class="btn-icon">
                                <svg width="12" height="12" fill="none" viewBox="0 0 12 12" xmlns="http://www.w3.org/2000/svg"><path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 11L11 1m0 10V1H1"></path></svg>
                            </span>

                                <span><?php esc_html_e('Check Out Our Plugins', 'rextheme'); ?></span>
                        </span>
                    </a>
                </div>
        </div>
    </div>



        <style>
     
            #fifty-percent-discount-popup {
                position: fixed;
                bottom: 20px;
                left: 20px;
                z-index: 9999;
                display: none; 
                max-width: 555px;
                border-radius: 20px 0 20px 20px;
                box-shadow: 0 20px 120px 0 rgba(12, 12, 86, 0.80);
                animation: fifty-percent-discount-popup-animation 0.5s ease-in-out;
                background-color: #fff;
            }

             /* Cross button in top-right */
            #fifty-percent-discount-close-popup {
                position: absolute;
                top: -15px;
                right: -15px;
                width: 36px;
                height: 36px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 22px;
                background: #FFF;
                box-shadow: 0 5px 20px 0 rgba(10, 8, 90, 0.10);
                border: none;
                cursor: pointer;
                z-index: 10000; 
            }

            .fifty-percent-discount-popup__content {
                padding: 20px 20px 40px;
                
            }


            .fifty-percent-discount-popup__logo {
                width: 100%;
                height: auto;
                display: block;
                max-width: 100%;
                margin-bottom: 20px;
            }

            .fifty-percent-discount-popup__cta-button {
                display: block;
                margin: 25px auto 0;
                text-align: center;
            }

            .fifty-percent-discount-popup__cta {
                position: relative;
                color: #fff;
                text-align: center;
                font-family: "Inter", sans-serif;
                font-size: 18px;
                font-weight: 700;
                line-height: 1.2;
                -webkit-transition: all .3s ease;
                -o-transition: all .3s ease;
                -moz-transition: all .3s ease;
                transition: all .3s ease;
                display: inline-block;
                filter: drop-shadow(20px 20px 60px rgba(21, 19, 119, 0.20));
            }

           .fifty-percent-discount-popup__cta::before {
                content: "";
                position: absolute;
                left: -1px;
                top: -1px;
                width: calc(100% + 2px);
                height: calc(100% + 2px);
                background: linear-gradient(108deg,  #201cfe 0%,#00b4ff 21%,#ffffff 100%);
            }

            .fifty-percent-discount-popup__cta  .cta-btn-inner {
                position: relative;
                overflow: hidden;
                display: inline-flex;
                flex-flow: row wrap;
                gap: 20px;
                align-items: center;
                padding: 19px 30px;
                box-shadow: 0px 30px 60px #15137733;
            }

            .fifty-percent-discount-popup__cta  .cta-btn-inner::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 150%;
                height: 100%;
                transition: all 0.3s ease;
                background: linear-gradient(140deg, #24EC2C 6.23%, #00B4FF 24.95%, #201CFE 99.45%);
            }

            .fifty-percent-discount-popup__cta .fifty-percent-discount-popup__cta-button:hover .cta-btn-inner::before {
                left: -90px;
            }

            .fifty-percent-discount-popup__cta span {
                position: relative;
                z-index: 1;
            }

            .fifty-percent-discount-popup h2 {
                max-width: 410px;
                margin: 0 auto 20px;
                color: #1A1A1D;
                text-align: center;
                font-family: 'Inter', sans-serif;
                font-size: 32px;
                font-weight: 700;
                line-height: 1.28; 
                letter-spacing: -1px;
            }

            .fifty-percent-discount-popup p {
                max-width: 410px;
                margin: 0 auto;
                color: #505061;
                text-align: center;
                font-family: 'Inter', sans-serif;
                font-size: 16px;
                font-weight: 400;
                line-height: 1.6;
            }

            .fifty-percent-discount-popup__heading-highlight {
                color: #201CFE;
                font-family: 'Inter', sans-serif;
                font-size: 36px;
                font-style: normal;
                font-weight: 900;
                line-height: 46px;
                letter-spacing: -1px;
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
            
            @media only screen and (max-width: 1699px) {

            }

            @media only screen and (max-width: 1399px) {
                #fifty-percent-discount-popup {
                    max-width: 500px;
                }

            }

            @media only screen and (max-width: 1199px) {
                #fifty-percent-discount-popup {
                    max-width: 450px;
                }

                .fifty-percent-discount-popup h2 {
                    font-size: 28px;
                }

                .fifty-percent-discount-popup__heading-highlight {
                    font-size: 32px;
                }

                #fifty-percent-discount-close-popup {
                    width: 30px;
                    height: 30px;
                }

                #fifty-percent-discount-close-popup svg {
                    display: block;
                    width: 12px;
                }

            }

            @media only screen and (max-width: 991px) {
                .fifty-percent-discount-popup__content {
                    padding: 16px 16px 30px;
                }

                #fifty-percent-discount-popup {
                    max-width: 400px;
                }

                .fifty-percent-discount-popup h2 {
                    font-size: 25px;
                    margin: 0 auto 10px;
                }

                .fifty-percent-discount-popup__heading-highlight {
                    font-size: 28px;
                }
            
            }

            @media only screen and (max-width: 767px) {
                #fifty-percent-discount-popup {
                    left: 10px;
                    border-radius: 10px 0 10px 10px;
                }
                .fifty-percent-discount-popup__content {
                    padding: 15px 15px 25px;
                }

                #fifty-percent-discount-popup {
                    max-width: 290px;
                }

                .fifty-percent-discount-popup__cta .cta-btn-inner {
                    padding: 16px 25px;
                    gap: 10px;
                }

                .fifty-percent-discount-popup__cta {
                    font-size: 15px;
                }

                .fifty-percent-discount-popup h2 {
                    font-size: 23px;
                    margin: 0 auto 10px;
                    line-height: 1.2;
                }

                .fifty-percent-discount-popup__heading-highlight {
                    font-size: 25px;
                }

                .fifty-percent-discount-popup p {
                    font-size: 14px;
                    line-height: 1.4;
                }
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
