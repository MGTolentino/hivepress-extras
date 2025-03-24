<?php
// includes/functions.php

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!function_exists('hpped_debug_log')) {
    function hpped_debug_log($message, $data = null) {
        if (WP_DEBUG) {
            error_log('HPPED Debug: ' . $message);
            if ($data !== null) {
                error_log(print_r($data, true));
            }
        }
    }
}

require_once dirname(__FILE__) . '/translations.php';

// En el constructor de Attachment
add_action('init', function() {
    hpped_debug_log('Attachment controller initialized');
});

add_action('wp_ajax_hpped_upload_attachment', function() {
    hpped_debug_log('Ajax upload action triggered', $_POST);
    hpped_debug_log('Files:', $_FILES);
});

function hpped_custom_block_content() {
    // Get current listing
    $listing = hivepress()->request->get_context('listing');
    if (!$listing) {
        $listing_id = get_the_ID();
        $listing = new \HivePress\Models\Listing($listing_id);
    }

    if ($listing) {
        $price_extras = $listing->get_price_extras();
        if (!empty($price_extras)) {
            if (WP_DEBUG) {
                error_log('=== Building Price Extras Display ===');
                error_log('Price Extras Data: ' . print_r($price_extras, true));
            }

            $output = '<div class="hp-price-extras-container"><div class="hp-price-extras">';
            
            foreach ($price_extras as $extra) {
                if (empty($extra['description'])) {
                    continue;
                }

                $description_items = explode("\n", $extra['description']);
                $line_count = count($description_items);
                $extra_name = sanitize_title($extra['name']);
                
                $output .= '<div class="hp-price-extra">';

                // Verificar si hay imágenes en el extra
                if (!empty($extra['extra_images'])) {
                    if (WP_DEBUG) {
                        error_log('Processing images for extra: ' . $extra['name']);
                        error_log('Images: ' . print_r($extra['extra_images'], true));
                    }

                    $attachment_ids = is_array($extra['extra_images']) ? $extra['extra_images'] : [$extra['extra_images']];
                    
                    $output .= '<div class="hp-price-extra__images-carousel">';
                    $output .= '<div class="carousel-container">';
                    $output .= '<div class="carousel-track">';
                    
                    foreach ($attachment_ids as $attachment_id) {
                        $image_url = wp_get_attachment_image_url($attachment_id, 'full');
                        if ($image_url) {
                            if (WP_DEBUG) {
                                error_log('Adding image: ' . $image_url);
                            }
                            $output .= '<div class="carousel-slide">';
                            $output .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($extra['name']) . '">';
                            $output .= '</div>';
                        }
                    }
                    
                    $output .= '</div>'; // carousel-track
                    
                    if (count($attachment_ids) > 1) {
                        $output .= '<button class="carousel-arrow prev">&lt;</button>';
                        $output .= '<button class="carousel-arrow next">&gt;</button>';
                    }
                    
                    $output .= '</div>'; // carousel-container
                    $output .= '</div>'; // hp-price-extra__images-carousel

                    // Añadir clase específica cuando hay imágenes
                    $output = str_replace('<div class="hp-price-extra">', '<div class="hp-price-extra has-images">', $output);
                }

                $output .= '<div class="hp-price-extra__content">';
                $output .= '<h4 class="hp-price-extra__name">' . esc_html($extra['name']) . '</h4>';
                $output .= '<div class="hp-price-extra__description">';
                foreach ($description_items as $index => $item) {
                    $output .= '<p>' . esc_html(trim($item)) . '</p>';
                }
                $output .= '</div>';
                
                if ($line_count > 11) {
                    $output .= '<button class="hp-price-extra__popup-button" data-extra-name="' . 
          esc_attr($extra['name']) . '">' . esc_html__('View more', 'hivepress-price-extras-description') . '</button>';
                }
                
                $output .= '<p class="hp-price-extra__type">' . hpped_get_extra_type($extra['type']) . '</p>';
                
                // Modificación solo para extras de tipo variable_quantity
                if ($extra['type'] === 'variable_quantity') {
                    $output .= '<div class="hp-price-extra__price-container">';
                    $output .= '<p class="hp-price-extra__price">' . hpped_format_price($extra['price']) . '</p>';
                    $output .= '<div class="hp-price-extra__quantity">';
                    $output .= '<input type="number" class="variable-quantity-input" min="0" placeholder="" data-extra-name="' . esc_attr($extra['name']) . '">';
                    $output .= '</div>';
                    $output .= '</div>';
                } else {
                    // Mantener la estructura original para otros tipos de extras
                    $output .= '<p class="hp-price-extra__price">' . hpped_format_price($extra['price']) . '</p>';
                }

                $output .= '<button class="hp-price-extra__reserve" data-extra-name="' . esc_attr($extra['name']) . '" data-state="add">
                        <span class="button-content">
                            <svg class="booking-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                            </svg>
                            <span>' . esc_html__('Add to booking', 'hivepress-price-extras-description') . '</span>
                        </span>
                        <span class="success-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                        </span>
                    </button>';
                
                $output .= '</div>'; // Cierre de content
                $output .= '</div>'; // Cierre de price-extra
            }

            $output .= '</div></div>';
            
            return $output;
        }
        return '<p>' . esc_html__('No price extras with description for this listing.', 'hivepress-price-extras-description') . '</p>';

    }
    return '<p>' . esc_html__('Listing not found.', 'hivepress-price-extras-description') . '</p>';
}

// Función personalizada para formatear el precio
function hpped_format_price($price) {
   if (function_exists('wc_get_price_thousand_separator') && function_exists('wc_get_price_decimal_separator') && function_exists('wc_get_price_decimals') && function_exists('get_woocommerce_currency')) {
       // Formatear el número usando la configuración de WooCommerce
       $formatted = number_format(
           (float) $price,
           wc_get_price_decimals(),
           wc_get_price_decimal_separator(),
           wc_get_price_thousand_separator()
       );
       return '$' . $formatted . ' ' . get_woocommerce_currency();
   }
   // Fallback si WooCommerce no está activo
   return 'USD$' . number_format((float) $price, 2, '.', ',');
}

// Función para obtener el tipo de extra formateado
function hpped_get_extra_type( $type ) {
    $types = [
        ''                  => __('per place per day', 'hivepress-price-extras-description'),
        'per_quantity'      => __('per place', 'hivepress-price-extras-description'),
        'per_item'         => __('per day', 'hivepress-price-extras-description'),
        'per_order'        => __('per booking', 'hivepress-price-extras-description'),
        'variable_quantity' => __('Variable quantity', 'hivepress-price-extras-description'),
    ];
    return isset($types[$type]) ? $types[$type] : $types[''];
}

/**
 * Registrar assets comunes
 */
function hpped_register_common_assets() {
    $plugin_url = plugin_dir_url(dirname(__FILE__));

    return [
        'pluginUrl' => $plugin_url,
        'restUrl' => rest_url('price-extras/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'maxFiles' => 5,
        'i18n' => hpped_get_js_translations()
    ];
}

/**
 * Registrar assets para visualización de listing
 */
function hpped_register_viewing_assets() {
    if (function_exists('hivepress') && is_singular('hp_listing')) {
        $common_vars = hpped_register_common_assets();
        $plugin_url = $common_vars['pluginUrl'];

        // CSS y JS para visualización (tarjetas y popups)
        wp_enqueue_style(
            'hpped-price-extras',
            $plugin_url . 'assets/css/price-extras.css',
            [],
            '1.0.0'
        );
        
        wp_enqueue_script(
            'hpped-price-extras',
            $plugin_url . 'assets/js/price-extras.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }
}

/**
 * Registrar assets para edición de listing
 */
function hpped_register_editing_assets() {
    if (!function_exists('hivepress')) {
        return;
    }

    if (WP_DEBUG) {
        error_log('=== Checking HivePress Edit Page ===');
        error_log('Current URL: ' . $_SERVER['REQUEST_URI']);
    }

    // Verificar si estamos en la página de edición o creación de listing
        if (strpos($_SERVER['REQUEST_URI'], '/account/listings/') === false && 
        strpos($_SERVER['REQUEST_URI'], '/submit-listing/details') === false) {
        return;
        }

    $common_vars = hpped_register_common_assets();
    $plugin_url = $common_vars['pluginUrl'];

    // CSS para upload
    wp_enqueue_style(
        'hpped-price-extras-upload',
        $plugin_url . 'assets/css/price-extras-upload.css',
        [],
        '1.0.0'
    );

    // JS para upload
    wp_enqueue_script(
        'hpped-price-extras-upload',
        $plugin_url . 'assets/js/price-extras-upload.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // Obtener el ID del listing de la URL
    $listing_id = null;

if (WP_DEBUG) {
    error_log('=== Registro de obtención de Listing ID ===');
    error_log('URL actual: ' . $_SERVER['REQUEST_URI']);
}

if (preg_match('/\/account\/listings\/(\d+)/', $_SERVER['REQUEST_URI'], $matches)) {
    $listing_id = $matches[1];
    if (WP_DEBUG) {
        error_log('ID obtenido de URL: ' . $listing_id);
    }
} else {
    $listing = hivepress()->request->get_context('listing');
    if (WP_DEBUG) {
        error_log('Listing del contexto: ');
        error_log(print_r($listing, true));
    }
    
    if ($listing) {
        $listing_id = $listing->get_id();
        if (WP_DEBUG) {
            error_log('ID obtenido del contexto: ' . $listing_id);
        }
    } else {
        if (WP_DEBUG) {
            error_log('No se encontró listing en el contexto');
        }
    }
}

if (WP_DEBUG) {
    error_log('ID final del listing: ' . $listing_id);
}

    wp_localize_script(
        'hpped-price-extras-upload',
        'hppedVars',
        array_merge($common_vars, [
            'context' => 'frontend_edit',
            'listingId' => $listing_id
        ])
    );

    if (WP_DEBUG) {
        error_log('=== Price Extras Editing Assets Loaded ===');
        error_log('Listing ID: ' . $listing_id);
    }
}

/**
 * Registrar assets para admin
 */
function hpped_register_admin_assets($hook) {
    global $post;

    if (($hook == 'post.php' || $hook == 'post-new.php') && 
        (!$post || $post->post_type == 'hp_listing' || 
        (isset($_GET['post_type']) && $_GET['post_type'] == 'hp_listing'))) {
        $common_vars = hpped_register_common_assets();
        $plugin_url = $common_vars['pluginUrl'];

        // CSS para upload en admin
        wp_enqueue_style(
            'hpped-price-extras-upload',
            $plugin_url . 'assets/css/price-extras-upload.css',
            [],
            '1.0.0'
        );

        // JS para upload en admin
        wp_enqueue_script(
            'hpped-price-extras-upload',
            $plugin_url . 'assets/js/price-extras-upload.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script(
            'hpped-price-extras-upload',
            'hppedVars',
            array_merge($common_vars, [
                'context' => 'admin',
                'postId' => isset($post->ID) ? $post->ID : 0
            ])
        );

        // JS específico de admin
        wp_enqueue_script(
            'hpped-admin',
            $plugin_url . 'assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }
}

// Hooks para los diferentes contextos
add_action('wp_enqueue_scripts', 'hpped_register_viewing_assets');  // Visualización
add_action('wp_enqueue_scripts', 'hpped_register_editing_assets');  // Edición frontend
add_action('admin_enqueue_scripts', 'hpped_register_admin_assets'); // Admin


// Remover la acción anterior si existe
remove_action('wp_enqueue_scripts', 'hpped_register_multiple_file_assets');
remove_action('admin_enqueue_scripts', 'hpped_register_multiple_file_assets');

/**
 * Procesar archivos antes de guardar el formulario
 */
add_filter('hivepress/v1/forms/submit_listing/values', 'hpped_process_price_extras_files', 10, 2);

function hpped_process_price_extras_files($values, $form) {
    if (isset($values['price_extras']) && is_array($values['price_extras'])) {
        foreach ($values['price_extras'] as $key => &$extra) {
            if (isset($extra['extra_images']) && !empty($extra['extra_images'])) {
                // Convertir IDs de string a int
                $extra['extra_images'] = array_map('intval', (array) $extra['extra_images']);
                
                // Filtrar IDs inválidos
                $extra['extra_images'] = array_filter($extra['extra_images'], function($id) {
                    return get_post_type($id) === 'attachment' && 
                           get_post_meta($id, 'price_extra_image', true);
                });
            }
        }
    }
    return $values;
}

/**
 * Limpiar archivos huérfanos
 */
add_action('delete_post', 'hpped_cleanup_price_extras_files', 10, 2);

function hpped_cleanup_price_extras_files($post_id, $post) {
    if ($post->post_type !== 'hp_listing') {
        return;
    }

    $attachments = get_posts([
        'post_type' => 'attachment',
        'meta_key' => 'price_extra_image',
        'meta_value' => '1',
        'posts_per_page' => -1,
        'post_parent' => $post_id,
    ]);

    foreach ($attachments as $attachment) {
        wp_delete_attachment($attachment->ID, true);
    }
}

// En functions.php
add_action('rest_api_init', function() {
    // Registrar rutas REST
    require_once dirname(__FILE__) . '/controllers/class-price-extras-upload.php';
    $controller = new \HPPriceExtrasDescription\Controllers\Price_Extras_Upload();
    $controller->register_routes();
});