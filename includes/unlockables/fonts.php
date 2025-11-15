<?php
/**
 * Font unlockables integration with BuddyPress
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Fonts navigation to BuddyPress settings
 */
function hs_font_settings_nav() {
    if (function_exists('bp_core_new_nav_item')) {
        bp_core_new_nav_item([
            'name' => 'Fonts',
            'slug' => 'fonts',
            'parent_slug' => bp_get_settings_slug(),
            'screen_function' => 'hs_font_settings_screen_content',
            'position' => 31
        ]);
    }
}
add_action('bp_setup_nav', 'hs_font_settings_nav');

/**
 * Render the content for the fonts tab
 */
function hs_font_settings_screen_content() {
    wp_enqueue_script(
        'hs-font-selector-js',
        plugin_dir_url(__FILE__) . '../../js/unlockables/font-selector.js',
        ['jquery'],
        '1.0.1',
        true
    );

    wp_enqueue_style(
        'hs-font-selector-css',
        plugin_dir_url(__FILE__) . '../../css/hs-fonts.css',
        [],
        '1.0.1'
    );

    wp_localize_script(
        'hs-font-selector-js',
        'hs_font_ajax',
        ['ajaxurl' => admin_url('admin-ajax.php')]
    );

    add_action('bp_template_content', 'hs_render_font_selector_buddypress');
    bp_core_load_template(apply_filters('bp_core_template_plugin', 'members/single/plugins'));
}

/**
 * Render the font selector for BuddyPress profile
 */
function hs_render_font_selector_buddypress() {
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = bp_displayed_user_id();
    $fonts = hs_get_available_fonts($user_id);
    $selected_font = get_user_meta($user_id, 'hs_selected_font', true) ?: 'default';

    ?>
    <h4>Font Preferences</h4>
    <p>Unlock custom fonts by reading, contributing to GRead, and accomplishing different tasks!</p>

    <div id="hs-font-selector-feedback"></div>

    <form id="hs-font-selector-form">
        <?php wp_nonce_field('hs_save_font_nonce', 'hs_font_nonce'); ?>

        <div class="hs-font-grid">
            <?php foreach ($fonts as $font): ?>
                <div class="hs-font-option <?php echo $font['unlocked'] ? '' : 'locked'; ?> <?php echo $selected_font === $font['slug'] ? 'selected' : ''; ?>">
                    <label>
                        <input type="radio" name="selected_font" value="<?php echo esc_attr($font['slug']); ?>"
                               <?php checked($selected_font, $font['slug']); ?>
                               <?php disabled(!$font['unlocked']); ?>>
                        <span class="font-preview" style="font-family: <?php echo esc_attr($font['font_family']); ?>;">
                            <?php echo esc_html($font['name']); ?>
                        </span>

                        <?php if (!$font['unlocked']): ?>
                            <span class="unlock-badge">🔒</span>
                            <span class="unlock-message"><?php echo esc_html($font['unlock_message']); ?></span>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <p><button type="submit" class="button button-primary">Save Font</button></p>
    </form>
    <?php
}
