jQuery(document).ready(function($) {
    // Feature 7: Make filter list sortable
    $('#multiposter-filters-sortable, #multiposter-form-fields-sortable, #multiposter-registration-fields-sortable, #multiposter-share-buttons-sortable, #multiposter-related-criteria-sortable').each(function() {
        $(this).sortable({
            handle: '.dashicons-menu',
            update: function() {
                $(this).find('li').each(function(index) {
                    $(this).find('input[type="hidden"], input[type="checkbox"]').each(function() {
                        var name = $(this).attr('name');
                        if (name) {
                            $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                        }
                    });
                });
            }
        });
    });

    // Trigger AJAX call
    $('#feachjobsnow').on('click', function(e) {
        e.preventDefault();
        $('#full-screen-loading').show();
        $.ajax({
            url: multiposter_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'multiposter_import_jobs_action',
                _ajax_nonce: multiposter_ajax.import_nonce,
            },
            success: function(response) {
                console.log('AJAX response:', response);
                location.reload();
            },
            error: function(error) {
                console.log('AJAX error:', error);
            },
            complete: function() {
                $('#full-screen-loading').hide();
            }
        });
    });
});