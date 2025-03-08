<?php
/**
 * Plugin Name: QwicPay for WooCommerce
 * Description: Adds a QwicPay button to the WooCommerce cart page.
 * Version: 1.2
 * Author: QwicPay
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Ensure WooCommerce is active
function qwicpay_check_woocommerce_active() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p>QwicPay for WooCommerce requires WooCommerce to be active.</p></div>';
        });
        return false;
    }
    return true;
}

// Add settings menu
function qwicpay_add_settings_menu() {
    add_submenu_page(
        'woocommerce',
        'QwicPay Settings',
        'QwicPay',
        'manage_options',
        'qwicpay-settings',
        'qwicpay_render_settings_page'
    );
}
add_action('admin_menu', 'qwicpay_add_settings_menu');

// Render settings page
function qwicpay_render_settings_page() {
    ?>
    <div class="wrap">
        <h2>QwicPay Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('qwicpay_settings_group');
            do_settings_sections('qwicpay-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}


// Function to enforce HTTPS and clean the store URL
function qwicpay_clean_url($url) {
    $url = preg_replace('/^http:\/\//', 'https://', $url); // Replace http:// with https://
    $url = preg_replace('/^https?:\/\//', '', $url); // Remove any existing scheme
    $url = preg_replace('/^www\./', '', $url); // Remove www.
    $url = rtrim($url, '/'); // Remove trailing slashes
    return 'https://' . $url; // Ensure https:// is always added
}


// Register settings
function qwicpay_register_settings() {
    // Get WooCommerce store URL and clean it
    $default_store_url = qwicpay_clean_url(get_site_url());

    register_setting('qwicpay_settings_group', 'qwicpay_button_shape');
    register_setting('qwicpay_settings_group', 'qwicpay_button_colour');
    register_setting('qwicpay_settings_group', 'qwicpay_button_position');
    register_setting('qwicpay_settings_group', 'qwicpay_store_url');

    add_settings_section('qwicpay_main_section', 'Button Settings', null, 'qwicpay-settings');

    add_settings_field(
        'qwicpay_store_url',
        'Store URL',
        function () use ($default_store_url) {
            $value = get_option('qwicpay_store_url', $default_store_url);
            echo '<input type="text" name="qwicpay_store_url" value="' . esc_attr($value) . '" />';
        },
        'qwicpay-settings',
        'qwicpay_main_section'
    );

    add_settings_field(
        'qwicpay_button_shape',
        'Button Shape',
        function () {
            $value = get_option('qwicpay_button_shape', 'rounded'); 
            ?>
            <label>
                <input type="radio" name="qwicpay_button_shape" value="rounded" <?php checked($value, 'rounded'); ?> />
                Rounded (Recommended)
            </label>
            <br>
            <label>
                <input type="radio" name="qwicpay_button_shape" value="square" <?php checked($value, 'square'); ?> />
                Square
            </label>
            <?php
        },
        'qwicpay-settings',
        'qwicpay_main_section'
    );
    

    add_settings_field(
        'qwicpay_button_colour',
        'Button Colour',
        function () {
            $value = get_option('qwicpay_button_colour', 'blue'); 
            ?>
            <label>
                <input type="radio" name="qwicpay_button_shape" value="blue" <?php checked($value, 'blue'); ?> />
                Blue (Recommended)
            </label>
            <br>
            <label>
                <input type="radio" name="qwicpay_button_shape" value="white" <?php checked($value, 'white'); ?> />
                White
            </label>
            <?php
        },
        'qwicpay-settings',
        'qwicpay_main_section'
    );
    
    

    add_settings_field(
        'qwicpay_button_position',
        'Button Position',
        function () {
            $value = get_option('qwicpay_button_position', 'woocommerce_after_cart');
            echo '<select name="qwicpay_button_position">
                    <option value="woocommerce_after_cart" ' . selected($value, 'woocommerce_after_cart', false) . '>Below Cart</option>
                    <option value="woocommerce_proceed_to_checkout" ' . selected($value, 'woocommerce_proceed_to_checkout', false) . '>Above Checkout Button</option>
                  </select>';
        },
        'qwicpay-settings',
        'qwicpay_main_section'
    );

    
}
add_action('admin_init', 'qwicpay_register_settings');

// Add QwicPay Button to Cart Page
function qwicpay_add_checkout_button() {
    $cart_data = json_encode(WC()->cart->get_cart());
    $store_url = get_option('qwicpay_store_url', qwicpay_clean_url(get_site_url()));
    $button_shape = get_option('qwicpay_button_shape', 'rounded');
    $button_colour = get_option('qwicpay_button_colour', 'blue');

    // Define the CDN links for each button style
    $svg_urls = [
        'blue-rounded' => 'https://cdn.qwicpay.com/qwicpay/buttons/BlueBGWhiteText.svg',
        'white-rounded' => 'https://cdn.qwicpay.com/qwicpay/buttons/WhiteBGBlueText.svg',
        'blue-square' => 'https://cdn.qwicpay.com/qwicpay/buttons/BlueBGWhiteText%20_Squared.svg',
        'white-square' => 'https://cdn.qwicpay.com/qwicpay/buttons/WhiteBGBlueText%20_Squared.svg',
    ];

    // Generate the correct key for SVG selection
    $svg_key = "{$button_colour}-{$button_shape}";

    // Get the correct SVG URL
    $svg_url = isset($svg_urls[$svg_key]) ? $svg_urls[$svg_key] : $svg_urls['blue-rounded']; // Default to blue rounded

    // Output the button
    echo '<a href="https://integrate.qwicpay.com/checkout?cart=' . urlencode($cart_data) . '&store=' . urlencode($store_url) . '" class="qwicpay-button">
            <img src="' . esc_url($svg_url) . '" alt="QwicPay Button" style="width: 150px; height: auto;">
          </a>';
}

// Add CSS to ensure proper scaling
function qwicpay_add_button_styles() {
    echo '<style>
    .qwicpay-button img {
        width: 100%;
        max-width: 100%;
        height: auto;
    }
    .qwicpay-button {
        display: block;
        text-align: center;
        width: 100%;
        margin-top: 10px;
    }
</style>';
}
add_action('wp_head', 'qwicpay_add_button_styles');


// Hook the button based on merchant settings
$position = get_option('qwicpay_button_position', 'woocommerce_after_cart');
add_action($position, 'qwicpay_add_checkout_button');
