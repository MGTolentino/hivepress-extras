<?php
/**
 * Plugin Name: Hivepress Price Extras Description
 * Description: Muestra los extras de precio en la página de visualización del listing
 * Version: 2.0.0
 * Author: Miguel Tolentino
 * Text Domain: hivepress-price-extras-description
 * Domain Path: /languages/
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

// Definir constante para la ruta base del plugin
if (!defined('HPPED_PATH')) {
    define('HPPED_PATH', plugin_dir_path(__FILE__));
}

// Register extension directory
add_filter(
    'hivepress/v1/extensions',
    function($extensions) {
        $extensions[] = __DIR__;
        return $extensions;
    }
);

// Initialize plugin
add_action('after_setup_theme', function() {
    if (!class_exists('HivePress\Components\Component')) {
        return;
    }

    // Include files
    require_once HPPED_PATH . 'includes/components/class-price-extras-description.php';
    require_once HPPED_PATH . 'includes/functions.php';
    require_once HPPED_PATH . 'includes/fields/class-multiple-file.php';
    require_once HPPED_PATH . 'includes/controllers/class-price-extras-upload.php';

    // Initialize components
    \HPPriceExtrasDescription\Components\Price_Extras_Description::init();
}, 5);

// Add blocks to listing view page
add_action('wp', function() {
    if (function_exists('hivepress') && is_singular('hp_listing')) {
        add_filter(
            'hivepress/v1/templates/listing_view_page/blocks',
            function($blocks) {
                return hivepress()->template->merge_blocks(
                    $blocks,
                    [
                        'page_content' => [
                            'blocks' => [
                                'custom_price_extras_block' => [
                                    'type' => 'content',
                                    'content' => hpped_custom_block_content(),
                                    '_order' => 75,
                                ],
                            ],
                        ],
                    ]
                );
            },
            20,
            1
        );
    }
});

// Cargar traducciones
add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'hivepress-price-extras-description', 
        false, 
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
});