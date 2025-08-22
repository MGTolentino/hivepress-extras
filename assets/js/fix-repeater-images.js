(function ($) {
    'use strict';
    
    // Parchear globalmente el método String.match para evitar errores
    $(document).ready(function() {
        // Interceptar errores globalmente
        window.addEventListener('error', function(e) {
            if (e.message && e.message.includes("Cannot read properties of undefined (reading 'match')")) {
                console.log('Error de match interceptado globalmente por hivepress-extras');
                e.preventDefault();
                return false;
            }
        });
        
        // Parchear String.prototype.match solo para casos undefined
        var originalMatch = String.prototype.match;
        String.prototype.match = function(regexp) {
            // Si this es undefined, devolver null en lugar de error
            if (this == null || this === undefined) {
                console.log('Match interceptado en valor undefined/null');
                return null;
            }
            return originalMatch.call(this, regexp);
        };
    });

    // Variable para evitar duplicación al procesar eventos
    var processingClick = false;

    function fixRepeaterImageField() {
        // Remover TODOS los eventos del repeater
        $(document).off('click', '[data-component="repeater"] [data-add]');
        
        // Añadir nuestro manejador personalizado con la máxima prioridad
        $(document).on('click.customRepeater', '[data-component="repeater"] [data-add]', function (e) {
            e.stopImmediatePropagation();
            // Evitar procesamiento duplicado
            if (processingClick) return;
            processingClick = true;
            
            // Prevenir el comportamiento predeterminado y propagación
            e.preventDefault();
            e.stopPropagation();
            
            var $repeater = $(this).closest('[data-component="repeater"]');
            var $tbody = $repeater.find('tbody');
            var $firstRow = $repeater.find('tr:first');

            if (!$firstRow.length) {
                processingClick = false;
                return;
            }

            // Crear una versión limpia del primer elemento
            var $newRow = $firstRow.clone();
            var randomId = Math.random().toString(36).slice(2);

            if (randomId) {
                try {
                    // Primero limpiar los elementos de select2 para evitar duplicados
                    $newRow.find('.select2-container').remove();
                    
                    // Limpiar los atributos de select2 de los selects
                    $newRow.find('select[data-select2-id]').each(function() {
                        var $select = $(this);
                        
                        // Quitar todos los atributos relacionados con select2
                        var attrsToRemove = [];
                        $.each(this.attributes, function(i, attr) {
                            if (attr.name.indexOf('data-select2') === 0) {
                                attrsToRemove.push(attr.name);
                            }
                        });
                        
                        // Eliminar los atributos marcados
                        $.each(attrsToRemove, function(i, attr) {
                            $select.removeAttr(attr);
                        });
                        
                        // Restablecer el estado del select
                        $select.removeClass('select2-hidden-accessible');
                    });
                    
                    // Limpiar todos los campos incluyendo los de imágenes
                    $newRow.find(':input').each(function () {
                        var $input = $(this);
                        var name = $input.attr('name');

                        if (typeof name !== 'undefined' && name !== false) {
                            var matches = name && typeof name === 'string' ? name.match(/\[([^\]]+)\]/) : null;

                            if (matches) {
                                $input.attr('name', name.replace(matches[1], randomId));
                            }

                            if ($input.attr('type') === 'checkbox') {
                                var newId = 'a' + Math.random().toString(36).slice(2);
                                $input.attr('id', newId);
                                $input.closest('label').attr('for', newId);
                            } else {
                                $input.val('');
                            }
                            
                            // Actualizar IDs para campos de selección
                            if ($input.is('select')) {
                                var oldId = $input.attr('id');
                                if (oldId) {
                                    var newId = oldId.replace(matches[1], randomId);
                                    $input.attr('id', newId);
                                }
                            }
                        }
                    });

                    // Limpiar campos de imágenes
                    $newRow.find('.hp-field--price-extras-upload, .hp-field--multiple-file').each(function () {
                        var $field = $(this);

                        $field.find('.hp-field__previews').empty();
                        $field.find('input[type="hidden"].hp-field__value').val('');
                        $field.find('.hp-field__counter').text('(0/' + $field.find('.hp-field__file').data('max-files') + ')');
                        $field.find('.hp-field__upload-button').removeClass('disabled');
                    });

                    // Añadir la nueva fila al DOM antes de inicializar
                    $newRow.appendTo($tbody);

                    // Inicializar select2 correctamente, antes de inicializar UI general
                    $newRow.find('select[data-component="select"]').each(function() {
                        if (typeof $.fn.select2 === 'function') {
                            $(this).select2({
                                width: '100%',
                                minimumResultsForSearch: 20,
                                dropdownAutoWidth: false
                            });
                        }
                    });

                    // Inicializar UI en la nueva fila
                    if (typeof hivepress !== 'undefined' && hivepress.initUI) {
                        hivepress.initUI($newRow);
                    }

                } catch (error) {
                    console.error('Error al crear nueva fila:', error);
                } finally {
                    processingClick = false;
                }
                
                return false;
            }
            
            processingClick = false;
        });
    }
    
    // Función para desactivar el manejador original de HivePress
    function disableOriginalHandler() {
        // Observar cambios en el DOM para detectar cuando se inicializa el repeater
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes && mutation.addedNodes.length) {
                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                        var node = mutation.addedNodes[i];
                        if (node.nodeType === 1 && $(node).find('[data-component="repeater"]').length) {
                            fixRepeaterImageField();
                        }
                    }
                }
            });
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
        
        // También agregar un parche para evitar la ejecución del manejador original
        var originalAddEventListener = EventTarget.prototype.addEventListener;
        EventTarget.prototype.addEventListener = function(type, listener, options) {
            if (type === 'click' && 
                this.matches && 
                this.matches('[data-component="repeater"] [data-add]')) {
                // No registrar este evento
                return;
            }
            originalAddEventListener.call(this, type, listener, options);
        };
    }

    $(document).ready(function () {
        // Intentar desactivar el manejador original
        try {
            disableOriginalHandler();
        } catch (e) {
            console.log('No se pudo desactivar el manejador original');
        }
        
        // Aplicar nuestro manejador personalizado
        fixRepeaterImageField();

        // Reinicializar cuando HivePress actualice el DOM
        $(document).on('hivepress:init', function (event, container) {
            if ($(container).find('[data-component="repeater"]').length) {
                fixRepeaterImageField();
            }
        });
    });

})(jQuery);