(function ($) {
    'use strict';

    function fixRepeaterImageField() {
        // Sobrescribir el comportamiento del botón de añadir extras
        $(document).on('click', '[data-component="repeater"] [data-add]', function (e) {
            var $repeater = $(this).closest('[data-component="repeater"]');
            var $tbody = $repeater.find('tbody');
            var $firstRow = $repeater.find('tr:first');

            if (!$firstRow.length) return;

            // Crear una versión limpia del primer elemento
            var $newRow = $firstRow.clone();
            var randomId = Math.random().toString(36).slice(2);

            if (randomId) {
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
                        var matches = name.match(/\[([^\]]+)\]/);

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

                // Inicializar UI en la nueva fila (incluyendo los select2)
                if (typeof hivepress !== 'undefined' && hivepress.initUI) {
                    hivepress.initUI($newRow);
                }

                // Asegurarse de que los select2 se inicialicen correctamente
                $newRow.find('select[data-component="select"]').each(function() {
                    var $select = $(this);
                    
                    // Si select2 ya está inicializado en este elemento, destruirlo y reinicializarlo
                    if ($select.data('select2')) {
                        $select.select2('destroy');
                    }
                    
                    if (typeof $.fn.select2 === 'function') {
                        // Opciones básicas para select2
                        var options = {
                            width: '100%',
                            minimumResultsForSearch: 20,
                            dropdownAutoWidth: false
                        };
                        
                        // Inicializar select2
                        $select.select2(options);
                    }
                });

                e.stopPropagation();
                return false;
            }
        });
    }

    $(document).ready(function () {
        fixRepeaterImageField();

        $(document).on('hivepress:init', function (event, container) {
            if ($(container).find('[data-component="repeater"]').length) {
                fixRepeaterImageField();
            }
        });
    });

})(jQuery);