<?php
/**
 * Plugin Name:       Multiposter
 * Version:           2.0
 * Text Domain:       multiposter
 * Domain Path:       /languages
 * Description:       Publiceer jouw vacatures vanuit Multiposter op je eigen WordPress website.
 * Author:            Multiposter
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 */

defined( 'ABSPATH' ) || exit;

// Migrate legacy option names
add_action('admin_init', 'multiposter_migrate_options', 0);
function multiposter_migrate_options() {
    $migrations = array(
        'api_key'       => 'multiposter_api_key',
        'api_intervals' => 'multiposter_api_intervals',
        'show_form'     => 'multiposter_show_form',
    );
    foreach ($migrations as $old => $new) {
        $old_value = get_option($old);
        if ($old_value !== false && get_option($new) === false) {
            update_option($new, $old_value);
            delete_option($old);
        }
    }
}

function multiposter_check_rate_limit($action) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    $ttl = apply_filters('multiposter_rate_limit_ttl', 60, $action);
    $key = 'multiposter_rl_' . md5($action . '_' . $ip);
    if (get_transient($key)) {
        wp_send_json_error(array('message' => __('Te veel verzoeken. Probeer het later opnieuw.', 'multiposter')));
    }
    set_transient($key, 1, $ttl);
}

function multiposter_enqueue_admin_scripts() {
    wp_enqueue_script('jquery-ui-sortable');
    wp_register_script(
        'multiposter-admin-js',
        plugins_url('assets/js/multiposter.js', __FILE__),
        array('jquery', 'jquery-ui-sortable'),
        '2.0.0',
        true
    );
    wp_enqueue_script('multiposter-admin-js');
    wp_localize_script('multiposter-admin-js', 'multiposter_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'import_nonce' => wp_create_nonce('multiposter_import_jobs'),
    ));

    wp_enqueue_style(
        'multiposter-admin-css',
        plugins_url('assets/css/multiposter.css', __FILE__), 
        array(), 
        '2.1.0'
    );
}
// Hook into WordPress
add_action('admin_enqueue_scripts', 'multiposter_enqueue_admin_scripts');


function multiposter_should_enqueue() {
    if (apply_filters('multiposter_force_enqueue', false)) return true;
    if (is_post_type_archive('vacatures')) return true;
    if (is_tax('cities') || is_tax('position')) return true;
    if (is_singular('vacatures')) return true;
    if (is_singular() || is_page()) {
        $post = get_post();
        if ($post) {
            $blocks = array(
                'multiposter/vacancy-archive', 'multiposter/latest-vacancies',
                'multiposter/single-vacancy', 'multiposter/vacancy-search',
                'multiposter/application-form', 'multiposter/vacancy-images',
                'multiposter/share-buttons', 'multiposter/related-vacancies',
                'multiposter/registration-form',
            );
            foreach ($blocks as $block) {
                if (has_block($block, $post)) return true;
            }
            if (has_shortcode($post->post_content, 'jobs_archive') || has_shortcode($post->post_content, 'job_single')) return true;
        }
    }
    return false;
}

function multiposter_enqueue_frontend_scripts() {
    if (!multiposter_should_enqueue()) return;

    wp_enqueue_style(
        'multiposter-style-css',
        plugins_url('assets/css/multiposter.css', __FILE__),
        array(),
        '2.1.0'
    );

    wp_register_script(
        'multiposter-front-js',
        plugins_url('assets/js/multiposter-front.js', __FILE__),
        array('jquery'),
        '2.1.0',
        true
    );
    // Enqueue the registered script
    wp_enqueue_script('multiposter-front-js');
    wp_localize_script('multiposter-front-js', 'multiposter_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'archive_nonce' => wp_create_nonce('multiposter_archive'),
        'i18n' => array(
            'previous' => __('&laquo; Vorige', 'multiposter'),
            'next' => __('Volgende &raquo;', 'multiposter'),
        ),
    ));
}
// Hook into WordPress
add_action('wp_enqueue_scripts', 'multiposter_enqueue_frontend_scripts');


function multiposter_register_cpt() {
	$labels = array(
		'name' => __('Multiposter', 'multiposter'),
		'singular_name' => __('Multiposter', 'multiposter'),
		'menu_name' => __('Multiposter', 'multiposter'),
		'all_items' => __('Alle vacatures', 'multiposter'),
		'add_new_item' => __('Nieuwe vacature toevoegen', 'multiposter'),
		'add_new' => __('Nieuw', 'multiposter'),
		'edit_item' => __('Vacature bewerken', 'multiposter'),
		'update_item' => __('Update vacature', 'multiposter'),
		'view_item' => __('Bekijk vacature', 'multiposter'),
	);
	$vacancy_slug = get_option('multiposter_vacancy_slug', 'vacatures');
	$args = array(
		'label' => __('Multiposter', 'multiposter'),
		'description' => __('Vacature overzicht Multiposter', 'multiposter'),
		'labels' => $labels,
		'supports' => array('title', 'editor', 'thumbnail'),
		'public' => true,
		'show_in_menu' => true,
		'menu_position' => 5,
		'has_archive' => true,
		'rewrite' => array('slug' => $vacancy_slug),
		'capability_type' => 'post',
        'menu_icon' => 'dashicons-rss',
        'show_in_rest' => true,
        'map_meta_cap' => true,
	);
	register_post_type('vacatures', $args);
}
add_action('init', 'multiposter_register_cpt', 0);

function multiposter_add_meta_box() {
    add_meta_box(
        'job_details_meta_box', 
        __('Vacature details', 'multiposter'),
        'multiposter_meta_box_callback', 
        'vacatures',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'multiposter_add_meta_box');


function multiposter_meta_box_callback($post) {
    // Add a nonce field for security
    wp_nonce_field('save_job_details', 'job_details_nonce');

    $short_description = get_post_meta($post->ID, 'short_description', true);
    $requirements = get_post_meta($post->ID, 'requirements', true);
    $offer = get_post_meta($post->ID, 'offer', true);
    $city = get_post_meta($post->ID, 'city', true);
    $number = get_post_meta($post->ID, 'number', true);
    $date = get_post_meta($post->ID, 'date', true);
    $education = get_post_meta($post->ID, 'education', true);
    $employment = get_post_meta($post->ID, 'employment', true);
    $career_level = get_post_meta($post->ID, 'career_level', true);
    $hours = get_post_meta($post->ID, 'hours', true);
    $contract = get_post_meta($post->ID, 'contract', true);
    $salary = get_post_meta($post->ID, 'salary', true);
    $email = get_post_meta($post->ID, 'email', true);
    $contact = get_post_meta($post->ID, 'contact', true);
    $office_city = get_post_meta($post->ID, 'office_city', true);
    $office_email = get_post_meta($post->ID, 'office_email', true);
    $office_phone = get_post_meta($post->ID, 'office_phone', true);

    // Meta box fields
    echo '<div class="job-details-container">';

        echo '<div class="job-details-field col-1">';
            echo '<label for="short_description">' . esc_html__('Functieomschrijving', 'multiposter') . '</label>';
            echo '<textarea id="short_description" name="short_description" rows="4">' . esc_textarea($short_description) . '</textarea>';
        echo '</div>';


        echo '<div class="job-details-field col-1">';
            echo '<label for="requirements">' . esc_html__('Functie eisen', 'multiposter') . '</label>';
            echo '<textarea id="requirements" name="requirements" rows="4">' . esc_textarea($requirements) . '</textarea>';
        echo '</div>';

        echo '<div class="job-details-field col-1">';
            echo '<label for="offer">' . esc_html__('Wat bieden wij', 'multiposter') . '</label>';
            echo '<textarea id="offer" name="offer" rows="4">' . esc_textarea($offer) . '</textarea>';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="city">' . esc_html__('Werklocatie', 'multiposter') . '</label>';
            echo '<input type="text" id="city" name="city" value="' . esc_attr($city) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="number">' . esc_html__('Vacaturenummer', 'multiposter') . '</label>';
            echo '<input type="text" id="number" name="number" value="' . esc_attr($number) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="date">' . esc_html__('Vacaturedatum', 'multiposter') . '</label>';
            echo '<input type="text" id="date" name="date" value="' . esc_attr($date) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="education">' . esc_html__('Opleidingsniveau', 'multiposter') . '</label>';
            echo '<input type="text" id="education" name="education" value="' . esc_attr($education) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="career_level">' . esc_html__('Carrièreniveau', 'multiposter') . '</label>';
            echo '<input type="text" id="career_level" name="career_level" value="' . esc_attr($career_level) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="employment">' . esc_html__('Dienstverband', 'multiposter') . '</label>';
            echo '<input type="text" id="employment" name="employment" value="' . esc_attr($employment) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="hours">' . esc_html__('Uren', 'multiposter') . '</label>';
            echo '<input type="text" id="hours" name="hours" value="' . esc_attr($hours) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="contract">' . esc_html__('Contract', 'multiposter') . '</label>';
            echo '<input type="text" id="contract" name="contract" value="' . esc_attr($contract) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="salary">' . esc_html__('Salaris', 'multiposter') . '</label>';
            echo '<input type="text" id="salary" name="salary" value="' . esc_attr($salary) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="email">' . esc_html__('E-mailadres', 'multiposter') . '</label>';
            echo '<input type="text" id="email" name="email" value="' . esc_attr($email) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="contact">' . esc_html__('Behandelaar', 'multiposter') . '</label>';
            echo '<input type="text" id="contact" name="contact" value="' . esc_attr($contact) . '">';
        echo '</div>';

    echo '</div>';

    echo '<h2 class="jobs_page_heading">' . esc_html__('Vestiging', 'multiposter') . '</h2>';

    echo '<div class="job-details-container office-section">';

        echo '<div class="job-details-field">';
            echo '<label for="office_city">' . esc_html__('Plaats vestiging', 'multiposter') . '</label>';
            echo '<input type="text" id="office_city" name="office_city" value="' . esc_attr($office_city) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="office_email">' . esc_html__('E-mailadres vestiging', 'multiposter') . '</label>';
            echo '<input type="text" id="office_email" name="office_email" value="' . esc_attr($office_email) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="office_phone">' . esc_html__('Telefoon vestiging', 'multiposter') . '</label>';
            echo '<input type="text" id="office_phone" name="office_phone" value="' . esc_attr($office_phone) . '">';
        echo '</div>';

    echo '</div>';
}


function multiposter_save_meta_box($post_id) {
    // Check if nonce is set
    if (!isset($_POST['job_details_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['job_details_nonce'])), 'save_job_details')) {
        return $post_id;
    }

    // Check if the current user has permission to edit the post
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    // Prevent auto-save from overwriting data
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Fields to save
    $fields = [
        'short_description',
        'requirements',
        'offer',
        'city',
        'number',
        'date',
        'education',
        'employment',
        'career_level',
        'hours',
        'contract',
        'salary',
        'email',
        'contact',
        'office_city',
        'office_email',
        'office_phone',
    ];

    $html_fields = ['short_description', 'requirements', 'offer'];

    // Loop through the fields and save their values
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = in_array($field, $html_fields, true)
                ? wp_kses_post(wp_unslash($_POST[$field]))
                : sanitize_text_field(wp_unslash($_POST[$field]));
            update_post_meta($post_id, $field, $value);
        } else {
            delete_post_meta($post_id, $field);          // If field is not set, remove it
        }
    }
}
add_action('save_post', 'multiposter_save_meta_box');

// Auto-set date meta on new vacancy creation
add_action('save_post_vacatures', 'multiposter_set_default_date', 20, 2);
function multiposter_set_default_date($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    $date = get_post_meta($post_id, 'date', true);
    if (empty($date)) {
        update_post_meta($post_id, 'date', current_time('Y-m-d'));
    }
}

// Feature 3: Admin List Columns
add_filter('manage_vacatures_posts_columns', 'multiposter_admin_columns');
function multiposter_admin_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['multiposter_city'] = __('Plaats', 'multiposter');
            $new_columns['multiposter_hours'] = __('Uren', 'multiposter');
            $new_columns['multiposter_salary'] = __('Salaris', 'multiposter');
            $new_columns['multiposter_id_col'] = __('Multiposter ID', 'multiposter');
        }
    }
    return $new_columns;
}

add_action('manage_vacatures_posts_custom_column', 'multiposter_admin_column_content', 10, 2);
function multiposter_admin_column_content($column, $post_id) {
    switch ($column) {
        case 'multiposter_city':
            echo esc_html(get_post_meta($post_id, 'city', true));
            break;
        case 'multiposter_hours':
            echo esc_html(get_post_meta($post_id, 'hours', true));
            break;
        case 'multiposter_salary':
            echo esc_html(get_post_meta($post_id, 'salary', true));
            break;
        case 'multiposter_id_col':
            echo esc_html(get_post_meta($post_id, 'jobit_id', true));
            break;
    }
}

add_filter('manage_edit-vacatures_sortable_columns', 'multiposter_sortable_columns');
function multiposter_sortable_columns($columns) {
    $columns['multiposter_city'] = 'multiposter_city';
    $columns['multiposter_salary'] = 'multiposter_salary';
    return $columns;
}

add_action('pre_get_posts', 'multiposter_admin_column_orderby');
function multiposter_admin_column_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    $orderby = $query->get('orderby');
    if ($orderby === 'multiposter_city') {
        $query->set('meta_key', 'city');
        $query->set('orderby', 'meta_value');
    } elseif ($orderby === 'multiposter_salary') {
        $query->set('meta_key', 'salary');
        $query->set('orderby', 'meta_value');
    }
}

// Feature 5: Admin notice for failed sync
add_action('admin_notices', 'multiposter_sync_error_notice');
function multiposter_sync_error_notice() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'vacatures') return;
    $last_sync = get_option('multiposter_last_sync');
    if ($last_sync && isset($last_sync['status']) && $last_sync['status'] === 'error') {
        echo '<div class="notice notice-error"><p><strong>Multiposter:</strong> ' . esc_html($last_sync['message']) . ' (' . esc_html($last_sync['time']) . ')</p></div>';
    }
}

add_action('admin_menu', 'multiposter_settings_page');
function multiposter_settings_page() {
    add_submenu_page(
        'edit.php?post_type=vacatures',
        __('Instellingen', 'multiposter'),
        __('Instellingen', 'multiposter'),
        'manage_options',
        'vacatures__settings',
        'multiposter_settings_callback'
    );
}

function multiposter_settings_callback() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation on WP settings page, no data modification.
    $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
    $tabs = array(
        'general'   => __('Algemeen', 'multiposter'),
        'archive'   => __('Archief', 'multiposter'),
        'detail'    => __('Detailpagina', 'multiposter'),
        'form'      => __('Sollicitatieformulier', 'multiposter'),
        'registration' => __('Inschrijfformulier', 'multiposter'),
        'seo'       => __('SEO', 'multiposter'),
        'media'     => __('Afbeeldingen', 'multiposter'),
        'reference' => __('Shortcodes & Blocks', 'multiposter'),
    );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Multiposter instellingen', 'multiposter'); ?></h1>
        <style>#full-screen-loading { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; } .loading-spinner { font-size: 1.5em; color: #fff; }</style>

        <nav class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_key => $tab_label): ?>
                <a href="?post_type=vacatures&page=vacatures__settings&tab=<?php echo esc_attr($tab_key); ?>" class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html($tab_label); ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (!get_option('multiposter_api_key')): ?>
            <div class="notice notice-info"><p><?php esc_html_e('Configureer je Multiposter API-token om automatische vacature synchronisatie in te schakelen. Zonder API-token werkt de plugin in standalone modus: je kunt handmatig vacatures aanmaken en sollicitaties worden per e-mail verzonden.', 'multiposter'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="options.php" novalidate>
            <?php
            settings_fields('multiposter_settings_group');
            do_settings_sections('multiposter_settings');
            ?>

            <div style="<?php echo $active_tab !== 'general' ? 'display:none;' : ''; ?>">
            <table class="form-table">

                <tr valign="top">
                    <td colspan="2" style="padding-left: 0;">
                        <p class="description">
                            <strong><?php esc_html_e('Standalone modus', 'multiposter'); ?></strong>: <?php esc_html_e('Maak handmatig vacatures aan en ontvang sollicitaties per e-mail.', 'multiposter'); ?><br>
                            <strong><?php esc_html_e('Multiposter integratie', 'multiposter'); ?></strong>: <?php esc_html_e('Vul een API-token in om vacatures automatisch te synchroniseren en sollicitaties naar de Multiposter API te versturen.', 'multiposter'); ?>
                        </p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('API-token', 'multiposter'); ?></th>
                    <td>
                        <?php
                        $stored_key = get_option('multiposter_api_key', '');
                        $has_key = !empty($stored_key);
                        $masked_value = $has_key ? str_repeat('•', 12) . substr($stored_key, -4) : '';
                        ?>
                        <input type="password" name="multiposter_api_key" id="multiposter-api-key-input" value="<?php echo esc_attr($masked_value); ?>" style="width: 40%; min-width: 350px;" autocomplete="off" />
                        <em><?php esc_html_e('Vul hier je API-token in. Deze vind je via Instellingen > Koppelingen > API tokens', 'multiposter'); ?></em>
                    </td>
                </tr>
                <?php if (get_option('multiposter_api_key')): ?>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Vacatures verversen', 'multiposter'); ?></th>
                    <td>
                        <?php $api_intervals = get_option('multiposter_api_intervals', 30); ?>
                        <select name="multiposter_api_intervals">
                            <option value="2" <?php selected($api_intervals, 2); ?>><?php esc_html_e('Iedere 2 minuten', 'multiposter'); ?></option>
                            <option value="5" <?php selected($api_intervals, 5); ?>><?php esc_html_e('Iedere 5 minuten', 'multiposter'); ?></option>
                            <option value="10" <?php selected($api_intervals, 10); ?>><?php esc_html_e('Iedere 10 minuten', 'multiposter'); ?></option>
                            <option value="15" <?php selected($api_intervals, 15); ?>><?php esc_html_e('Iedere 15 minuten', 'multiposter'); ?></option>
                            <option value="30" <?php selected($api_intervals, 30); ?>><?php esc_html_e('Iedere 30 minuten', 'multiposter'); ?></option>
                            <option value="45" <?php selected($api_intervals, 45); ?>><?php esc_html_e('Iedere 45 minuten', 'multiposter'); ?></option>
                            <option value="60" <?php selected($api_intervals, 60); ?>><?php esc_html_e('Iedere 60 minuten', 'multiposter'); ?></option>
                            <option value="240" <?php selected($api_intervals, 240); ?>><?php esc_html_e('Iedere 4 uur', 'multiposter'); ?></option>
                            <option value="480" <?php selected($api_intervals, 480); ?>><?php esc_html_e('Iedere 8 uur', 'multiposter'); ?></option>
                            <option value="1440" <?php selected($api_intervals, 1440); ?>><?php esc_html_e('Iedere 24 uur', 'multiposter'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Laatste synchronisatie', 'multiposter'); ?></th>
                    <td>
                        <?php
                        $last_sync = get_option('multiposter_last_sync');
                        if ($last_sync) {
                            $status_class = $last_sync['status'] === 'success' ? 'color:green' : 'color:red';
                            echo '<span style="' . esc_attr($status_class) . '"><strong>' . esc_html(ucfirst($last_sync['status'])) . '</strong></span> - ';
                            echo esc_html($last_sync['message']) . '<br>';
                            echo '<small>' . esc_html($last_sync['time']) . '</small>';
                        } else {
                            esc_html_e('Nog niet gesynchroniseerd', 'multiposter');
                        }
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Vacature URL slug', 'multiposter'); ?></th>
                    <td>
                        <input type="text" name="multiposter_vacancy_slug" value="<?php echo esc_attr(get_option('multiposter_vacancy_slug', 'vacatures')); ?>" />
                        <em><?php esc_html_e('De URL slug voor vacatures (standaard: vacatures)', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Cache duur (seconden)', 'multiposter'); ?></th>
                    <td>
                        <input type="number" name="multiposter_cache_duration" value="<?php echo esc_attr(get_option('multiposter_cache_duration', 3600)); ?>" min="0" />
                        <em><?php esc_html_e('Slaat de vacature-archiefpagina op in cache om laadtijden te verbeteren. 0 = cache uitgeschakeld.', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Favorieten', 'multiposter'); ?></th>
                    <td>
                        <input type="hidden" name="multiposter_favorites_enabled" value="0" />
                        <input type="checkbox" name="multiposter_favorites_enabled" value="1" <?php checked(get_option('multiposter_favorites_enabled', 1), 1); ?> />
                        <em><?php esc_html_e('Toon favorieten functionaliteit', 'multiposter'); ?></em>
                    </td>
                </tr>

            </table>
            </div>

            <div style="<?php echo $active_tab !== 'archive' ? 'display:none;' : ''; ?>">
            <table class="form-table">

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Filters', 'multiposter'); ?></th>
                    <td>
                        <?php
                        $default_filters = array(
                            array('id' => 'keyword', 'label' => __('Zoeken', 'multiposter'), 'enabled' => 1),
                            array('id' => 'position', 'label' => __('Functie', 'multiposter'), 'enabled' => 1),
                            array('id' => 'city', 'label' => __('Plaats', 'multiposter'), 'enabled' => 1),
                            array('id' => 'salary', 'label' => __('Salaris', 'multiposter'), 'enabled' => 1),
                        );
                        $filters_config = get_option('multiposter_filters_config', $default_filters);
                        if (!is_array($filters_config)) {
                            $filters_config = $default_filters;
                        }
                        ?>
                        <ul id="multiposter-filters-sortable" style="list-style:none;padding:0;margin:0;">
                        <?php foreach ($filters_config as $i => $filter): ?>
                            <li style="padding:5px;margin:2px 0;background:#f9f9f9;border:1px solid #ddd;cursor:move;">
                                <span class="dashicons dashicons-menu" style="cursor:move;margin-right:5px;"></span>
                                <input type="hidden" name="multiposter_filters_config[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr($filter['id']); ?>">
                                <input type="hidden" name="multiposter_filters_config[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr($filter['label']); ?>">
                                <label>
                                    <input type="checkbox" name="multiposter_filters_config[<?php echo (int) $i; ?>][enabled]" value="1" <?php checked(!empty($filter['enabled']), true); ?>>
                                    <?php echo esc_html($filter['label']); ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <em><?php esc_html_e('Sleep om de volgorde te wijzigen, vink aan/uit om filters te tonen/verbergen.', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Standaard per pagina', 'multiposter'); ?></th>
                    <td>
                        <input type="number" name="multiposter_default_per_page" value="<?php echo esc_attr(get_option('multiposter_default_per_page', 10)); ?>" min="1" max="100" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Toon per-pagina selector', 'multiposter'); ?></th>
                    <td>
                        <input type="hidden" name="multiposter_show_per_page_selector" value="0" />
                        <input type="checkbox" name="multiposter_show_per_page_selector" value="1" <?php checked(get_option('multiposter_show_per_page_selector', 1), 1); ?> />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Per-pagina opties', 'multiposter'); ?></th>
                    <td>
                        <input type="text" name="multiposter_per_page_options" value="<?php echo esc_attr(get_option('multiposter_per_page_options', '10,25,50,100')); ?>" />
                        <em><?php esc_html_e('Komma-gescheiden (bijv. 10,25,50,100)', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Aantal kolommen', 'multiposter'); ?></th>
                    <td>
                        <select name="multiposter_archive_columns">
                            <?php $cols = get_option('multiposter_archive_columns', 1); ?>
                            <option value="1" <?php selected($cols, 1); ?>>1</option>
                            <option value="2" <?php selected($cols, 2); ?>>2</option>
                            <option value="3" <?php selected($cols, 3); ?>>3</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Afbeelding in vacaturekaart', 'multiposter'); ?></th>
                    <td>
                        <input type="hidden" name="multiposter_card_image_enabled" value="0" />
                        <label><input type="checkbox" name="multiposter_card_image_enabled" value="1" <?php checked(get_option('multiposter_card_image_enabled', 1), 1); ?> /> <?php esc_html_e('Toon afbeelding', 'multiposter'); ?></label>
                    </td>
                </tr>

            </table>
            </div>

            <div style="<?php echo $active_tab !== 'detail' ? 'display:none;' : ''; ?>">
            <table class="form-table">

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Maximum aantal afbeeldingen', 'multiposter'); ?></th>
                    <td>
                        <input type="number" name="multiposter_gallery_max_images" value="<?php echo esc_attr(get_option('multiposter_gallery_max_images', 0)); ?>" min="0" max="50" />
                        <em><?php esc_html_e('0 = geen limiet', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Gerelateerde vacatures', 'multiposter'); ?></th>
                    <td>
                        <input type="hidden" name="multiposter_related_enabled" value="0" />
                        <label><input type="checkbox" name="multiposter_related_enabled" value="1" <?php checked(get_option('multiposter_related_enabled', 1), 1); ?> /> <?php esc_html_e('Inschakelen', 'multiposter'); ?></label><br>
                        <label><?php esc_html_e('Aantal:', 'multiposter'); ?> <input type="number" name="multiposter_related_count" value="<?php echo esc_attr(get_option('multiposter_related_count', 3)); ?>" min="1" max="12" /></label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Gerelateerde vacatures criteria', 'multiposter'); ?></th>
                    <td>
                        <?php
                        $default_related_criteria = array(
                            array('id' => 'position', 'label' => 'Functie', 'enabled' => 1),
                            array('id' => 'city', 'label' => 'Plaats', 'enabled' => 1),
                            array('id' => 'employment', 'label' => 'Dienstverband', 'enabled' => 0),
                            array('id' => 'career_level', 'label' => 'Carrièreniveau', 'enabled' => 0),
                            array('id' => 'random', 'label' => 'Willekeurig', 'enabled' => 1),
                        );
                        $related_criteria = get_option('multiposter_related_criteria', $default_related_criteria);
                        if (!is_array($related_criteria) || empty($related_criteria) || !isset($related_criteria[0]['id'])) {
                            $related_criteria = $default_related_criteria;
                        }
                        ?>
                        <ul id="multiposter-related-criteria-sortable" style="list-style:none;padding:0;margin:0;">
                        <?php foreach ($related_criteria as $i => $criterion): ?>
                            <li style="padding:5px;margin:2px 0;background:#f9f9f9;border:1px solid #ddd;cursor:move;">
                                <span class="dashicons dashicons-menu" style="cursor:move;margin-right:5px;"></span>
                                <input type="hidden" name="multiposter_related_criteria[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr($criterion['id']); ?>">
                                <input type="hidden" name="multiposter_related_criteria[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr($criterion['label']); ?>">
                                <label>
                                    <input type="checkbox" name="multiposter_related_criteria[<?php echo (int) $i; ?>][enabled]" value="1" <?php checked(!empty($criterion['enabled']), true); ?>>
                                    <?php echo esc_html($criterion['label']); ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <em><?php esc_html_e('Sleep om de volgorde te wijzigen, vink aan/uit om criteria te tonen/verbergen.', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Deel knoppen', 'multiposter'); ?></th>
                    <td>
                        <input type="hidden" name="multiposter_share_enabled" value="0" />
                        <input type="checkbox" name="multiposter_share_enabled" value="1" <?php checked(get_option('multiposter_share_enabled', 1), 1); ?> />
                        <em><?php esc_html_e('Toon deel knoppen op vacature pagina', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Deel knoppen configuratie', 'multiposter'); ?></th>
                    <td>
                        <?php
                        $default_share_buttons = array(
                            array('id' => 'linkedin', 'label' => 'LinkedIn', 'enabled' => 1),
                            array('id' => 'facebook', 'label' => 'Facebook', 'enabled' => 1),
                            array('id' => 'twitter', 'label' => 'X', 'enabled' => 1),
                            array('id' => 'whatsapp', 'label' => 'WhatsApp', 'enabled' => 1),
                            array('id' => 'email', 'label' => 'Email', 'enabled' => 1),
                        );
                        $share_buttons = get_option('multiposter_share_buttons', $default_share_buttons);
                        if (!is_array($share_buttons) || empty($share_buttons) || !isset($share_buttons[0]['id'])) {
                            $share_buttons = $default_share_buttons;
                        }
                        ?>
                        <ul id="multiposter-share-buttons-sortable" style="list-style:none;padding:0;margin:0;">
                        <?php foreach ($share_buttons as $i => $btn): ?>
                            <li style="padding:5px;margin:2px 0;background:#f9f9f9;border:1px solid #ddd;cursor:move;">
                                <span class="dashicons dashicons-menu" style="cursor:move;margin-right:5px;"></span>
                                <input type="hidden" name="multiposter_share_buttons[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr($btn['id']); ?>">
                                <input type="hidden" name="multiposter_share_buttons[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr($btn['label']); ?>">
                                <label>
                                    <input type="checkbox" name="multiposter_share_buttons[<?php echo (int) $i; ?>][enabled]" value="1" <?php checked(!empty($btn['enabled']), true); ?>>
                                    <?php echo esc_html($btn['label']); ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <em><?php esc_html_e('Sleep om de volgorde te wijzigen, vink aan/uit om knoppen te tonen/verbergen.', 'multiposter'); ?></em>
                    </td>
                </tr>

            </table>
            </div>

            <div style="<?php echo $active_tab !== 'form' ? 'display:none;' : ''; ?>">
            <table class="form-table">

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Toon sollicitatieformulier bij vacatures', 'multiposter'); ?></th>
                    <td>
                        <?php $show_form = get_option('multiposter_show_form', 0); ?>
                        <input type="hidden" name="multiposter_show_form" value="0" />
                        <input type="checkbox" name="multiposter_show_form" value="1" <?php checked($show_form, 1); ?> />
                    </td>
                </tr>
                <tr valign="top" id="multiposter-form-fields-row">
                    <th scope="row"><?php esc_html_e('Formuliervelden', 'multiposter'); ?></th>
                    <td>
                        <?php
                        $default_form_fields = array(
                            array('id' => 'tel', 'label' => __('Telefoon', 'multiposter'), 'enabled' => 1, 'required' => 0),
                            array('id' => 'motivation', 'label' => __('Motivatie', 'multiposter'), 'enabled' => 1, 'required' => 0),
                            array('id' => 'resume', 'label' => __('CV upload', 'multiposter'), 'enabled' => 1, 'required' => 0),
                        );
                        $form_fields = get_option('multiposter_form_fields', $default_form_fields);
                        if (!is_array($form_fields) || !isset($form_fields[0]['id'])) {
                            $form_fields = $default_form_fields;
                        }
                        ?>
                        <ul id="multiposter-form-fields-sortable" style="list-style:none;padding:0;margin:0;">
                        <?php foreach ($form_fields as $i => $field): ?>
                            <li style="padding:5px;margin:2px 0;background:#f9f9f9;border:1px solid #ddd;cursor:move;">
                                <span class="dashicons dashicons-menu" style="cursor:move;margin-right:5px;"></span>
                                <input type="hidden" name="multiposter_form_fields[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr($field['id']); ?>">
                                <input type="hidden" name="multiposter_form_fields[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr($field['label']); ?>">
                                <label>
                                    <input type="checkbox" name="multiposter_form_fields[<?php echo (int) $i; ?>][enabled]" value="1" <?php checked(!empty($field['enabled']), true); ?>>
                                    <?php echo esc_html($field['label']); ?>
                                </label>
                                <label style="margin-left:10px;">
                                    <input type="checkbox" name="multiposter_form_fields[<?php echo (int) $i; ?>][required]" value="1" <?php checked(!empty($field['required']), true); ?>>
                                    <?php esc_html_e('Verplicht', 'multiposter'); ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <em><?php esc_html_e('Voornaam, achternaam en e-mail zijn altijd verplicht. Sleep om de volgorde te wijzigen.', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Stuur sollicitaties naar', 'multiposter'); ?></th>
                    <td>
                        <?php $email_mode = get_option('multiposter_form_email_mode', 'api_only'); ?>
                        <?php $has_api_key = (bool) get_option('multiposter_api_key'); ?>
                        <select name="multiposter_form_email_mode">
                            <option value="api_only" <?php selected($email_mode, 'api_only'); ?> <?php disabled(!$has_api_key); ?>><?php esc_html_e('Alleen Multiposter', 'multiposter'); ?></option>
                            <option value="email_only" <?php selected($email_mode, 'email_only'); ?>><?php esc_html_e('Alleen e-mail', 'multiposter'); ?></option>
                            <option value="both" <?php selected($email_mode, 'both'); ?> <?php disabled(!$has_api_key); ?>><?php esc_html_e('Multiposter en e-mail', 'multiposter'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Notificatie e-mailadres', 'multiposter'); ?></th>
                    <td>
                        <input type="email" name="multiposter_form_notification_email" value="<?php echo esc_attr(get_option('multiposter_form_notification_email', '')); ?>" />
                        <em><?php esc_html_e('Leeg = e-mail van vacature contactpersoon', 'multiposter'); ?></em>
                    </td>
                </tr>

            </table>
            </div>

            <div style="<?php echo $active_tab !== 'registration' ? 'display:none;' : ''; ?>">
            <table class="form-table">

                <tr valign="top" id="multiposter-registration-fields-row">
                    <th scope="row"><?php esc_html_e('Formuliervelden', 'multiposter'); ?></th>
                    <td>
                        <?php
                        $default_reg_fields = array(
                            array('id' => 'tel', 'label' => __('Telefoon', 'multiposter'), 'enabled' => 1, 'required' => 0),
                            array('id' => 'motivation', 'label' => __('Motivatie', 'multiposter'), 'enabled' => 1, 'required' => 0),
                            array('id' => 'resume', 'label' => __('CV upload', 'multiposter'), 'enabled' => 1, 'required' => 0),
                        );
                        $reg_fields = get_option('multiposter_registration_form_fields', $default_reg_fields);
                        if (!is_array($reg_fields) || !isset($reg_fields[0]['id'])) {
                            $reg_fields = $default_reg_fields;
                        }
                        ?>
                        <ul id="multiposter-registration-fields-sortable" style="list-style:none;padding:0;margin:0;">
                        <?php foreach ($reg_fields as $i => $field): ?>
                            <li style="padding:5px;margin:2px 0;background:#f9f9f9;border:1px solid #ddd;cursor:move;">
                                <span class="dashicons dashicons-menu" style="cursor:move;margin-right:5px;"></span>
                                <input type="hidden" name="multiposter_registration_form_fields[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr($field['id']); ?>">
                                <input type="hidden" name="multiposter_registration_form_fields[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr($field['label']); ?>">
                                <label>
                                    <input type="checkbox" name="multiposter_registration_form_fields[<?php echo (int) $i; ?>][enabled]" value="1" <?php checked(!empty($field['enabled']), true); ?>>
                                    <?php echo esc_html($field['label']); ?>
                                </label>
                                <label style="margin-left:10px;">
                                    <input type="checkbox" name="multiposter_registration_form_fields[<?php echo (int) $i; ?>][required]" value="1" <?php checked(!empty($field['required']), true); ?>>
                                    <?php esc_html_e('Verplicht', 'multiposter'); ?>
                                </label>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <em><?php esc_html_e('Voornaam, achternaam en e-mail zijn altijd verplicht. Sleep om de volgorde te wijzigen.', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Stuur inschrijvingen naar', 'multiposter'); ?></th>
                    <td>
                        <?php $reg_email_mode = get_option('multiposter_registration_email_mode', 'api_only'); ?>
                        <?php $has_api_key = (bool) get_option('multiposter_api_key'); ?>
                        <select name="multiposter_registration_email_mode">
                            <option value="api_only" <?php selected($reg_email_mode, 'api_only'); ?> <?php disabled(!$has_api_key); ?>><?php esc_html_e('Alleen Multiposter', 'multiposter'); ?></option>
                            <option value="email_only" <?php selected($reg_email_mode, 'email_only'); ?>><?php esc_html_e('Alleen e-mail', 'multiposter'); ?></option>
                            <option value="both" <?php selected($reg_email_mode, 'both'); ?> <?php disabled(!$has_api_key); ?>><?php esc_html_e('Multiposter en e-mail', 'multiposter'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Notificatie e-mailadres', 'multiposter'); ?></th>
                    <td>
                        <input type="email" name="multiposter_registration_notification_email" value="<?php echo esc_attr(get_option('multiposter_registration_notification_email', '')); ?>" />
                        <em><?php esc_html_e('Leeg = admin e-mailadres', 'multiposter'); ?></em>
                    </td>
                </tr>

            </table>
            </div>

            <div style="<?php echo $active_tab !== 'seo' ? 'display:none;' : ''; ?>">
            <table class="form-table">

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Titel template (vacature)', 'multiposter'); ?></th>
                    <td>
                        <input type="text" name="multiposter_seo_single_title" value="<?php echo esc_attr(get_option('multiposter_seo_single_title', '{title} - {city} | {site_name}')); ?>" style="width:100%;" />
                        <em><?php esc_html_e('Variabelen: {title}, {city}, {company}, {hours}, {salary}, {site_name}, {short_description}', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Meta beschrijving (vacature)', 'multiposter'); ?></th>
                    <td>
                        <input type="text" name="multiposter_seo_single_desc" value="<?php echo esc_attr(get_option('multiposter_seo_single_desc', '{short_description}')); ?>" style="width:100%;" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Titel template (archief)', 'multiposter'); ?></th>
                    <td>
                        <input type="text" name="multiposter_seo_archive_title" value="<?php echo esc_attr(get_option('multiposter_seo_archive_title', '')); ?>" style="width:100%;" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Meta beschrijving (archief)', 'multiposter'); ?></th>
                    <td>
                        <input type="text" name="multiposter_seo_archive_desc" value="<?php echo esc_attr(get_option('multiposter_seo_archive_desc', '')); ?>" style="width:100%;" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Open Graph tags', 'multiposter'); ?></th>
                    <td>
                        <input type="hidden" name="multiposter_og_enabled" value="0" />
                        <input type="checkbox" name="multiposter_og_enabled" value="1" <?php checked(get_option('multiposter_og_enabled', 1), 1); ?> />
                        <em><?php esc_html_e('Voeg OG/Twitter meta tags toe aan vacature pagina\'s', 'multiposter'); ?></em>
                    </td>
                </tr>

            </table>
            </div>

            <div style="<?php echo $active_tab !== 'media' ? 'display:none;' : ''; ?>">
            <table class="form-table">

                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Afbeelding conversie', 'multiposter'); ?></th>
                    <td>
                        <input type="text" name="multiposter_image_conversion" value="<?php echo esc_attr(get_option('multiposter_image_conversion', '')); ?>" <?php disabled(!get_option('multiposter_api_key')); ?> />
                        <em><?php esc_html_e('Vul een conversienaam in om die versie te gebruiken. Zorg ervoor dat je de conversie ingesteld hebt bij de Beeldbank instellingen.', 'multiposter'); ?></em>
                    </td>
                </tr>

            </table>
            </div>

            <div style="<?php echo $active_tab !== 'reference' ? 'display:none;' : ''; ?>">
            <table class="form-table">

                <tr valign="top">
                    <th colspan="2" style="text-align: left;">
                        <h2 style="margin: 0;"><?php esc_html_e('Shortcodes', 'multiposter'); ?></h2>
                    </th>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Archive pagina shortcode', 'multiposter'); ?></th>
                    <td>
                        <input type="text" value="[jobs_archive]" disabled/>
                        <em><?php esc_html_e('Gebruik deze shortcode om een lijst met vacatures te tonen', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Single pagina shortcode', 'multiposter'); ?></th>
                    <td>
                        <input type="text" value="[job_single]" disabled/>
                        <em><?php esc_html_e('Gebruik deze shortcode om een enkele vacature te tonen', 'multiposter'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th colspan="2" style="text-align: left;">
                        <h2 style="margin: 0;"><?php esc_html_e('Gutenberg Blocks', 'multiposter'); ?></h2>
                    </th>
                </tr>
                <tr valign="top">
                    <td colspan="2">
                        <ul style="list-style:disc;padding-left:20px;">
                            <li><strong>multiposter/vacancy-archive</strong> - <?php esc_html_e('Vacature archief met filters', 'multiposter'); ?></li>
                            <li><strong>multiposter/latest-vacancies</strong> - <?php esc_html_e('Laatste vacatures (grid/lijst)', 'multiposter'); ?></li>
                            <li><strong>multiposter/single-vacancy</strong> - <?php esc_html_e('Enkele vacature op ID', 'multiposter'); ?></li>
                            <li><strong>multiposter/vacancy-search</strong> - <?php esc_html_e('Vacature zoekbalk', 'multiposter'); ?></li>
                            <li><strong>multiposter/application-form</strong> - <?php esc_html_e('Sollicitatieformulier', 'multiposter'); ?></li>
                            <li><strong>multiposter/registration-form</strong> - <?php esc_html_e('Inschrijfformulier', 'multiposter'); ?></li>
                            <li><strong>multiposter/related-vacancies</strong> - <?php esc_html_e('Gerelateerde vacatures', 'multiposter'); ?></li>
                            <li><strong>multiposter/share-buttons</strong> - <?php esc_html_e('Deelknoppen', 'multiposter'); ?></li>
                            <li><strong>multiposter/vacancy-images</strong> - <?php esc_html_e('Vacature afbeeldingen grid', 'multiposter'); ?></li>
                        </ul>
                    </td>
                </tr>

            </table>
            </div>

            <?php if ($active_tab !== 'reference'): ?>
                <?php submit_button(__('Instellingen opslaan', 'multiposter')); ?>
            <?php endif; ?>

            <?php if ($active_tab === 'general' && get_option('multiposter_api_key')): ?>
                <button type="button" class="button button-secondary" id="feachjobsnow"><?php esc_html_e('Vacatures nu ophalen', 'multiposter'); ?></button>
                <div id="full-screen-loading" style="display: none;"> <div class="loading-spinner"><img src="<?php echo esc_url(plugins_url('assets/img/loading.gif', __FILE__)); ?>"/></div> </div>
            <?php endif; ?>

        </form>
    </div>
    <?php
}

// Register the setting
add_action('admin_init', 'multiposter_settings_init');
function multiposter_settings_init() {
    register_setting('multiposter_settings_group', 'multiposter_api_intervals', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('multiposter_settings_group', 'multiposter_api_key', array(
        'sanitize_callback' => 'multiposter_sanitize_api_key',
    ));
    register_setting('multiposter_settings_group', 'multiposter_show_form', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('multiposter_settings_group', 'multiposter_vacancy_slug', array(
        'sanitize_callback' => 'sanitize_title',
        'default' => 'vacatures',
    ));
    // Feature 7: Filter Control
    register_setting('multiposter_settings_group', 'multiposter_filters_config', array('sanitize_callback' => 'multiposter_sanitize_sortable_config'));
    // Feature 9: Pagination
    register_setting('multiposter_settings_group', 'multiposter_default_per_page', array('default' => 10, 'sanitize_callback' => 'absint'));
    register_setting('multiposter_settings_group', 'multiposter_show_per_page_selector', array('default' => 1, 'sanitize_callback' => 'absint'));
    register_setting('multiposter_settings_group', 'multiposter_per_page_options', array('default' => '10,25,50,100', 'sanitize_callback' => 'sanitize_text_field'));
    register_setting('multiposter_settings_group', 'multiposter_archive_columns', array('default' => 1, 'sanitize_callback' => 'absint'));
    register_setting('multiposter_settings_group', 'multiposter_card_image_enabled', array('default' => 1, 'sanitize_callback' => 'absint'));
    // Feature 11: Related Vacancies
    register_setting('multiposter_settings_group', 'multiposter_related_enabled', array('default' => 1, 'sanitize_callback' => 'absint'));
    register_setting('multiposter_settings_group', 'multiposter_related_count', array('default' => 3, 'sanitize_callback' => 'absint'));
    register_setting('multiposter_settings_group', 'multiposter_related_criteria', array('sanitize_callback' => 'multiposter_sanitize_sortable_config'));
    // Feature 12: Share Buttons
    register_setting('multiposter_settings_group', 'multiposter_gallery_max_images', array('default' => 0, 'sanitize_callback' => 'absint'));
    register_setting('multiposter_settings_group', 'multiposter_share_enabled', array('default' => 1, 'sanitize_callback' => 'absint'));
    register_setting('multiposter_settings_group', 'multiposter_share_buttons', array('sanitize_callback' => 'multiposter_sanitize_sortable_config'));
    // Feature 13: Application Form
    register_setting('multiposter_settings_group', 'multiposter_form_fields', array('sanitize_callback' => 'multiposter_sanitize_sortable_config'));
    register_setting('multiposter_settings_group', 'multiposter_form_email_mode', array('default' => 'api_only', 'sanitize_callback' => 'sanitize_text_field'));
    register_setting('multiposter_settings_group', 'multiposter_form_notification_email', array('sanitize_callback' => 'sanitize_email'));

    register_setting('multiposter_settings_group', 'multiposter_registration_form_fields', array('sanitize_callback' => 'multiposter_sanitize_sortable_config'));
    register_setting('multiposter_settings_group', 'multiposter_registration_email_mode', array('default' => 'api_only', 'sanitize_callback' => 'sanitize_text_field'));
    register_setting('multiposter_settings_group', 'multiposter_registration_notification_email', array('sanitize_callback' => 'sanitize_email'));
    // Feature 10: Favorites
    register_setting('multiposter_settings_group', 'multiposter_favorites_enabled', array('default' => 1, 'sanitize_callback' => 'absint'));
    // Feature 14: SEO
    register_setting('multiposter_settings_group', 'multiposter_seo_single_title', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('multiposter_settings_group', 'multiposter_seo_single_desc', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('multiposter_settings_group', 'multiposter_seo_archive_title', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('multiposter_settings_group', 'multiposter_seo_archive_desc', array('sanitize_callback' => 'sanitize_text_field'));
    // Feature 15: Open Graph
    register_setting('multiposter_settings_group', 'multiposter_og_enabled', array('default' => 1, 'sanitize_callback' => 'absint'));
    // Feature 16: Cache
    register_setting('multiposter_settings_group', 'multiposter_cache_duration', array('default' => 3600, 'sanitize_callback' => 'absint'));
    // Multi-image: Image conversion setting
    register_setting('multiposter_settings_group', 'multiposter_image_conversion', array('default' => '', 'sanitize_callback' => 'sanitize_text_field'));
}

function multiposter_sanitize_sortable_config($value) {
    if (!is_array($value)) return array();
    $sanitized = array();
    foreach ($value as $item) {
        if (!is_array($item) || empty($item['id'])) continue;
        $entry = array(
            'id' => sanitize_key($item['id']),
            'label' => sanitize_text_field($item['label'] ?? ''),
        );
        if (isset($item['enabled'])) {
            $entry['enabled'] = absint($item['enabled']);
        }
        if (isset($item['required'])) {
            $entry['required'] = absint($item['required']);
        }
        $sanitized[] = $entry;
    }
    return $sanitized;
}

function multiposter_sanitize_api_key($value) {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (strpos($value, '••••') === 0) {
        return get_option('multiposter_api_key', '');
    }
    return sanitize_text_field($value);
}

add_action('update_option_multiposter_favorites_enabled', function() { multiposter_invalidate_caches(); });
add_action('update_option_multiposter_card_image_enabled', function() { multiposter_invalidate_caches(); });
add_action('update_option_multiposter_vacancy_slug', 'multiposter_flush_rewrite_on_slug_change', 10, 2);
function multiposter_flush_rewrite_on_slug_change() {
    flush_rewrite_rules();
}


add_action('update_option_multiposter_api_key', 'multiposter_update_cron_schedule', 10, 2);
add_action('update_option_multiposter_api_intervals', 'multiposter_update_cron_schedule', 10, 2);
function multiposter_update_cron_schedule() {
    wp_clear_scheduled_hook('multiposter_custom_cron_event');
    $interval_minutes = (int) get_option('multiposter_api_intervals', 60);
    $interval_seconds = $interval_minutes * 60;
    if ($interval_seconds > 0) {
        wp_clear_scheduled_hook('multiposter_custom_interval');
        wp_schedule_event(time(), 'multiposter_custom_interval', 'multiposter_custom_cron_event');
    }
    
}


add_filter('cron_schedules', 'multiposter_custom_cron_schedule');
function multiposter_custom_cron_schedule($schedules) {
    $interval_minutes = (int) get_option('multiposter_api_intervals', 60);
    if(!$interval_minutes){
        $interval_minutes = 15;
    }
    $interval_seconds = $interval_minutes * 60;
    $schedules['multiposter_custom_interval'] = array(
        'interval' => $interval_seconds,
        'display'  => __('Multiposter Custom Interval', 'multiposter')
    );
    return $schedules;
}

add_action('multiposter_custom_cron_event', 'multiposter_sync_callback');



function multiposter_fetch_api($current_page) {
    $api_key = get_option('multiposter_api_key');
    $url = "https://app.jobit.nl/api/vacancies/channel/62?limit=100&page={$current_page}";
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'method' => 'GET',
        'timeout' => 30,
    );

    $max_retries = 3;
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            if ($attempt < $max_retries) {
                sleep(pow(2, $attempt));
                continue;
            }
            $error_msg = $response->get_error_message();
            update_option('multiposter_last_sync', array(
                'time' => current_time('mysql'),
                'status' => 'error',
                'message' => $error_msg,
                'count' => 0,
            ));
            multiposter_log_sync(0, 0, 0, $error_msg);
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 200 && $status_code < 300) {
            $response_body = wp_remote_retrieve_body($response);
            return json_decode($response_body, true);
        }

        if ($attempt < $max_retries) {
            sleep(pow(2, $attempt));
            continue;
        }

        // translators: %d is the HTTP status code.
        $error_msg = sprintf(__('API returned HTTP %d', 'multiposter'), $status_code);
        update_option('multiposter_last_sync', array(
            'time' => current_time('mysql'),
            'status' => 'error',
            'message' => $error_msg,
            'count' => 0,
        ));
        multiposter_log_sync(0, 0, 0, $error_msg);
        return null;
    }

    return null;
}

function multiposter_sync_callback() {
    $api_key = get_option('multiposter_api_key');
    if (!$api_key) return;

    do_action('multiposter_before_sync');

    $start_time = microtime(true);
    $current_page = get_option('multiposter_current_page', 1);
    $vacancies = multiposter_fetch_api($current_page);

    if ($vacancies === null) return; // Error already logged in multiposter_fetch_api

    if (empty($vacancies['data']['vacancies'])) {
        update_option('multiposter_current_page', 1);
        $vacancies = multiposter_fetch_api(1);
        if ($vacancies === null) return;
    }

    $jobs_list = isset($vacancies['data']['vacancies']) ? $vacancies['data']['vacancies'] : array();
    $sync_count = 0;

    if ($jobs_list) {
        foreach ($jobs_list as $job) {
            multiposter_insert_job($job);
            $sync_count++;
        }
    }

    $last_one = isset($vacancies['meta']['last_page']) ? $vacancies['meta']['last_page'] : 1;
    if ($current_page < $last_one) {
        update_option('multiposter_current_page', $current_page + 1);
    } else {
        update_option('multiposter_current_page', 1);
    }

    multiposter_update_expired_jobs();

    // Invalidate caches (Feature 16)
    multiposter_invalidate_caches();

    $duration = round(microtime(true) - $start_time, 2);
    update_option('multiposter_last_sync', array(
        'time' => current_time('mysql'),
        'status' => 'success',
        // translators: %d is the number of synced vacancies.
        'message' => sprintf(__('%d vacatures gesynchroniseerd', 'multiposter'), $sync_count),
        'count' => $sync_count,
        'duration' => $duration,
    ));

    // Feature 6: Log sync
    multiposter_log_sync($sync_count, 0, $duration);

    do_action('multiposter_after_sync', $sync_count, $duration);
}


function multiposter_attach_image_to_post($image_url, $post_id) {
    if (empty($image_url)) return;

    $existing_url = get_post_meta($post_id, 'multiposter_image_url', true);
    if ($existing_url === $image_url && has_post_thumbnail($post_id)) return;

    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');
    if (!is_wp_error($attachment_id)) {
        set_post_thumbnail($post_id, $attachment_id);
        update_post_meta($post_id, 'multiposter_image_url', $image_url);
    }
}

function multiposter_attach_images_to_post($media_items, $post_id) {
    if (empty($media_items) || !is_array($media_items)) return;

    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    $conversion = get_option('multiposter_image_conversion', '');
    $existing_media_ids = get_post_meta($post_id, 'multiposter_media_ids', true);
    if (!is_array($existing_media_ids)) {
        // Migration: if old meta exists but new doesn't, preserve existing attachment
        $existing_media_ids = array();
    }

    $new_media_ids = array();
    $is_first = true;

    foreach ($media_items as $item) {
        if (empty($item['id']) || empty($item['url'])) continue;

        $api_media_id = (string) $item['id'];
        $new_media_ids[] = $api_media_id;

        // Skip if already imported
        if (in_array($api_media_id, $existing_media_ids)) {
            $is_first = false;
            continue;
        }

        // Determine URL: use conversion if set and available, else original
        $image_url = $item['url'];
        if (!empty($conversion) && !empty($item['conversions'][$conversion])) {
            $image_url = $item['conversions'][$conversion];
        }

        $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');
        if (!is_wp_error($attachment_id)) {
            update_post_meta($attachment_id, 'multiposter_media_id', $api_media_id);

            // Set featured image only if none exists and this is the first image
            if ($is_first && !has_post_thumbnail($post_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }

        $is_first = false;
    }

    // Remove attachments no longer in the API response
    $removed_ids = array_diff($existing_media_ids, $new_media_ids);
    if (!empty($removed_ids)) {
        $existing_attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_parent' => $post_id,
            'posts_per_page' => -1,
            'post_status' => 'any',
        ));
        foreach ($existing_attachments as $att) {
            $att_media_id = get_post_meta($att->ID, 'multiposter_media_id', true);
            if ($att_media_id && in_array($att_media_id, $removed_ids)) {
                wp_delete_attachment($att->ID, true);
            }
        }
    }

    update_post_meta($post_id, 'multiposter_media_ids', $new_media_ids);
}

function multiposter_insert_job($job) {
    // Extract job details from the input array
    $title = $job['title'];
    $full_text = $job['full_text'];
    $description = $job['description'];
    $requirements = $job['requirements'];
    $offer = $job['offer'];
    $number = $job['number'];
    $city = $job['city'];
    $date = $job['date'];
    $education = $job['education'];
    $career_level = $job['career_level'];
    $employment = $job['employment'];
    $hours = $job['hours'];
    $contract = $job['contract'];
    $salary = $job['salary'];
    $email = $job['email'];
    $contact = $job['contact'];
    $office_city = $job['office']['city'];
    $office_email = $job['office']['email'];
    $office_phone = $job['office']['phone'];
    $contact = $contact['first_name'].' '.$contact['last_name'];
    $position = $job['position'];

    // Check if job post already exists by 'jobit_id'
    $existing_posts = get_posts([
        'post_type'   => 'vacatures',
        'meta_key'    => 'jobit_id',
        'meta_value'  => $job['id'],
        'numberposts' => 1
    ]);

    if(isset($existing_posts[0]->ID)){
        $post_id = $existing_posts[0]->ID;
        $post_data = [
            'ID'           => $post_id,
            'post_title'   => $title,
            'post_content' => $full_text,
            'post_excerpt' => $description
        ];
        wp_update_post($post_data);
    }
    else{
        $post_data = [
            'post_title'   => $title,
            'post_content' => $full_text,
            'post_excerpt' => $description,
            'post_type'    => 'vacatures',
            'post_status'  => 'publish'
        ];
        $post_id = wp_insert_post($post_data);
    }

    if($post_id){
        $meta = apply_filters('multiposter_job_meta', array(
            'short_description' => $description,
            'requirements'      => $requirements,
            'offer'             => $offer,
            'city'              => $city,
            'number'            => $number,
            'date'              => $date,
            'education'         => $education,
            'career_level'      => $career_level,
            'employment'        => $employment,
            'hours'             => $hours,
            'contract'          => $contract,
            'salary'            => $salary,
            'email'             => $email,
            'contact'           => $contact,
            'office_city'       => $office_city,
            'office_email'      => $office_email,
            'office_phone'      => $office_phone,
            'jobit_id'          => $job['id'],
            'position'          => $position,
        ), $job, $post_id);

        foreach ($meta as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        // Feature 8: Parse salary into numeric values for filtering
        if (!empty($salary)) {
            $nums = array();
            preg_match_all('/[\d]+(?:[.,]\d+)?/', str_replace('.', '', $salary), $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $num) {
                    $nums[] = intval(str_replace(',', '.', $num));
                }
            }
            if (!empty($nums)) {
                update_post_meta($post_id, 'salary_numeric_min', min($nums));
                update_post_meta($post_id, 'salary_numeric_max', max($nums));
            }
        } 
        $city_term = term_exists($city, 'cities');
        if (!$city_term) {
            $city_term = wp_insert_term($city, 'cities');
        }
        // Assign the city to the job post
        if (!is_wp_error($city_term)) {
            $city_term_id = is_array($city_term) ? $city_term['term_id'] : $city_term;
            wp_set_post_terms($post_id, $city_term_id, 'cities');
        }

        $position_term = term_exists($position, 'position');
        if (!$position_term) {
            $position_term = wp_insert_term($position, 'position');
        }
        if (!is_wp_error($position_term)) {
            $position_term_id = is_array($position_term) ? $position_term['term_id'] : $position_term;
            wp_set_post_terms($post_id, $position_term_id, 'position');
        }

        // Feature 4: Attach vacancy images (multi-image support)
        if (!empty($job['media']) && is_array($job['media'])) {
            multiposter_attach_images_to_post($job['media'], $post_id);
        }

        do_action('multiposter_job_imported', $post_id, $job);
    }
}



// Feature 6: Import Logging
register_activation_hook(__FILE__, 'multiposter_activate');
function multiposter_activate() {
    multiposter_register_cpt();
    flush_rewrite_rules();
    multiposter_create_log_table();
}
function multiposter_create_log_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'multiposter_import_log';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        sync_time datetime NOT NULL,
        vacancies_synced int(11) DEFAULT 0,
        vacancies_trashed int(11) DEFAULT 0,
        duration float DEFAULT 0,
        errors text,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function multiposter_log_sync($synced, $trashed, $duration, $errors = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'multiposter_import_log';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        multiposter_create_log_table();
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->insert($table, array(
        'sync_time' => current_time('mysql'),
        'vacancies_synced' => $synced,
        'vacancies_trashed' => $trashed,
        'duration' => $duration,
        'errors' => $errors,
    ));
    // Keep only the last 1000 log entries
    $safe_table = esc_sql($table);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DELETE FROM `$safe_table` WHERE id NOT IN (SELECT id FROM (SELECT id FROM `$safe_table` ORDER BY id DESC LIMIT 1000) AS keep)");
}

// Import Log admin page
add_action('admin_menu', 'multiposter_import_log_page');
function multiposter_import_log_page() {
    if (!get_option('multiposter_api_key')) return;
    add_submenu_page(
        'edit.php?post_type=vacatures',
        __('Import Log', 'multiposter'),
        __('Import Log', 'multiposter'),
        'manage_options',
        'multiposter_import_log',
        'multiposter_import_log_callback'
    );
}

function multiposter_import_log_callback() {
    global $wpdb;
    $table = $wpdb->prefix . 'multiposter_import_log';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination on admin page, no data modification.
    $page = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    $safe_table = esc_sql($table);
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $total = $wpdb->get_var("SELECT COUNT(*) FROM `$safe_table`");
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$safe_table` ORDER BY sync_time DESC LIMIT %d OFFSET %d", $per_page, $offset));
    $total_pages = ceil($total / $per_page);

    echo '<div class="wrap"><h1 style="display:inline-block;">' . esc_html__('Import Log', 'multiposter') . '</h1>';
    if (get_option('multiposter_api_key')) {
        echo ' <button class="button button-secondary" id="feachjobsnow" style="margin-left:10px;vertical-align:middle;">' . esc_html__('Vacatures nu ophalen', 'multiposter') . '</button>';
        echo '<div id="full-screen-loading" style="display: none;"> <div class="loading-spinner"><img src="' . esc_url(plugins_url('assets/img/loading.gif', __FILE__)) . '"/></div> </div>';
    }
    echo '<table class="widefat fixed striped"><thead><tr>';
    echo '<th>' . esc_html__('Datum', 'multiposter') . '</th>';
    echo '<th>' . esc_html__('Gesynchroniseerd', 'multiposter') . '</th>';
    echo '<th>' . esc_html__('Verwijderd', 'multiposter') . '</th>';
    echo '<th>' . esc_html__('Duur (s)', 'multiposter') . '</th>';
    echo '<th>' . esc_html__('Fouten', 'multiposter') . '</th>';
    echo '</tr></thead><tbody>';

    if ($logs) {
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log->sync_time) . '</td>';
            echo '<td>' . esc_html($log->vacancies_synced) . '</td>';
            echo '<td>' . esc_html($log->vacancies_trashed) . '</td>';
            echo '<td>' . esc_html($log->duration) . '</td>';
            echo '<td>' . esc_html($log->errors ?: '-') . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">' . esc_html__('Geen logs gevonden.', 'multiposter') . '</td></tr>';
    }

    echo '</tbody></table>';

    if ($total_pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $url = add_query_arg('log_page', $i);
            $class = $i === $page ? ' class="current"' : '';
            echo '<a' . esc_attr($class) . ' href="' . esc_url($url) . '">' . (int) $i . '</a> ';
        }
        echo '</div></div>';
    }
    echo '</div>';
}

function multiposter_update_expired_jobs() {
    $api_key = get_option('multiposter_api_key');
    if($api_key){
        $url = 'https://app.jobit.nl/api/vacancies/channel/62?limit=9999';

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'method' => 'GET',
        );
        $response = wp_remote_get($url, $args);
        $response_body = wp_remote_retrieve_body($response);
        $vacancies = json_decode($response_body,true);

        if(isset($vacancies['data']['vacancies'])){
            $jobs_list = $vacancies['data']['vacancies'];
            $jobs_id = array();
            foreach($jobs_list as $job){
                $jobs_id[] = $job['id'];
            }

            $query = new WP_Query(array(
                'post_type' => 'vacatures',
                'posts_per_page' => -1,
            ));
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $multiposter_id = get_post_meta($post_id, 'jobit_id', true);
                    if (!in_array($multiposter_id, $jobs_id)) {
                        wp_trash_post($post_id);
                    }
                }
                wp_reset_postdata();
            }
        }
    }
}


function multiposter_ajax_import_jobs() {
    check_ajax_referer('multiposter_import_jobs');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'), 403);
    }
    $res = multiposter_sync_callback();
    $response = array(
        'status' => 'success',
        'message' => 'AJAX request received successfully!',
        'response' => $res
    );
    wp_send_json($response);
    wp_die();
}
add_action('wp_ajax_multiposter_import_jobs_action', 'multiposter_ajax_import_jobs');



add_shortcode( 'jobs_archive', 'multiposter_shortcode_archive' );
function multiposter_shortcode_archive( $atts ) {
    ob_start();
    include_once('template/archive-jobs.php');
    $content = ob_get_clean();
    return $content;
}

add_filter('template_include', 'multiposter_custom_archive_template');
function multiposter_custom_archive_template($template) {
    if (is_post_type_archive('vacatures')) { // Replace 'jobs' with your post type name
        $plugin_template = plugin_dir_path(__FILE__) . 'template/archive-jobs.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}



function multiposter_register_taxonomies() {
    $labels = array(
        'name'              => _x( 'Plaatsen', 'taxonomy general name', 'multiposter' ),
        'singular_name'     => _x( 'Plaats', 'taxonomy singular name', 'multiposter' ),
        'search_items'      => __( 'Zoek plaats', 'multiposter' ),
        'all_items'         => __( 'Alle plaatsen', 'multiposter' ),
        'parent_item'       => __( 'Hoofd plaatsen', 'multiposter' ),
        'parent_item_colon' => __( 'Hoofd plaatsen:', 'multiposter' ),
        'edit_item'         => __( 'Bewerk plaats', 'multiposter' ),
        'update_item'       => __( 'Plaats opslaan', 'multiposter' ),
        'add_new_item'      => __( 'Plaats toevoegen', 'multiposter' ),
        'new_item_name'     => __( 'Nieuwe plaats naam', 'multiposter' ),
        'menu_name'         => __( 'Plaatsen', 'multiposter' ),
    );
    register_taxonomy( 'cities', 'vacatures', array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'city' ),
    ));

    $labels = array(
        'name'              => _x( 'Functies', 'taxonomy general name', 'multiposter' ),
        'singular_name'     => _x( 'Functie', 'taxonomy singular name', 'multiposter' ),
        'search_items'      => __( 'Zoek functie', 'multiposter' ),
        'all_items'         => __( 'Alle functies', 'multiposter' ),
        'parent_item'       => __( 'Hoofd functie', 'multiposter' ),
        'parent_item_colon' => __( 'Hoofd functie:', 'multiposter' ),
        'edit_item'         => __( 'Bewerk functie', 'multiposter' ),
        'update_item'       => __( 'Functie opslaan', 'multiposter' ),
        'add_new_item'      => __( 'Functie toevoegen', 'multiposter' ),
        'new_item_name'     => __( 'Nieuwe functie naam', 'multiposter' ),
        'menu_name'         => __( 'Functies', 'multiposter' ),
    );
    register_taxonomy( 'position', 'vacatures', array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'position' ),
    ));
}
add_action( 'init', 'multiposter_register_taxonomies', 0 );



// SVG icon helper
function multiposter_icon($name) {
    $icons = array(
        'map-pin' => '<svg class="multiposter-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>',
        'clock' => '<svg class="multiposter-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
        'euro' => '<svg class="multiposter-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 5.5A7 7 0 0 0 7 12a7 7 0 0 0 10 6.5"/><line x1="4" y1="10" x2="14" y2="10"/><line x1="4" y1="14" x2="14" y2="14"/></svg>',
        'heart' => '<svg class="multiposter-icon" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>',
        'briefcase' => '<svg class="multiposter-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>',
        'graduation' => '<svg class="multiposter-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"/></svg>',
        'calendar' => '<svg class="multiposter-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'phone' => '<svg class="multiposter-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.86 19.86 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.34a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.74-1.25a2 2 0 0 1 2.11-.45c.74.32 1.53.55 2.34.68A2 2 0 0 1 22 16.92z"/></svg>',
        'mail' => '<svg class="multiposter-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg>',
    );
    return isset($icons[$name]) ? $icons[$name] : '';
}

// Reusable job card renderer
function multiposter_render_job_card($job_id, $show_favorites = true) {
    $job_title = get_the_title($job_id);
    $short_description = get_post_meta($job_id, 'short_description', true);
    $link = get_permalink($job_id);
    $city = get_post_meta($job_id, 'city', true);
    $hours = get_post_meta($job_id, 'hours', true);
    $salary = get_post_meta($job_id, 'salary', true);

    $html = '<article class="multiposter-card" data-vacancy-id="' . esc_attr($job_id) . '">';
    $html .= '<a href="' . esc_url($link) . '" class="multiposter-card__link">';

    if (get_option('multiposter_card_image_enabled', 1) && has_post_thumbnail($job_id)) {
        $html .= '<div class="multiposter-card__thumb">' . get_the_post_thumbnail($job_id, 'medium', array('loading' => 'lazy')) . '</div>';
    }

    $html .= '<div class="multiposter-card__body">';
    $html .= '<h3 class="multiposter-card__title">' . esc_html($job_title) . '</h3>';
    if (!empty($short_description)) {
        $html .= '<p class="multiposter-card__excerpt">' . esc_html(wp_strip_all_tags($short_description)) . '</p>';
    }
    $html .= '</div>';

    $html .= '<div class="multiposter-card__meta">';
    if (!empty($city)) {
        $html .= '<span class="multiposter-card__meta-item">' . multiposter_icon('map-pin') . ' ' . esc_html($city) . '</span>';
    }
    if (!empty($hours)) {
        $html .= '<span class="multiposter-card__meta-item">' . multiposter_icon('clock') . ' ' . esc_html($hours) . '</span>';
    }
    if (!empty($salary)) {
        $html .= '<span class="multiposter-card__meta-item">' . multiposter_icon('euro') . ' ' . esc_html($salary) . '</span>';
    }
    if ($show_favorites) {
        $html .= '<span class="multiposter-card__meta-item"><button type="button" class="multiposter-favorite-btn" data-id="' . esc_attr($job_id) . '" aria-label="' . esc_attr__('Favoriet', 'multiposter') . '">' . multiposter_icon('heart') . '</button></span>';
    }
    $html .= '</div>';

    $html .= '</a>';
    $html .= '</article>';
    return apply_filters('multiposter_job_card_html', $html, $job_id);
}

// SSR: Render first page of vacancies server-side
function multiposter_render_archive_page_ssr($per_page, $show_favorites) {
    $cache_key = 'multiposter_ssr_page1_' . $per_page;
    $cache_duration = get_option('multiposter_cache_duration', 3600);
    if ($cache_duration > 0) {
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;
    }

    $query = new WP_Query(array(
        'post_type' => 'vacatures',
        'posts_per_page' => $per_page,
        'paged' => 1,
        'post_status' => 'publish',
    ));

    $html = '';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $html .= multiposter_render_job_card(get_the_ID(), $show_favorites);
        }
        wp_reset_postdata();
    } else {
        $html = '<p>' . esc_html__('Er zijn geen vacatures gevonden.', 'multiposter') . '</p>';
    }

    if ($cache_duration > 0) {
        set_transient($cache_key, $html, $cache_duration);
    }
    return $html;
}

function multiposter_render_archive_pagination_ssr($per_page) {
    $query = new WP_Query(array(
        'post_type' => 'vacatures',
        'posts_per_page' => $per_page,
        'paged' => 1,
        'post_status' => 'publish',
    ));

    if ($query->max_num_pages <= 1) return '';

    $pagination = paginate_links(array(
        'total' => $query->max_num_pages,
        'current' => 1,
        'prev_text' => __('&laquo; Vorige', 'multiposter'),
        'next_text' => __('Volgende &raquo;', 'multiposter'),
        'type' => 'array',
    ));

    if (!$pagination) return '';

    $html = '';
    foreach ($pagination as $page_link) {
        $html .= str_replace('<a ', '<a data-page="1" ', $page_link);
    }
    return $html;
}

function multiposter_ajax_archive() {
    check_ajax_referer('multiposter_archive');
    $default_per_page = get_option('multiposter_default_per_page', 10);
    $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : $default_per_page;
    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
    $selected_cities = isset($_POST['selectedCities']) ? array_map('intval', $_POST['selectedCities']) : [];
    $selected_postions = isset($_POST['selectedPostions']) ? array_map('intval', $_POST['selectedPostions']) : [];
    $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
    $salary_min = isset($_POST['salary_min']) ? intval($_POST['salary_min']) : 0;
    $salary_max = isset($_POST['salary_max']) ? intval($_POST['salary_max']) : 0;

    // Cache key uses only sanitized values (intval/sanitize_text_field above)
    $cache_duration = get_option('multiposter_cache_duration', 3600);
    $favorites_enabled = get_option('multiposter_favorites_enabled', 1);
    $cache_key = 'multiposter_archive_' . md5(serialize(array($posts_per_page, $paged, $selected_cities, $selected_postions, $keyword, $salary_min, $salary_max, $favorites_enabled)));
    if ($cache_duration > 0) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cached JSON output from our own render functions.
            echo $cached;
            wp_die();
        }
    }

    $args = array(
        'post_type'      => 'vacatures',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
    );

    $tax_queries = array();
    if (!empty($selected_cities)) {
        $tax_queries[] = array(
            'taxonomy' => 'cities',
            'field'    => 'term_id',
            'terms'    => $selected_cities,
        );
    }

    if (!empty($selected_postions)) {
        $tax_queries[] = array(
            'taxonomy' => 'position',
            'field'    => 'term_id',
            'terms'    => $selected_postions,
        );
    }
    if (!empty($tax_queries)) {
        $args['tax_query'] = array(
            'relation' => 'AND',
            ...$tax_queries,
        );
    }

    if (!empty($keyword)) {
        $args['s'] = $keyword;
    }

    // Feature 8: Salary filter
    if ($salary_min > 0 || $salary_max > 0) {
        $meta_query = array();
        if ($salary_min > 0) {
            $meta_query[] = array(
                'key' => 'salary_numeric_min',
                'value' => $salary_min,
                'compare' => '>=',
                'type' => 'NUMERIC',
            );
        }
        if ($salary_max > 0) {
            $meta_query[] = array(
                'key' => 'salary_numeric_max',
                'value' => $salary_max,
                'compare' => '<=',
                'type' => 'NUMERIC',
            );
        }
        if (count($meta_query) > 1) {
            $meta_query['relation'] = 'AND';
        }
        $args['meta_query'] = $meta_query;
    }

    $jobs_query = new WP_Query($args);

    // Start output buffering
    ob_start();

    if ($jobs_query->have_posts()) {
        while ($jobs_query->have_posts()) {
            $jobs_query->the_post();
            $job_id = get_the_ID();

            $job_title = get_the_title();
            $short_description = get_post_meta($job_id, 'short_description', true);
            $link = get_permalink($job_id);
            $city = get_post_meta($job_id, 'city', true);
            $hours = get_post_meta($job_id, 'hours', true);
            $salary = get_post_meta($job_id, 'salary', true);
            $date = get_post_meta($job_id, 'date', true);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output from multiposter_render_job_card() is pre-escaped.
            echo multiposter_render_job_card($job_id, $favorites_enabled);
            ?><?php
        }

        // Capture and end the HTML output buffer
        $html = ob_get_clean();

        // Generate pagination links
        $pagination = paginate_links(array(
            'total'     => $jobs_query->max_num_pages,
            'current'   => $paged,
            'prev_text' => __('&laquo; Vorige', 'multiposter'),
            'next_text' => __('Volgende &raquo;', 'multiposter'),
            'type'      => 'array' // Output as an array for easy manipulation
        ));
        
        // Transform pagination links to AJAX-friendly links
        if ($pagination) {
            $pagination_html = '';
            foreach ($pagination as $page_link) {
                $pagination_html .= str_replace('<a ', '<a data-page="1" ', $page_link);
            }
        }
        
        $result = json_encode(array(
            'html'       => $html,
            'pagination' => $pagination_html,
        ));
    } else {
        $html = ob_get_clean();
        $result = json_encode(array(
            'html' => '<p>' . esc_html__('Er zijn geen vacatures gevonden.', 'multiposter') . '</p>',
            'pagination' => ''
        ));
    }

    // Feature 16: Store in cache
    if ($cache_duration > 0) {
        set_transient($cache_key, $result, $cache_duration);
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded response with pre-escaped HTML.
    echo $result;
    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_multiposter_change_per_page', 'multiposter_ajax_archive');
add_action('wp_ajax_nopriv_multiposter_change_per_page', 'multiposter_ajax_archive');


function multiposter_single_content($content) {
    if (is_singular('vacatures')) {
        $job_id = get_the_ID();
        $city = get_post_meta($job_id, 'city', true);
        $salary = get_post_meta($job_id, 'salary', true);
        $hours = get_post_meta($job_id, 'hours', true);
        $career_level = get_post_meta($job_id, 'career_level', true);
        $date = get_post_meta($job_id, 'date', true);
        $employment = get_post_meta($job_id, 'employment', true);
        $email = get_post_meta($job_id, 'email', true);
        $contact = get_post_meta($job_id, 'contact', true);
        $office_phone = get_post_meta($job_id, 'office_phone', true);
        $show_form = get_option('multiposter_show_form');

        $custom_content = '<div class="multiposter-detail">';

        // Main content column
        $custom_content .= '<div class="multiposter-detail__content">';

        // Title + favorite button header
        $custom_content .= '<div class="multiposter-detail__header">';
        $custom_content .= '<h3 class="multiposter-detail__title">' . get_the_title($job_id) . '</h3>';
        if (get_option('multiposter_favorites_enabled', 1)) {
            $custom_content .= '<button type="button" class="multiposter-favorite-btn multiposter-favorite-btn--detail" data-id="' . esc_attr($job_id) . '" aria-label="' . esc_attr__('Favoriet', 'multiposter') . '">' . multiposter_icon('heart') . '</button>';
        }
        $custom_content .= '</div>';

        // Specifications as flat list
        $specs = array();
        if (!empty($employment)) {
            $specs[] = '<li>' . multiposter_icon('briefcase') . ' ' . esc_html($employment) . '</li>';
        }
        if (!empty($city)) {
            $specs[] = '<li>' . multiposter_icon('map-pin') . ' ' . esc_html($city) . '</li>';
        }
        if (!empty($hours)) {
            $specs[] = '<li>' . multiposter_icon('clock') . ' ' . esc_html($hours) . '</li>';
        }
        if (!empty($career_level)) {
            $specs[] = '<li>' . multiposter_icon('graduation') . ' ' . esc_html($career_level) . '</li>';
        }
        if (!empty($salary)) {
            $specs[] = '<li>' . multiposter_icon('euro') . ' ' . esc_html($salary) . '</li>';
        }
        if (!empty($date)) {
            $specs[] = '<li>' . multiposter_icon('calendar') . ' ' . esc_html(gmdate('d-m-Y', strtotime($date))) . '</li>';
        }
        if (!empty($specs)) {
            $custom_content .= '<ul class="multiposter-detail__specs">' . implode('', $specs) . '</ul>';
        }

        // Image gallery (show if 2+ images attached)
        $gallery_html = multiposter_render_image_gallery($job_id);
        if ($gallery_html) {
            $custom_content .= $gallery_html;
        }

        // Description
        $custom_content .= '<div class="multiposter-detail__description">' . $content . '</div>';

        // Share buttons
        if (get_option('multiposter_share_enabled', 1)) {
            $custom_content .= multiposter_render_share_buttons($job_id);
        }

        // Related vacancies
        if (get_option('multiposter_related_enabled', 1)) {
            $related_count = get_option('multiposter_related_count', 3);
            $custom_content .= multiposter_render_related_vacancies($job_id, $related_count);
        }

        $custom_content .= '</div>'; // .multiposter-detail__content

        // Sidebar
        $custom_content .= '<aside class="multiposter-detail__sidebar">';

        if ($show_form) {
            $custom_content .= '<section>';
            $custom_content .= '<h3>' . esc_html__('Solliciteren', 'multiposter') . '</h3>';
            $custom_content .= multiposter_render_application_form($job_id);
            $custom_content .= '</section>';
        }

        $custom_content .= '<section>';
        $custom_content .= '<h3>' . esc_html__('Vragen over deze vacature?', 'multiposter') . '</h3>';
        if (!empty($contact)) {
            $custom_content .= '<p>' . wp_kses_post($contact) . '</p>';
        }
        if (!empty($office_phone)) {
            $custom_content .= '<p><a href="tel:' . esc_attr($office_phone) . '">' . multiposter_icon('phone') . ' ' . esc_html($office_phone) . '</a></p>';
        }
        if (!empty($email)) {
            $custom_content .= '<p><a href="mailto:' . esc_attr($email) . '">' . multiposter_icon('mail') . ' ' . esc_html__('Stuur ons een mail', 'multiposter') . '</a></p>';
        }
        $custom_content .= '</section>';

        $custom_content .= '</aside>'; // .multiposter-detail__sidebar
        $custom_content .= '</div>'; // .multiposter-detail

        return $custom_content;
    }
    return $content;
}
add_filter('the_content', 'multiposter_single_content');
add_shortcode('job_single', 'multiposter_single_content');








function multiposter_job_posting_schema() {
    if (is_singular('vacatures')) { // Check if viewing a single 'vacatures' post
        $job_id = get_the_ID();
        $title = get_the_title($job_id);
        $city = get_post_meta($job_id, 'city', true);
        $salary = get_post_meta($job_id, 'salary', true);
        $hours = get_post_meta($job_id, 'hours', true);
        $career_level = get_post_meta($job_id, 'career_level', true);
        $date = get_post_meta($job_id, 'date', true);
        $employment = get_post_meta($job_id, 'employment', true);
        $short_description = wp_strip_all_tags(get_post_meta($job_id, 'short_description', true));
        $office_city = get_post_meta($job_id, 'office_city', true);
        $office_email = get_post_meta($job_id, 'office_email', true);
        $office_phone = get_post_meta($job_id, 'office_phone', true);
        
        // Format the date to ISO 8601
        $valid_through = gmdate('c', strtotime('+30 days')); // Set validity to 30 days from now
        $date_posted = !empty($date) ? gmdate('c', strtotime($date)) : gmdate('c');

        // Parse salary if available
        $salary_data = [
            "@type" => "MonetaryAmount",
            "currency" => "EUR",
            "value" => [
                "@type" => "QuantitativeValue",
                "value" => $salary,
                "unitText" => "MONTH"
            ]
        ];

        // Prepare data
        $schema_data = [
            "@context" => "https://schema.org/",
            "@type" => "JobPosting",
            "title" => $title,
            "description" => $short_description,
            "datePosted" => $date_posted,
            "validThrough" => $valid_through,
            "employmentType" => $employment,
            "hiringOrganization" => [
                "@type" => "Organization",
                "name" => get_bloginfo('name'),
                "sameAs" => home_url(),
                "logo" => get_site_icon_url()
            ],
            "jobLocation" => [
                "@type" => "Place",
                "address" => [
                    "@type" => "PostalAddress",
                    "addressLocality" => $city,
                    "addressCountry" => "NL"
                ]
            ]
        ];

        // Add salary if available
        if (!empty($salary)) {
            $schema_data["baseSalary"] = $salary_data;
        }

        // Add work hours if available
        if (!empty($hours)) {
            $schema_data["workHours"] = $hours;
        }

        // Add qualifications if available
        if (!empty($career_level)) {
            $schema_data["qualifications"] = $career_level;
        }

        // Add application contact info
        if (!empty($office_email) || !empty($office_phone)) {
            $schema_data["applicationContact"] = [
                "@type" => "ContactPoint",
                "email" => $office_email,
                "telephone" => $office_phone,
                "contactType" => "hiring"
            ];
        }

        ?>
        <script type="application/ld+json">
            <?php echo wp_json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
        </script>
        <?php
    }
}
add_action('wp_head', 'multiposter_job_posting_schema');

// =============================================
// Feature 11: Related Vacancies
// =============================================
function multiposter_get_related_vacancies($post_id, $count = 3) {
    $default_criteria = array(
        array('id' => 'position', 'label' => 'Functie', 'enabled' => 1),
        array('id' => 'city', 'label' => 'Plaats', 'enabled' => 1),
        array('id' => 'employment', 'label' => 'Dienstverband', 'enabled' => 0),
        array('id' => 'career_level', 'label' => 'Carrièreniveau', 'enabled' => 0),
        array('id' => 'random', 'label' => 'Willekeurig', 'enabled' => 1),
    );
    $criteria = get_option('multiposter_related_criteria', $default_criteria);
    if (!is_array($criteria) || empty($criteria) || !isset($criteria[0]['id'])) {
        $criteria = $default_criteria;
    }

    $collected = array();
    $exclude = array($post_id);

    foreach ($criteria as $criterion) {
        if (empty($criterion['enabled'])) continue;

        $new_posts = array();
        $remaining = $count - count($collected);
        if ($remaining <= 0) break;

        switch ($criterion['id']) {
            case 'position':
                $terms = wp_get_post_terms($post_id, 'position', array('fields' => 'ids'));
                if (!is_wp_error($terms) && !empty($terms)) {
                    $new_posts = get_posts(array(
                        'post_type' => 'vacatures',
                        'posts_per_page' => $remaining,
                        'post__not_in' => $exclude,
                        'tax_query' => array(array('taxonomy' => 'position', 'field' => 'term_id', 'terms' => $terms)),
                    ));
                }
                break;

            case 'city':
                $terms = wp_get_post_terms($post_id, 'cities', array('fields' => 'ids'));
                if (!is_wp_error($terms) && !empty($terms)) {
                    $new_posts = get_posts(array(
                        'post_type' => 'vacatures',
                        'posts_per_page' => $remaining,
                        'post__not_in' => $exclude,
                        'tax_query' => array(array('taxonomy' => 'cities', 'field' => 'term_id', 'terms' => $terms)),
                    ));
                }
                break;

            case 'employment':
                $meta_value = get_post_meta($post_id, 'employment', true);
                if (!empty($meta_value)) {
                    $new_posts = get_posts(array(
                        'post_type' => 'vacatures',
                        'posts_per_page' => $remaining,
                        'post__not_in' => $exclude,
                        'meta_query' => array(array('key' => 'employment', 'value' => $meta_value)),
                    ));
                }
                break;

            case 'career_level':
                $meta_value = get_post_meta($post_id, 'career_level', true);
                if (!empty($meta_value)) {
                    $new_posts = get_posts(array(
                        'post_type' => 'vacatures',
                        'posts_per_page' => $remaining,
                        'post__not_in' => $exclude,
                        'meta_query' => array(array('key' => 'career_level', 'value' => $meta_value)),
                    ));
                }
                break;

            case 'random':
                $new_posts = get_posts(array(
                    'post_type' => 'vacatures',
                    'posts_per_page' => $remaining,
                    'post__not_in' => $exclude,
                    'orderby' => 'rand',
                ));
                break;
        }

        foreach ($new_posts as $post) {
            $collected[] = $post;
            $exclude[] = $post->ID;
        }
    }

    return array_slice($collected, 0, $count);
}

function multiposter_render_related_vacancies($post_id, $count = 3) {
    $related = multiposter_get_related_vacancies($post_id, $count);
    if (empty($related)) return '';

    $html = '<div class="multiposter-related-vacancies">';
    $html .= '<h3>' . esc_html__('Gerelateerde vacatures', 'multiposter') . '</h3>';
    $html .= '<div class="multiposter-related-grid">';
    foreach ($related as $post) {
        $html .= multiposter_render_job_card($post->ID, false);
    }
    $html .= '</div></div>';
    return $html;
}

// =============================================
// Feature 12: Share Buttons
// =============================================
function multiposter_render_image_gallery($post_id) {
    $gallery_max = get_option('multiposter_gallery_max_images', 0);
    $gallery_images = get_posts(array(
        'post_type' => 'attachment',
        'post_parent' => $post_id,
        'post_mime_type' => 'image',
        'posts_per_page' => $gallery_max > 0 ? $gallery_max : -1,
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ));
    if (count($gallery_images) < 1) {
        return '';
    }
    $html = '<div class="vacancy-gallery">';
    foreach ($gallery_images as $img) {
        $thumb = wp_get_attachment_image_url($img->ID, 'medium');
        $full = wp_get_attachment_image_url($img->ID, 'full');
        $alt = get_post_meta($img->ID, '_wp_attachment_image_alt', true);
        $html .= '<a href="' . esc_url($full) . '" class="vacancy-gallery__item" data-lightbox>';
        $html .= '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($alt) . '" loading="lazy" />';
        $html .= '</a>';
    }
    $html .= '</div>';
    return $html;
}

function multiposter_render_share_buttons($post_id) {
    $url = urlencode(get_permalink($post_id));
    $title = urlencode(get_the_title($post_id));

    $default_share_buttons = array(
        array('id' => 'linkedin', 'label' => 'LinkedIn', 'enabled' => 1),
        array('id' => 'facebook', 'label' => 'Facebook', 'enabled' => 1),
        array('id' => 'twitter', 'label' => 'X', 'enabled' => 1),
        array('id' => 'whatsapp', 'label' => 'WhatsApp', 'enabled' => 1),
        array('id' => 'email', 'label' => 'Email', 'enabled' => 1),
    );
    $share_buttons = get_option('multiposter_share_buttons', $default_share_buttons);
    if (!is_array($share_buttons) || empty($share_buttons) || !isset($share_buttons[0]['id'])) {
        $share_buttons = $default_share_buttons;
    }

    $buttons = array(
        'linkedin' => array(
            'href' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url,
            'class' => 'multiposter-share-linkedin',
            'title' => 'LinkedIn',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
            'target' => '_blank',
        ),
        'facebook' => array(
            'href' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
            'class' => 'multiposter-share-facebook',
            'title' => 'Facebook',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
            'target' => '_blank',
        ),
        'twitter' => array(
            'href' => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
            'class' => 'multiposter-share-twitter',
            'title' => 'X',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
            'target' => '_blank',
        ),
        'whatsapp' => array(
            'href' => 'https://api.whatsapp.com/send?text=' . $title . '%20' . $url,
            'class' => 'multiposter-share-whatsapp',
            'title' => 'WhatsApp',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>',
            'target' => '_blank',
        ),
        'email' => array(
            'href' => 'mailto:?subject=' . $title . '&body=' . $url,
            'class' => 'multiposter-share-email',
            'title' => 'Email',
            'icon' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>',
            'target' => '',
        ),
    );

    $html = '<div class="multiposter-share-buttons">';
    $html .= '<span class="multiposter-share-label">' . esc_html__('Delen:', 'multiposter') . '</span>';

    foreach ($share_buttons as $btn) {
        if (empty($btn['enabled'])) continue;
        if (!isset($buttons[$btn['id']])) continue;

        $b = $buttons[$btn['id']];
        $target = $b['target'] ? ' target="' . $b['target'] . '" rel="noopener noreferrer"' : '';
        $html .= '<a href="' . $b['href'] . '"' . $target . ' class="multiposter-share-btn ' . $b['class'] . '" title="' . $b['title'] . '">';
        $html .= $b['icon'];
        $html .= '</a>';
    }

    $html .= '</div>';
    return apply_filters('multiposter_share_buttons_html', $html, $post_id);
}

// =============================================
// Feature 13: Built-in Application Form
// =============================================
function multiposter_render_application_form($post_id) {
    $multiposter_id = get_post_meta($post_id, 'jobit_id', true);
    $default_form_fields = array(
        array('id' => 'tel', 'label' => __('Telefoon', 'multiposter'), 'enabled' => 1, 'required' => 0),
        array('id' => 'motivation', 'label' => __('Motivatie', 'multiposter'), 'enabled' => 1, 'required' => 0),
        array('id' => 'resume', 'label' => __('CV upload', 'multiposter'), 'enabled' => 1, 'required' => 0),
    );
    $form_fields = get_option('multiposter_form_fields', $default_form_fields);
    if (!is_array($form_fields) || !isset($form_fields[0]['id'])) {
        $form_fields = $default_form_fields;
    }
    $form_fields = apply_filters('multiposter_form_fields', $form_fields, $post_id);

    $html = '<form id="multiposter-application-form" enctype="multipart/form-data">';
    $html .= wp_nonce_field('multiposter_apply_action', 'multiposter_apply_nonce', true, false);
    $html .= '<input type="hidden" name="vacancy_id" value="' . esc_attr($multiposter_id) . '">';
    $html .= '<input type="hidden" name="post_id" value="' . esc_attr($post_id) . '">';

    // Honeypot
    $html .= '<div style="display:none;"><input type="text" name="multiposter_hp" value="" tabindex="-1" autocomplete="off"></div>';

    // Required fields
    $html .= '<div class="multiposter-form-field"><label>' . esc_html__('Voornaam', 'multiposter') . ' *</label>';
    $html .= '<input type="text" name="first_name" required></div>';

    $html .= '<div class="multiposter-form-field"><label>' . esc_html__('Achternaam', 'multiposter') . ' *</label>';
    $html .= '<input type="text" name="last_name" required></div>';

    $html .= '<div class="multiposter-form-field"><label>' . esc_html__('E-mail', 'multiposter') . ' *</label>';
    $html .= '<input type="email" name="email" required></div>';

    // Optional fields in configured order
    foreach ($form_fields as $field) {
        if (empty($field['enabled'])) continue;
        $req = !empty($field['required']) ? ' required' : '';
        $star = $req ? ' *' : '';

        switch ($field['id']) {
            case 'tel':
                $html .= '<div class="multiposter-form-field"><label>' . esc_html__('Telefoon', 'multiposter') . $star . '</label>';
                $html .= '<input type="tel" name="tel"' . $req . '></div>';
                break;
            case 'motivation':
                $html .= '<div class="multiposter-form-field"><label>' . esc_html__('Motivatie', 'multiposter') . $star . '</label>';
                $html .= '<textarea name="motivation" rows="4"' . $req . '></textarea></div>';
                break;
            case 'resume':
                $html .= '<div class="multiposter-form-field"><label>' . esc_html__('CV uploaden', 'multiposter') . $star . '</label>';
                $html .= '<input type="file" name="resume" accept=".pdf,.doc,.docx"' . $req . '>';
                $html .= '<small>' . esc_html__('PDF, DOC of DOCX (max 5MB)', 'multiposter') . '</small></div>';
                break;
        }
    }

    $html .= '<div class="multiposter-form-message"></div>';
    $html .= '<button type="submit" class="button blue2ghost" data-label="' . esc_attr__('Versturen', 'multiposter') . '" data-loading="' . esc_attr__('Versturen...', 'multiposter') . '">' . esc_html__('Versturen', 'multiposter') . '</button>';
    $html .= '</form>';

    return apply_filters('multiposter_application_form_html', $html, $post_id);
}

// AJAX handler for application form
add_action('wp_ajax_multiposter_apply', 'multiposter_handle_application');
add_action('wp_ajax_nopriv_multiposter_apply', 'multiposter_handle_application');
function multiposter_handle_application() {
    check_ajax_referer('multiposter_apply_action', '_ajax_nonce');
    multiposter_check_rate_limit('apply');

    // Honeypot check
    if (!empty($_POST['multiposter_hp'])) {
        wp_send_json_error(array('message' => __('Spam gedetecteerd.', 'multiposter')));
    }

    $first_name = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
    $last_name = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
    $email_addr = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $tel = sanitize_text_field(wp_unslash($_POST['tel'] ?? ''));
    $motivation = sanitize_textarea_field(wp_unslash($_POST['motivation'] ?? ''));
    $vacancy_id = sanitize_text_field(wp_unslash($_POST['vacancy_id'] ?? ''));
    $post_id = intval($_POST['post_id'] ?? 0);

    if (empty($first_name) || empty($last_name) || empty($email_addr)) {
        wp_send_json_error(array('message' => __('Vul alle verplichte velden in.', 'multiposter')));
    }

    if (!is_email($email_addr)) {
        wp_send_json_error(array('message' => __('Ongeldig e-mailadres.', 'multiposter')));
    }

    // Handle file upload
    $resume_path = '';
    if (isset($_FILES['resume']) && !empty($_FILES['resume']['name'])) {
        $file = $_FILES['resume']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File handled via wp_handle_upload().
        $allowed_exts = array('pdf', 'doc', 'docx');
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (empty($filetype['ext']) || !in_array($filetype['ext'], $allowed_exts, true)) {
            wp_send_json_error(array('message' => __('Alleen PDF, DOC of DOCX bestanden zijn toegestaan.', 'multiposter')));
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('Bestand is te groot (max 5MB).', 'multiposter')));
        }
        $upload = wp_handle_upload($file, array('test_form' => false));
        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }
        $resume_path = $upload['file'];
    }

    $email_mode = get_option('multiposter_form_email_mode', 'api_only');
    $api_key = get_option('multiposter_api_key');

    // Force email mode when no API key is configured
    if (empty($api_key)) {
        $email_mode = 'email_only';
    }

    $api_success = true;

    // Send to API
    if ($email_mode === 'api_only' || $email_mode === 'both') {
        $boundary = wp_generate_password(24, false);

        $body = '';
        $fields = array(
            'type' => 'application',
            'vacancy_id' => $vacancy_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email_addr,
            'tel' => $tel,
            'motivation' => $motivation,
            'source' => 'wordpress',
            'channel_id' => '62',
        );

        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }

        if (!empty($resume_path) && file_exists($resume_path)) {
            $file_contents = file_get_contents($resume_path);
            $filename = basename($resume_path);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"resume\"; filename=\"{$filename}\"\r\n";
            $body .= "Content-Type: application/octet-stream\r\n\r\n";
            $body .= $file_contents . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post('https://app.jobit.nl/api/candidates', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body' => $body,
            'timeout' => 30,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
            $api_success = false;
        }
    }

    // Send email notification
    if ($email_mode === 'email_only' || $email_mode === 'both') {
        $notification_email = get_option('multiposter_form_notification_email', '');
        if (empty($notification_email) && $post_id) {
            $notification_email = get_post_meta($post_id, 'email', true);
        }
        if (empty($notification_email)) {
            $notification_email = get_option('admin_email');
        }
        if (!empty($notification_email)) {
            // translators: %s is the vacancy title.
            $subject = sprintf(__('Nieuwe sollicitatie: %s', 'multiposter'), get_the_title($post_id));
            $message = sprintf("%s %s\n%s: %s\n%s: %s\n\n%s",
                $first_name, $last_name,
                __('E-mail', 'multiposter'), $email_addr,
                __('Telefoon', 'multiposter'), $tel,
                $motivation
            );
            $attachments = !empty($resume_path) ? array($resume_path) : array();
            wp_mail($notification_email, $subject, $message, '', $attachments);
        }
    }

    if ($api_success || $email_mode === 'email_only') {
        do_action('multiposter_application_submitted', $post_id, $email_addr);
        wp_send_json_success(array('message' => __('Je sollicitatie is succesvol verzonden!', 'multiposter')));
    } else {
        wp_send_json_error(array('message' => __('Er is een fout opgetreden bij het verzenden. Probeer het later opnieuw.', 'multiposter')));
    }
}

// =============================================
// Registration Form
// =============================================
function multiposter_render_registration_form() {
    $default_form_fields = array(
        array('id' => 'tel', 'label' => __('Telefoon', 'multiposter'), 'enabled' => 1, 'required' => 0),
        array('id' => 'motivation', 'label' => __('Motivatie', 'multiposter'), 'enabled' => 1, 'required' => 0),
        array('id' => 'resume', 'label' => __('CV upload', 'multiposter'), 'enabled' => 1, 'required' => 0),
    );
    $form_fields = get_option('multiposter_registration_form_fields', $default_form_fields);
    if (!is_array($form_fields) || !isset($form_fields[0]['id'])) {
        $form_fields = $default_form_fields;
    }

    $html = '<form id="multiposter-registration-form" enctype="multipart/form-data">';
    $html .= wp_nonce_field('multiposter_register_action', 'multiposter_register_nonce', true, false);

    // Honeypot
    $html .= '<div style="display:none;"><input type="text" name="multiposter_hp" value="" tabindex="-1" autocomplete="off"></div>';

    // Required fields
    $html .= '<div class="multiposter-form-field"><label>' . esc_html__('Voornaam', 'multiposter') . ' *</label>';
    $html .= '<input type="text" name="first_name" required></div>';

    $html .= '<div class="multiposter-form-field"><label>' . esc_html__('Achternaam', 'multiposter') . ' *</label>';
    $html .= '<input type="text" name="last_name" required></div>';

    $html .= '<div class="multiposter-form-field"><label>' . esc_html__('E-mail', 'multiposter') . ' *</label>';
    $html .= '<input type="email" name="email" required></div>';

    // Optional fields in configured order
    foreach ($form_fields as $field) {
        if (empty($field['enabled'])) continue;
        $req = !empty($field['required']) ? ' required' : '';
        $star = $req ? ' *' : '';

        switch ($field['id']) {
            case 'tel':
                $html .= '<div class="multiposter-form-field"><label>' . esc_html__('Telefoon', 'multiposter') . $star . '</label>';
                $html .= '<input type="tel" name="tel"' . $req . '></div>';
                break;
            case 'motivation':
                $html .= '<div class="multiposter-form-field"><label>' . esc_html__('Motivatie', 'multiposter') . $star . '</label>';
                $html .= '<textarea name="motivation" rows="4"' . $req . '></textarea></div>';
                break;
            case 'resume':
                $html .= '<div class="multiposter-form-field"><label>' . esc_html__('CV uploaden', 'multiposter') . $star . '</label>';
                $html .= '<input type="file" name="resume" accept=".pdf,.doc,.docx"' . $req . '>';
                $html .= '<small>' . esc_html__('PDF, DOC of DOCX (max 5MB)', 'multiposter') . '</small></div>';
                break;
        }
    }

    $html .= '<div class="multiposter-form-message"></div>';
    $html .= '<button type="submit" class="button blue2ghost" data-label="' . esc_attr__('Registreren', 'multiposter') . '" data-loading="' . esc_attr__('Registreren...', 'multiposter') . '">' . esc_html__('Registreren', 'multiposter') . '</button>';
    $html .= '</form>';

    return apply_filters('multiposter_registration_form_html', $html);
}

// AJAX handler for registration form
add_action('wp_ajax_multiposter_register', 'multiposter_handle_registration');
add_action('wp_ajax_nopriv_multiposter_register', 'multiposter_handle_registration');
function multiposter_handle_registration() {
    check_ajax_referer('multiposter_register_action', '_ajax_nonce');
    multiposter_check_rate_limit('register');

    // Honeypot check
    if (!empty($_POST['multiposter_hp'])) {
        wp_send_json_error(array('message' => __('Spam gedetecteerd.', 'multiposter')));
    }

    $first_name = sanitize_text_field(wp_unslash($_POST['first_name'] ?? ''));
    $last_name = sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''));
    $email_addr = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $tel = sanitize_text_field(wp_unslash($_POST['tel'] ?? ''));
    $motivation = sanitize_textarea_field(wp_unslash($_POST['motivation'] ?? ''));

    if (empty($first_name) || empty($last_name) || empty($email_addr)) {
        wp_send_json_error(array('message' => __('Vul alle verplichte velden in.', 'multiposter')));
    }

    if (!is_email($email_addr)) {
        wp_send_json_error(array('message' => __('Ongeldig e-mailadres.', 'multiposter')));
    }

    // Handle file upload
    $resume_path = '';
    if (isset($_FILES['resume']) && !empty($_FILES['resume']['name'])) {
        $file = $_FILES['resume']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File handled via wp_handle_upload().
        $allowed_exts = array('pdf', 'doc', 'docx');
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        if (empty($filetype['ext']) || !in_array($filetype['ext'], $allowed_exts, true)) {
            wp_send_json_error(array('message' => __('Alleen PDF, DOC of DOCX bestanden zijn toegestaan.', 'multiposter')));
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('Bestand is te groot (max 5MB).', 'multiposter')));
        }
        $upload = wp_handle_upload($file, array('test_form' => false));
        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }
        $resume_path = $upload['file'];
    }

    $email_mode = get_option('multiposter_registration_email_mode', 'api_only');
    $api_key = get_option('multiposter_api_key');

    // Force email mode when no API key is configured
    if (empty($api_key)) {
        $email_mode = 'email_only';
    }

    $api_success = true;

    // Send to API
    if ($email_mode === 'api_only' || $email_mode === 'both') {
        $boundary = wp_generate_password(24, false);

        $body = '';
        $fields = array(
            'type' => 'registration',
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email_addr,
            'tel' => $tel,
            'motivation' => $motivation,
            'source' => 'wordpress',
            'channel_id' => '62',
        );

        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }

        if (!empty($resume_path) && file_exists($resume_path)) {
            $file_contents = file_get_contents($resume_path);
            $filename = basename($resume_path);
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"resume\"; filename=\"{$filename}\"\r\n";
            $body .= "Content-Type: application/octet-stream\r\n\r\n";
            $body .= $file_contents . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post('https://app.jobit.nl/api/candidates', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body' => $body,
            'timeout' => 30,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
            $api_success = false;
        }
    }

    // Send email notification
    if ($email_mode === 'email_only' || $email_mode === 'both') {
        $notification_email = get_option('multiposter_registration_notification_email', '');
        if (empty($notification_email)) {
            $notification_email = get_option('admin_email');
        }
        if (!empty($notification_email)) {
            $subject = __('Nieuwe registratie', 'multiposter');
            $message = sprintf("%s %s\n%s: %s\n%s: %s\n\n%s",
                $first_name, $last_name,
                __('E-mail', 'multiposter'), $email_addr,
                __('Telefoon', 'multiposter'), $tel,
                $motivation
            );
            $attachments = !empty($resume_path) ? array($resume_path) : array();
            wp_mail($notification_email, $subject, $message, '', $attachments);
        }
    }

    if ($api_success || $email_mode === 'email_only') {
        do_action('multiposter_registration_submitted', $email_addr);
        wp_send_json_success(array('message' => __('Je registratie is succesvol verzonden!', 'multiposter')));
    } else {
        wp_send_json_error(array('message' => __('Er is een fout opgetreden bij het verzenden. Probeer het later opnieuw.', 'multiposter')));
    }
}

// =============================================
// Feature 14: SEO Options
// =============================================
function multiposter_seo_replace_placeholders($template, $post_id) {
    $replacements = array(
        '{title}' => get_the_title($post_id),
        '{city}' => get_post_meta($post_id, 'city', true),
        '{company}' => get_bloginfo('name'),
        '{hours}' => get_post_meta($post_id, 'hours', true),
        '{salary}' => get_post_meta($post_id, 'salary', true),
        '{site_name}' => get_bloginfo('name'),
        '{short_description}' => wp_strip_all_tags(get_post_meta($post_id, 'short_description', true)),
    );
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

add_filter('document_title_parts', 'multiposter_seo_title');
function multiposter_seo_title($title_parts) {
    if (is_singular('vacatures')) {
        $template = get_option('multiposter_seo_single_title', '');
        if (!empty($template)) {
            $title_parts['title'] = multiposter_seo_replace_placeholders($template, get_the_ID());
        }
    } elseif (is_post_type_archive('vacatures')) {
        $template = get_option('multiposter_seo_archive_title', '');
        if (!empty($template)) {
            $title_parts['title'] = $template;
        }
    }
    return $title_parts;
}

add_action('wp_head', 'multiposter_seo_meta_description', 1);
function multiposter_seo_meta_description() {
    if (is_singular('vacatures')) {
        $template = get_option('multiposter_seo_single_desc', '');
        if (!empty($template)) {
            $desc = multiposter_seo_replace_placeholders($template, get_the_ID());
            echo '<meta name="description" content="' . esc_attr(wp_trim_words($desc, 30)) . '">' . "\n";
        }
    } elseif (is_post_type_archive('vacatures')) {
        $desc = get_option('multiposter_seo_archive_desc', '');
        if (!empty($desc)) {
            echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
        }
    }
}

// =============================================
// Feature 15: Open Graph Meta Tags
// =============================================
add_action('wp_head', 'multiposter_og_tags', 2);
function multiposter_og_tags() {
    if (!get_option('multiposter_og_enabled', 1)) return;
    if (!is_singular('vacatures')) return;

    $post_id = get_the_ID();
    $title = get_the_title($post_id);
    $desc = wp_trim_words(wp_strip_all_tags(get_post_meta($post_id, 'short_description', true)), 30);
    $url = get_permalink($post_id);
    $image = get_the_post_thumbnail_url($post_id, 'large');

    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_attr($url) . '">' . "\n";
    if ($image) {
        echo '<meta property="og:image" content="' . esc_attr($image) . '">' . "\n";
    }

    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";
    if ($image) {
        echo '<meta name="twitter:image" content="' . esc_attr($image) . '">' . "\n";
    }
}

// =============================================
// Feature 16: Cache Invalidation
// =============================================
function multiposter_invalidate_caches() {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_multiposter_archive_%' OR option_name LIKE '_transient_timeout_multiposter_archive_%' OR option_name LIKE '_transient_multiposter_ssr_%' OR option_name LIKE '_transient_timeout_multiposter_ssr_%'");
}

// =============================================
// Feature 17: Gutenberg Blocks
// =============================================
add_action('init', 'multiposter_register_blocks');
function multiposter_register_blocks() {
    $blocks_dir = plugin_dir_path(__FILE__) . 'assets/js/blocks/';
    if (!file_exists($blocks_dir)) return;

    // Vacancy Archive Block
    if (file_exists($blocks_dir . 'vacancy-archive.js')) {
        wp_register_script('multiposter-block-archive', plugins_url('assets/js/blocks/vacancy-archive.js', __FILE__), array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'), '2.0.0', true);
        register_block_type('multiposter/vacancy-archive', array(
            'editor_script' => 'multiposter-block-archive',
            'render_callback' => 'multiposter_block_archive_render',
            'attributes' => array(
                'postsPerPage' => array('type' => 'number', 'default' => 10),
                'showFilters' => array('type' => 'boolean', 'default' => true),
            ),
        ));
    }

    // Latest Vacancies Block
    if (file_exists($blocks_dir . 'latest-vacancies.js')) {
        wp_register_script('multiposter-block-latest', plugins_url('assets/js/blocks/latest-vacancies.js', __FILE__), array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'), '2.0.0', true);
        register_block_type('multiposter/latest-vacancies', array(
            'editor_script' => 'multiposter-block-latest',
            'render_callback' => 'multiposter_block_latest_render',
            'attributes' => array(
                'count' => array('type' => 'number', 'default' => 3),
                'layout' => array('type' => 'string', 'default' => 'grid'),
            ),
        ));
    }

    // Single Vacancy Block
    if (file_exists($blocks_dir . 'single-vacancy.js')) {
        wp_register_script('multiposter-block-single', plugins_url('assets/js/blocks/single-vacancy.js', __FILE__), array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'), '2.0.0', true);
        register_block_type('multiposter/single-vacancy', array(
            'editor_script' => 'multiposter-block-single',
            'render_callback' => 'multiposter_block_single_render',
            'attributes' => array(
                'vacancyId' => array('type' => 'string', 'default' => ''),
            ),
        ));
    }

    // Vacancy Search Block
    if (file_exists($blocks_dir . 'vacancy-search.js')) {
        wp_register_script('multiposter-block-search', plugins_url('assets/js/blocks/vacancy-search.js', __FILE__), array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n'), '2.0.0', true);
        register_block_type('multiposter/vacancy-search', array(
            'editor_script' => 'multiposter-block-search',
            'render_callback' => 'multiposter_block_search_render',
        ));
    }

    // Application Form Block
    $context_deps = array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data');

    if (file_exists($blocks_dir . 'application-form.js')) {
        wp_register_script('multiposter-block-application-form', plugins_url('assets/js/blocks/application-form.js', __FILE__), $context_deps, '2.0.0', true);
        register_block_type('multiposter/application-form', array(
            'editor_script' => 'multiposter-block-application-form',
            'render_callback' => 'multiposter_block_application_form_render',
            'attributes' => array(
                'vacancyId' => array('type' => 'string', 'default' => ''),
            ),
        ));
    }

    // Vacancy Images Block
    if (file_exists($blocks_dir . 'vacancy-images.js')) {
        wp_register_script('multiposter-block-vacancy-images', plugins_url('assets/js/blocks/vacancy-images.js', __FILE__), $context_deps, '2.0.0', true);
        register_block_type('multiposter/vacancy-images', array(
            'editor_script' => 'multiposter-block-vacancy-images',
            'render_callback' => 'multiposter_block_images_render',
            'attributes' => array(
                'vacancyId' => array('type' => 'string', 'default' => ''),
            ),
        ));
    }

    // Share Buttons Block
    if (file_exists($blocks_dir . 'share-buttons.js')) {
        wp_register_script('multiposter-block-share-buttons', plugins_url('assets/js/blocks/share-buttons.js', __FILE__), $context_deps, '2.0.0', true);
        register_block_type('multiposter/share-buttons', array(
            'editor_script' => 'multiposter-block-share-buttons',
            'render_callback' => 'multiposter_block_share_render',
            'attributes' => array(
                'vacancyId' => array('type' => 'string', 'default' => ''),
            ),
        ));
    }

    // Related Vacancies Block
    if (file_exists($blocks_dir . 'related-vacancies.js')) {
        wp_register_script('multiposter-block-related-vacancies', plugins_url('assets/js/blocks/related-vacancies.js', __FILE__), $context_deps, '2.0.0', true);
        register_block_type('multiposter/related-vacancies', array(
            'editor_script' => 'multiposter-block-related-vacancies',
            'render_callback' => 'multiposter_block_related_render',
            'attributes' => array(
                'vacancyId' => array('type' => 'string', 'default' => ''),
                'count' => array('type' => 'number', 'default' => 3),
            ),
        ));
    }

    // Registration Form Block
    if (file_exists($blocks_dir . 'registration-form.js')) {
        wp_register_script('multiposter-block-registration-form', plugins_url('assets/js/blocks/registration-form.js', __FILE__), array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n'), '2.0.0', true);
        register_block_type('multiposter/registration-form', array(
            'editor_script' => 'multiposter-block-registration-form',
            'render_callback' => 'multiposter_block_registration_form_render',
        ));
    }
}

function multiposter_block_registration_form_render() {
    return multiposter_render_registration_form();
}

function multiposter_block_archive_render($attributes) {
    ob_start();
    include(plugin_dir_path(__FILE__) . 'template/archive-jobs.php');
    return ob_get_clean();
}

function multiposter_block_latest_render($attributes) {
    $count = isset($attributes['count']) ? intval($attributes['count']) : 3;
    $layout = isset($attributes['layout']) ? $attributes['layout'] : 'grid';

    $posts = get_posts(array(
        'post_type' => 'vacatures',
        'posts_per_page' => $count,
        'post_status' => 'publish',
    ));

    if (empty($posts)) return '<p>' . esc_html__('Er zijn geen vacatures gevonden.', 'multiposter') . '</p>';

    $class = $layout === 'grid' ? 'multiposter-latest-grid' : 'multiposter-latest-list';
    $html = '<div class="multiposter-latest-vacancies ' . esc_attr($class) . '">';
    foreach ($posts as $post) {
        $html .= multiposter_render_job_card($post->ID, false);
    }
    $html .= '</div>';
    return $html;
}

function multiposter_block_single_render($attributes) {
    $vacancy_id = isset($attributes['vacancyId']) ? intval($attributes['vacancyId']) : 0;
    if (!$vacancy_id) return '<p>' . esc_html__('Selecteer een vacature.', 'multiposter') . '</p>';

    $post = get_post($vacancy_id);
    if (!$post || $post->post_type !== 'vacatures') return '<p>' . esc_html__('Vacature niet gevonden.', 'multiposter') . '</p>';

    return multiposter_render_job_card($vacancy_id, false);
}

function multiposter_block_search_render($attributes) {
    ob_start();
    $default_filters = array(
        array('id' => 'keyword', 'label' => __('Zoeken', 'multiposter'), 'enabled' => 1),
        array('id' => 'position', 'label' => __('Functie', 'multiposter'), 'enabled' => 1),
        array('id' => 'city', 'label' => __('Plaats', 'multiposter'), 'enabled' => 1),
    );
    $filters_config = get_option('multiposter_filters_config', $default_filters);
    if (!is_array($filters_config)) {
        $filters_config = $default_filters;
    }
    $vacancy_slug = get_option('multiposter_vacancy_slug', 'vacatures');
    ?>
    <div class="multiposter-search-widget">
        <form action="<?php echo esc_url(home_url('/' . $vacancy_slug . '/')); ?>" method="get">
            <?php foreach ($filters_config as $filter):
                if (empty($filter['enabled']) || $filter['id'] !== 'keyword') continue; ?>
                <input type="text" name="s" placeholder="<?php esc_attr_e('Zoek vacatures...', 'multiposter'); ?>" class="multiposter-search-input">
            <?php endforeach; ?>
            <button type="submit" class="button blue2ghost"><?php esc_html_e('Zoeken', 'multiposter'); ?></button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function multiposter_resolve_vacancy_id($attributes) {
    $vacancy_id = !empty($attributes['vacancyId']) ? intval($attributes['vacancyId']) : 0;
    if (!$vacancy_id) {
        $vacancy_id = get_the_ID();
    }
    if (!$vacancy_id) return 0;
    $post = get_post($vacancy_id);
    if (!$post || $post->post_type !== 'vacatures') return 0;
    return $vacancy_id;
}

function multiposter_block_application_form_render($attributes) {
    $vacancy_id = multiposter_resolve_vacancy_id($attributes);
    if (!$vacancy_id) {
        return '<p>' . esc_html__('Geen vacature gevonden.', 'multiposter') . '</p>';
    }
    return multiposter_render_application_form($vacancy_id);
}

function multiposter_block_images_render($attributes) {
    $vacancy_id = multiposter_resolve_vacancy_id($attributes);
    if (!$vacancy_id) {
        return '<p>' . esc_html__('Geen vacature gevonden.', 'multiposter') . '</p>';
    }
    $html = multiposter_render_image_gallery($vacancy_id);
    return $html ?: '<p>' . esc_html__('Geen afbeeldingen gevonden.', 'multiposter') . '</p>';
}

function multiposter_block_share_render($attributes) {
    $vacancy_id = multiposter_resolve_vacancy_id($attributes);
    if (!$vacancy_id) {
        return '<p>' . esc_html__('Geen vacature gevonden.', 'multiposter') . '</p>';
    }
    return multiposter_render_share_buttons($vacancy_id);
}

function multiposter_block_related_render($attributes) {
    $vacancy_id = multiposter_resolve_vacancy_id($attributes);
    if (!$vacancy_id) {
        return '<p>' . esc_html__('Geen vacature gevonden.', 'multiposter') . '</p>';
    }
    $count = isset($attributes['count']) ? intval($attributes['count']) : 3;
    return multiposter_render_related_vacancies($vacancy_id, $count);
}

