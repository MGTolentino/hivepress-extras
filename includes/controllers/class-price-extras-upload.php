<?php

namespace HPPriceExtrasDescription\Controllers;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
* Controlador para la subida de archivos de extras
*/
class Price_Extras_Upload {

   /**
    * Class constructor.
    */
   public function __construct() {
       error_log('=== Price Extras Upload Controller Constructor ===');
       add_action('rest_api_init', [$this, 'register_routes']);
   }

   /**
    * Registrar rutas de API
    */
   public function register_routes() {
       error_log('=== Registering REST routes ===');

       register_rest_route('price-extras/v1', '/upload', [
           'methods' => 'POST',
           'callback' => [$this, 'handle_upload'],
           'permission_callback' => [$this, 'check_upload_permission'],
       ]);

       // Añadir nueva ruta para eliminación
        register_rest_route('price-extras/v1', '/delete-image', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_image_delete'],
            'permission_callback' => [$this, 'check_upload_permission'],
        ]);

       error_log('REST routes registered successfully');
   }

   /**
    * Verificar permisos
    */
   public function check_upload_permission() {
       error_log('Checking upload permission');
       return current_user_can('upload_files');
   }

   /**
 * Manejar subida de archivos
 */
public function handle_upload($request) {
    error_log('=== Starting handle_upload ===');
    
    // Asegurar que los filtros se apliquen desde el inicio
    $this->remove_default_image_sizes();
    $this->set_custom_image_sizes();
    
    $files = $request->get_file_params();
    if (empty($files['file'])) {
        error_log('No file found in request');
        return new \WP_Error('no_file', __('No file was uploaded', 'hivepress-price-extras-description'), ['status' => 400]);
    }

    $listingId = $request->get_param('listing_id');
    $extraId = $request->get_param('extra_id');
    $isTempListing = false;

    if (!$extraId) {
        return new \WP_Error('missing_params', __('Missing required parameters', 'hivepress-price-extras-description'), ['status' => 400]);
    }

    // Si listingId es null o 'new', usamos un directorio temporal
    if (!$listingId || $listingId === 'null' || $listingId === 'new' || $listingId === '0') {
        $isTempListing = true;
        $listingId = 'temp';
        error_log('Using temporary listing folder: ' . $listingId);
    }

    try {
        error_log('Setting up image handling for upload');
        
        // Crear estructura de directorios
        $upload_dir = wp_upload_dir();
        $relative_path = "/price-extras/{$listingId}/{$extraId}";    
        $target_dir = $upload_dir['basedir'] . $relative_path;

        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        // Configurar directorio de subida
        add_filter('upload_dir', function($dirs) use ($relative_path) {
            $dirs['subdir'] = $relative_path;
            $dirs['path'] = $dirs['basedir'] . $relative_path;
            $dirs['url'] = $dirs['baseurl'] . $relative_path;
            return $dirs;
        });

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $file = $files['file'];
        $upload_overrides = ['test_form' => false];
        
        $uploaded_file = wp_handle_upload($file, $upload_overrides);

        if (isset($uploaded_file['error'])) {
            error_log('Upload error: ' . $uploaded_file['error']);
            return new \WP_Error('upload_error', $uploaded_file['error'], ['status' => 400]);
        }

        $attachment = [
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded_file['file'])),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);

        if (is_wp_error($attachment_id)) {
            error_log('Attachment creation error: ' . $attachment_id->get_error_message());
            return $attachment_id;
        }

        if (!is_wp_error($attachment_id)) {
            update_post_meta($attachment_id, 'price_extra_image', true);
            // Si es temporal, marcar para futura migración
            if ($isTempListing) {
                update_post_meta($attachment_id, 'price_extra_temp_listing', true);
            } else {
                update_post_meta($attachment_id, 'price_extra_listing_id', $listingId);
            }
            update_post_meta($attachment_id, 'price_extra_key', $extraId);
            update_post_meta($attachment_id, 'price_extra_temporary', true);
            update_post_meta($attachment_id, 'price_extra_upload_time', current_time('mysql'));
        }

        // Usar el método público
        $priceExtras = \HPPriceExtrasDescription\Components\Price_Extras_Description::init();
        $priceExtras->handle_image_sizes($attachment_id);

        $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        error_log('Generated metadata: ' . print_r($attachment_data, true));
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return rest_ensure_response([
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'thumbnail' => wp_get_attachment_image_url($attachment_id, 'price-extra-thumbnail'),
            'name' => get_the_title($attachment_id)
        ]);

    } finally {
        // Asegurar que los filtros se limpien incluso si hay error
        $this->cleanup_image_filters();
        remove_all_filters('upload_dir');
    }
}

   /**
    * Remover tamaños de imagen por defecto
    */
   private function remove_default_image_sizes() {
       error_log('Removing default image sizes');
       
       // Remover todos los tamaños intermedios
       add_filter('intermediate_image_sizes', function($sizes) {
           return ['price-extra-thumbnail', 'price-extra-card', 'price-extra-popup'];
       }, 999);

       // Deshabilitar escalado grande
       add_filter('big_image_size_threshold', '__return_false');
   }

   /**
    * Configurar tamaños personalizados
    */
   private function set_custom_image_sizes() {
       error_log('Setting up custom image sizes');
       
       add_filter('intermediate_image_sizes_advanced', function($sizes) {
           error_log('Applying custom image sizes filter');
           return [
               'price-extra-thumbnail' => [
                   'width' => 150,
                   'height' => 150,
                   'crop' => true,
               ],
               'price-extra-card' => [
                   'width' => 350,
                   'height' => 250,
                   'crop' => true,
               ],
               'price-extra-popup' => [
                   'width' => 800,
                   'height' => 400,
                   'crop' => true,
               ],
           ];
       }, 999);
   }

   /**
    * Limpiar filtros
    */
   private function cleanup_image_filters() {
       error_log('Cleaning up image filters');
       remove_all_filters('intermediate_image_sizes');
       remove_all_filters('intermediate_image_sizes_advanced');
       remove_all_filters('big_image_size_threshold');
   }

    public function handle_image_delete($request) {
        $image_id = $request->get_param('image_id');
        
        if (!$image_id) {
            return new WP_Error('no_image_id', 'No image ID provided', ['status' => 400]);
        }

        if (WP_DEBUG) {
            error_log('=== Deleting image ID: ' . $image_id . ' ===');
        }

        // Verificar que la imagen existe y es una imagen de extra
        $is_extra_image = get_post_meta($image_id, 'price_extra_image', true);
        if (!$is_extra_image) {
            return new WP_Error('not_extra_image', 'Not a valid extra image', ['status' => 400]);
        }

        // Obtener información de la imagen antes de eliminarla
        $file_path = get_attached_file($image_id);
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'], '', $file_path);

        if (WP_DEBUG) {
            error_log('File path: ' . $file_path);
            error_log('Relative path: ' . $relative_path);
        }

        // Eliminar la imagen
        $deleted = wp_delete_attachment($image_id, true);

        if ($deleted) {
            // Verificar si la carpeta está vacía y eliminarla si es necesario
            $directory = dirname($file_path);
            if (is_dir($directory) && count(scandir($directory)) <= 2) { // . y ..
                @rmdir($directory);
                if (WP_DEBUG) {
                    error_log('Empty directory removed: ' . $directory);
                }
            }

            return [
                'success' => true,
                'message' => 'Image deleted successfully'
            ];
        }

        return new WP_Error('delete_failed', 'Failed to delete image', ['status' => 500]);
    }
}