<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Carga los archivos de traducción del plugin
 */
function hpped_load_textdomain() {
    load_plugin_textdomain(
        'hivepress-price-extras-description',
        false,
        dirname(plugin_basename(__FILE__)) . '/../languages/'
    );
}
add_action('plugins_loaded', 'hpped_load_textdomain');

/**
 * Contiene todas las cadenas traducibles para JavaScript
 */
function hpped_get_js_translations() {
    return [
        // Mensajes de error
        'uploadError' => __('Error uploading image', 'hivepress-price-extras-description'),
        'maxFilesError' => __('Maximum files allowed:', 'hivepress-price-extras-description'),
        'invalidType' => __('Invalid file type', 'hivepress-price-extras-description'),
        'maxImagesAllowed' => __('Maximum %s images allowed', 'hivepress-price-extras-description'),
        'selectMoreImages' => __('You can only select %s more image(s)', 'hivepress-price-extras-description'),
        'uploadingImage' => __('Uploading image...', 'hivepress-price-extras-description'),
        'fileUploadError' => __('Error uploading file:', 'hivepress-price-extras-description'),
        'imageDeleteError' => __('Error deleting image:', 'hivepress-price-extras-description'),
        'generalDeleteError' => __('Error deleting image', 'hivepress-price-extras-description'),
        'requiredExtra' => __('Required extra', 'hivepress-price-extras-description'),
        'requiredExtraCantRemove' => __('This is a required extra and cannot be removed.', 'hivepress-price-extras-description'),
        'noExtraFound' => __('Extra not found in booking form:', 'hivepress-price-extras-description'),
        
        // Etiquetas y botones
        'addToBooking' => __('Add to booking', 'hivepress-price-extras-description'),
        'removeFromBooking' => __('Remove from booking', 'hivepress-price-extras-description'),
        'viewMore' => __('View more', 'hivepress-price-extras-description'),
        'selectImages' => __('Select Images', 'hivepress-price-extras-description'),
        'maxImagesLabel' => __('*Maximum %d images', 'hivepress-price-extras-description'),
        
        // Popups
        'detailedInfo' => __('Detailed extra information', 'hivepress-price-extras-description'),
        
        // No hay extras
        'noExtrasDescription' => __('No price extras with description for this listing.', 'hivepress-price-extras-description'),
        'listingNotFound' => __('Listing not found.', 'hivepress-price-extras-description'),
        
        // Tipos de extras
        'perPlacePerDay' => __('per place per day', 'hivepress-price-extras-description'),
        'perPlace' => __('per place', 'hivepress-price-extras-description'),
        'perDay' => __('per day', 'hivepress-price-extras-description'),
        'perBooking' => __('per booking', 'hivepress-price-extras-description'),
        'variableQuantity' => __('Variable quantity', 'hivepress-price-extras-description'),
    ];
}