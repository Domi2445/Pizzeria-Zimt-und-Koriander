<?php
/**
 * Theme Functions – enqueues assets and basic setup
 */

if (!function_exists('zimt_enqueue_assets')) {
  function zimt_enqueue_assets() {
    wp_enqueue_style('zimt-style', get_stylesheet_uri());
  }
}
add_action('wp_enqueue_scripts','zimt_enqueue_assets');

if (!function_exists('zimt_custom_theme_setup')) {
  function zimt_custom_theme_setup(){
    add_theme_support('custom-logo');
    add_theme_support('woocommerce');
  }
}
add_action('after_setup_theme','zimt_custom_theme_setup');

/* Load required scripts/styles for shop pages */
add_action('wp_enqueue_scripts', function(){
  if (!function_exists('is_woocommerce')) return;

  // Only load on WooCommerce related pages (shop / product archive / single product)
  if (is_woocommerce() || is_shop() || is_product_taxonomy() || is_product()) {
    // Woo scripts we rely on
    wp_enqueue_script('jquery');
    wp_enqueue_script('wc-add-to-cart-variation');
    wp_enqueue_script('wc-cart-fragments');

    $theme_dir = get_stylesheet_directory_uri();

    // Category nav CSS + quickview CSS (kept minimal, you can extend)
    wp_enqueue_style('theme-category-nav', $theme_dir . '/assets/css/category-nav.css', [], '1.0.0');
    wp_enqueue_style('theme-quickview', $theme_dir . '/assets/css/quickview.css', [], '1.0.0');

    // Category nav JS + quickview JS
    wp_enqueue_script('theme-category-nav', $theme_dir . '/assets/js/category-nav.js', [], '1.0.0', true);
    wp_enqueue_script('theme-quickview', $theme_dir . '/assets/js/quickview.js', ['jquery','wc-add-to-cart-variation'], '1.0.0', true);

    // small config passed to category-nav / quickview
    wp_localize_script('theme-category-nav', 'ThemeCategoryNav', [
      'offset' => 88, // adjust to header height (topbar + header)
    ]);
    wp_localize_script('theme-quickview', 'ThemeQuickview', [
      'wc_ajax_url' => (isset($GLOBALS['wc_add_to_cart_params']) && !empty($GLOBALS['wc_add_to_cart_params']['wc_ajax_url'])) ? $GLOBALS['wc_add_to_cart_params']['wc_ajax_url'] : home_url('/?wc-ajax='),
      'ajax_quickview_endpoint' => home_url('/?wc-ajax=gf_quickview'),
    ]);
  }
}, 20);