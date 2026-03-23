jQuery(document).ready(function($) {
    let typingTimer;
    const typingDelay = 300;

    // Mobile filter toggle
    $('.multiposter-filter-toggle').on('click', function() {
        var $btn = $(this);
        var $form = $('#multiposter-filter-form');
        var open = $form.toggleClass('multiposter-filter-form--open').hasClass('multiposter-filter-form--open');
        $btn.attr('aria-expanded', open);
    });

    // Feature 10: Favorites (localStorage-based)
    function getFavorites() {
        try {
            return JSON.parse(localStorage.getItem('multiposter_favorites')) || [];
        } catch(e) { return []; }
    }

    function setFavorites(favs) {
        localStorage.setItem('multiposter_favorites', JSON.stringify(favs));
    }

    function updateFavoriteIcons() {
        var favs = getFavorites();
        $('.multiposter-favorite-btn').each(function() {
            var id = $(this).data('id').toString();
            if (favs.indexOf(id) !== -1) {
                $(this).addClass('is-favorite');
            } else {
                $(this).removeClass('is-favorite');
            }
        });
    }

    $(document).on('click', '.multiposter-favorite-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).data('id').toString();
        var favs = getFavorites();
        var idx = favs.indexOf(id);
        if (idx === -1) {
            favs.push(id);
        } else {
            favs.splice(idx, 1);
        }
        setFavorites(favs);
        updateFavoriteIcons();
    });

    // Show favorites only
    $(document).on('change', '#multiposter-show-favorites', function() {
        if ($(this).is(':checked')) {
            var favs = getFavorites();
            $('.multiposter-card').each(function() {
                var id = $(this).data('vacancy-id').toString();
                if (favs.indexOf(id) === -1) {
                    $(this).hide();
                }
            });
        } else {
            $('.multiposter-card').show();
        }
    });

    function getSelectedCities() {
        var selected = [];
        $('input[name="city[]"]:checked').each(function() {
            selected.push($(this).val());
        });
        return selected;
    }

    function getSelectedPositions() {
        var selected = [];
        $('input[name="position[]"]:checked').each(function() {
            selected.push($(this).val());
        });
        return selected;
    }

    function loadJobs(pageNumber, postsPerPage) {
        pageNumber = pageNumber || 1;
        postsPerPage = postsPerPage || ($('#multiposter-per-page').val() || 10);

        var selectedCities = getSelectedCities();
        var selectedPositions = getSelectedPositions();
        var keyword = $('#multiposter-keyword').val() || '';
        if (keyword.length < 3) {
            keyword = '';
        }

        // Feature 8: Salary filter
        var salaryMin = parseInt($('input[name="salary_min"]').val()) || 0;
        var salaryMax = parseInt($('input[name="salary_max"]').val()) || 0;

        $.ajax({
            url: multiposter_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'multiposter_change_per_page',
                _ajax_nonce: multiposter_ajax.archive_nonce,
                paged: pageNumber,
                posts_per_page: postsPerPage,
                selectedCities: selectedCities,
                selectedPostions: selectedPositions,
                keyword: keyword,
                salary_min: salaryMin,
                salary_max: salaryMax
            },
            beforeSend: function() {
                $('.multiposter-loader').removeAttr('hidden');
            },
            success: function(response) {
                var data = JSON.parse(response);
                $('.multiposter-loader').attr('hidden', '');
                $('.multiposter-vacancies').html(data.html);
                $('.multiposter-pagination').html(data.pagination);
                updateFavoriteIcons();

                // Re-apply favorites filter if active
                if ($('#multiposter-show-favorites').is(':checked')) {
                    $('#multiposter-show-favorites').trigger('change');
                }

                $('html, body').animate({
                    scrollTop: $('.multiposter-archive').offset().top
                }, 800);
            }
        });
    }

    // SSR: Only load via AJAX if the container is empty (no SSR content)
    if ($('.multiposter-vacancies').children().length === 0) {
        loadJobs();
    } else {
        updateFavoriteIcons();
    }

    // Per-page change
    $('#multiposter-per-page').change(function() {
        loadJobs(1, $(this).val());
    });

    // Pagination click
    $(document).on('click', '.multiposter-pagination a', function(e) {
        e.preventDefault();
        var page = $(this).text() || 1;
        var currentPage = parseInt($('.multiposter-pagination span.page-numbers.current').text());
        var postsPerPage = $('#multiposter-per-page').val() || 10;

        if ($(this).hasClass('prev') || page.indexOf('Vorige') !== -1) {
            page = currentPage - 1;
        } else if ($(this).hasClass('next') || page.indexOf('Volgende') !== -1) {
            page = currentPage + 1;
        }

        loadJobs(page, postsPerPage);
    });

    // Filter changes
    $(document).on('change', 'input[name="city[]"]', function() { loadJobs(); });
    $(document).on('change', 'input[name="position[]"]', function() { loadJobs(); });

    // Salary filter change
    $(document).on('change', 'input[name="salary_min"], input[name="salary_max"]', function() { loadJobs(); });

    // Keyword search with debounce
    $(document).on('keyup', '#multiposter-keyword', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(function() {
            loadJobs();
        }, typingDelay);
    });

    // Prevent form submit on enter in keyword field
    $(document).on('keydown', '#multiposter-filter-form', function(event) {
        if (event.target.id === 'multiposter-keyword' && (event.key === 'Enter' || event.keyCode === 13)) {
            event.preventDefault();
        }
    });

    // Vacancy gallery lightbox
    (function() {
        var galleryItems = [];
        var currentIndex = 0;
        var $overlay = null;

        function openLightbox(index) {
            currentIndex = index;
            if (!$overlay) {
                $overlay = $('<div class="vacancy-lightbox">' +
                    '<button class="vacancy-lightbox__close" aria-label="Close">&times;</button>' +
                    '<button class="vacancy-lightbox__prev" aria-label="Previous">&#8249;</button>' +
                    '<img class="vacancy-lightbox__img" src="" alt="" />' +
                    '<button class="vacancy-lightbox__next" aria-label="Next">&#8250;</button>' +
                    '</div>');
                $('body').append($overlay);

                $overlay.on('click', function(e) {
                    if ($(e.target).hasClass('vacancy-lightbox')) closeLightbox();
                });
                $overlay.find('.vacancy-lightbox__close').on('click', closeLightbox);
                $overlay.find('.vacancy-lightbox__prev').on('click', function() { navigate(-1); });
                $overlay.find('.vacancy-lightbox__next').on('click', function() { navigate(1); });
            }
            updateImage();
            $overlay.addClass('is-open');
            $(document).on('keydown.lightbox', function(e) {
                if (e.key === 'Escape') closeLightbox();
                if (e.key === 'ArrowLeft') navigate(-1);
                if (e.key === 'ArrowRight') navigate(1);
            });
        }

        function closeLightbox() {
            if ($overlay) $overlay.removeClass('is-open');
            $(document).off('keydown.lightbox');
        }

        function navigate(dir) {
            currentIndex = (currentIndex + dir + galleryItems.length) % galleryItems.length;
            updateImage();
        }

        function updateImage() {
            if ($overlay && galleryItems[currentIndex]) {
                $overlay.find('.vacancy-lightbox__img').attr('src', galleryItems[currentIndex]);
            }
        }

        $(document).on('click', '.vacancy-gallery__item[data-lightbox]', function(e) {
            e.preventDefault();
            galleryItems = [];
            $('.vacancy-gallery__item[data-lightbox]').each(function() {
                galleryItems.push($(this).attr('href'));
            });
            var idx = $('.vacancy-gallery__item[data-lightbox]').index(this);
            openLightbox(idx);
        });
    })();

    // Feature 13: Application form submission
    $(document).on('submit', '#multiposter-application-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $msg = $form.find('.multiposter-form-message');

        $btn.prop('disabled', true).text($btn.data('loading') || 'Versturen...');
        $msg.html('').hide();

        var formData = new FormData(this);
        formData.append('action', 'multiposter_apply');
        formData.append('_ajax_nonce', $form.find('[name="multiposter_apply_nonce"]').val());

        $.ajax({
            url: multiposter_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $msg.html('<div class="multiposter-form-success">' + response.data.message + '</div>').show();
                    $form[0].reset();
                } else {
                    $msg.html('<div class="multiposter-form-error">' + response.data.message + '</div>').show();
                }
            },
            error: function() {
                $msg.html('<div class="multiposter-form-error">Er is een fout opgetreden.</div>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text($btn.data('label') || 'Versturen');
            }
        });
    });

    // Registration form submission
    $(document).on('submit', '#multiposter-registration-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $msg = $form.find('.multiposter-form-message');

        $btn.prop('disabled', true).text($btn.data('loading') || 'Registreren...');
        $msg.html('').hide();

        var formData = new FormData(this);
        formData.append('action', 'multiposter_register');
        formData.append('_ajax_nonce', $form.find('[name="multiposter_register_nonce"]').val());

        $.ajax({
            url: multiposter_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $msg.html('<div class="multiposter-form-success">' + response.data.message + '</div>').show();
                    $form[0].reset();
                } else {
                    $msg.html('<div class="multiposter-form-error">' + response.data.message + '</div>').show();
                }
            },
            error: function() {
                $msg.html('<div class="multiposter-form-error">Er is een fout opgetreden.</div>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).text($btn.data('label') || 'Registreren');
            }
        });
    });
});
