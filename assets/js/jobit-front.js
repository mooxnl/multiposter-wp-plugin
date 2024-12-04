jQuery(document).ready(function($) {
    let typingTimer;
    const typingDelay = 300; // Time in ms after typing stops to trigger AJAX

    function getSelectedCities() {
        var selectedCities = [];
        $('.selected_city:checked').each(function() {
            selectedCities.push($(this).val()); // Push the value of each checked checkbox to the array
        });
        return selectedCities;
    }

    function getSelectedPostions() {
        var selected_position = [];
        $('.selected_position:checked').each(function() {
            selected_position.push($(this).val()); // Push the value of each checked checkbox to the array
        });
        return selected_position;
    }

    function loadJobs(pageNumber=1, postsPerPage=50) {
        var selectedCities = getSelectedCities();
        var selectedPostions = getSelectedPostions();
        let keyword = $('.jobtitle').val();
        if (keyword.length < 3) {
            keyword = '';
        }

        console.log(selectedPostions);
        console.log(selectedCities);

        $.ajax({
            url: my_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'jobit_change_per_page',
                paged: pageNumber,
                posts_per_page: postsPerPage,
                selectedCities: selectedCities,
                selectedPostions: selectedPostions,
                keyword: keyword
            },
            beforeSend: function() {
                $('#jobs-results .loader__wrapper').show(); // Show loader
            },
            success: function(response) {
                var data = JSON.parse(response);
                $('#jobs-results .loader__wrapper').hide();
                $('#jobs-results .vacancies').html(data.html); // Update job listings
                $('#jobs-results .pagination').html(data.pagination); // Update pagination

                // Scroll to the jobit-container after loading jobs
                $('html, body').animate({
                    scrollTop: $('.jobit-container').offset().top
                }, 800); // 800ms for smooth scrolling
            }
        });
    }

    setTimeout(function() {
        loadJobs();
    }, 500);

    // Trigger AJAX call
    $('.perpage select').change(function() {
        var perpage = $(this).val();
        loadJobs(1,perpage);
    });

    jQuery(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        let page = jQuery(this).text() || 1;
        const postsPerPage = jQuery('#jobs-results .perpage select').val() || 10;
        if(page == "« Previous"){
            var page_value = jQuery('span.page-numbers.current').text();
            page = parseInt(page_value) - 1;
        }
        else if(page == "Next »"){
            var page_value = jQuery('span.page-numbers.current').text();
            page = parseInt(page_value) + 1;
        }
        loadJobs(page,postsPerPage);
    });


    $('.selected_city').on('change', function() {
        loadJobs();
    });

    $('.selected_position').on('change', function() {
        loadJobs();
    });

    $('.jobtitle').on('keyup', function(event) {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function() {
            loadJobs();
        }, typingDelay);
    });

    $('#jobs-filter').on('keydown', function(event) {
        if (event.target.classList.contains('jobtitle') && (event.key === "Enter" || event.keyCode === 13)) {
            event.preventDefault(); // Stop form submission when Enter is pressed inside .jobtitle
        }
    });

});
