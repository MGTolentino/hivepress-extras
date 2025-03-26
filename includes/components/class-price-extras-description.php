<?php

namespace HPPriceExtrasDescription\Components;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Price Extras Description component.
 */
class Price_Extras_Description
{

    /**
     * Class constructor.
     */
    public function __construct()
    {

        // Filtros principales
        add_filter('hivepress/v1/models/listing/attributes', [$this, 'add_description_to_price_extras']);
        add_action('hivepress/v1/models/listing/update', [$this, 'handle_price_extras_update'], 20, 1);
        add_action('wp_scheduled_delete', [$this, 'cleanup_orphaned_images']);
        add_action('hivepress/v1/models/listing/update', function ($listing_id) {
        }, 5);  

        add_action('hivepress/v1/models/listing/update', function ($listing_id) {
        }, 5);

        add_filter('hivepress/v1/models/listing/get_images', [$this, 'filter_listing_images'], 1, 2);
        add_filter('hivepress/v1/models/listing/get_images__id', [$this, 'filter_listing_images'], 1, 2);

        // Filtro para impedir que HivePress procese nuestras imágenes
        add_filter('hivepress/v1/models/listing/merge', function ($values, $listing_id) {
            if (isset($values['images'])) {
                $values['images'] = array_filter($values['images'], function ($image_id) {
                    return !get_post_meta($image_id, '_price_extra_parent', true);
                });
            }
            return $values;
        }, 1, 2);

        // Filtro para excluir nuestras imágenes de las consultas de attachments
        add_filter('ajax_query_attachments_args', function ($args) {
            if (!isset($args['meta_query'])) {
                $args['meta_query'] = [];
            }

            $args['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key' => 'price_extra_temporary',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'price_extra_temporary',
                    'value' => '0'
                ]
            ];

            return $args;
        });

        // Filtros para el manejo de imágenes y sus tamaños
        add_filter('intermediate_image_sizes_advanced', [$this, 'restrict_image_sizes'], 1, 2);
        add_filter('big_image_size_threshold', function ($threshold, $imagesize, $file, $attachment_id) {
            if (get_post_meta($attachment_id, 'price_extra_image', true)) {
                return false; // Desactiva el escalado para nuestras imágenes
            }
            return $threshold;
        }, 10, 4);

        add_filter('wp_get_attachment_metadata', [$this, 'filter_attachment_metadata'], 999, 2);
        add_filter('hivepress/v1/models/attachment/get_image_urls', [$this, 'filter_attachment_urls'], 999, 2);
        add_filter('hivepress/v1/models/listing/get_attachments', [$this, 'filter_listing_attachments'], 999, 2);

        // Acciones para manejo de archivos
        add_action('save_post_hp_listing', [$this, 'save_price_extras_images'], 20, 3);
        add_action('add_attachment', [$this, 'process_new_attachment']);
        add_action('edit_attachment', [$this, 'process_edited_attachment']);

        // Filtros para formularios
        add_filter('hivepress/v1/forms', [$this, 'modify_forms'], 10, 2);

        // Prevenir que HivePress procese nuestras imágenes
        add_filter('hivepress/v1/models/listing/attributes', function ($attributes) {
            if (isset($attributes['images'])) {
                $attributes['images']['edit_field']['exclude'] = ['price_extra_image'];
            }
            return $attributes;
        });

        add_filter('hivepress/v1/models/listing/get_images', function ($images, $listing) {
            return $images;
        }, 5, 2);

        // Agregar soporte para admin
        if (is_admin()) {
            add_action('admin_init', [$this, 'setup_admin_handlers']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }

        // Registrar tamaños de imagen personalizados con prioridad alta
        add_action('after_setup_theme', function () {
            // Remover tamaños existentes si existen
            remove_image_size('price-extra-thumbnail');
            remove_image_size('price-extra-card');
            remove_image_size('price-extra-popup');

            // Registrar nuestros tamaños
            add_image_size('price-extra-thumbnail', 150, 150, true);
            add_image_size('price-extra-card', 350, 250, true);
            add_image_size('price-extra-popup', 800, 400, true);
        }, 999);

        // Agregar filtro para metadata
        add_filter('wp_generate_attachment_metadata', function ($metadata, $attachment_id) {
            if (get_post_meta($attachment_id, 'price_extra_image', true)) {
                if (isset($metadata['sizes'])) {
                    // Mantener solo nuestros tamaños
                    $allowed_sizes = ['price-extra-thumbnail', 'price-extra-card', 'price-extra-popup'];
                    foreach ($metadata['sizes'] as $size => $data) {
                        if (!in_array($size, $allowed_sizes)) {
                            unset($metadata['sizes'][$size]);
                        }
                    }
                }
            }
            return $metadata;
        }, 999, 2);

        add_filter('hivepress/v1/forms/listing_submit', function ($form) {
            $form['attributes']['enctype'] = 'multipart/form-data';
            return $form;
        });

        add_filter('hivepress/v1/forms/listing_update', function ($form) {
            $form['attributes']['enctype'] = 'multipart/form-data';
            return $form;
        });



        add_filter('hivepress/v1/models/listing/fill_fields', function ($values) {
            if (isset($values['price_extras']) && is_array($values['price_extras'])) {
                foreach ($values['price_extras'] as &$extra) {
                    if (isset($extra['extra_images'])) {
                        // Asegurar que tenemos un array de IDs
                        if (!is_array($extra['extra_images'])) {
                            $extra['extra_images'] = array_filter(
                                explode(',', $extra['extra_images'])
                            );
                        }

                        // Verificar que cada imagen existe
                        $extra['extra_images'] = array_filter($extra['extra_images'], function ($id) {
                            return wp_get_attachment_url($id) !== false;
                        });
                    }
                }
            }
            return $values;
        });

    }

    /**
     * Controla la generación de tamaños de imagen específicos para extras
     */
    private function manage_image_sizes($attachment_id)
    {
        if (!get_post_meta($attachment_id, 'price_extra_image', true)) {
            return;
        }

        if (WP_DEBUG) {
            error_log('=== Managing Image Sizes for Price Extra ===');
            error_log('Attachment ID: ' . $attachment_id);
        }

        // Guardar la configuración global actual
        global $_wp_additional_image_sizes;
        $original_sizes = $_wp_additional_image_sizes;

        // Guardar los tamaños intermedios actuales
        $original_intermediate_sizes = get_intermediate_image_sizes();

        // Desregistrar temporalmente todos los tamaños de imagen
        foreach ($original_intermediate_sizes as $size) {
            if (
                $size !== 'price-extra-thumbnail' &&
                $size !== 'price-extra-card' &&
                $size !== 'price-extra-popup'
            ) {
                remove_image_size($size);
            }
        }

        $_wp_additional_image_sizes = array();

        // Registrar solo nuestros tamaños
        $_wp_additional_image_sizes['price-extra-thumbnail'] = array(
            'width' => 150,
            'height' => 150,
            'crop' => true
        );

        $_wp_additional_image_sizes['price-extra-card'] = array(
            'width' => 350,
            'height' => 250,
            'crop' => true
        );

        $_wp_additional_image_sizes['price-extra-popup'] = array(
            'width' => 800,
            'height' => 400,
            'crop' => true
        );

        if (WP_DEBUG) {
            error_log('Temporary sizes configured');
            error_log('Current sizes: ' . print_r($_wp_additional_image_sizes, true));
        }

        // Restaurar la configuración original después del procesamiento
        add_action('wp_generate_attachment_metadata', function ($metadata, $attach_id) use ($original_sizes, $original_intermediate_sizes, $attachment_id) {
            if ($attach_id == $attachment_id) {
                global $_wp_additional_image_sizes;
                $_wp_additional_image_sizes = $original_sizes;

                // Restaurar los tamaños originales
                foreach ($original_intermediate_sizes as $size) {
                    if (isset($original_sizes[$size])) {
                        add_image_size(
                            $size,
                            $original_sizes[$size]['width'],
                            $original_sizes[$size]['height'],
                            $original_sizes[$size]['crop']
                        );
                    }
                }

                if (WP_DEBUG) {
                    error_log('Original image sizes restored');
                }
            }
            return $metadata;
        }, 999, 2);

        // Asegurar que solo se procesen nuestros tamaños
        add_filter('intermediate_image_sizes_advanced', function ($sizes) use ($attachment_id) {
            if (get_post_meta($attachment_id, 'price_extra_image', true)) {
                return array_intersect_key($sizes, array_flip(['price-extra-thumbnail', 'price-extra-card', 'price-extra-popup']));
            }
            return $sizes;
        }, 999);
    }

    /**
     * Método público para manejar los tamaños de imagen
     */
    public function handle_image_sizes($attachment_id)
    {
        error_log('=== Starting handle_image_sizes ===');
        error_log('Attachment ID: ' . $attachment_id);

        // Remover todos los tamaños por defecto de WordPress
        add_filter('intermediate_image_sizes', function ($sizes) {
            return ['price-extra-thumbnail', 'price-extra-card', 'price-extra-popup'];
        }, 999);

        // Llamar al método privado
        $result = $this->manage_image_sizes($attachment_id);

        error_log('=== Finished handle_image_sizes ===');
        return $result;
    }

    /**
     * Initialize the component.
     */
    public static function init()
    {
        return new self();
    }

    /**
     * Add description field to price extras.
     *
     * @param array $attributes Listing attributes.
     * @return array Modified attributes.
     */
    public function add_description_to_price_extras($attributes)
    {
        if (isset($attributes['price_extras']) && isset($attributes['price_extras']['edit_field']['fields'])) {
            // Add description field
            $attributes['price_extras']['edit_field']['fields']['description'] = [
                'label' => esc_html__('Description', 'hivepress-price-extras-description'),
                'type' => 'textarea',
                'max_length' => 1000,
                '_order' => 30,
            ];

            // Add images field with updated configuration
            $attributes['price_extras']['edit_field']['fields']['extra_images'] = [
                'label' => esc_html__('Extra Images', 'hivepress-price-extras-description'),
                'type' => 'multiple_file',
                '_order' => 40,
                'required' => false,
                '_model' => 'attachment',
                'formats' => ['jpg', 'jpeg', 'png'],
                'multiple' => true,
                'max_files' => 5,
                '_external' => true,
                'caption' => esc_html__('Select Images', 'hivepress-price-extras-description'),
                '_settings' => [
                    'storage' => 'local',
                    'path' => 'price-extras',
                ],
                'attributes' => [
                    'accept' => '.jpg,.jpeg,.png',
                    'data-max-files' => 5,
                    'data-component' => 'price-extras-upload',
                    'class' => 'hp-field--price-extras-upload',
                    'multiple' => 'multiple',
                    'data-nonce' => wp_create_nonce('wp_rest'),
                    'data-url' => rest_url('price-extras/v1/upload')
                ],
            ];

            // Ensure proper order of fields
            $attributes['price_extras']['edit_field']['fields'] = hp\sort_array($attributes['price_extras']['edit_field']['fields']);

            // Add support for file uploads to the form
            add_filter('hivepress/v1/forms/listing_submit', function ($form) {
                $form['attributes']['enctype'] = 'multipart/form-data';
                return $form;
            });

            add_filter('hivepress/v1/forms/listing_update', function ($form) {
                $form['attributes']['enctype'] = 'multipart/form-data';
                return $form;
            });
        }

        return $attributes;
    }

    /**
 * Migra imágenes de la carpeta temporal a la carpeta del listing real
 */
private function migrate_temp_images($listing_id) {
    if (WP_DEBUG) {
        error_log('=== Starting temp images migration ===');
        error_log('Listing ID: ' . $listing_id);
    }
    
    global $wpdb;
    
    // Buscar todas las imágenes temporales
    $temp_images = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'price_extra_temp_listing' AND pm1.meta_value = '1'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'price_extra_image' AND pm2.meta_value = '1'
            WHERE p.post_type = 'attachment'"
        )
    );
    
    if (empty($temp_images)) {
        if (WP_DEBUG) {
            error_log('No temporary images found to migrate');
        }
        return;
    }
    
    if (WP_DEBUG) {
        error_log('Found ' . count($temp_images) . ' temporary images to migrate');
    }
    
    $upload_dir = wp_upload_dir();
    $temp_base_path = $upload_dir['basedir'] . '/price-extras/temp';
    $listing_base_path = $upload_dir['basedir'] . '/price-extras/' . $listing_id;
    
    foreach ($temp_images as $image) {
        $image_id = $image->ID;
        $extra_key = get_post_meta($image_id, 'price_extra_key', true);
        
        if (!$extra_key) {
            if (WP_DEBUG) {
                error_log('Skipping image ' . $image_id . ' - no extra key found');
            }
            continue;
        }
        
        // Obtener ruta actual del archivo
        $current_file = get_attached_file($image_id);
        if (!$current_file || !file_exists($current_file)) {
            if (WP_DEBUG) {
                error_log('File not found for image ' . $image_id);
            }
            continue;
        }
        
        // Crear carpeta destino
        $extra_path = $listing_base_path . '/' . $extra_key;
        if (!is_dir($extra_path)) {
            wp_mkdir_p($extra_path);
        }
        
        // Mover archivo principal
        $filename = basename($current_file);
        $new_file = $extra_path . '/' . $filename;
        
        if (WP_DEBUG) {
            error_log('Moving image ' . $image_id . ' from ' . $current_file . ' to ' . $new_file);
        }
        
        if (rename($current_file, $new_file)) {
            // Obtener metadatos para mover miniaturas
            $metadata = wp_get_attachment_metadata($image_id);
            if (!empty($metadata['sizes'])) {
                $current_dir = dirname($current_file);
                foreach ($metadata['sizes'] as $size) {
                    if (isset($size['file'])) {
                        $old_thumb = $current_dir . '/' . $size['file'];
                        $new_thumb = $extra_path . '/' . $size['file'];
                        if (file_exists($old_thumb)) {
                            rename($old_thumb, $new_thumb);
                        }
                    }
                }
            }
            
            // Actualizar metadatos
            update_post_meta($image_id, 'price_extra_temp_listing', '0');
            update_post_meta($image_id, 'price_extra_listing_id', $listing_id);
            update_post_meta($image_id, 'price_extra_temporary', '0');
            update_attached_file($image_id, $new_file);
            
            // Actualizar metadatos del archivo
            if ($metadata) {
                // Actualizar ruta del archivo principal
                if (isset($metadata['file'])) {
                    $metadata['file'] = str_replace(
                        'price-extras/temp/' . $extra_key,
                        'price-extras/' . $listing_id . '/' . $extra_key,
                        $metadata['file']
                    );
                }
                wp_update_attachment_metadata($image_id, $metadata);
            }
            
            if (WP_DEBUG) {
                error_log('Successfully migrated image ' . $image_id);
            }
        } else {
            if (WP_DEBUG) {
                error_log('Failed to move file for image ' . $image_id);
            }
        }
    }
    
    // Limpiar carpeta temporal si está vacía
    if (is_dir($temp_base_path)) {
        $is_empty = true;
        $dir_contents = scandir($temp_base_path);
        foreach ($dir_contents as $item) {
            if ($item != '.' && $item != '..') {
                $is_empty = false;
                break;
            }
        }
        
        if ($is_empty) {
            rmdir($temp_base_path);
            if (WP_DEBUG) {
                error_log('Removed empty temp directory');
            }
        }
    }
    
    if (WP_DEBUG) {
        error_log('=== Finished temp images migration ===');
    }
}

  /**************************************
* FUNCIÓN: Process_price_extras_images
* Maneja el procesamiento de imágenes y carpetas
**************************************/
private function process_price_extras_images($listing_id, $extras) {
    global $wpdb;
 
    /**************************************
     * PASO 1: Validación inicial
     **************************************/
    if (empty($extras) || !is_array($extras)) {
        if (WP_DEBUG) {
            error_log("No extras found for listing {$listing_id}");
        }
        return;
    }
 
    /**************************************
     * PASO 2: Preparación de rutas
     **************************************/
    $upload_dir = wp_upload_dir();
    $base_path = $upload_dir['basedir'] . '/price-extras/' . $listing_id;
 
    /**************************************
     * PASO 3: Procesamiento de cada extra
     **************************************/
    foreach ($extras as $extra) {
        if (empty($extra['name']) || empty($extra['extra_images'])) {
            continue;
        }
 
        $extra_name = sanitize_title($extra['name']);
        $extra_path = $base_path . '/' . $extra_name;
        
        // Convertir extra_images a array de IDs
        $image_ids = is_string($extra['extra_images']) ? 
            explode(',', $extra['extra_images']) : 
            (is_array($extra['extra_images']) ? $extra['extra_images'] : [$extra['extra_images']]);
 
        /**************************************
         * PASO 4: Procesamiento de cada imagen
         **************************************/
        foreach ($image_ids as $image_id) {
            $image_id = intval($image_id);
            
            if (!get_post_meta($image_id, 'price_extra_image', true)) {
                continue;
            }
 
            $current_key = get_post_meta($image_id, 'price_extra_key', true);
            $is_temporary = get_post_meta($image_id, 'price_extra_temporary', true);
            $current_file = get_attached_file($image_id);

             // Añadir estos logs aquí
            if (WP_DEBUG) {
                error_log("Processing image ID: {$image_id}");
                error_log("Current directory: " . dirname($current_file));
                error_log("Target directory: {$extra_path}");
                error_log("Current key: {$current_key}");
                error_log("Extra name: {$extra_name}");
                error_log("Is temporary: " . ($is_temporary ? 'true' : 'false'));
            }
 
            if (!$current_file || !file_exists($current_file)) {
                continue;
            }
 
            $current_dir = dirname($current_file);
 
            /**************************************
             * PASO 5: Manejo de imágenes temporales
             **************************************/
            if ($is_temporary) {
                if (!is_dir($extra_path)) {
                    wp_mkdir_p($extra_path);
                }
 
                $filename = basename($current_file);
                $new_file = $extra_path . '/' . $filename;
 
                if (rename($current_file, $new_file)) {
                    // Mover miniaturas
                    $metadata = wp_get_attachment_metadata($image_id);
                    if (!empty($metadata['sizes'])) {
                        foreach ($metadata['sizes'] as $size) {
                            if (isset($size['file'])) {
                                $old_thumb = $current_dir . '/' . $size['file'];
                                $new_thumb = $extra_path . '/' . $size['file'];
                                if (file_exists($old_thumb)) {
                                    rename($old_thumb, $new_thumb);
                                }
                            }
                        }
                    }
 
                    // Actualizar metadatos
                    update_post_meta($image_id, 'price_extra_temporary', false);
                    update_post_meta($image_id, 'price_extra_key', $extra_name);
                    update_attached_file($image_id, $new_file);
                    wp_update_attachment_metadata($image_id, $metadata);
                }
 
                // Limpiar carpeta temporal si está vacía
                if (is_dir($current_dir) && count(scandir($current_dir)) <= 2) {
                    rmdir($current_dir);
                }
            }
            /**************************************
             * PASO 6: Manejo de carpetas existentes
             **************************************/
            else if ($current_dir !== $extra_path) {
                if (WP_DEBUG) {
                    error_log("Renombrando carpeta de {$current_dir} a {$extra_path}");
                }
             
                // Primero obtener todas las imágenes en la carpeta actual
                $images_in_folder = $wpdb->get_col($wpdb->prepare(
                    "SELECT p.ID 
                     FROM {$wpdb->prefix}posts p
                     INNER JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id
                     INNER JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id
                     WHERE p.post_type = 'attachment'
                     AND pm1.meta_key = 'price_extra_image'
                     AND pm1.meta_value = '1'
                     AND pm2.meta_key = 'price_extra_listing_id'
                     AND pm2.meta_value = %d",
                    $listing_id
                ));
             
                // Renombrar la carpeta
                if (rename($current_dir, $extra_path)) {
                    if (WP_DEBUG) {
                        error_log("Carpeta renombrada correctamente");
                        error_log("Actualizando metadatos para todas las imágenes en la carpeta");
                    }
             
                    // Actualizar metadatos para todas las imágenes
                    foreach ($images_in_folder as $img_id) {
                        $img_file = get_attached_file($img_id);
                        // Verificar si esta imagen estaba en la carpeta anterior
                        if (strpos($img_file, $current_dir) === 0) {
                            // Actualizar la ruta del archivo
                            $new_img_file = str_replace($current_dir, $extra_path, $img_file);
                            update_post_meta($img_id, 'price_extra_key', $extra_name);
                            update_attached_file($img_id, $new_img_file);
             
                            // Actualizar metadata
                            $img_metadata = wp_get_attachment_metadata($img_id);
                            if (!empty($img_metadata)) {
                                if (isset($img_metadata['file'])) {
                                    $img_metadata['file'] = str_replace(
                                        dirname($img_metadata['file']),
                                        'price-extras/' . $listing_id . '/' . $extra_name,
                                        $img_metadata['file']
                                    );
                                }
                                wp_update_attachment_metadata($img_id, $img_metadata);
                            }
             
                            if (WP_DEBUG) {
                                error_log("Actualizados metadatos para imagen ID: {$img_id}");
                                error_log("Nueva ruta: {$new_img_file}");
                            }
                        }
                    }
                }
             }
             
        }
    }
    
    // Recolección mejorada de IDs y carpetas activas después del renombrado
$active_image_ids = [];
$active_folders = [];

// Primero, verificar los extras del formulario actual
foreach ($extras as $extra) {
    if (!empty($extra['name'])) {
        $folder_name = sanitize_title($extra['name']);
        $active_folders[] = $folder_name;
        
        // Procesar imágenes si existen
        if (!empty($extra['extra_images'])) {
            // Normalizar extra_images a array
            $image_ids = [];
            
            if (is_array($extra['extra_images'])) {
                $image_ids = $extra['extra_images'];
            } elseif (is_string($extra['extra_images'])) {
                // Dividir por comas si es necesario
                $image_ids = strpos($extra['extra_images'], ',') !== false ? 
                           explode(',', $extra['extra_images']) : 
                           [$extra['extra_images']];
            }
            
            // Sanitizar y agregar cada ID
            foreach ($image_ids as $id) {
                $clean_id = intval(trim($id));
                if ($clean_id > 0) {
                    $active_image_ids[] = $clean_id;
                }
            }
        }
    }
}

// Segundo, buscar imágenes recientes que podrían no estar en el formulario todavía
$recent_query = $wpdb->prepare(
    "SELECT p.ID 
     FROM {$wpdb->prefix}posts p
     INNER JOIN {$wpdb->prefix}postmeta pm1 ON p.ID = pm1.post_id
     INNER JOIN {$wpdb->prefix}postmeta pm2 ON p.ID = pm2.post_id
     WHERE p.post_type = 'attachment'
     AND pm1.meta_key = 'price_extra_image'
     AND pm1.meta_value = '1'
     AND pm2.meta_key = 'price_extra_listing_id'
     AND pm2.meta_value = %d
     AND p.post_date > %s",
    $listing_id,
    date('Y-m-d H:i:s', strtotime('-10 minutes'))
);

$recent_images = $wpdb->get_col($recent_query);
if (!empty($recent_images)) {
    foreach ($recent_images as $recent_id) {
        $active_image_ids[] = intval($recent_id);
    }
}

// Eliminar duplicados
$active_image_ids = array_unique($active_image_ids);

   if (WP_DEBUG) {
       error_log('IDs activos después del renombrado: ' . print_r($active_image_ids, true));
       error_log('Carpetas activas después del renombrado: ' . print_r($active_folders, true));
   }

        /**************************************
* PASO 7: Proceso de borrado seguro
**************************************/
// Obtener todas las imágenes de extras de este listing
$query = $wpdb->prepare(
    "SELECT DISTINCT p.ID 
    FROM {$wpdb->prefix}posts p
    INNER JOIN {$wpdb->prefix}postmeta pm_extra ON p.ID = pm_extra.post_id
    INNER JOIN {$wpdb->prefix}postmeta pm_listing ON p.ID = pm_listing.post_id
    WHERE p.post_type = 'attachment'
    AND pm_extra.meta_key = 'price_extra_image'
    AND pm_extra.meta_value = '1'
    AND pm_listing.meta_key = 'price_extra_listing_id'
    AND pm_listing.meta_value = %d",
    $listing_id
);

$all_images = $wpdb->get_results($query);

if (WP_DEBUG) {
    error_log('=== Iniciando proceso de borrado seguro ===');
    error_log('Total de imágenes encontradas: ' . count($all_images));
    error_log('IDs activos en hp_price_extras: ' . print_r($active_image_ids, true));
}

// Conservar imágenes recién migradas (desde hace menos de 5 minutos)
$recently_active_ids = [];
foreach ($all_images as $image) {
    $updated_time = get_post_meta($image->ID, '_wp_attachment_metadata_updated', true);
    if (empty($updated_time)) {
        $updated_time = get_post_meta($image->ID, 'price_extra_upload_time', true);
    }
    
    // Si la imagen fue actualizada en los últimos 5 minutos, considerarla activa
    if ($updated_time && (strtotime($updated_time) > strtotime('-5 minutes'))) {
        $recently_active_ids[] = intval($image->ID);
        if (WP_DEBUG) {
            error_log('Imagen reciente considerada activa: ' . $image->ID);
        }
    }
}

// Combinar IDs activos con IDs recientes
$protected_image_ids = array_unique(array_merge($active_image_ids, $recently_active_ids));

// Procesar cada imagen de manera segura
foreach ($all_images as $image) {
    if (!in_array($image->ID, $protected_image_ids)) {
        if (WP_DEBUG) {
            error_log('Marcando imagen como huérfana (no eliminada físicamente): ' . $image->ID);
        }
        
        // No eliminar físicamente, solo marcar como huérfana
        update_post_meta($image->ID, 'price_extra_orphaned', '1');
        update_post_meta($image->ID, 'price_extra_orphaned_time', current_time('mysql'));
        
        // Opcional: Mover a una carpeta de "huérfanos" en lugar de eliminar
        $file_path = get_attached_file($image->ID);
        if ($file_path && file_exists($file_path)) {
            $orphan_dir = $upload_dir['basedir'] . '/price-extras/orphaned/' . $listing_id;
            if (!is_dir($orphan_dir)) {
                wp_mkdir_p($orphan_dir);
            }
            
            $filename = basename($file_path);
            $new_path = $orphan_dir . '/' . $filename;
            
            // Solo registrar información para depuración, no mover todavía
            if (WP_DEBUG) {
                error_log('Archivo huérfano: ' . $file_path);
                error_log('Podría moverse a: ' . $new_path);
            }
        }
    }
}

// No eliminar carpetas automáticamente, solo registrar información
if (is_dir($base_path)) {
    $folders = glob($base_path . '/*', GLOB_ONLYDIR);
    
    foreach ($folders as $folder) {
        $folder_name = basename($folder);
        
        // Si no está en extras activos
        if (!in_array($folder_name, $active_folders)) {
            if (WP_DEBUG) {
                error_log('Carpeta no activa detectada (no eliminada): ' . $folder);
                
                // Verificar si hay archivos dentro
                $files = glob($folder . '/*');
                if (count($files) > 0) {
                    error_log('La carpeta contiene ' . count($files) . ' archivos');
                }
            }
        }
    }
}

if (WP_DEBUG) {
    error_log('=== Proceso de borrado seguro completado ===');
}

 }

 public function handle_price_extras_update($listing_id) {
    // PASO 1: Preparación
    if (is_object($listing_id)) {
        $listing_id = $listing_id->get_id();
    }
 
    if (WP_DEBUG) {
        error_log('=== Starting handle_price_extras_update ===');
        error_log('Listing ID: ' . $listing_id);
        
        // Revisar ambos tipos de metadatos
        $price_extras = get_post_meta($listing_id, 'price_extras', true);
        $hp_price_extras = get_post_meta($listing_id, 'hp_price_extras', true);
        
        error_log('price_extras data:');
        error_log(print_r($price_extras, true));
        error_log('hp_price_extras data:');
        error_log(print_r($hp_price_extras, true));
    }
 
    // PASO 1.5: Intentar migrar imágenes temporales
    $this->migrate_temp_images($listing_id);
 
    // PASO 2: Obtener datos
    $extras = get_post_meta($listing_id, 'hp_price_extras', true);

    if (WP_DEBUG) {
        error_log('hp_price_extras data:');
        error_log(print_r($extras, true));
    }

    if (empty($extras) || !is_array($extras)) {
        if (WP_DEBUG) {
            error_log("No extras found for listing {$listing_id}");
        }
        return;
    }
 
    // PASO 3: Procesar imágenes y carpetas
    $this->process_price_extras_images($listing_id, $extras);
}


public function save_price_extras_images($post_id, $post, $update) {
    /**************************************
     * PASO 1: Verificaciones iniciales
     **************************************/
    if (WP_DEBUG) {
        error_log('=== Starting save_price_extras_images ===');
        error_log('Post ID: ' . $post_id);
    }
 
    if ($post->post_type !== 'hp_listing' || !is_admin()) {
        return;
    }
 
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
 
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
 
    /**************************************
     * PASO 2: Obtención de datos
     **************************************/
    try {
        // SOLUCIÓN: Llamar a migrate_temp_images antes de procesar
        if (WP_DEBUG) {
            error_log('Calling migrate_temp_images from admin save');
        }
        
        // Esta es la línea clave que faltaba
        $this->migrate_temp_images($post_id);
        
        $price_extras_from_post = isset($_POST['hp_price_extras']) ? $_POST['hp_price_extras'] : null;
        $extras = $price_extras_from_post ?: get_post_meta($post_id, 'hp_price_extras', true);
 
        if (WP_DEBUG) {
            error_log('Retrieved hp_price_extras:');
            error_log(print_r($extras, true));
        }
 
        /**************************************
         * PASO 3: Procesamiento
         **************************************/
        $this->process_price_extras_images($post_id, $extras);
 
        /**************************************
         * PASO 4: Limpieza final
         **************************************/
 
    } catch (Exception $e) {
        if (WP_DEBUG) {
            error_log('Error in save_price_extras_images: ' . $e->getMessage());
            error_log($e->getTraceAsString());
        }
    }
}

    /**
     * Actualiza las URLs y metadatos de un attachment después del renombrado de carpeta
     *
     * @param int    $attachment_id ID del attachment
     * @param string $old_folder    Nombre de la carpeta antigua
     * @param string $new_folder    Nombre de la carpeta nueva
     */
    public function update_attachment_urls($attachment_id, $old_folder, $new_folder)
    {
        $listing_id = get_post_meta($attachment_id, 'price_extra_listing_id', true);
        $base_path = 'price-extras/' . $listing_id;

        // 1. Actualizar _wp_attached_file
        $attached_file = get_post_meta($attachment_id, '_wp_attached_file', true);
        if ($attached_file) {
            $new_file = str_replace(
                $base_path . '/' . $old_folder,
                $base_path . '/' . $new_folder,
                $attached_file
            );
            update_post_meta($attachment_id, '_wp_attached_file', $new_file);
        }

        // 2. Actualizar metadata con todas las imágenes generadas
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata)) {
            // Actualizar archivo principal
            if (isset($metadata['file'])) {
                $metadata['file'] = str_replace(
                    $base_path . '/' . $old_folder,
                    $base_path . '/' . $new_folder,
                    $metadata['file']
                );
            }

            // Actualizar todos los tamaños generados
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $data) {
                    if (isset($data['file'])) {
                        $old_path = $base_path . '/' . $old_folder . '/' . $data['file'];
                        $new_path = $base_path . '/' . $new_folder . '/' . $data['file'];
                        $metadata['sizes'][$size]['file'] = basename($new_path);
                    }
                }
            }

            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        // 3. Actualizar la URL en el post (guid)
        $attachment_post = get_post($attachment_id);
        if ($attachment_post) {
            global $wpdb;
            $new_guid = str_replace(
                '/price-extras/' . $listing_id . '/' . $old_folder . '/',
                '/price-extras/' . $listing_id . '/' . $new_folder . '/',
                $attachment_post->guid
            );

            if ($new_guid !== $attachment_post->guid) {
                $wpdb->update(
                    $wpdb->posts,
                    ['guid' => $new_guid],
                    ['ID' => $attachment_id]
                );
            }
        }

        // 4. Actualizar los metadatos específicos de extras
        update_post_meta($attachment_id, 'price_extra_id', $new_folder);

        // 5. Limpiar caché de la imagen
        clean_attachment_cache($attachment_id);
    }

    // Función auxiliar para obtener imágenes temporales
    private function get_temporary_images_for_listing($listing_id)
    {
        return get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'price_extra_listing_id',
                    'value' => $listing_id
                ],
                [
                    'key' => 'price_extra_temporary',
                    'value' => '1'
                ]
            ]
        ]);
    }

    // Función para mover imagen a nueva carpeta
    private function move_image_to_folder($image_id, $listing_id, $old_folder, $new_folder)
    {
        if (WP_DEBUG) {
            error_log("Moving image {$image_id} from {$old_folder} to {$new_folder}");
        }

        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/price-extras/' . $listing_id;

        // Crear carpeta destino si no existe
        $new_path = $base_path . '/' . $new_folder;
        if (!is_dir($new_path)) {
            wp_mkdir_p($new_path);
        }

        // Obtener archivo actual y sus miniaturas
        $current_file = get_attached_file($image_id);
        $metadata = wp_get_attachment_metadata($image_id);

        if (!$current_file || !file_exists($current_file)) {
            if (WP_DEBUG) {
                error_log("File not found for image {$image_id}");
            }
            return;
        }

        // Mover archivo principal
        $filename = basename($current_file);
        $new_file = $new_path . '/' . $filename;

        if (rename($current_file, $new_file)) {
            // Mover cada miniatura
            if (!empty($metadata['sizes'])) {
                $old_dir = dirname($current_file);
                foreach ($metadata['sizes'] as $size => $size_info) {
                    $old_thumb = $old_dir . '/' . $size_info['file'];
                    $new_thumb = $new_path . '/' . $size_info['file'];
                    if (file_exists($old_thumb)) {
                        rename($old_thumb, $new_thumb);
                    }
                }
            }

            // Actualizar metadatos
            update_attached_file($image_id, $new_file);
            $this->update_attachment_metadata($image_id, $old_folder, $new_folder);
            update_post_meta($image_id, 'price_extra_id', $new_folder);
        }
    }


    // Función para limpiar imágenes temporales de un listing específico
    private function cleanup_temporary_images_for_listing($listing_id)
    {
        $temp_images = $this->get_temporary_images_for_listing($listing_id);

        foreach ($temp_images as $image) {
            if (WP_DEBUG) {
                error_log('Cleaning up temporary image: ' . $image->ID);
            }
            wp_delete_attachment($image->ID, true);
        }
    }

    // Función para actualizar metadatos del attachment
    private function update_attachment_metadata($attachment_id, $old_folder, $new_folder)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata)) {
            // Actualizar ruta del archivo principal
            if (isset($metadata['file'])) {
                $metadata['file'] = str_replace(
                    '/price-extras/' . $old_folder . '/',
                    '/price-extras/' . $new_folder . '/',
                    $metadata['file']
                );
            }

            // Actualizar rutas de miniaturas
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $data) {
                    $metadata['sizes'][$size]['file'] = basename($data['file']);
                }
            }

            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }


    /**
     * Modify form attributes to handle file uploads.
     *
     * @param array $form Form configuration.
     * @return array
     */
    public function modify_forms($forms, $form_name)
    {
        if (in_array($form_name, ['listing_submit', 'listing_update'])) {
            if (!isset($forms[$form_name]['attributes'])) {
                $forms[$form_name]['attributes'] = [];
            }

            $forms[$form_name]['attributes']['enctype'] = 'multipart/form-data';
            $forms[$form_name]['attributes']['data-component'] = 'file-upload';

            if (WP_DEBUG) {
                error_log('=== Modifying Form: ' . $form_name . ' ===');
                error_log(print_r($forms[$form_name], true));
            }
        }

        return $forms;
    }

    /**
     * Filter listing images to exclude price extra images.
     *
     * @param array $images Array of image IDs.
     * @param object $listing Listing object.
     * @return array
     */
    public function filter_listing_images($images, $listing)
    {
        if (empty($images)) {
            return $images;
        }

        if (WP_DEBUG) {
            error_log('=== Filtering Listing Images ===');
            error_log('Original images: ' . print_r($images, true));
        }

        // Asegurarse de que tenemos un array
        if (!is_array($images)) {
            $images = array($images);
        }

        // Filtrar las imágenes
        $filtered_images = array_filter($images, function ($image_id) {
            return !get_post_meta($image_id, '_price_extra_parent', true);
        });

        if (WP_DEBUG) {
            error_log('Filtered images: ' . print_r($filtered_images, true));
        }

        return array_values($filtered_images);
    }

    // Agregar este método a la clase Price_Extras_Description
    public function filter_attachment_fields_to_edit($form_fields, $post)
    {
        if (get_post_meta($post->ID, 'price_extra_image', true)) {
            $form_fields = array(); // Vaciar los campos si es una imagen de extra
        }
        return $form_fields;
    }

    // Añadir esta nueva función a la clase
    public function filter_attachment_metadata($data, $attachment_id)
    {
        if (get_post_meta($attachment_id, 'price_extra_image', true)) {
            if (WP_DEBUG) {
                error_log('=== Filtering Attachment Metadata ===');
                error_log('Attachment ID: ' . $attachment_id);
                error_log('Original metadata: ' . print_r($data, true));
            }

            // Si es una imagen de extra, excluirla de las galerías de HivePress
            if (isset($data['hp_listing_images'])) {
                unset($data['hp_listing_images']);
            }

            // Mantener solo nuestros tamaños
            if (isset($data['sizes'])) {
                $allowed_sizes = ['price-extra-thumbnail', 'price-extra-card', 'price-extra-popup'];
                foreach ($data['sizes'] as $size => $size_data) {
                    if (!in_array($size, $allowed_sizes)) {
                        unset($data['sizes'][$size]);
                    }
                }
            }

            if (WP_DEBUG) {
                error_log('Filtered metadata: ' . print_r($data, true));
            }
        }
        return $data;
    }

    public function process_new_attachment($attachment_id)
    {
        if (WP_DEBUG) {
            error_log("=== Processing New Attachment ===");
            error_log("Attachment ID: $attachment_id");
        }

        $is_extra_image = get_post_meta($attachment_id, 'price_extra_image', true);
        if ($is_extra_image) {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_parent' => 0  // Desvincula del listing principal
            ));

            // Marca especial para HivePress
            update_post_meta($attachment_id, '_hp_attachment_type', 'price_extra');
            update_post_meta($attachment_id, '_skip_hivepress_processing', true);
        }
    }

    public function process_edited_attachment($attachment_id)
    {

        $is_extra_image = get_post_meta($attachment_id, 'price_extra_image', true);
        if ($is_extra_image) {
            update_post_meta($attachment_id, '_hp_attachment_type', 'price_extra');
            update_post_meta($attachment_id, '_skip_hivepress_processing', true);
        }
    }

    public function filter_attachment_urls($urls, $attachment)
    {
        $attachment_id = is_object($attachment) ? $attachment->get_id() : $attachment;

        if (get_post_meta($attachment_id, 'price_extra_image', true)) {
            return [];
        }
        return $urls;
    }

    public function filter_listing_attachments($attachments, $listing)
    {
        if (empty($attachments)) {
            return $attachments;
        }


        $filtered = array_filter($attachments, function ($attachment) {
            $attachment_id = is_object($attachment) ? $attachment->get_id() : $attachment;
            return !get_post_meta($attachment_id, 'price_extra_image', true);
        });

        return $filtered;
    }

    /**
     * Configura los manejadores para el admin
     */
    public function setup_admin_handlers()
    {

        // Asegurar que el formulario pueda manejar archivos
        add_action('post_edit_form_tag', function () {
            echo ' enctype="multipart/form-data"';
        });

        // Modificar el nombre del campo en el admin
        add_filter('hivepress/v1/models/listing/attributes', function ($attributes) {
            if (isset($attributes['price_extras'])) {
                $attributes['price_extras']['edit_field']['name'] = 'hp_price_extras';
            }
            return $attributes;
        });
    }

    /**
     * Encola scripts necesarios para el admin
     */
    public function enqueue_admin_scripts($hook)
    {
        global $post;

        if ($hook == 'post.php' && $post->post_type == 'hp_listing') {
            wp_enqueue_script(
                'hpped-admin',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
                ['jquery'],
                '1.0.0',
                true
            );
        }
    }

    /**
     * Filtra los tamaños de imagen para los extras
     */
    public function filter_image_sizes($sizes, $metadata)
    {
        // El ID del attachment viene en los metadatos o podemos obtenerlo del contexto
        $attachment_id = null;

        // Intentar obtener el ID del attachment desde el backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($backtrace as $trace) {
            if (isset($trace['args'][0]) && is_numeric($trace['args'][0])) {
                $attachment_id = $trace['args'][0];
                break;
            }
        }

        if (!$attachment_id) {
            return $sizes;
        }

        if (get_post_meta($attachment_id, 'price_extra_image', true)) {
            // Solo mantener nuestros tamaños personalizados
            $our_sizes = array(
                'price-extra-thumbnail' => $sizes['price-extra-thumbnail'] ?? null,
                'price-extra-card' => $sizes['price-extra-card'] ?? null,
                'price-extra-popup' => $sizes['price-extra-popup'] ?? null
            );

            // Filtrar tamaños nulos
            $our_sizes = array_filter($our_sizes);

            return $our_sizes;
        }

        return $sizes;
    }

    /**
     * Filtra los nombres de tamaños de imagen
     */
    public function filter_image_size_names($sizes)
    {
        $attachment_id = null;

        // Obtener el ID del attachment desde el backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($backtrace as $trace) {
            if (isset($trace['args'][0]) && is_numeric($trace['args'][0])) {
                $attachment_id = $trace['args'][0];
                break;
            }
        }

        if ($attachment_id && get_post_meta($attachment_id, 'price_extra_image', true)) {

            // Solo retornar nuestros tamaños
            $our_sizes = ['price-extra-thumbnail', 'price-extra-card', 'price-extra-popup'];

            return $our_sizes;
        }

        return $sizes;
    }

    public function restrict_image_sizes($sizes, $metadata)
    {
        $attachment_id = null;

        // Obtener ID desde backtrace
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($backtrace as $trace) {
            if (isset($trace['args'][0]) && is_numeric($trace['args'][0])) {
                $attachment_id = $trace['args'][0];
                break;
            }
        }

        if (!$attachment_id) {
            return $sizes;
        }

        if (WP_DEBUG) {
            error_log('=== Restricting Image Sizes ===');
            error_log('Attachment ID: ' . $attachment_id);
        }

        // Si es nuestra imagen, solo permitir nuestros tamaños
        if (get_post_meta($attachment_id, 'price_extra_image', true)) {
            $our_sizes = [];

            // Definir nuestros tamaños con sus configuraciones
            if (isset($sizes['price-extra-thumbnail'])) {
                $our_sizes['price-extra-thumbnail'] = $sizes['price-extra-thumbnail'];
            } else {
                $our_sizes['price-extra-thumbnail'] = [
                    'width' => 150,
                    'height' => 150,
                    'crop' => true
                ];
            }

            if (isset($sizes['price-extra-card'])) {
                $our_sizes['price-extra-card'] = $sizes['price-extra-card'];
            } else {
                $our_sizes['price-extra-card'] = [
                    'width' => 350,
                    'height' => 250,
                    'crop' => true
                ];
            }

            if (isset($sizes['price-extra-popup'])) {
                $our_sizes['price-extra-popup'] = $sizes['price-extra-popup'];
            } else {
                $our_sizes['price-extra-popup'] = [
                    'width' => 800,
                    'height' => 400,
                    'crop' => true
                ];
            }

            if (WP_DEBUG) {
                error_log('Restricted sizes: ' . print_r($our_sizes, true));
            }

            return $our_sizes;
        }

        return $sizes;
    }

    private function is_valid_extra_data($extra)
    {
        return !empty($extra['name']) &&
            (
                (!empty($extra['extra_images']) &&
                    (is_array($extra['extra_images']) || is_numeric($extra['extra_images'])))
                ||
                (isset($extra['price']) && is_numeric($extra['price']))
            );
    }

    /**
 * Limpia imágenes huérfanas después de un período de seguridad
 */
public function cleanup_orphaned_images() {
    global $wpdb;
    
    // Obtener todas las imágenes marcadas como huérfanas hace más de 24 horas
    $query = $wpdb->prepare(
        "SELECT p.ID 
         FROM {$wpdb->prefix}posts p
         INNER JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
         WHERE p.post_type = 'attachment'
         AND pm.meta_key = 'price_extra_orphaned'
         AND pm.meta_value = '1'
         AND p.post_modified < %s",
        date('Y-m-d H:i:s', strtotime('-24 hours'))
    );
    
    $orphaned_images = $wpdb->get_col($query);
    
    
    foreach ($orphaned_images as $image_id) {
        // Verificar una última vez si la imagen todavía está marcada como huérfana
        if (get_post_meta($image_id, 'price_extra_orphaned', true) === '1') {
            // Ahora sí podemos eliminar la imagen con seguridad
            wp_delete_attachment($image_id, true);
            
        }
    }

}

}