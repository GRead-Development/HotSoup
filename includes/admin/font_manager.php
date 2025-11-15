<?php
/**
 * Font Manager
 *
 * Handles custom font unlockables similar to themes.
 * Users can unlock fonts based on achievements and apply them site-wide.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize font manager hooks
 */
function hs_font_manager_init() {
    // Admin menu
    add_action('admin_menu', 'hs_register_font_manager_page');

    // AJAX handlers
    add_action('wp_ajax_hs_save_user_font', 'hs_save_user_font_callback');
    add_action('wp_ajax_hs_upload_font', 'hs_upload_font_callback');
    add_action('wp_ajax_hs_delete_font', 'hs_delete_font_callback');

    // Apply font to frontend
    add_filter('body_class', 'hs_apply_font_body_class');
    add_action('wp_head', 'hs_generate_font_css', 10);
    add_action('wp_head', 'hs_load_font_files', 5);

    // API endpoints (for app)
    add_action('rest_api_init', 'hs_register_font_api_endpoints');
}
add_action('init', 'hs_font_manager_init');

/**
 * AJAX handler for saving user's selected font
 */
function hs_save_user_font_callback() {
    // Verify nonce
    if (!isset($_POST['hs_font_nonce']) || !wp_verify_nonce($_POST['hs_font_nonce'], 'hs_save_font_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'You must be logged in']);
        return;
    }

    $user_id = get_current_user_id();
    $selected_font = sanitize_text_field($_POST['selected_font']);

    // Get font from database
    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';
    $font = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $fonts_table WHERE slug = %s",
        $selected_font
    ));

    if (!$font) {
        wp_send_json_error(['message' => 'Invalid font selected']);
        return;
    }

    // Check if font is unlocked
    if (!hs_is_font_unlocked($font, $user_id)) {
        wp_send_json_error(['message' => 'This font is not unlocked yet. ' . $font->unlock_message]);
        return;
    }

    // Save font selection
    update_user_meta($user_id, 'hs_selected_font', $selected_font);

    wp_send_json_success(['message' => 'Font updated successfully!']);
}

/**
 * Check if a font is unlocked for a user
 */
function hs_is_font_unlocked($font, $user_id) {
    // Default font is always unlocked
    if ($font->is_default == 1) {
        return true;
    }

    // No unlock requirement
    if (empty($font->unlock_metric) || $font->unlock_metric === 'none') {
        return true;
    }

    // Get user's metric value
    $metric_key = $font->unlock_metric;

    // Map metric names to user meta keys
    $meta_key_map = [
        'points' => 'user_points',
        'books_read' => 'hs_completed_books_count',
        'pages_read' => 'hs_total_pages_read',
    ];

    $meta_key = isset($meta_key_map[$metric_key]) ? $meta_key_map[$metric_key] : 'hs_' . $metric_key;
    $user_value = (int) get_user_meta($user_id, $meta_key, true);

    return $user_value >= $font->unlock_value;
}

/**
 * AJAX handler for uploading fonts
 */
function hs_upload_font_callback() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    // Verify nonce
    if (!isset($_POST['hs_upload_font_nonce']) || !wp_verify_nonce($_POST['hs_upload_font_nonce'], 'hs_upload_font_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    // Handle font file upload
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploaded_files = [];
    $font_formats = ['woff2', 'woff', 'ttf', 'otf'];

    foreach ($font_formats as $format) {
        if (isset($_FILES["font_file_{$format}"]) && $_FILES["font_file_{$format}"]['error'] === UPLOAD_ERR_OK) {
            $uploadedfile = $_FILES["font_file_{$format}"];
            $upload_overrides = [
                'test_form' => false,
                'mimes' => [
                    'woff2' => 'font/woff2',
                    'woff' => 'font/woff',
                    'ttf' => 'font/ttf',
                    'otf' => 'font/otf',
                ]
            ];

            $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $uploaded_files[$format] = $movefile['url'];
            }
        }
    }

    if (empty($uploaded_files)) {
        wp_send_json_error(['message' => 'No valid font files uploaded']);
        return;
    }

    // Save font to database
    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';

    $font_id = isset($_POST['font_id']) ? (int) $_POST['font_id'] : 0;
    $font_data = [
        'name' => sanitize_text_field($_POST['font_name']),
        'slug' => sanitize_title($_POST['font_slug']),
        'font_family' => sanitize_text_field($_POST['font_family']),
        'file_woff2' => isset($uploaded_files['woff2']) ? esc_url_raw($uploaded_files['woff2']) : '',
        'file_woff' => isset($uploaded_files['woff']) ? esc_url_raw($uploaded_files['woff']) : '',
        'file_ttf' => isset($uploaded_files['ttf']) ? esc_url_raw($uploaded_files['ttf']) : '',
        'file_otf' => isset($uploaded_files['otf']) ? esc_url_raw($uploaded_files['otf']) : '',
        'unlock_metric' => sanitize_text_field($_POST['unlock_metric']),
        'unlock_value' => (int) $_POST['unlock_value'],
        'unlock_message' => sanitize_text_field($_POST['unlock_message']),
        'is_default' => isset($_POST['is_default']) ? 1 : 0,
    ];

    if ($font_id > 0) {
        // Update existing font
        $wpdb->update($fonts_table, $font_data, ['id' => $font_id]);
        wp_send_json_success(['message' => 'Font updated successfully!']);
    } else {
        // Insert new font
        $wpdb->insert($fonts_table, $font_data);
        wp_send_json_success(['message' => 'Font uploaded successfully!']);
    }
}

/**
 * AJAX handler for deleting fonts
 */
function hs_delete_font_callback() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hs_delete_font_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    $font_id = (int) $_POST['font_id'];

    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';

    // Don't delete default font
    $font = $wpdb->get_row($wpdb->prepare("SELECT is_default FROM $fonts_table WHERE id = %d", $font_id));
    if ($font && $font->is_default == 1) {
        wp_send_json_error(['message' => 'Cannot delete default font']);
        return;
    }

    $wpdb->delete($fonts_table, ['id' => $font_id]);
    wp_send_json_success(['message' => 'Font deleted successfully!']);
}

/**
 * Register admin menu page
 */
function hs_register_font_manager_page() {
    add_menu_page(
        'Font Manager',
        'Font Manager',
        'manage_options',
        'hs-font-manager',
        'hs_font_manager_page',
        'dashicons-editor-textcolor',
        30
    );
}

/**
 * Render admin page
 */
function hs_font_manager_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission
    if (isset($_POST['hs_save_font']) && check_admin_referer('hs_save_font_action', 'hs_save_font_nonce_field')) {
        hs_handle_font_save();
    }

    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $font_id = isset($_GET['font_id']) ? (int) $_GET['font_id'] : 0;

    ?>
    <div class="wrap">
        <h1>Font Manager</h1>

        <?php if ($action === 'list'): ?>
            <a href="?page=hs-font-manager&action=add" class="button button-primary">Add New Font</a>
            <?php hs_render_fonts_list(); ?>
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <?php hs_render_font_form($font_id); ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Handle font save from form
 */
function hs_handle_font_save() {
    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';

    $font_id = isset($_POST['font_id']) ? (int) $_POST['font_id'] : 0;

    $font_data = [
        'name' => sanitize_text_field($_POST['font_name']),
        'slug' => sanitize_title($_POST['font_slug']),
        'font_family' => sanitize_text_field($_POST['font_family']),
        'unlock_metric' => sanitize_text_field($_POST['unlock_metric']),
        'unlock_value' => (int) $_POST['unlock_value'],
        'unlock_message' => sanitize_text_field($_POST['unlock_message']),
        'is_default' => isset($_POST['is_default']) ? 1 : 0,
    ];

    // Handle file URLs (if provided manually)
    if (!empty($_POST['file_woff2'])) $font_data['file_woff2'] = esc_url_raw($_POST['file_woff2']);
    if (!empty($_POST['file_woff'])) $font_data['file_woff'] = esc_url_raw($_POST['file_woff']);
    if (!empty($_POST['file_ttf'])) $font_data['file_ttf'] = esc_url_raw($_POST['file_ttf']);
    if (!empty($_POST['file_otf'])) $font_data['file_otf'] = esc_url_raw($_POST['file_otf']);

    if ($font_id > 0) {
        $wpdb->update($fonts_table, $font_data, ['id' => $font_id]);
        echo '<div class="notice notice-success"><p>Font updated successfully!</p></div>';
    } else {
        $wpdb->insert($fonts_table, $font_data);
        echo '<div class="notice notice-success"><p>Font created successfully!</p></div>';
    }
}

/**
 * Render fonts list table
 */
function hs_render_fonts_list() {
    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';
    $fonts = $wpdb->get_results("SELECT * FROM $fonts_table ORDER BY is_default DESC, name ASC");

    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Font Family</th>
                <th>Unlock Requirement</th>
                <th>Default</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($fonts as $font): ?>
                <tr>
                    <td><?php echo esc_html($font->name); ?></td>
                    <td><?php echo esc_html($font->slug); ?></td>
                    <td style="font-family: <?php echo esc_attr($font->font_family); ?>;"><?php echo esc_html($font->font_family); ?></td>
                    <td>
                        <?php
                        if ($font->unlock_metric && $font->unlock_metric !== 'none') {
                            echo esc_html(ucfirst(str_replace('_', ' ', $font->unlock_metric)) . ': ' . $font->unlock_value);
                        } else {
                            echo 'None';
                        }
                        ?>
                    </td>
                    <td><?php echo $font->is_default ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a href="?page=hs-font-manager&action=edit&font_id=<?php echo $font->id; ?>">Edit</a>
                        <?php if (!$font->is_default): ?>
                            | <a href="#" class="delete-font" data-font-id="<?php echo $font->id; ?>" style="color: red;">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
    jQuery(document).ready(function($) {
        $('.delete-font').on('click', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this font?')) return;

            var fontId = $(this).data('font-id');
            $.post(ajaxurl, {
                action: 'hs_delete_font',
                font_id: fontId,
                nonce: '<?php echo wp_create_nonce('hs_delete_font_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Render font form (add/edit)
 */
function hs_render_font_form($font_id = 0) {
    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';

    $font = null;
    if ($font_id > 0) {
        $font = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fonts_table WHERE id = %d", $font_id));
    }

    ?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('hs_save_font_action', 'hs_save_font_nonce_field'); ?>
        <input type="hidden" name="font_id" value="<?php echo $font_id; ?>">

        <table class="form-table">
            <tr>
                <th><label for="font_name">Font Name *</label></th>
                <td><input type="text" id="font_name" name="font_name" value="<?php echo $font ? esc_attr($font->name) : ''; ?>" required class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="font_slug">Slug *</label></th>
                <td><input type="text" id="font_slug" name="font_slug" value="<?php echo $font ? esc_attr($font->slug) : ''; ?>" required class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="font_family">Font Family *</label></th>
                <td>
                    <input type="text" id="font_family" name="font_family" value="<?php echo $font ? esc_attr($font->font_family) : ''; ?>" required class="regular-text">
                    <p class="description">The CSS font-family name (e.g., "Roboto", "Open Sans")</p>
                </td>
            </tr>
            <tr>
                <th><label>Font Files</label></th>
                <td>
                    <p><strong>Upload font files or enter URLs:</strong></p>

                    <p><label>WOFF2:</label><br>
                    <input type="text" name="file_woff2" value="<?php echo $font ? esc_attr($font->file_woff2) : ''; ?>" class="regular-text" placeholder="URL or leave empty to upload"></p>

                    <p><label>WOFF:</label><br>
                    <input type="text" name="file_woff" value="<?php echo $font ? esc_attr($font->file_woff) : ''; ?>" class="regular-text" placeholder="URL or leave empty to upload"></p>

                    <p><label>TTF:</label><br>
                    <input type="text" name="file_ttf" value="<?php echo $font ? esc_attr($font->file_ttf) : ''; ?>" class="regular-text" placeholder="URL or leave empty to upload"></p>

                    <p><label>OTF:</label><br>
                    <input type="text" name="file_otf" value="<?php echo $font ? esc_attr($font->file_otf) : ''; ?>" class="regular-text" placeholder="URL or leave empty to upload"></p>

                    <p class="description">You can use Google Fonts or other CDN URLs, or upload custom font files.</p>
                </td>
            </tr>
            <tr>
                <th><label for="unlock_metric">Unlock Metric</label></th>
                <td>
                    <select id="unlock_metric" name="unlock_metric">
                        <option value="none" <?php selected($font ? $font->unlock_metric : '', 'none'); ?>>None (Always Unlocked)</option>
                        <option value="points" <?php selected($font ? $font->unlock_metric : '', 'points'); ?>>Points</option>
                        <option value="books_read" <?php selected($font ? $font->unlock_metric : '', 'books_read'); ?>>Books Read</option>
                        <option value="pages_read" <?php selected($font ? $font->unlock_metric : '', 'pages_read'); ?>>Pages Read</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="unlock_value">Unlock Value</label></th>
                <td><input type="number" id="unlock_value" name="unlock_value" value="<?php echo $font ? esc_attr($font->unlock_value) : '0'; ?>" min="0"></td>
            </tr>
            <tr>
                <th><label for="unlock_message">Unlock Message</label></th>
                <td><input type="text" id="unlock_message" name="unlock_message" value="<?php echo $font ? esc_attr($font->unlock_message) : ''; ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="is_default">Default Font</label></th>
                <td><input type="checkbox" id="is_default" name="is_default" value="1" <?php checked($font ? $font->is_default : 0, 1); ?>></td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="hs_save_font" class="button button-primary" value="Save Font">
            <a href="?page=hs-font-manager" class="button">Cancel</a>
        </p>
    </form>
    <?php
}

/**
 * Create fonts database table
 */
function hs_create_fonts_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hs_fonts';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        slug varchar(50) NOT NULL,
        name varchar(100) NOT NULL,
        font_family varchar(100) NOT NULL,
        file_woff2 varchar(255) DEFAULT '',
        file_woff varchar(255) DEFAULT '',
        file_ttf varchar(255) DEFAULT '',
        file_otf varchar(255) DEFAULT '',
        unlock_metric varchar(50) DEFAULT 'none',
        unlock_value int(11) DEFAULT 0,
        unlock_message text,
        is_default tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Add default system font
    $default_exists = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE slug = 'default'");
    if (!$default_exists) {
        $wpdb->insert($table_name, [
            'slug' => 'default',
            'name' => 'Default (System Font)',
            'font_family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
            'unlock_metric' => 'none',
            'unlock_value' => 0,
            'is_default' => 1,
        ]);
    }
}
register_activation_hook(__FILE__, 'hs_create_fonts_table');

/**
 * Get available fonts for a user
 */
function hs_get_available_fonts($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';
    $fonts = $wpdb->get_results("SELECT * FROM $fonts_table ORDER BY is_default DESC, name ASC");

    $available_fonts = [];
    foreach ($fonts as $font) {
        $available_fonts[] = [
            'id' => $font->id,
            'slug' => $font->slug,
            'name' => $font->name,
            'font_family' => $font->font_family,
            'unlocked' => hs_is_font_unlocked($font, $user_id),
            'unlock_metric' => $font->unlock_metric,
            'unlock_value' => $font->unlock_value,
            'unlock_message' => $font->unlock_message,
            'is_default' => $font->is_default,
        ];
    }

    return $available_fonts;
}

/**
 * Render font selector for user profile
 */
function hs_render_font_selector() {
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $fonts = hs_get_available_fonts($user_id);
    $selected_font = get_user_meta($user_id, 'hs_selected_font', true) ?: 'default';

    ?>
    <div class="hs-font-selector">
        <h3>Font Preferences</h3>
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

            <button type="submit" class="button button-primary">Save Font</button>
            <div id="hs-font-selector-feedback"></div>
        </form>
    </div>
    <?php
}

/**
 * Apply font body class
 */
function hs_apply_font_body_class($classes) {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $selected_font = get_user_meta($user_id, 'hs_selected_font', true);

        if ($selected_font) {
            $classes[] = 'font-' . sanitize_html_class($selected_font);
        }
    }

    return $classes;
}

/**
 * Load font files
 */
function hs_load_font_files() {
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $selected_font = get_user_meta($user_id, 'hs_selected_font', true);

    if (!$selected_font || $selected_font === 'default') {
        return;
    }

    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';
    $font = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fonts_table WHERE slug = %s", $selected_font));

    if (!$font) {
        return;
    }

    // Generate @font-face CSS
    echo "\n<style id=\"hs-font-face\">\n";
    echo "@font-face {\n";
    echo "  font-family: '" . esc_attr($font->font_family) . "';\n";
    echo "  src: ";

    $sources = [];
    if ($font->file_woff2) $sources[] = "url('" . esc_url($font->file_woff2) . "') format('woff2')";
    if ($font->file_woff) $sources[] = "url('" . esc_url($font->file_woff) . "') format('woff')";
    if ($font->file_ttf) $sources[] = "url('" . esc_url($font->file_ttf) . "') format('truetype')";
    if ($font->file_otf) $sources[] = "url('" . esc_url($font->file_otf) . "') format('opentype')";

    echo implode(",\n       ", $sources) . ";\n";
    echo "  font-weight: normal;\n";
    echo "  font-style: normal;\n";
    echo "  font-display: swap;\n";
    echo "}\n";
    echo "</style>\n";
}

/**
 * Generate font CSS
 */
function hs_generate_font_css() {
    if (!is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $selected_font = get_user_meta($user_id, 'hs_selected_font', true);

    if (!$selected_font || $selected_font === 'default') {
        return;
    }

    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';
    $font = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fonts_table WHERE slug = %s", $selected_font));

    if (!$font) {
        return;
    }

    // Apply font-family to body and common elements
    echo "\n<style id=\"hs-dynamic-font-styles\">\n";
    echo "body.font-" . sanitize_html_class($selected_font) . ",\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " input,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " textarea,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " select,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " button,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " .button,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " h1,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " h2,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " h3,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " h4,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " h5,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " h6,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " p,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " span,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " a,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " div,\n";
    echo "body.font-" . sanitize_html_class($selected_font) . " li {\n";
    echo "  font-family: " . esc_attr($font->font_family) . " !important;\n";
    echo "}\n";
    echo "</style>\n";
}

/**
 * Register REST API endpoints for app
 */
function hs_register_font_api_endpoints() {
    // Get available fonts
    register_rest_route('hotsoup/v1', '/fonts', [
        'methods' => 'GET',
        'callback' => 'hs_api_get_fonts',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);

    // Select font
    register_rest_route('hotsoup/v1', '/fonts/select', [
        'methods' => 'POST',
        'callback' => 'hs_api_select_font',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);

    // Get user's selected font
    register_rest_route('hotsoup/v1', '/fonts/selected', [
        'methods' => 'GET',
        'callback' => 'hs_api_get_selected_font',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
}

/**
 * API: Get available fonts
 */
function hs_api_get_fonts($request) {
    $user_id = get_current_user_id();
    $fonts = hs_get_available_fonts($user_id);

    return new WP_REST_Response($fonts, 200);
}

/**
 * API: Select font
 */
function hs_api_select_font($request) {
    $user_id = get_current_user_id();
    $font_slug = $request->get_param('font_slug');

    if (empty($font_slug)) {
        return new WP_REST_Response(['error' => 'Font slug is required'], 400);
    }

    // Get font from database
    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';
    $font = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fonts_table WHERE slug = %s", $font_slug));

    if (!$font) {
        return new WP_REST_Response(['error' => 'Invalid font'], 404);
    }

    // Check if unlocked
    if (!hs_is_font_unlocked($font, $user_id)) {
        return new WP_REST_Response([
            'error' => 'Font is locked',
            'message' => $font->unlock_message
        ], 403);
    }

    // Save selection
    update_user_meta($user_id, 'hs_selected_font', $font_slug);

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Font updated successfully',
        'font' => [
            'slug' => $font->slug,
            'name' => $font->name,
            'font_family' => $font->font_family,
        ]
    ], 200);
}

/**
 * API: Get selected font
 */
function hs_api_get_selected_font($request) {
    $user_id = get_current_user_id();
    $selected_font_slug = get_user_meta($user_id, 'hs_selected_font', true) ?: 'default';

    global $wpdb;
    $fonts_table = $wpdb->prefix . 'hs_fonts';
    $font = $wpdb->get_row($wpdb->prepare("SELECT * FROM $fonts_table WHERE slug = %s", $selected_font_slug));

    if (!$font) {
        return new WP_REST_Response(['error' => 'Font not found'], 404);
    }

    return new WP_REST_Response([
        'slug' => $font->slug,
        'name' => $font->name,
        'font_family' => $font->font_family,
        'file_woff2' => $font->file_woff2,
        'file_woff' => $font->file_woff,
        'file_ttf' => $font->file_ttf,
        'file_otf' => $font->file_otf,
    ], 200);
}
