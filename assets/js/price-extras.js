(function ($) {
    'use strict';

    function sanitizeId(name) {
        // Asegurar que name sea string y tenga valor
        if (!name || typeof name !== 'string') {
            console.warn('Invalid name provided to sanitizeId:', name);
            return 'default-id';
        }

        return name.toLowerCase()
            .replace(/[^a-z0-9]/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }

    function syncExtraButtons() {

        $('.hp-price-extra__reserve').each(function () {
            var $button = $(this);
            var extraName = $button.data('extra-name') || '';

            if (!extraName) {
                extraName = $button.closest('.hp-price-extra').find('.hp-price-extra__name').text().trim();
            }

            var buttonUpdated = false;
            var isRequired = false;

            // Verificar inputs checkbox
            $('input[name="_extras[]"]').each(function () {
                var $input = $(this);
                var inputExtraName = '';

                // Obtener nombre del extra de varias formas
                var $label = $input.closest('label').find('.bv-extra-name');
                if ($label.length) {
                    inputExtraName = $label.text().trim();
                } else {
                    inputExtraName = $input.siblings('.bv-extra-name').text().trim();
                    if (!inputExtraName) {
                        inputExtraName = $input.data('name') || '';
                    }
                }

                // Limpiar el nombre para comparación
                inputExtraName = inputExtraName.toLowerCase().split('(')[0].trim();
                var compareExtraName = extraName.toString().toLowerCase().trim();


                if (compareExtraName === inputExtraName) {
                    // Verificar si es required
                    isRequired = $input.attr('data-required') === 'true';

                    var isChecked = $input.prop('checked');

                    if (isRequired) {
                        $button.attr('data-state', 'required')
                            .addClass('required-extra')
                            .find('.button-content span')
                            .text(hppedVars.i18n.requiredExtra);
                    } else {
                        var state = isChecked ? 'remove' : 'add';
                        var text = isChecked ? 'Remover de reserva' : 'Agregar a reserva';

                        $button.attr('data-state', state)
                            .removeClass('required-extra')
                            .find('.button-content span')
                            .text(text);
                    }

                    buttonUpdated = true;
                    return false; // Salir del loop
                }
            });

            // Si no se actualizó con checkbox, verificar variable quantity
            if (!buttonUpdated) {
                $('.bv-extra-quantity').each(function () {
                    var $input = $(this);
                    var inputName = $input.data('name') || '';
                    inputName = inputName.toLowerCase().trim();
                    var compareExtraName = extraName.toString().toLowerCase().trim();


                    if (compareExtraName === inputName) {
                        // Verificar si es required
                        isRequired = $input.attr('data-required') === 'true';

                        if (isRequired) {
                            $button.attr('data-state', 'required')
                                .addClass('required-extra')
                                .find('.button-content span')
                                .text('Extra requerido');
                        } else {
                            var value = parseInt($input.val()) || 0;
                            var state = value > 0 ? 'remove' : 'add';
                            var text = value > 0 ? 'Remover de reserva' : 'Agregar a reserva';

                            $button.attr('data-state', state)
                                .removeClass('required-extra')
                                .find('.button-content span')
                                .text(text);
                        }

                        buttonUpdated = true;
                        return false; // Salir del loop
                    }
                });
            }

        });
    }

    function reserveExtra(extraName) {

        // Normalizar nombre
        extraName = String(extraName).trim();

        var $relatedButtons = $('.hp-price-extra__reserve').filter(function () {
            var buttonExtraName = $(this).data('extra-name') ||
                $(this).closest('.hp-price-extra').find('.hp-price-extra__name').text().trim();
            return buttonExtraName.toLowerCase().trim() === extraName.toLowerCase();
        });


        // Primero verificar si este extra es obligatorio
        var isRequired = false;

        // Verificar en checkboxes
        $('input[name="_extras[]"]').each(function () {
            var $input = $(this);
            var $label = $input.closest('label').find('.bv-extra-name');
            var inputName = '';

            if ($label.length) {
                inputName = $label.text().trim();
            } else {
                $label = $input.siblings('span').first();
                if ($label.length) {
                    inputName = $label.text().trim();
                }
            }

            if (!inputName) {
                inputName = $input.data('name') || '';
            }

            inputName = inputName.toLowerCase().split('(')[0].trim();

            if (inputName === extraName.toLowerCase()) {
                isRequired = $input.attr('data-required') === 'true';

                if (isRequired) {
                    alert(hppedVars.i18n.requiredExtraCantRemove);
                    return false;
                }
            }
        });

        // Verificar en variable quantity
        if (!isRequired) {
            $('.bv-extra-quantity').each(function () {
                var $input = $(this);
                var inputName = $input.data('name') || '';

                if (inputName.toLowerCase().trim() === extraName.toLowerCase()) {
                    isRequired = $input.attr('data-required') === 'true';

                    if (isRequired) {
                        alert(hppedVars.i18n.requiredExtraCantRemove);
                        return false;
                    }
                }
            });
        }

        // Si es required, no permitir cambios
        if (isRequired) {
            return;
        }

        // Continuar con la lógica normal si no es required
        var extraFound = false;

        // Procesar extras de tipo checkbox
        $('input[name="_extras[]"]').each(function () {
            var $input = $(this);
            var $label = $input.closest('label').find('.bv-extra-name');
            var inputName = '';

            if ($label.length) {
                inputName = $label.text().trim();
            } else {
                $label = $input.siblings('span').first();
                if ($label.length) {
                    inputName = $label.text().trim();
                }
            }

            if (!inputName) {
                inputName = $input.data('name') || '';
            }

            inputName = inputName.toLowerCase().split('(')[0].trim();

            if (inputName === extraName.toLowerCase()) {
                var isChecked = $input.prop('checked');
                $input.prop('checked', !isChecked).trigger('change');

                $relatedButtons.attr('data-state', !isChecked ? 'remove' : 'add')
                    .find('.button-content span')
                    .text(!isChecked ? 'Remove from booking' : 'Add to booking')
                    .end()
                    .addClass('success');

                setTimeout(function () {
                    $relatedButtons.removeClass('success');
                }, 2000);

                extraFound = true;
                return false;
            }
        });

        // Procesar extras de tipo variable quantity
        if (!extraFound) {
            $('.bv-extra-quantity').each(function () {
                var $input = $(this);
                var inputName = $input.data('name') || '';

                if (inputName.toLowerCase().trim() === extraName.toLowerCase()) {
                    var currentValue = parseInt($input.val()) || 0;
                    var newValue = currentValue > 0 ? 0 : 1;

                    $input.val(newValue).trigger('change');
                    $('.variable-quantity-input[data-extra-name="' + extraName + '"]').val(newValue === 0 ? '' : newValue);

                    updateVariableQuantityButton($relatedButtons, newValue);

                    var $bookingForm = $('.bv-booking-form');
                    if ($bookingForm.length && $bookingForm[0]._bookingFormInstance) {
                        var bookingInstance = $bookingForm[0]._bookingFormInstance;
                        bookingInstance.calculateTotals();
                        bookingInstance.updateExtrasText();
                    }

                    extraFound = true;
                    return false;
                }
            });
        }

    }

    // Validación de número máximo de archivos
    $('input[type="file"][multiple]').on('change', function () {
        var maxFiles = $(this).data('max-files') || 5;
        if (this.files.length > maxFiles) {
            alert(hppedVars.i18n.maxImagesAllowed.replace('%s', maxFiles));
            this.value = '';
            return false;
        }
    });

    function initializePopupCarousel($popup) {
        var $carousel = $popup.find('.popup-carousel');
        var $track = $carousel.find('.popup-carousel-track');
        var $slides = $carousel.find('.popup-carousel-slide');
        var currentSlide = 0;

        if ($slides.length <= 1) {
            $carousel.find('.popup-carousel-arrow').hide();
            return;
        }

        function updateSlidePosition() {
            $track.css('transform', `translateX(-${currentSlide * 100}%)`);
        }

        $carousel.find('.popup-carousel-arrow.prev').on('click', function (e) {
            e.preventDefault();
            currentSlide = Math.max(currentSlide - 1, 0);
            updateSlidePosition();
        });

        $carousel.find('.popup-carousel-arrow.next').on('click', function (e) {
            e.preventDefault();
            currentSlide = Math.min(currentSlide + 1, $slides.length - 1);
            updateSlidePosition();
        });

        var touchStartX = 0;
        var touchEndX = 0;

        $carousel.on('touchstart', function (e) {
            touchStartX = e.originalEvent.touches[0].clientX;
        });

        $carousel.on('touchend', function (e) {
            touchEndX = e.originalEvent.changedTouches[0].clientX;
            if (touchStartX - touchEndX > 50) {
                currentSlide = Math.min(currentSlide + 1, $slides.length - 1);
                updateSlidePosition();
            }
            if (touchEndX - touchStartX > 50) {
                currentSlide = Math.max(currentSlide - 1, 0);
                updateSlidePosition();
            }
        });
    }

    function initializeCarousels() {
        $('.hp-price-extra__images-carousel').each(function () {
            if (!$(this).length) {
                return;
            }

            var $carousel = $(this);
            var $track = $carousel.find('.carousel-track');
            var $slides = $carousel.find('.carousel-slide');

            if (!$slides.length) {
                return;
            }

            if ($slides.length <= 1) {
                $carousel.find('.carousel-arrow').hide();
                return;
            }

            var currentSlide = 0;

            function updateSlidePosition() {
                if ($track.length) {
                    $track.css('transform', `translateX(-${currentSlide * 100}%)`);
                }
            }

            $carousel.find('.carousel-arrow.prev').on('click', function (e) {
                e.preventDefault();
                currentSlide = Math.max(currentSlide - 1, 0);
                updateSlidePosition();
            });

            $carousel.find('.carousel-arrow.next').on('click', function (e) {
                e.preventDefault();
                currentSlide = Math.min(currentSlide + 1, $slides.length - 1);
                updateSlidePosition();
            });

            var touchStartX = 0;
            var touchEndX = 0;

            $carousel.on('touchstart', function (e) {
                touchStartX = e.originalEvent.touches[0].clientX;
            });

            $carousel.on('touchend', function (e) {
                touchEndX = e.originalEvent.changedTouches[0].clientX;
                handleSwipe();
            });

            function handleSwipe() {
                if (touchStartX - touchEndX > 50) {
                    currentSlide = Math.min(currentSlide + 1, $slides.length - 1);
                    updateSlidePosition();
                }
                if (touchEndX - touchStartX > 50) {
                    currentSlide = Math.max(currentSlide - 1, 0);
                    updateSlidePosition();
                }
            }
        });
    }

    function initializePopups() {
        $('.hp-price-extra').each(function () {
            var $extra = $(this);
            var $description = $extra.find('.hp-price-extra__description');
            var $paragraphs = $description.children('p');
            var lineCount = $paragraphs.length;

            if (lineCount > 11) {
                $paragraphs.slice(11).hide();

                var $popupButton = $extra.find('.hp-price-extra__popup-button');
                var extraName = $popupButton.data('extra-name');
                var popupId = sanitizeId(extraName);

                var $images = $extra.find('.carousel-slide img');
                var imageHtml = '';
                if ($images.length > 0) {
                    imageHtml = '<div class="hp-price-extra__popup-image">' +
                        '<div class="popup-carousel">' +
                        '<div class="popup-carousel-track">';

                    $images.each(function () {
                        imageHtml += '<div class="popup-carousel-slide">' +
                            '<img src="' + $(this).attr('src') + '" alt="' + extraName + '">' +
                            '</div>';
                    });

                    imageHtml += '</div>';

                    if ($images.length > 1) {
                        imageHtml += '<button class="popup-carousel-arrow prev">&lt;</button>' +
                            '<button class="popup-carousel-arrow next">&gt;</button>';
                    }

                    imageHtml += '</div></div>';
                }

                var $allParagraphs = $description.find('p').clone();
                var halfLength = Math.ceil($allParagraphs.length / 2);
                var column1Content = $('<div class="popup-column"></div>');
                var column2Content = $('<div class="popup-column"></div>');

                $allParagraphs.each(function (index) {
                    if (index < halfLength) {
                        column1Content.append($(this));
                    } else {
                        column2Content.append($(this));
                    }
                });

                if ($('#popup-' + popupId).length === 0) {
                    var $popup = $(
                        '<div class="hp-price-extra__popup" id="popup-' + popupId + '">' +
                        '<div class="hp-price-extra__popup-content">' +
                        '<div class="hp-price-extra__popup-header">' +
                        '<h3>' + hppedVars.i18n.detailedInfo + '</h3>' +
                        '<button class="hp-price-extra__popup-close">&times;</button>' +
                        '</div>' +
                        imageHtml +
                        '<div class="hp-price-extra__popup-info">' +
                        '<h4 class="popup-extra-name">' + $extra.find('.hp-price-extra__name').text() + '</h4>' +
                        '<div class="hp-price-extra__popup-description">' +
                        '<div class="popup-columns">' +
                        column1Content[0].outerHTML +
                        column2Content[0].outerHTML +
                        '</div>' +
                        '</div>' +
                        '<div class="popup-footer">' +
                        '<p class="hp-price-extra__type">' + $extra.find('.hp-price-extra__type').text() + '</p>' +
                        '<p class="hp-price-extra__price">' + $extra.find('.hp-price-extra__price').text() + '</p>' +
                        '<button class="hp-price-extra__reserve" data-extra-name="' + $extra.find('.hp-price-extra__name').text() + '" data-normalized-name="' + $extra.find('.hp-price-extra__name').text().trim().toLowerCase() + '" data-state="add">' +
                        '<span class="button-content">' +
                        '<svg class="booking-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                        '<path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/>' +
                        '</svg>' +
                        '<span>' + hppedVars.i18n.addToBooking + '</span>' +
                        '</span>' +
                        '<span class="success-icon">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">' +
                        '<path d="M20 6L9 17l-5-5"/>' +
                        '</svg>' +
                        '</span>' +
                        '</button>' +
                        '</div>' +
                        '</div>' +
                        '</div></div>'
                    );

                    $('body').append($popup);

                    $popupButton.on('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        $('#popup-' + popupId).fadeIn(300);
                        $('#popup-' + popupId).find('.hp-price-extra__popup-description p').show();
                        initializePopupCarousel($('#popup-' + popupId));
                        // Añadido: actualizar botones required después de abrir popup
                        setTimeout(markRequiredExtras, 300);
                    });

                    $popup.on('click', function (e) {
                        if ($(e.target).is('.hp-price-extra__popup') ||
                            $(e.target).is('.hp-price-extra__popup-close')) {
                            $popup.fadeOut(300);
                        }
                    });

                    $popup.find('.hp-price-extra__reserve').on('click', function () {
                        var extraName = $(this).data('extra-name');
                        reserveExtra(String(extraName)); // Asegurar que sea string
                    });

                    $(document).on('keydown', function (e) {
                        if (e.key === 'Escape' && $popup.is(':visible')) {
                            $popup.fadeOut(300);
                        }
                    });
                }
            }
        });
    }

    // Manejar clicks en botones de reserva
    $(document).on('click', '.hp-price-extra__reserve', function () {
        var extraName = $(this).closest('.hp-price-extra').find('.hp-price-extra__name').text().trim();
        reserveExtra(extraName);
    });

    function preloadImages() {
        $('.carousel-slide img').each(function () {
            var img = new Image();
            img.src = $(this).attr('src');
        });
    }

    // Función para sincronizar inputs de variable quantity
    function syncVariableQuantityInputs() {
        $('.variable-quantity-input').each(function () {
            var $tarjetaInput = $(this);
            var extraName = $tarjetaInput.data('extra-name');

            $('.bv-extras-dropdown .bv-extra-quantity').each(function () {
                var $formInput = $(this);
                var formExtraName = $formInput.data('name');

                if (formExtraName === extraName) {
                    // Sincronizar valores iniciales
                    var initialValue = parseInt($formInput.val()) || 0;
                    $tarjetaInput.val(initialValue === 0 ? '' : initialValue);

                    // Escuchar cambios en el input de la tarjeta
                    $tarjetaInput.off('input').on('input', function () {
                        var newValue = parseInt($(this).val()) || 0;
                        if (newValue < 0) newValue = 0;

                        // Limpiar el input si el valor es 0
                        if (newValue === 0) {
                            $(this).val('');
                        }

                        // Actualizar el input del form y mantenerlo vacío si es 0
                        $formInput.val(newValue === 0 ? '' : newValue).trigger('change');

                        // Forzar recálculo de totales en el form principal
                        var $bookingForm = $('.bv-booking-form');
                        if ($bookingForm.length && $bookingForm[0]._bookingFormInstance) {
                            var bookingInstance = $bookingForm[0]._bookingFormInstance;
                            bookingInstance.calculateTotals();
                            bookingInstance.updateExtrasText();
                        }

                        // Actualizar estado del botón
                        var $reserveButton = $tarjetaInput.closest('.hp-price-extra').find('.hp-price-extra__reserve');
                        updateVariableQuantityButton($reserveButton, newValue);
                    });
                }
            });
        });
    }

    // Función para actualizar el estado del botón según la cantidad
    function updateVariableQuantityButton($buttons, quantity) {
        // Buscar todos los botones relacionados (tarjeta y popup)
        $buttons.each(function () {
            var $button = $(this);
            if (quantity > 0) {
                $button.attr('data-state', 'remove')
                    .find('.button-content span')
                    .text(hppedVars.i18n.removeFromBooking);
            } else {
                $button.attr('data-state', 'add')
                    .find('.button-content span')
                    .text(hppedVars.i18n.addToBooking);
            }
        });

        // Agregar animación
        $buttons.addClass('success');
        setTimeout(function () {
            $buttons.removeClass('success');
        }, 2000);
    }

    // Función específica para detectar y marcar extras obligatorios
    function markRequiredExtras() {

        // Primero, verificar checkboxes required
        $('input[name="_extras[]"][data-required="true"]').each(function () {
            var $input = $(this);
            var extraName = '';

            // Intentar obtener el nombre del extra de varias formas
            var $nameElement = $input.closest('label').find('.bv-extra-name');
            if ($nameElement.length) {
                extraName = $nameElement.text().trim();
            } else {
                extraName = $input.siblings('.bv-extra-name').text().trim();
                if (!extraName) {
                    var $parentLabel = $input.parent('label');
                    if ($parentLabel.length) {
                        extraName = $parentLabel.text().trim().split('(')[0].trim();
                    }
                }
            }

            if (!extraName && $input.data('name')) {
                extraName = $input.data('name');
            }

            if (extraName) {
                var normalizedName = extraName.toLowerCase();

                // Forzar marcado directo por atributo data o texto
                $('.hp-price-extra__reserve').each(function () {
                    var $button = $(this);
                    var buttonName = $button.data('extra-name') || '';
                    var buttonText = $button.closest('.hp-price-extra').find('.hp-price-extra__name').text().trim();

                    // Comparar de múltiples formas
                    if (buttonName.toLowerCase() === normalizedName ||
                        buttonText.toLowerCase() === normalizedName) {

                        // Forzar actualización directa del botón
                        $button.attr('data-state', 'required');
                        $button.addClass('required-extra');
                        $button.find('.button-content span').text('Required extra');

                        // Forzar CSS inline para asegurar el estilo
                        $button.css({
                            'background-color': '#f4f4f4',
                            'color': '#333',
                            'cursor': 'default',
                            'border-color': '#ccc',
                            'pointer-events': 'none'
                        });

                        // Asegurar que el atributo y la clase se apliquen
                        setTimeout(function () {
                            $button.attr('data-state', 'required');
                            $button.addClass('required-extra');
                            $button.find('.button-content span').text('Required extra');
                        }, 100);
                    }
                });
            }
        });

        $('.bv-extra-quantity[data-required="true"]').each(function () {
            var $input = $(this);
            var extraName = $input.data('name') || '';

            if (extraName) {
                var normalizedName = extraName.toLowerCase();

                $('.hp-price-extra__reserve').each(function () {
                    var $button = $(this);
                    var buttonName = $button.data('extra-name') || '';
                    var buttonText = $button.closest('.hp-price-extra').find('.hp-price-extra__name').text().trim();

                    if (buttonName.toLowerCase() === normalizedName ||
                        buttonText.toLowerCase() === normalizedName) {

                        // Forzar actualización directa
                        $button.attr('data-state', 'required');
                        $button.addClass('required-extra');
                        $button.find('.button-content span').text('Required extra');

                        // Forzar CSS inline
                        $button.css({
                            'background-color': '#f4f4f4',
                            'color': '#333',
                            'cursor': 'default',
                            'border-color': '#ccc',
                            'pointer-events': 'none'
                        });

                        setTimeout(function () {
                            $button.attr('data-state', 'required');
                            $button.addClass('required-extra');
                            $button.find('.button-content span').text('Required extra');
                        }, 100);
                    }
                });
            }
        });

        window.fixRequiredExtras();

    }

    function addRequiredExtraStyles() {
        var css = `
        .hp-price-extra__reserve[data-state="required"] {
            background-color: #f4f4f4 !important;
            color: #333 !important;
            cursor: default !important;
            border-color: #ccc !important;
            pointer-events: none;
        }
        
        .hp-price-extra__reserve[data-state="required"]:hover {
            background-color: #f4f4f4 !important;
        }
        
        .hp-price-extra__reserve[data-state="required"] .button-content span {
            font-weight: bold;
        }
        
        .hp-price-extra__reserve[data-state="required"]::after {
            content: "✓";
            font-weight: bold;
            margin-left: 5px;
        }
    `;

        // Eliminar estilos previos si existen
        $('#required-extra-styles').remove();
        $('<style id="required-extra-styles">').html(css).appendTo('head');

        // Forzar sincronización inicial
        setTimeout(function () {
            syncExtraButtons();
        }, 500);
    }

    window.fixRequiredExtras = function () {

        // Restablecer todos los botones a su estado normal primero
        $('.hp-price-extra__reserve').each(function () {
            var $button = $(this);
            $button.removeClass('required-extra');
            $button.css({
                'background-color': '',
                'color': '',
                'cursor': '',
                'border-color': '',
                'pointer-events': ''
            });
        });

        $('input[name="_extras[]"][data-required="true"]').each(function () {
            var $input = $(this);
            var index = $input.val(); // Este es el índice del extra en el array

            $('.hp-price-extra__reserve').each(function () {
                var $button = $(this);
                var buttonExtraName = $button.closest('.hp-price-extra').find('.hp-price-extra__name').text().trim();
                var inputExtraName = $input.data('name') || '';

                if (!inputExtraName) {
                    var $nameElem = $input.siblings('.bv-extra-name');
                    if ($nameElem.length) {
                        inputExtraName = $nameElem.text().trim();
                    } else {
                        var $labelElem = $input.closest('label');
                        if ($labelElem.length) {
                            inputExtraName = $labelElem.text().trim().split('(')[0].trim();
                        }
                    }
                }

                if (buttonExtraName.toLowerCase() === inputExtraName.toLowerCase()) {

                    $button.attr('data-state', 'required');
                    $button.addClass('required-extra');
                    $button.find('.button-content span').text('Required extra');

                    $button.css({
                        'background-color': '#f4f4f4',
                        'color': '#333',
                        'cursor': 'default',
                        'border-color': '#ccc',
                        'pointer-events': 'none'
                    });
                }
            });
        });

        $('.bv-extra-quantity[data-required="true"]').each(function () {
            var $input = $(this);
            var inputExtraName = $input.data('name') || '';


            $('.hp-price-extra__reserve').each(function () {
                var $button = $(this);
                var buttonExtraName = $button.closest('.hp-price-extra').find('.hp-price-extra__name').text().trim();

                if (buttonExtraName.toLowerCase() === inputExtraName.toLowerCase()) {

                    $button.attr('data-state', 'required');
                    $button.addClass('required-extra');
                    $button.find('.button-content span').text('Required extra');

                    $button.css({
                        'background-color': '#f4f4f4',
                        'color': '#333',
                        'cursor': 'default',
                        'border-color': '#ccc',
                        'pointer-events': 'none'
                    });
                }
            });
        });

        return "Proceso de fijación manual completado";
    };

    // Inicializar todas las funcionalidades
    $(document).ready(() => {
        initializeCarousels();
        initializePopups();
        preloadImages();
        syncExtraButtons(); 
        syncVariableQuantityInputs();
        addRequiredExtraStyles();
        markRequiredExtras();

        // Forzar verificación adicional después de un breve retraso
        setTimeout(function () {
            markRequiredExtras();
            window.fixRequiredExtras(); // Aplicar fijación manual
        }, 1000);

        setTimeout(function () { window.fixRequiredExtras(); }, 500);
        setTimeout(function () { window.fixRequiredExtras(); }, 1000);
        setTimeout(function () { window.fixRequiredExtras(); }, 2000);

        $(document).on('change', '.bv-extra-quantity', function () {
            var $formInput = $(this);
            var extraName = $formInput.data('name');
            var newValue = parseInt($formInput.val()) || 0;

            var $tarjetaInput = $('.variable-quantity-input[data-extra-name="' + extraName + '"]');
            $tarjetaInput.val(newValue === 0 ? '' : newValue);

            var $reserveButton = $tarjetaInput.closest('.hp-price-extra').find('.hp-price-extra__reserve');
            updateVariableQuantityButton($reserveButton, newValue);

            markRequiredExtras();
        });

        $(document).on('change', 'input[name="_extras[]"]', function () {
            syncExtraButtons();
            markRequiredExtras();
        });
    });

    $(window).on('load', function () {
        setTimeout(function () {
            window.fixRequiredExtras();
        }, 500);
    });

    $(document).on('hp-form.field.added', function (e, container) {
        initializeCarousels();
        initializePopups();

        setTimeout(function () {
            markRequiredExtras();
            window.fixRequiredExtras();
        }, 300);
    });

    $(document).ajaxComplete(function () {
        setTimeout(function () {
            window.fixRequiredExtras();
        }, 300);
    });

    var resizeTimer;
    $(window).on('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            $('.carousel-track').each(function () {
                $(this).css('transform', 'translateX(0)');
            });
        }, 250);
    });
})(jQuery);