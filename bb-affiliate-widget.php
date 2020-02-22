<?php
/**
 * Plugin Name:     Affiliate Widget
 * Description:     An example affiliate widget - DEMO ONLY.
 * Author:          BoxyBird
 * Author URI:      https://boxybird.com
 * Text Domain:     bb-affiliate-widget
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Bb_Affiliate_Widget
 */

require_once __DIR__ . '/classes/BB_Affiliate_Widget.php';
require_once __DIR__ . '/classes/BB_Ebay_Rss.php';

add_action('widgets_init', function () {
    register_widget('BoxyBird\Classes\BB_Affiliate_Widget');
});
