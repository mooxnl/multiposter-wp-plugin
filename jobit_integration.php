<?php
/**
 * Plugin Name:       Multiposter
 * Version:           1.0
 * Description:       Publiceer jouw vacatures vanuit Multiposter op je eigen Wordpress website.
 * Author:            Multiposter
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 */

defined( 'ABSPATH' ) || exit;

function my_plugin_enqueue_scripts() {
    // Register the script with a unique handle, file path, and any dependencies
    wp_register_script(
        'Jobit-script-js',
        plugins_url('assets/js/jobit.js', __FILE__), 
        array('jquery'), 
        '1.0.0', 
        true 
    );
    // Enqueue the registered script
    wp_enqueue_script('Jobit-script-js');
    wp_localize_script('Jobit-script-js', 'my_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));

    wp_enqueue_style(
        'Jobit-style-css',
        plugins_url('assets/css/jobit.css', __FILE__), 
        array(), 
        '1.0.0'
    );
}
// Hook into WordPress
add_action('admin_enqueue_scripts', 'my_plugin_enqueue_scripts');


function jobit_enqueue_scripts_scripts() {
    wp_enqueue_style(
        'jobit-style-css',
        plugins_url('assets/css/jobit.css', __FILE__), 
        array(), 
        '1.0.0'
    );
    wp_enqueue_script(
        'font-awesome', // Unique handle for this stylesheet
        'https://kit.fontawesome.com/278986c12c.js', 
        array('jquery'), 
        '6.6.0' // Version number
    );
    wp_register_script(
        'jobit-script-front-js',
        plugins_url('assets/js/jobit-front.js', __FILE__), 
        array('jquery'), 
        '2.0.0', 
        true 
    );
    // Enqueue the registered script
    wp_enqueue_script('jobit-script-front-js');
    wp_localize_script('jobit-script-front-js', 'my_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
// Hook into WordPress
add_action('wp_enqueue_scripts', 'jobit_enqueue_scripts_scripts');


function create_jobs_Jobit_cpt() {
	$labels = array(
		'name' => __('Multiposter', 'jobit-integration'),
		'singular_name' => __('Multiposter', 'jobit-integration'),
		'menu_name' => __('Multiposter', 'jobit-integration'),
		'all_items' => __('Alle vacatures', 'jobit-integration'),
		'add_new_item' => __('Nieuwe vacature toevoegen', 'jobit-integration'),
		'add_new' => __('Nieuw', 'jobit-integration'),
		'edit_item' => __('Vacature bewerken', 'jobit-integration'),
		'update_item' => __('Update vacature', 'jobit-integration'),
		'view_item' => __('Bekijk vacature', 'jobit-integration'),
	);
	$args = array(
		'label' => __('Multiposter', 'jobit-integration'),
		'description' => __('Vacature overzicht Multiposter', 'jobit-integration'),
		'labels' => $labels,
		'supports' => array('title', 'editor'),
		'public' => true,
		'show_in_menu' => true,
		'menu_position' => 5,
		'has_archive' => true,
		'capability_type' => 'post',
        'menu_icon' => 'dashicons-rss',
        'show_in_rest' => true,
	);
	register_post_type('vacatures', $args);
}
add_action('init', 'create_jobs_Jobit_cpt', 0);

function add_job_details_meta_box() {
    add_meta_box(
        'job_details_meta_box', 
        __('Vacature details', 'jobit-integration'),
        'job_details_meta_box_callback', 
        'vacatures',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_job_details_meta_box');


function job_details_meta_box_callback($post) {
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

    // Inline CSS for two-column layout
    echo '<style>
        .job-details-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .job-details-field {
            flex: 1 1 45%;
            min-width: 200px;
        }
        .job-details-field label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .job-details-field input,
        .job-details-field textarea,
        .job-details-field select {
            width: 100%;
        }
        .job-details-field.col-1 {
            flex: 1 1 100%;
        }
        .jobs_page_heading { margin-top: 21px; font-weight: 700; font-size: 16px; padding-left: 0; border-bottom: solid 1px #bab3b3; margin-bottom: 17px; }
    </style>';

    // Meta box fields
    echo '<div class="job-details-container">';

        echo '<div class="job-details-field col-1">';
            echo '<label for="short_description">' . __('Functieomschrijving', 'jobit-integration') . '</label>';
            echo '<textarea id="short_description" name="short_description" rows="4">' . esc_textarea($short_description) . '</textarea>';
        echo '</div>';


        echo '<div class="job-details-field col-1">';
            echo '<label for="requirements">' . __('Functie eisen', 'jobit-integration') . '</label>';
            echo '<textarea id="requirements" name="requirements" rows="4">' . esc_textarea($requirements) . '</textarea>';
        echo '</div>';

        echo '<div class="job-details-field col-1">';
            echo '<label for="offer">' . __('Wat bieden wij', 'jobit-integration') . '</label>';
            echo '<textarea id="offer" name="offer" rows="4">' . esc_textarea($offer) . '</textarea>';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="city">' . __('Werklocatie', 'jobit-integration') . '</label>';
            echo '<input type="text" id="city" name="city" value="' . esc_attr($city) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="number">' . __('Vacaturenummer', 'jobit-integration') . '</label>';
            echo '<input type="text" id="number" name="number" value="' . esc_attr($number) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="date">' . __('Vacaturedatum', 'jobit-integration') . '</label>';
            echo '<input type="text" id="date" name="date" value="' . esc_attr($date) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="education">' . __('Opleidingsniveau', 'jobit-integration') . '</label>';
            echo '<input type="text" id="education" name="education" value="' . esc_attr($education) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="career_level">' . __('Carrièreniveau', 'jobit-integration') . '</label>';
            echo '<input type="text" id="career_level" name="career_level" value="' . esc_attr($career_level) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="employment">' . __('Dienstverband', 'jobit-integration') . '</label>';
            echo '<input type="text" id="employment" name="employment" value="' . esc_attr($employment) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="hours">' . __('Uren', 'jobit-integration') . '</label>';
            echo '<input type="text" id="hours" name="hours" value="' . esc_attr($hours) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="contract">' . __('Contract', 'jobit-integration') . '</label>';
            echo '<input type="text" id="contract" name="contract" value="' . esc_attr($contract) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="salary">' . __('Salaris', 'jobit-integration') . '</label>';
            echo '<input type="text" id="salary" name="salary" value="' . esc_attr($salary) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="email">' . __('E-mailadres', 'jobit-integration') . '</label>';
            echo '<input type="text" id="email" name="email" value="' . esc_attr($email) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="contact">' . __('Behandelaar', 'jobit-integration') . '</label>';
            echo '<input type="text" id="contact" name="contact" value="' . esc_attr($contact) . '">';
        echo '</div>';

    echo '</div>';

    echo '<h2 class="jobs_page_heading">' . __('Vestiging', 'jobit-integration') . '</h2>';

    echo '<div class="job-details-container office-section">';

        echo '<div class="job-details-field">';
            echo '<label for="office_city">' . __('Plaats vestiging', 'jobit-integration') . '</label>';
            echo '<input type="text" id="office_city" name="office_city" value="' . esc_attr($office_city) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="office_email">' . __('E-mailadres vestiging', 'jobit-integration') . '</label>';
            echo '<input type="text" id="office_email" name="office_email" value="' . esc_attr($office_email) . '">';
        echo '</div>';

        echo '<div class="job-details-field">';
            echo '<label for="office_phone">' . __('Telefoon vestiging', 'jobit-integration') . '</label>';
            echo '<input type="text" id="office_phone" name="office_phone" value="' . esc_attr($office_phone) . '">';
        echo '</div>';

    echo '</div>';
}


function save_job_details_meta_box_data($post_id) {
    // Check if nonce is set
    if (!isset($_POST['job_details_nonce']) || !wp_verify_nonce($_POST['job_details_nonce'], 'save_job_details')) {
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

    // Loop through the fields and save their values
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]); // Sanitize the value
            update_post_meta($post_id, $field, $value);   // Save the meta field
        } else {
            delete_post_meta($post_id, $field);          // If field is not set, remove it
        }
    }
}
add_action('save_post', 'save_job_details_meta_box_data');


add_action('admin_menu', 'vacatures_settings_page');
function vacatures_settings_page() {
    add_submenu_page(
        'edit.php?post_type=vacatures',
        __('Instellingen', 'jobit-integration'),
        __('Instellingen', 'jobit-integration'),
        'manage_options',
        'vacatures__settings',
        'vacatures_settings_callback'
    );
}

function vacatures_settings_callback() {
    ?>
    <div class="wrap">
        <form method="post" action="options.php">
            <?php
            settings_fields('jobit_settings_group'); // Settings group
            do_settings_sections('jobit_settings');
            ?>
            <style>#full-screen-loading { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent background */ display: flex; align-items: center; justify-content: center; z-index: 9999; /* Ensure it appears above other elements */ } .loading-spinner { font-size: 1.5em; color: #fff; }</style>
            <table class="form-table">
                
                <tr valign="top">
                    <th colspan="2" style="text-align: left;">
                        <h1 style="margin: 0;"><?php echo __('Multiposter instellingen', 'jobit-integration'); ?></h1>
                    </th>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php echo __('API-sleutel', 'jobit-integration'); ?></th>
                    <td>
                        <textarea name="api_key" style="width: 100%; min-height: 50px;"><?php echo esc_attr(get_option('api_key')); ?></textarea>
                        <em><?php echo __('Vul hier je API-sleutel in. Deze vind je via Instellingen &gt; Koppelingen &gt; API', 'jobit-integration'); ?></em>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo __('Vacatures verversen', 'jobit-integration'); ?></th>
                    <td>
                        <?php 
                            $api_intervals = get_option('api_intervals', 30);
                        ?>
                        <select name="api_intervals">
                            <option value="15" <?php selected($api_intervals, 15); ?>>Iedere 15 minuten</option>
                            <option value="30" <?php selected($api_intervals, 30); ?>>Iedere 30 minuten</option>
                            <option value="45" <?php selected($api_intervals, 45); ?>>Iedere 45 minuten</option>
                            <option value="60" <?php selected($api_intervals, 60); ?>>Ieder uur</option>
                            <option value="240" <?php selected($api_intervals, 240); ?>>Iedere 4 uur</option>
                            <option value="480" <?php selected($api_intervals, 480); ?>>Iedere 8 uur</option>
                            <option value="1440" <?php selected($api_intervals, 1440); ?>>Iedere 24 uur</option>
                        </select>
                    </td>
                </tr>


                <tr valign="top">
                    <th scope="row"><?php echo __('Toon sollicitatieformulier bij vacatures', 'jobit-integration'); ?></th>
                    <td>
                        <?php $show_form = get_option('show_form', 0); // Default to 0 (unchecked) ?>
                        <input type="checkbox" name="show_form" value="1" <?php checked($show_form, 1); ?> />
                    </td>
                </tr>


                <tr valign="top">
                    <th colspan="2" style="text-align: left;">
                        <?php submit_button(__('Instellingen opslaan', 'jobit-integration')); ?>
                    </th>
                </tr>


                <tr valign="top">
                    <th colspan="2" style="text-align: left;">
                        <h1 style="margin: 0;"><?php echo __('Shortcodes', 'jobit-integration'); ?></h1>
                    </th>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php echo __('Archive pagina shortcode', 'jobit-integration'); ?></th>
                    <td>
                        <input type="text" value="[jobs_archive]" disabled/>
                        <em><?php echo __('Gebruik deze shortcode om een lijst met vacatures te tonen', 'jobit-integration'); ?></em>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><?php echo __('Single pagina shortcode', 'jobit-integration'); ?></th>
                    <td>
                        <input type="text" value="[job_single]" disabled/>
                        <em><?php echo __('Gebruik deze shortcode om een enkele vacature te tonen', 'jobit-integration'); ?></em>
                    </td>
                </tr>


            </table>
            
            <?php
                if(get_option('api_key')){
                    echo '<button class="button button-secondary" id="feachjobsnow">Vacatures nu ophalen</button>';
                    echo '<div id="full-screen-loading" style="display: none;"> <div class="loading-spinner"><img src="'.plugins_url('assets/img/loading.gif', __FILE__).'"/></div> </div>';
                }
            ?>
        </form>
    </div>
    <?php
}

// Register the setting
add_action('admin_init', 'jobit_settings_init');
function jobit_settings_init() {
    register_setting('jobit_settings_group', 'api_intervals');
    register_setting('jobit_settings_group', 'api_key');
    register_setting('jobit_settings_group', 'show_form');
}


add_action('update_option_api_key', 'jobit_update_cron_schedule', 10, 2);
add_action('update_option_api_intervals', 'jobit_update_cron_schedule', 10, 2);
function jobit_update_cron_schedule() {
    flush_rewrite_rules();
    wp_clear_scheduled_hook('jobit_custom_cron_event');
    $interval_minutes = (int) get_option('api_intervals', 60);
    $interval_seconds = $interval_minutes * 60;
    if ($interval_seconds > 0) {
        wp_clear_scheduled_hook('jobit_custom_interval');
        wp_schedule_event(time(), 'jobit_custom_interval', 'jobit_custom_cron_event');
    }
    
}


add_filter('cron_schedules', 'jobit_custom_cron_schedule');
function jobit_custom_cron_schedule($schedules) {
    $interval_minutes = (int) get_option('api_intervals', 60);
    if(!$interval_minutes){
        $interval_minutes = 15;
    }
    $interval_seconds = $interval_minutes * 60;
    $schedules['jobit_custom_interval'] = array(
        'interval' => $interval_seconds,
        'display'  => __('Jobit Custom Interval', 'textdomain')
    );
    return $schedules;
}

add_action('jobit_custom_cron_event', 'jobs_feach_callback');



function jobs_feach_list_api($current_page) {
    $api_key = get_option('api_key');
    $url = "https://app.jobit.nl/api/vacancies?limit=100&page={$current_page}";
    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'method' => 'GET',
    );
    $response = wp_remote_get($url, $args);
    $response_body = wp_remote_retrieve_body($response);
    $vacancies = json_decode($response_body, true);
    return $vacancies;
}

function jobs_feach_callback() {
    flush_rewrite_rules();
    $api_key = get_option('api_key');
    if ($api_key) {
        $current_page = get_option('jobit_current_page', 1); // Default to page 1 if not set
        $vacancies = jobs_feach_list_api($current_page);
        if (empty($vacancies['data']['vacancies'])) {
            update_option('jobit_current_page', 1);
            $vacancies = jobs_feach_list_api(1);
        } 
        $jobs_list = $vacancies['data']['vacancies'];

        if ($jobs_list) {
            foreach ($jobs_list as $job) {
                insert_job_function($job); // Insert each job
            }
        } 

        $last_one = $vacancies["meta"]["last_page"] ?? 1;
        if ($current_page > $last_one) {
            update_option('jobit_current_page', $current_page + 1); // Move to the next page
        } else {
            update_option('jobit_current_page', 1); // Reset to page 1 if last page is reached
            update_expired_jobs(); // Update expired jobs only after the last page
        }
    }
}


function insert_job_function($job) {
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
        update_post_meta($post_id, 'short_description', $description);
        update_post_meta($post_id, 'requirements', $requirements);
        update_post_meta($post_id, 'offer', $offer);
        update_post_meta($post_id, 'city', $city);
        update_post_meta($post_id, 'number', $number);
        update_post_meta($post_id, 'date', $date);
        update_post_meta($post_id, 'education', $education);
        update_post_meta($post_id, 'career_level', $career_level);
        update_post_meta($post_id, 'employment', $employment);
        update_post_meta($post_id, 'hours', $hours);
        update_post_meta($post_id, 'contract', $contract);
        update_post_meta($post_id, 'salary', $salary);
        update_post_meta($post_id, 'email', $email);
        update_post_meta($post_id, 'contact', $contact);
        update_post_meta($post_id, 'office_city', $office_city);
        update_post_meta($post_id, 'office_email', $office_email);
        update_post_meta($post_id, 'office_phone', $office_phone);
        update_post_meta($post_id, 'jobit_id', $job['id']); 
        update_post_meta($post_id, 'position', $position); 
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


    }
}

function jobit_load_textdomain() {
    load_plugin_textdomain('jobit-integration', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'jobit_load_textdomain');

function update_expired_jobs() {
    $api_key = get_option('api_key');
    if($api_key){
        $url = 'https://app.jobit.nl/api/vacancies?limit=9999';

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
                    $jobit_id = get_post_meta($post_id, 'jobit_id', true);
                    if (!in_array($jobit_id, $jobs_id)) {
                        wp_update_post(array(
                            'ID' => $post_id,
                            'post_status' => 'draft',
                        ));
                    }
                }
                wp_reset_postdata();
            }
        }
    }
}


function jobit_import_jobs_action_ajax_handler() {
    $res = jobs_feach_callback();
    $response = array(
        'status' => 'success',
        'message' => 'AJAX request received successfully!',
        'response' => $res
    );
    wp_send_json($response);
    wp_die();
}
add_action('wp_ajax_jobit_import_jobs_action', 'jobit_import_jobs_action_ajax_handler');



add_shortcode( 'jobs_archive', 'jobs_archive_func' );
function jobs_archive_func( $atts ) {
    ob_start();
    include_once('template/archive-jobs.php');
    $content = ob_get_clean();
    return $content;
}

add_filter('template_include', 'my_plugin_custom_archive_template');
function my_plugin_custom_archive_template($template) {
    if (is_post_type_archive('vacatures')) { // Replace 'jobs' with your post type name
        $plugin_template = plugin_dir_path(__FILE__) . 'template/archive-jobs.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}



function create_jobs_cities_taxonomy() {
    // Define the labels for the taxonomy
    $labels = array(
        'name'              => _x( 'Plaatsen', 'taxonomy general name' ),
        'singular_name'     => _x( 'Plaats', 'taxonomy singular name' ),
        'search_items'      => __( 'Zoek plaats' ),
        'all_items'         => __( 'Alle plaatsen' ),
        'parent_item'       => __( 'Hoofd plaatsen' ),
        'parent_item_colon' => __( 'Hoofd plaatsen:' ),
        'edit_item'         => __( 'Bewerk plaats' ),
        'update_item'       => __( 'Plaats opslaan' ),
        'add_new_item'      => __( 'Plaats toevoegen' ),
        'new_item_name'     => __( 'Nieuwe plaats naam' ),
        'menu_name'         => __( 'Plaatsen' ),
    );
    register_taxonomy( 'cities', 'vacatures', array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'city' ),
    ));

    $labels = array(
        'name'              => _x( 'Functies', 'taxonomy general name' ),
        'singular_name'     => _x( 'Functie', 'taxonomy singular name' ),
        'search_items'      => __( 'Zoek functie' ),
        'all_items'         => __( 'Alle functies' ),
        'parent_item'       => __( 'Hoofd functie' ),
        'parent_item_colon' => __( 'Hoofd functie:' ),
        'edit_item'         => __( 'Bewerk functie' ),
        'update_item'       => __( 'Functie opslaan' ),
        'add_new_item'      => __( 'Functie toevoegen' ),
        'new_item_name'     => __( 'Nieuwe functie naam' ),
        'menu_name'         => __( 'Functies' ),
    );
    register_taxonomy( 'position', 'vacatures', array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'position' ),
    ));
}
add_action( 'init', 'create_jobs_cities_taxonomy', 0 );



function jobit_change_per_page_action_ajax_handler() {
    // Get the number of posts per page and current page from the AJAX request
    $posts_per_page = isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 10;
    $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
    $selected_cities = isset($_POST['selectedCities']) ? array_map('intval', $_POST['selectedCities']) : [];
    $selected_postions = isset($_POST['selectedPostions']) ? array_map('intval', $_POST['selectedPostions']) : [];
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    // Set up WP_Query arguments
    $args = array(
        'post_type'      => 'vacatures',
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
    );
    if (!empty($selected_cities)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'cities', // Replace 'city' with the actual city taxonomy slug
                'field'    => 'term_id',
                'terms'    => $selected_cities,
            ),
        );
    }
    if (!empty($selected_postions)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'position',
                'field'    => 'term_id',
                'terms'    => $selected_postions,
            ),
        );
    }


    if (!empty($keyword)) {
        $args['s'] = $keyword;
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

            // Output HTML for each job listing
            ?>
            <div class="vacancies__vacancy">
                <div class="left">
                    <div class="title">
                        <h4 class="h3"><a href="<?php echo esc_url($link); ?>"><?php echo esc_html($job_title); ?></a></h4>
                    </div>
                    <div class="intro">
                        <p><?php echo strip_tags($short_description); ?></p>
                    </div>
                </div>
                <div class="right">
                    <div class="location">
                        <b><i class="fa fa-map-marker"></i><?php echo esc_html($city); ?></b>
                    </div>
                    <div class="type-of-time">
                        <b><i class="fa fa-clock-o"></i><?php echo esc_html($hours); ?></b>
                    </div>
                    <div class="salary_indication">
                        <b><i class="fa fa-money"></i><?php echo esc_html($salary); ?></b>
                    </div>
                    <div>
                        <a href="<?php echo esc_url($link); ?>" class="button blue2ghost vacancy-btn">Bekijken</a>
                    </div>
                </div>
            </div>
            <?php
        }

        // Capture and end the HTML output buffer
        $html = ob_get_clean();

        // Generate pagination links
        $pagination = paginate_links(array(
            'total'     => $jobs_query->max_num_pages,
            'current'   => $paged,
            'prev_text' => __('&laquo; Previous'),
            'next_text' => __('Next &raquo;'),
            'type'      => 'array' // Output as an array for easy manipulation
        ));
        
        // Transform pagination links to AJAX-friendly links
        if ($pagination) {
            $pagination_html = '<div class="pagination">';
            foreach ($pagination as $page_link) {
                // Add data-page attributes to links and remove href for AJAX
                $pagination_html .= str_replace('<a ', '<a data-page="1" ', $page_link);
            }
            $pagination_html .= '</div>';
        }
        
        echo json_encode(array(
            'html'       => $html,
            'pagination' => $pagination_html,
        ));
    } else {
        echo json_encode(array(
            'html' => '<p>No job vacancies found.</p>',
            'pagination' => ''
        ));
    }

    wp_reset_postdata();
    wp_die();
}
add_action('wp_ajax_jobit_change_per_page', 'jobit_change_per_page_action_ajax_handler');
add_action('wp_ajax_nopriv_jobit_change_per_page', 'jobit_change_per_page_action_ajax_handler');


// Add this in your custom plugin's main file
function custom_single_post_content($content) {
    if (is_singular('vacatures')) { // Check if viewing a single post of type "jobs"
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
        $org_id =  get_post_meta($job_id, 'jobit_id', true);

        $token = get_option('api_key');
        $show_form = get_option('show_form');

        $vacancyNumber = $org_id;
        $script = '<script src="//app.jobit.nl/vendor/wire-elements/wire-extender.js" data-livewire-asset-uri="//app.jobit.nl/livewire/livewire.js"></script>';
        $script .= '<livewire data-component="websites.livewire.frontend.embedded-application-form" ';
        $script .= 'data-params=\'{"token":"' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '","vacancyNumber":"' . htmlspecialchars($vacancyNumber, ENT_QUOTES, 'UTF-8') . '"}\'>';
        $script .= 'Laden...</livewire>';
        // Custom HTML or modifications

        $custom_content = '<div class="job-single-wrapper">';

            $custom_content .= '<div class="custom-job-content">';
                $custom_content .= '<h3 class="title job__title--detail">'.get_the_title($job_id).'</h3>';
                $custom_content .= '
                    <div class="specifications">
                        <div class="specifications__wrapper">
                            <div class="specifications__item">
                                <div class="job-detail">
                                    <div class="title">
                                        <svg class="sitemap-icon specifications__item--svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512">
                                            <path d="M608 352h-32v-97.59c0-16.77-13.62-30.41-30.41-30.41H336v-64h48c17.67 0 32-14.33 32-32V32c0-17.67-14.33-32-32-32H256c-17.67 0-32 14.33-32 32v96c0 17.67 14.33 32 32 32h48v64H94.41C77.62 224 64 237.64 64 254.41V352H32c-17.67 0-32 14.33-32 32v96c0 17.67 14.33 32 32 32h96c17.67 0 32-14.33 32-32v-96c0-17.67-14.33-32-32-32H96v-96h208v96h-32c-17.67 0-32 14.33-32 32v96c0 17.67 14.33 32 32 32h96c17.67 0 32-14.33 32-32v-96c0-17.67-14.33-32-32-32h-32v-96h208v96h-32c-17.67 0-32 14.33-32 32v96c0 17.67 14.33 32 32 32h96c17.67 0 32-14.33 32-32v-96c0-17.67-14.33-32-32-32zm-480 32v96H32v-96h96zm240 0v96h-96v-96h96zM256 128V32h128v96H256zm352 352h-96v-96h96v96z"></path>
                                        </svg>
                                        '.$employment.'</div>
                                </div>
                            </div>
                            <div class="specifications__item">
                                <div class="job-detail">
                                    <div class="location"><i class="fa fa-map-marker"></i>'.$city.'</div>
                                </div>
                            </div>
                            <div class="specifications__item">
                                <div class="job-detail">
                                    <div class="type-of-time"><i class="fa fa-clock-o"></i>'.$hours.'</div>
                                </div>
                            </div>
                            <div class="specifications__item">
                                <div class="job-detail">
                                    <div class="education"><i class="fa fa-graduation-cap"></i>'.$career_level.'</div>
                                </div>
                            </div>
                            <div class="specifications__item">
                                <div class="job-detail">
                                    <div class="salary-indication"><i class="fa fa-money"></i>'.$salary.'</div>
                                </div>
                            </div>
                            <div class="specifications__item">
                                <div class="job-detail">
                                    <div class="publicationdate"><i class="fa fa-calendar"></i>'.$date.'</div>
                                </div>
                            </div>
                        </div>
                    </div>
                ';

            $custom_content .= $content; // Original content
            $custom_content .= '</div>';


            if ($show_form) {
                $custom_content .= '<div class="custom-job-sidebar">';
                $custom_content .= '
                        <div class="vacancy-contact__wrapper">
                        <div class="vacancy-contact__header"><h3 class="office-title">Solliciteren</h3></div>
    
    
                        <div class="vacancy-contact__content">
    
                        
                            ' . $script . '
    
    
                        </div>
                    </div>';
            }

                $custom_content .= '
                    <div class="vacancy-contact__wrapper">
                    <div class="vacancy-contact__header">
                        <h3 class="office-title">Vragen over deze vacature?</h3></div>
                    <div class="vacancy-contact__content">
                        <div class="office-address personal-text">
                            '.$contact.'
                        </div>
                        <div class="contact-buttons margin">
                            <div class="contact-button no-margin">
                                <a class="button-text" href="tel:'.$office_phone.'">'.$office_phone.'</a>
                            </div>
                            <div class="contact-button no-margin">
                                <a class="button-text" href="mailto:'.$email.'" >Stuur ons een mail</a>
                            </div>
                        </div>
                        <div style="clear:both; display: block;"></div>
                    </div>
                </div>';


            $custom_content .= '</div>';





        $custom_content .= '</div>';                                    



        return $custom_content;
    }
    return $content; // Return unmodified content for other post types
}
add_filter('the_content', 'custom_single_post_content');
add_shortcode('job_single', 'custom_single_post_content');








function add_job_posting_schema() {
    if (is_singular('vacatures')) { // Check if viewing a single 'vacatures' post
        $job_id = get_the_ID();
        $title = get_the_title($job_id);
        $city = get_post_meta($job_id, 'city', true);
        $salary = get_post_meta($job_id, 'salary', true);
        $hours = get_post_meta($job_id, 'hours', true);
        $career_level = get_post_meta($job_id, 'career_level', true);
        $date = get_post_meta($job_id, 'date', true);
        $employment = get_post_meta($job_id, 'employment', true);
        $short_description = strip_tags(get_post_meta($job_id, 'short_description', true));
        // Prepare data
        $schema_data = [
            "@context" => "https://schema.org/",
            "@type" => "JobPosting",
            "title" => $title,
            "description" => $short_description,
            "datePosted" => $date,
            "employmentType" => $employment,
            "jobLocation" => [
                "@type" => "Place",
                "address" => [
                    "@type" => "PostalAddress",
                    "addressLocality" => $city
                ]
            ],
            "baseSalary" => [
                "@type" => "MonetaryAmount",
                "currency" => "EUR",
                "value" => [
                    "@type" => "QuantitativeValue",
                    "value" => $salary,
                    "unitText" => "MONTH"
                ]
            ],
            "workHours" => $hours,
            "experienceRequirements" => $career_level
        ];
        ?>
        <script type="application/ld+json">
            <?php echo wp_json_encode($schema_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
        </script>
        <?php
    }
}
add_action('wp_head', 'add_job_posting_schema');

