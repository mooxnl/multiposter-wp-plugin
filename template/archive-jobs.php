<?php
defined( 'ABSPATH' ) || exit;
get_header();

// Filter config
$multiposter_default_filters = array(
    array('id' => 'keyword', 'label' => __('Zoeken', 'jobit-vacancies-for-multiposter'), 'enabled' => 1),
    array('id' => 'position', 'label' => __('Functie', 'jobit-vacancies-for-multiposter'), 'enabled' => 1),
    array('id' => 'city', 'label' => __('Plaats', 'jobit-vacancies-for-multiposter'), 'enabled' => 1),
    array('id' => 'salary', 'label' => __('Salaris', 'jobit-vacancies-for-multiposter'), 'enabled' => 1),
);
$multiposter_filters_config = get_option('multiposter_filters_config', $multiposter_default_filters);
if (!is_array($multiposter_filters_config)) {
    $multiposter_filters_config = $multiposter_default_filters;
}

$multiposter_default_per_page = get_option('multiposter_default_per_page', 10);
$multiposter_show_per_page_selector = get_option('multiposter_show_per_page_selector', 1);
$multiposter_per_page_options = array_map('trim', explode(',', get_option('multiposter_per_page_options', '10,25,50,100')));
$multiposter_favorites_enabled = get_option('multiposter_favorites_enabled', 1);
$multiposter_columns = intval(get_option('multiposter_archive_columns', 1));
?>

<div class="multiposter-archive">

    <aside class="multiposter-archive__filters">
        <button type="button" class="multiposter-filter-toggle" aria-expanded="false" aria-controls="multiposter-filter-form">
            <?php esc_html_e('Filters', 'jobit-vacancies-for-multiposter'); ?>
            <svg class="multiposter-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46"/></svg>
        </button>
        <form id="multiposter-filter-form">
            <?php foreach ($multiposter_filters_config as $multiposter_filter):
                if (empty($multiposter_filter['enabled'])) continue;

                switch ($multiposter_filter['id']):
                    case 'keyword': ?>
                        <div class="multiposter-filter-group">
                            <label for="multiposter-keyword"><?php esc_html_e('Zoeken', 'jobit-vacancies-for-multiposter'); ?></label>
                            <input type="text" id="multiposter-keyword" name="keyword" placeholder="<?php esc_attr_e('Zoeken...', 'jobit-vacancies-for-multiposter'); ?>">
                        </div>
                    <?php break;

                    case 'position': ?>
                        <fieldset class="multiposter-filter-group">
                            <legend><?php esc_html_e('Functie', 'jobit-vacancies-for-multiposter'); ?></legend>
                            <?php
                            $multiposter_terms = get_terms(array('taxonomy' => 'multiposter_position', 'hide_empty' => true));
                            if (!is_wp_error($multiposter_terms) && !empty($multiposter_terms)): ?>
                                <ul class="multiposter-checkbox-list">
                                    <?php foreach ($multiposter_terms as $term): ?>
                                        <li>
                                            <label>
                                                <input type="checkbox" name="position[]" value="<?php echo esc_attr($term->term_id); ?>">
                                                <?php echo esc_html($term->name); ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </fieldset>
                    <?php break;

                    case 'city': ?>
                        <fieldset class="multiposter-filter-group">
                            <legend><?php esc_html_e('Plaats', 'jobit-vacancies-for-multiposter'); ?></legend>
                            <?php
                            $multiposter_terms = get_terms(array('taxonomy' => 'multiposter_city', 'hide_empty' => true));
                            if (!is_wp_error($multiposter_terms) && !empty($multiposter_terms)): ?>
                                <ul class="multiposter-checkbox-list">
                                    <?php foreach ($multiposter_terms as $term): ?>
                                        <li>
                                            <label>
                                                <input type="checkbox" name="city[]" value="<?php echo esc_attr($term->term_id); ?>">
                                                <?php echo esc_html($term->name); ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </fieldset>
                    <?php break;

                    case 'salary': ?>
                        <div class="multiposter-filter-group">
                            <label><?php esc_html_e('Salaris', 'jobit-vacancies-for-multiposter'); ?></label>
                            <div class="multiposter-salary-range">
                                <input type="number" name="salary_min" placeholder="<?php esc_attr_e('Min', 'jobit-vacancies-for-multiposter'); ?>">
                                <input type="number" name="salary_max" placeholder="<?php esc_attr_e('Max', 'jobit-vacancies-for-multiposter'); ?>">
                            </div>
                        </div>
                    <?php break;

                endswitch;
            endforeach; ?>

            <?php if ($multiposter_favorites_enabled): ?>
            <div class="multiposter-filter-group multiposter-favorites-filter">
                <label>
                    <input type="checkbox" id="multiposter-show-favorites">
                    <span class="multiposter-heart-icon">&#9829;</span> <?php esc_html_e('Alleen favorieten', 'jobit-vacancies-for-multiposter'); ?>
                </label>
            </div>
            <?php endif; ?>
        </form>
    </aside>

    <main class="multiposter-archive__results">
        <div class="multiposter-loader" hidden>
            <svg class="multiposter-spinner" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
            </svg>
        </div>

        <div class="multiposter-vacancies multiposter-columns-<?php echo esc_attr($multiposter_columns); ?>">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Returns pre-escaped HTML.
            echo multiposter_render_archive_page_ssr($multiposter_default_per_page, $multiposter_favorites_enabled);
            ?>
        </div>

        <nav class="multiposter-pagination">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Returns pre-escaped HTML.
            echo multiposter_render_archive_pagination_ssr($multiposter_default_per_page);
            ?>
        </nav>

        <?php if ($multiposter_show_per_page_selector): ?>
        <div class="multiposter-per-page">
            <select id="multiposter-per-page">
                <?php foreach ($multiposter_per_page_options as $multiposter_opt):
                    $multiposter_opt = intval($multiposter_opt);
                    if ($multiposter_opt <= 0) continue;
                ?>
                    <option value="<?php echo (int) $multiposter_opt; ?>" <?php selected($multiposter_opt, $multiposter_default_per_page); ?>><?php echo (int) $multiposter_opt; ?> <?php esc_html_e('per pagina', 'jobit-vacancies-for-multiposter'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </main>

</div>

<?php
get_footer();
