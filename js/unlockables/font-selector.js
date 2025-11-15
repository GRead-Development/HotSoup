/**
 * Font Selector
 *
 * Handles font selection for users
 */

jQuery(document).ready(function($) {
    $('#hs-font-selector-form').on('submit', function(e) {
        e.preventDefault();

        var selectedFont = $('input[name="selected_font"]:checked').val();
        var nonce = $('#hs_font_nonce').val();
        var $button = $(this).find('button[type="submit"]');
        var $feedback = $('#hs-font-selector-feedback');

        if (!selectedFont) {
            $feedback.html('<div class="error">Please select a font</div>');
            return;
        }

        // Show loading state
        $button.prop('disabled', true).text('Saving...');
        $feedback.html('');

        $.ajax({
            url: ajaxurl || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'hs_save_user_font',
                selected_font: selectedFont,
                hs_font_nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $feedback.html('<div class="success">' + response.data.message + '</div>');
                    // Reload page to apply new font
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $feedback.html('<div class="error">' + response.data.message + '</div>');
                    $button.prop('disabled', false).text('Save Font');
                }
            },
            error: function() {
                $feedback.html('<div class="error">An error occurred. Please try again.</div>');
                $button.prop('disabled', false).text('Save Font');
            }
        });
    });

    // Preview font on hover
    $('.hs-font-option:not(.locked)').on('mouseenter', function() {
        var $preview = $(this).find('.font-preview');
        var fontFamily = $preview.css('font-family');
        $(this).css('transform', 'scale(1.05)');
    }).on('mouseleave', function() {
        $(this).css('transform', 'scale(1)');
    });

    // Click to select
    $('.hs-font-option:not(.locked)').on('click', function() {
        $(this).find('input[type="radio"]').prop('checked', true);
        $('.hs-font-option').removeClass('selected');
        $(this).addClass('selected');
    });
});
