jQuery(document).ready(function ($) {
    // Asegurar que el formulario tenga el enctype correcto
    $('form#post').attr('enctype', 'multipart/form-data');

    // Manejar la subida de archivos
    $('.hp-price-extra__image-upload').on('change', function (e) {
        var $input = $(this);
        var $preview = $input.siblings('.image-preview');
        var files = e.target.files;

        if (files && files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $preview.html('<img src="' + e.target.result + '" />');
            }
            reader.readAsDataURL(files[0]);
        }
    });
});