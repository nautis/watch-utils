<?php
/**
 * Admin Settings Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register admin menu
 */
function fwd_add_admin_menu() {
    add_options_page(
        'Film Watch Database Settings',
        'Film Watch DB',
        'manage_options',
        'film-watch-database',
        'fwd_settings_page'
    );
}
add_action('admin_menu', 'fwd_add_admin_menu');

/**
 * Register settings
 */
function fwd_register_settings() {
    register_setting('fwd_settings_group', 'fwd_settings', 'fwd_validate_settings');

    add_settings_section(
        'fwd_api_section',
        'Flask API Configuration',
        'fwd_api_section_callback',
        'film-watch-database'
    );

    add_settings_field(
        'api_url',
        'API URL',
        'fwd_api_url_callback',
        'film-watch-database',
        'fwd_api_section'
    );

    add_settings_section(
        'fwd_cache_section',
        'Cache Settings',
        'fwd_cache_section_callback',
        'film-watch-database'
    );

    add_settings_field(
        'cache_enabled',
        'Enable Cache',
        'fwd_cache_enabled_callback',
        'film-watch-database',
        'fwd_cache_section'
    );

    add_settings_field(
        'cache_duration',
        'Cache Duration (seconds)',
        'fwd_cache_duration_callback',
        'film-watch-database',
        'fwd_cache_section'
    );
}
add_action('admin_init', 'fwd_register_settings');

/**
 * API section description
 */
function fwd_api_section_callback() {
    echo '<p>Configure the connection to your Flask backend API.</p>';
}

/**
 * Cache section description
 */
function fwd_cache_section_callback() {
    echo '<p>Control how long API responses are cached to improve performance.</p>';
}

/**
 * API URL field
 */
function fwd_api_url_callback() {
    $settings = fwd_get_settings();
    ?>
    <input
        type="url"
        name="fwd_settings[api_url]"
        value="<?php echo esc_attr($settings['api_url']); ?>"
        class="regular-text"
        placeholder="http://127.0.0.1:5000"
    >
    <p class="description">
        The URL of your Flask backend API (e.g., http://127.0.0.1:5000 or https://api.example.com)
    </p>
    <?php
}

/**
 * Cache enabled field
 */
function fwd_cache_enabled_callback() {
    $settings = fwd_get_settings();
    ?>
    <label>
        <input
            type="checkbox"
            name="fwd_settings[cache_enabled]"
            value="1"
            <?php checked($settings['cache_enabled'], true); ?>
        >
        Enable caching of API responses
    </label>
    <p class="description">
        Recommended for better performance. Disable for development/testing.
    </p>
    <?php
}

/**
 * Cache duration field
 */
function fwd_cache_duration_callback() {
    $settings = fwd_get_settings();
    ?>
    <input
        type="number"
        name="fwd_settings[cache_duration]"
        value="<?php echo esc_attr($settings['cache_duration']); ?>"
        min="0"
        step="60"
        class="small-text"
    >
    <p class="description">
        How long to cache API responses (in seconds). Default: 300 (5 minutes)
    </p>
    <?php
}

/**
 * Validate settings
 */
function fwd_validate_settings($input) {
    $validated = array();

    // Validate API URL
    if (isset($input['api_url'])) {
        $url = esc_url_raw($input['api_url']);
        $validated['api_url'] = rtrim($url, '/');
    }

    // Validate cache enabled
    $validated['cache_enabled'] = isset($input['cache_enabled']) && $input['cache_enabled'] === '1';

    // Validate cache duration
    if (isset($input['cache_duration'])) {
        $validated['cache_duration'] = absint($input['cache_duration']);
    }

    return $validated;
}

/**
 * Settings page HTML
 */
function fwd_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    // Check backend status
    $backend_online = fwd_check_backend_status();

    ?>
    <div class="wrap">
        <h1>Film Watch Database Settings</h1>

        <div class="fwd-admin-status" style="margin: 20px 0; padding: 15px; border-left: 4px solid <?php echo $backend_online ? '#46b450' : '#dc3232'; ?>; background: <?php echo $backend_online ? '#ecf7ed' : '#f9e2e2'; ?>;">
            <strong>Backend Status:</strong>
            <?php if ($backend_online): ?>
                <span style="color: #46b450;">✓ Connected</span>
                <p style="margin: 10px 0 0 0;">Your Flask backend is running and accessible.</p>
            <?php else: ?>
                <span style="color: #dc3232;">✗ Offline</span>
                <p style="margin: 10px 0 0 0;">Cannot connect to Flask backend. Please ensure flask_backend.py is running.</p>
            <?php endif; ?>
        </div>

        <form method="post" action="options.php">
            <?php
            settings_fields('fwd_settings_group');
            do_settings_sections('film-watch-database');
            submit_button();
            ?>
        </form>

        <hr>

        <h2>Shortcode Usage</h2>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1;">
            <h3>Available Shortcodes:</h3>

            <h4>[film_watch_search]</h4>
            <p>Display a search form for the database.</p>
            <code>[film_watch_search]</code>
            <p><strong>Parameters:</strong></p>
            <ul>
                <li><code>type</code> - Search type: "all", "actor", "brand", or "film" (default: "all")</li>
                <li><code>placeholder</code> - Custom placeholder text</li>
            </ul>
            <p><strong>Example:</strong> <code>[film_watch_search type="actor" placeholder="Search for an actor..."]</code></p>

            <hr>

            <h4>[film_watch_stats]</h4>
            <p>Display database statistics.</p>
            <code>[film_watch_stats]</code>
            <p><strong>Parameters:</strong></p>
            <ul>
                <li><code>show_top_brands</code> - Show top brands list: "yes" or "no" (default: "yes")</li>
            </ul>

            <hr>

            <h4>[film_watch_actor name="Tom Cruise"]</h4>
            <p>Display watches for a specific actor.</p>
            <code>[film_watch_actor name="Tom Cruise"]</code>

            <hr>

            <h4>[film_watch_brand name="Rolex"]</h4>
            <p>Display films featuring a specific watch brand.</p>
            <code>[film_watch_brand name="Rolex"]</code>

            <hr>

            <h4>[film_watch_film title="Casino Royale"]</h4>
            <p>Display watches featured in a specific film.</p>
            <code>[film_watch_film title="Casino Royale"]</code>

            <hr>

            <h4>[film_watch_add]</h4>
            <p>Display a form to add new entries (admin only).</p>
            <code>[film_watch_add]</code>
        </div>

        <hr>

        <h2>Tools</h2>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1;">
            <h3>Clear Cache</h3>
            <p>Clear all cached API responses.</p>
            <button type="button" class="button" onclick="fwdClearCache()">Clear Cache</button>
            <div id="fwd-cache-result" style="margin-top: 10px;"></div>
        </div>
    </div>

    <script>
    function fwdClearCache() {
        const resultDiv = document.getElementById('fwd-cache-result');
        resultDiv.innerHTML = '<em>Clearing cache...</em>';

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'fwd_clear_cache',
                nonce: '<?php echo wp_create_nonce('fwd_clear_cache'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<span style="color: #46b450;">✓ Cache cleared successfully!</span>';
            } else {
                resultDiv.innerHTML = '<span style="color: #dc3232;">✗ Error clearing cache</span>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<span style="color: #dc3232;">✗ Network error</span>';
        });
    }
    </script>
    <?php
}

/**
 * AJAX handler to clear cache
 */
function fwd_ajax_clear_cache() {
    check_ajax_referer('fwd_clear_cache', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fwd_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_fwd_%'");

    wp_send_json_success(array('message' => 'Cache cleared'));
}
add_action('wp_ajax_fwd_clear_cache', 'fwd_ajax_clear_cache');
