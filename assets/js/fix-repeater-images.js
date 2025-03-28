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
                    }
                });

                $newRow.find('.hp-field--price-extras-upload, .hp-field--multiple-file').each(function () {
                    var $field = $(this);

                    $field.find('.hp-field__previews').empty();

                    $field.find('input[type="hidden"].hp-field__value').val('');

                    $field.find('.hp-field__counter').text('(0/' + $field.find('.hp-field__file').data('max-files') + ')');

                    $field.find('.hp-field__upload-button').removeClass('disabled');
                });

                $newRow.appendTo($tbody);

                // Inicializar UI en la nueva fila
                if (typeof hivepress !== 'undefined' && hivepress.initUI) {
                    hivepress.initUI($newRow);
                }

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