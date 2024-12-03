jQuery(document).ready(function($) {
    // Trigger AJAX call
    $('#feachjobsnow').on('click', function(e) {
        e.preventDefault();
        $('#full-screen-loading').show();
        $.ajax({
            url: my_ajax_object.ajax_url,
            method: 'POST',
            data: {
                action: 'jobit_import_jobs_action',
            },
            success: function(response) {
                console.log('AJAX response:', response);
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