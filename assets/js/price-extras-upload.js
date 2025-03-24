(function($) {
    'use strict';

    // Definición dentro del closure
    var isAdmin = typeof window.wp !== 'undefined' && typeof window.wp.blocks !== 'undefined';

    /**
     * BaseUploader - Solo maneja subida y previsualización
     */
    var BaseUploader = {
        init: function() {
            this.initializeExistingPreviews();
            this.bindEvents();
            console.log('Base Uploader initialized in ' + (isAdmin ? 'admin' : 'frontend') + ' mode');
        },

        initializeExistingPreviews: function() {
            var self = this;
            $('.hp-field--multiple-file').each(function() {
                var $container = $(this);
                var $hiddenInput = $container.find('input[type="hidden"].hp-field__value');
                var currentValue = $hiddenInput.val();
                
                if (currentValue) {
                    self.updateCounter($container);
                    self.updateUploadButtonState($container);
                }
            });
        },

        bindEvents: function() {
            var self = this;
            var clickedContainer = null;

            // Click en botón de subida
            $(document).on('click', '.hp-field__upload-button', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $container = $(this).closest('.hp-field--multiple-file');
                var currentCount = $container.find('.hp-field__preview').length;
                var maxFiles = $container.find('.hp-field__file').data('max-files');
            
                if (currentCount >= maxFiles) {
                    alert('Máximo ' + maxFiles + ' imágenes permitidas');
                    return false;
                }
            
                clickedContainer = $container;
                $container.find('.hp-field__file').click();
            });

            // Cambio en input file
            $(document).on('change', '.hp-field__file', function(e) {
                if (!clickedContainer) return;

                var $container = clickedContainer;
                var files = e.target.files;

                if (files && files.length > 0) {
                    self.handleFileSelect(files, $container);
                }

                // Limpiar referencia
                clickedContainer = null;
                $(this).val('');
            });

            // Eliminación de archivos
            $(document).on('click', '.hp-field__preview-button--delete', this.handleFileDelete.bind(this));
        },

        handleFileSelect: function(files, $container) {
            var self = this;
            var currentCount = $container.find('.hp-field__preview').length;
            var maxFiles = $container.find('.hp-field__file').data('max-files');
            var remainingSlots = maxFiles - currentCount;

            if (files.length > remainingSlots) {
                alert('Solo puede seleccionar ' + remainingSlots + ' imagen(es) más');
                return;
            }

            var $hiddenInput = $container.find('input[type="hidden"]');
            var extraId = this.getExtraId($hiddenInput);

            if (!extraId) {
                console.error('Could not extract extra ID');
                return;
            }

            Array.from(files).forEach(function(file) {
                self.uploadFile(file, $container, extraId);
            });
        },

        uploadFile: function(file, $container, extraId) {
            var self = this;
            var $hiddenInput = $container.find('input[type="hidden"]');
            
            var formData = new FormData();
            formData.append('file', file);
            formData.append('extra_id', extraId);
            formData.append('listing_id', this.getListingId());

            $container.addClass('is-uploading');
            $container.find('.hp-field__upload-status').text('Subiendo imagen...');

            $.ajax({
                url: '/wp-json/price-extras/v1/upload',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', hppedVars.nonce);
                }
            })
            .done(function(response) {
                if (response && response.id) {
                    self.addPreview($container, {
                        id: response.id,
                        url: response.thumbnail || response.url
                    });

                    // Actualizar el campo oculto que HivePress usará
                    var currentValue = $hiddenInput.val();
                    var newValue = currentValue ? currentValue + ',' + response.id : response.id;
                    $hiddenInput.val(newValue).trigger('change');  // Notificar a HivePress del cambio
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                console.error('Upload failed:', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText
                });
                alert('Error al subir el archivo: ' + file.name);
            })
            .always(function() {
                $container.removeClass('is-uploading');
                $container.find('.hp-field__upload-status').text('');
                self.updateUploadButtonState($container);
            });
        },

        handleFileDelete: function(e) {
            e.preventDefault();
            var self = this;
            var $button = $(e.currentTarget);
            var $preview = $button.closest('.hp-field__preview');
            var $container = $preview.closest('.hp-field--multiple-file');
            var $hiddenInput = $container.find('input[type="hidden"].hp-field__value');
            var imageId = $preview.data('id');
        
            $.ajax({
                url: '/wp-json/price-extras/v1/delete-image',
                type: 'POST',
                data: {
                    image_id: imageId
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', hppedVars.nonce);
                }
            })
            .done(function(response) {
                if (response.success) {
                    $preview.remove();
                    
                    // Actualizar el campo oculto que HivePress usará
                    var currentIds = $hiddenInput.val().split(',').filter(function(id) { 
                        return id !== '' && id !== imageId.toString(); 
                    });
                    $hiddenInput.val(currentIds.join(',')).trigger('change');  // Notificar a HivePress del cambio
        
                    self.updateCounter($container);
                } else {
                    alert('Error al eliminar la imagen: ' + response.message);
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                console.error('Delete failed:', textStatus, errorThrown);
                alert('Error al eliminar la imagen');
            });
        },

        getExtraId: function($hiddenInput) {
            var nameAttr = $hiddenInput.attr('name');
            var matches = nameAttr.match(/price_extras\[([^\]]+)\]/);
            return matches ? matches[1] : null;
        },

        updateCounter: function($container) {
            var currentCount = $container.find('.hp-field__preview').length;
            var maxFiles = $container.find('.hp-field__file').data('max-files');
            $container.find('.hp-field__counter').text('(' + currentCount + '/' + maxFiles + ')');
            this.updateUploadButtonState($container);
        },

        updateUploadButtonState: function($container) {
            var currentCount = $container.find('.hp-field__preview').length;
            var maxFiles = $container.find('.hp-field__file').data('max-files');
            
            if (currentCount >= maxFiles) {
                $container.find('.hp-field__upload-button').addClass('disabled');
            } else {
                $container.find('.hp-field__upload-button').removeClass('disabled');
            }
        },

        getListingId: function() {
            // Este método debe ser sobreescrito por las implementaciones específicas
            throw new Error('getListingId must be implemented by specific uploader');
        },

        addPreview: function($container, data) {
            var $preview = $(
                '<div class="hp-field__preview" data-id="' + data.id + '">' +
                    '<img src="' + data.url + '" alt="" />' +
                    '<div class="hp-field__preview-actions">' +
                        '<button type="button" class="hp-field__preview-button hp-field__preview-button--delete">' +
                            '<i class="fas fa-times"></i>' +
                        '</button>' +
                    '</div>' +
                '</div>'
            );

            $container.find('.hp-field__previews').append($preview);
            this.updateCounter($container);
        }
    };

    // Frontend Uploader
    var FrontendUploader = Object.create(BaseUploader);
    FrontendUploader.getListingId = function() {
        // Obtener ID del listing de la URL en frontend
        var matches = window.location.pathname.match(/\/listings\/(\d+)/);
        return matches ? matches[1] : null;
    };

    // Admin Uploader
    var AdminUploader = Object.create(BaseUploader);
    AdminUploader.getListingId = function() {
        // Obtener ID del listing en admin
        return $('#post_ID').val();
    };

    // Inicializar
    $(document).ready(function() {
        if (isAdmin) {
            AdminUploader.init();
        } else {
            FrontendUploader.init();
        }
    });

})(jQuery);