<?php
namespace HivePress\Fields;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Campo para múltiples archivos dentro de un repeater
 */
class Multiple_File extends File {
    /**
     * Class constructor.
     *
     * @param array $args Field arguments.
     */
    public function __construct($args = []) {
        $args = hp\merge_arrays(
            [
                'multiple' => true,
                'max_files' => 5,
                'formats' => ['jpg', 'jpeg', 'png'],
                'attributes' => [
                    'accept' => '.jpg,.jpeg,.png',
                    'data-component' => 'price-extras-upload',
                    'data-max-files' => 5,
                    'multiple' => 'multiple',
                ],
            ],
            $args
        );

        parent::__construct($args);
    }

    /**
     * Sanitiza el valor del campo.
     */
    protected function sanitize() {
        // Convertir a array si es string
        if (is_string($this->value) && !empty($this->value)) {
            $this->value = array_filter(
                explode(',', $this->value),
                'strlen'
            );
        }

        // Asegurar que es array
        if (!is_array($this->value)) {
            $this->value = empty($this->value) ? [] : [$this->value];
        }

        // Limpiar y validar cada ID
        $this->value = array_filter(array_map('absint', $this->value));
    }

    /**
 * Valida el valor del campo.
 *
 * @return bool
 */
public function validate() {
    if (is_null($this->value)) {
        $this->value = [];
    }

    // Verificar que cada ID es realmente una imagen válida
    $valid_ids = [];
    foreach ($this->value as $attachment_id) {
        if (wp_attachment_is_image($attachment_id)) {
            // Verificar que el archivo realmente existe
            $file_path = get_attached_file($attachment_id);
            if ($file_path && file_exists($file_path)) {
                $valid_ids[] = $attachment_id;
            } else {
                if (WP_DEBUG) {
                    error_log('Image file not found for attachment ID: ' . $attachment_id);
                }
                // No añadir mensaje de error, solo ignorar este ID
            }
        } else {
            if (WP_DEBUG) {
                error_log('Invalid image attachment ID: ' . $attachment_id);
            }
        }
    }
    
    // Actualizar el valor con solo los IDs válidos
    $this->value = $valid_ids;

    // Validar número máximo de archivos
   if (count($this->value) > $this->attributes['data-max-files']) {
    $this->add_errors(
        sprintf(
            esc_html__('Maximum %s files allowed.', 'hivepress-price-extras-description'),
            $this->attributes['data-max-files']
        )
    );
    return false;
}

return empty($this->errors);
}

    /**
     * Renderiza el campo HTML.
     *
     * @return string
     */

    public function render() {
        $field_id = uniqid('hp-upload-');
        
        // Inicio del contenedor principal
        $output = '<div class="hp-field hp-field--multiple-file">';
        
        // Añadir contenedor de estado de carga
         $output .= '<div class="hp-field__upload-status"></div>';

        // Campo oculto para IDs
        $output .= sprintf(
            '<input type="hidden" name="%s" class="hp-field__value" value="%s" />',
            esc_attr($this->name),
            esc_attr(is_array($this->value) ? implode(',', $this->value) : '')
        );
        
        // Contenedor de previsualizaciones
        $output .= '<div class="hp-field__previews">';
        
        // Cargar imágenes existentes
        if (!empty($this->value)) {
            $image_ids = is_array($this->value) ? $this->value : explode(',', $this->value);
            foreach ($image_ids as $image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                if ($image_url) {
                    $output .= $this->render_preview($image_id, $image_url);
                }
            }
        }
        
        $output .= '</div>'; // Cierra previews
        
        // Mensajes de error
        $output .= '<div class="hp-field__messages"></div>';
        
        // Contenedor para botón y texto de límite
        $output .= '<div class="hp-field__upload-wrapper">';
        
        // Campo de archivo y botón
        $output .= sprintf(
            '<label for="%s" class="hp-field__upload-button">%s</label>',
            esc_attr($field_id),
            esc_html__('Select Images', 'hivepress-price-extras-description')
        );
        
        // Texto de límite y contador
        $current_count = !empty($image_ids) ? count($image_ids) : 0;
        $output .= sprintf(
            '<span class="hp-field__limit-text">' . esc_html__('*Maximum %d images', 'hivepress-price-extras-description') . ' <span class="hp-field__counter">(%d/%d)</span></span>',
            $this->attributes['data-max-files'],
            $current_count,
            $this->attributes['data-max-files']
        );
        
        // Input file oculto
        $output .= sprintf(
            '<input type="file" id="%s" name="%s[]" class="hp-field__file" %s />',
            esc_attr($field_id),
            esc_attr($this->name),
            hp\html_attributes($this->attributes)
        );
        
        $output .= '</div>'; // Cierra hp-field__upload-wrapper
        $output .= '</div>'; // Cierra hp-field--multiple-file
        
        return $output;
    }

        /**
     * Renderiza la previsualización de una imagen.
     *
     * @param int $attachment_id ID del attachment
     * @param string $url URL de la miniatura
     * @return string
     */
    protected function render_preview($attachment_id, $url) {
        return sprintf(
            '<div class="hp-field__preview" data-id="%1$s">
                <img src="%2$s" alt="" />
                <div class="hp-field__preview-actions">
                    <button type="button" class="hp-field__preview-button hp-field__preview-button--delete">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>',
            esc_attr($attachment_id),
            esc_url($url)
        );
    }
}