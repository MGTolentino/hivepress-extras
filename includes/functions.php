<?php

// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!function_exists('hpped_debug_log')) {
    function hpped_debug_log($message, $data = null)
    {
        if (WP_DEBUG) {
            error_log('HPPED Debug: ' . $message);
            if ($data !== null) {
                error_log(print_r($data, true));
            }
        }
    }
}

require_once dirname(__FILE__) . '/translations.php';

function hpped_custom_block_content()
{
    // Get current listing
    $listing = hivepress()->request->get_context('listing');
    if (!$listing) {
        $listing_id = get_the_ID();
        $listing = new \HivePress\Models\Listing($listing_id);
    }

    if ($listing) {
        $price_extras = $listing->get_price_extras();
        if (!empty($price_extras)) {

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

                    $attachment_ids = is_array($extra['extra_images']) ? $extra['extra_images'] : [$extra['extra_images']];
                    $valid_images = [];

                    foreach ($attachment_ids as $attachment_id) {
                        $image_url = wp_get_attachment_image_url($attachment_id, 'full');
                        $file_path = get_attached_file($attachment_id);

                        // Verificar que la imagen existe físicamente
                        if ($image_url && $file_path && file_exists($file_path)) {
                            $valid_images[] = [
                                'id' => $attachment_id,
                                'url' => $image_url
                            ];
                        } else {
                            if (WP_DEBUG) {
                                error_log('Image not found for ID: ' . $attachment_id);
                            }
                        }
                    }

                    if (!empty($valid_images)) {
                        $output .= '<div class="hp-price-extra__images-carousel">';
                        $output .= '<div class="carousel-container">';
                        $output .= '<div class="carousel-track">';

                        foreach ($valid_images as $image) {

                            $output .= '<div class="carousel-slide">';
                            $output .= '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($extra['name']) . '">';
                            $output .= '</div>';
                        }

                        $output .= '</div>'; // carousel-track

                        if (count($valid_images) > 1) {
                            $output .= '<button class="carousel-arrow prev">&lt;</button>';
                            $output .= '<button class="carousel-arrow next">&gt;</button>';
                        }

                        $output .= '</div>'; // carousel-container
                        $output .= '</div>'; // hp-price-extra__images-carousel

                        // Añadir clase específica cuando hay imágenes
                        $output = str_replace('<div class="hp-price-extra">', '<div class="hp-price-extra has-images">', $output);
                    }
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

function hpped_format_price($price)
{
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

function hpped_get_extra_type($type)
{
    $types = [
        ''                  => __('per place per day', 'hivepress-price-extras-description'),
        'per_quantity'      => __('per place', 'hivepress-price-extras-description'),
        'per_item'         => __('per day', 'hivepress-price-extras-description'),
        'per_order'        => __('per booking', 'hivepress-price-extras-description'),
        'variable_quantity' => __('Variable quantity', 'hivepress-price-extras-description'),
    ];
    return isset($types[$type]) ? $types[$type] : $types[''];
}

function hpped_register_common_assets()
{
    $plugin_url = plugin_dir_url(dirname(__FILE__));

    return [
        'pluginUrl' => $plugin_url,
        'restUrl' => rest_url('price-extras/v1/'),
        'nonce' => wp_create_nonce('wp_rest'),
        'maxFiles' => 5,
        'i18n' => hpped_get_js_translations()
    ];
}

function hpped_register_viewing_assets()
{
    if (function_exists('hivepress') && is_singular('hp_listing')) {
        $common_vars = hpped_register_common_assets();
        $plugin_url = $common_vars['pluginUrl'];

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

        wp_localize_script(
            'hpped-price-extras',
            'hppedVars',
            $common_vars
        );
    }
}

function hpped_register_editing_assets()
{
    if (!function_exists('hivepress')) {
        return;
    }

    if (
        strpos($_SERVER['REQUEST_URI'], '/account/listings/') === false &&
        strpos($_SERVER['REQUEST_URI'], '/submit-listing/details') === false
    ) {
        return;
    }

    $common_vars = hpped_register_common_assets();
    $plugin_url = $common_vars['pluginUrl'];

    wp_enqueue_style(
        'hpped-price-extras-upload',
        $plugin_url . 'assets/css/price-extras-upload.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'hpped-price-extras-upload',
        $plugin_url . 'assets/js/price-extras-upload.js',
        ['jquery'],
        '1.0.0',
        true
    );

    wp_enqueue_script(
        'hpped-fix-repeater-images',
        $plugin_url . 'assets/js/fix-repeater-images.js',
        ['jquery', 'hivepress-core'],
        '1.0.0',
        true
    );

    // Obtener el ID del listing de la URL
    $listing_id = null;

    if (preg_match('/\/account\/listings\/(\d+)/', $_SERVER['REQUEST_URI'], $matches)) {
        $listing_id = $matches[1];

    } else {
        $listing = hivepress()->request->get_context('listing');

        if ($listing) {
            $listing_id = $listing->get_id();

        } else {
            if (WP_DEBUG) {
                error_log('No se encontró listing en el contexto');
            }
        }
    }

    wp_localize_script(
        'hpped-price-extras-upload',
        'hppedVars',
        array_merge($common_vars, [
            'context' => 'frontend_edit',
            'listingId' => $listing_id
        ])
    );
}

function hpped_register_admin_assets($hook)
{
    global $post;

    if (($hook == 'post.php' || $hook == 'post-new.php') &&
        (!$post || $post->post_type == 'hp_listing' ||
            (isset($_GET['post_type']) && $_GET['post_type'] == 'hp_listing'))
    ) {
        $common_vars = hpped_register_common_assets();
        $plugin_url = $common_vars['pluginUrl'];

        wp_enqueue_style(
            'hpped-price-extras-upload',
            $plugin_url . 'assets/css/price-extras-upload.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'hpped-price-extras-upload',
            $plugin_url . 'assets/js/price-extras-upload.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_script(
            'hpped-fix-repeater-images',
            $plugin_url . 'assets/js/fix-repeater-images.js',
            ['jquery', 'hivepress-core'],
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

        wp_enqueue_script(
            'hpped-admin',
            $plugin_url . 'assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }
}

add_action('wp_enqueue_scripts', 'hpped_register_viewing_assets');  // Visualización
add_action('wp_enqueue_scripts', 'hpped_register_editing_assets');  // Edición frontend
add_action('admin_enqueue_scripts', 'hpped_register_admin_assets'); // Admin

remove_action('wp_enqueue_scripts', 'hpped_register_multiple_file_assets');
remove_action('admin_enqueue_scripts', 'hpped_register_multiple_file_assets');

/**
 * Procesar archivos antes de guardar el formulario
 */
add_filter('hivepress/v1/forms/submit_listing/values', 'hpped_process_price_extras_files', 10, 2);

function hpped_process_price_extras_files($values, $form)
{
    if (isset($values['price_extras']) && is_array($values['price_extras'])) {
        foreach ($values['price_extras'] as $key => &$extra) {
            if (isset($extra['extra_images']) && !empty($extra['extra_images'])) {
                // Convertir IDs de string a int
                $extra['extra_images'] = array_map('intval', (array) $extra['extra_images']);

                // Filtrar IDs inválidos
                $extra['extra_images'] = array_filter($extra['extra_images'], function ($id) {
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

function hpped_cleanup_price_extras_files($post_id, $post)
{
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

add_action('rest_api_init', function () {
    // Registrar rutas REST
    require_once dirname(__FILE__) . '/controllers/class-price-extras-upload.php';
    $controller = new \HPPriceExtrasDescription\Controllers\Price_Extras_Upload();
    $controller->register_routes();
});

/**
 * Limpia adjuntos huérfanos de extras de precio
 */
function hpped_cleanup_orphaned_attachments()
{
    global $wpdb;


    // Buscar adjuntos con metadata de extras pero sin archivo físico
    $attachments = $wpdb->get_results(
        "SELECT p.ID, pm.meta_value as file_path 
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
         JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
         WHERE p.post_type = 'attachment'
         AND pm1.meta_key = 'price_extra_image' 
         AND pm1.meta_value = '1'"
    );

    $deleted_count = 0;

    foreach ($attachments as $attachment) {
        $file_path = get_attached_file($attachment->ID);

        // Si no hay archivo o no existe, eliminar el attachment
        if (!$file_path || !file_exists($file_path)) {

            wp_delete_attachment($attachment->ID, true);
            $deleted_count++;
        }
    }

    return $deleted_count;
}

// Programar limpieza diaria
if (!wp_next_scheduled('hpped_cleanup_orphaned_attachments_hook')) {
    wp_schedule_event(time(), 'daily', 'hpped_cleanup_orphaned_attachments_hook');
}
add_action('hpped_cleanup_orphaned_attachments_hook', 'hpped_cleanup_orphaned_attachments');

// También ejecutar en actualización de plugin
add_action('upgrader_process_complete', 'hpped_cleanup_orphaned_attachments', 10, 0);

function hpped_repair_listing_images($listing_id)
{

    // Obtener extras del listing
    $extras = get_post_meta($listing_id, 'hp_price_extras', true);
    if (empty($extras) || !is_array($extras)) {

        return false;
    }

    $fixed_images = 0;

    foreach ($extras as $key => &$extra) {
        if (empty($extra['extra_images'])) {
            continue;
        }

        // Normalizar extra_images a array
        $image_ids = is_array($extra['extra_images']) ? $extra['extra_images'] : explode(',', $extra['extra_images']);
        $image_ids = array_filter(array_map('intval', $image_ids));

        if (empty($image_ids)) {
            continue;
        }

        $valid_ids = [];

        foreach ($image_ids as $image_id) {
            // Verificar si la imagen existe
            $attachment = get_post($image_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                if (WP_DEBUG) {
                    error_log('Attachment does not exist: ' . $image_id);
                }
                continue;
            }

            // Verificar si el archivo físico existe
            $file_path = get_attached_file($image_id);
            if (!$file_path || !file_exists($file_path)) {
                if (WP_DEBUG) {
                    error_log('File does not exist for attachment: ' . $image_id);
                }

                // Intentar encontrar el archivo en carpeta null o temporal
                $upload_dir = wp_upload_dir();
                $extra_name = sanitize_title($extra['name']);

                // Posibles rutas alternativas
                $possible_paths = [
                    $upload_dir['basedir'] . '/price-extras/null/' . $extra_name,
                    $upload_dir['basedir'] . '/price-extras/temp/' . $extra_name,
                    $upload_dir['basedir'] . '/price-extras/' . $listing_id . '/' . $extra_name,
                ];

                $file_fixed = false;

                foreach ($possible_paths as $dir_path) {
                    if (!is_dir($dir_path)) {
                        continue;
                    }

                    // Buscar por nombre de archivo original
                    $file_name = basename($file_path);
                    $alternative_path = $dir_path . '/' . $file_name;

                    if (file_exists($alternative_path)) {
                        // Crear directorio destino si no existe
                        $target_dir = $upload_dir['basedir'] . '/price-extras/' . $listing_id . '/' . $extra_name;
                        if (!is_dir($target_dir)) {
                            wp_mkdir_p($target_dir);
                        }

                        // Copiar archivo
                        $target_path = $target_dir . '/' . $file_name;
                        if (copy($alternative_path, $target_path)) {
                            // Actualizar metadata
                            update_attached_file($image_id, $target_path);

                            // Actualizar metadata de listing
                            update_post_meta($image_id, 'price_extra_listing_id', $listing_id);
                            update_post_meta($image_id, 'price_extra_key', $extra_name);

                            $file_fixed = true;
                            $fixed_images++;


                            // Regenerar metadata y thumbnails
                            $metadata = wp_generate_attachment_metadata($image_id, $target_path);
                            wp_update_attachment_metadata($image_id, $metadata);

                            $valid_ids[] = $image_id;
                            break;
                        }
                    }
                }

                if (!$file_fixed) {
                    if (WP_DEBUG) {
                        error_log('Could not fix image: ' . $image_id);
                    }
                    // No añadir a valid_ids
                }
            } else {
                // La imagen es válida
                $valid_ids[] = $image_id;
            }
        }

        // Actualizar extra con solo las imágenes válidas
        $extra['extra_images'] = $valid_ids;
    }

    // Guardar los cambios
    update_post_meta($listing_id, 'hp_price_extras', $extras);


    return $fixed_images;
}

// Añadir herramienta de reparación accesible para administradores
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'Repair Price Extras Images',
        'Repair Price Extras',
        'manage_options',
        'repair-price-extras',
        'hpped_repair_images_page'
    );
});

function hpped_repair_images_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'hivepress-price-extras-description'));
    }

    $message = '';

    if (isset($_POST['repair_listing']) && isset($_POST['listing_id']) && is_numeric($_POST['listing_id'])) {
        $listing_id = intval($_POST['listing_id']);
        $fixed = hpped_repair_listing_images($listing_id);

        if ($fixed !== false) {
            $message = sprintf(
                __('Repaired %d images for listing #%d', 'hivepress-price-extras-description'),
                $fixed,
                $listing_id
            );
        } else {
            $message = sprintf(
                __('No extras found for listing #%d', 'hivepress-price-extras-description'),
                $listing_id
            );
        }
    }

    if (isset($_POST['repair_orphaned'])) {
        $deleted = hpped_cleanup_orphaned_attachments();
        $message = sprintf(
            __('Deleted %d orphaned attachments', 'hivepress-price-extras-description'),
            $deleted
        );
    }

?>
    <div class="wrap">
        <h1><?php _e('Repair Price Extras Images', 'hivepress-price-extras-description'); ?></h1>

        <?php if ($message): ?>
            <div class="notice notice-success">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><?php _e('Repair Listing Images', 'hivepress-price-extras-description'); ?></h2>
            <p><?php _e('This tool will attempt to find and fix missing images for a specific listing.', 'hivepress-price-extras-description'); ?></p>

            <form method="post">
                <label for="listing_id"><?php _e('Listing ID:', 'hivepress-price-extras-description'); ?></label>
                <input type="number" name="listing_id" id="listing_id" required>
                <button type="submit" name="repair_listing" class="button button-primary"><?php _e('Repair Listing', 'hivepress-price-extras-description'); ?></button>
            </form>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2><?php _e('Clean Orphaned Attachments', 'hivepress-price-extras-description'); ?></h2>
            <p><?php _e('This tool will remove attachment records from the database that have missing physical files.', 'hivepress-price-extras-description'); ?></p>

            <form method="post">
                <button type="submit" name="repair_orphaned" class="button button-primary"><?php _e('Clean Orphaned Attachments', 'hivepress-price-extras-description'); ?></button>
            </form>
        </div>
    </div>
<?php
}
