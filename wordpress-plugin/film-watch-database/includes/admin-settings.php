<?php
/**
 * Admin Settings Page
 * Native PHP Implementation - No Flask backend required
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
 * Settings page HTML
 */
function fwd_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    // Get database stats and info
    $stats = fwd_get_stats();
    $db_info = fwd_db()->get_db_info();

    ?>
    <div class="wrap">
        <h1>Film Watch Database Settings</h1>

        <div class="fwd-admin-status" style="margin: 20px 0; padding: 15px; border-left: 4px solid #46b450; background: #ecf7ed;">
            <strong>Status:</strong>
            <span style="color: #46b450;">✓ WordPress-Native MySQL Backend</span>
            <p style="margin: 10px 0 0 0;">
                No external Flask server or SQLite extension required! The database uses WordPress's native MySQL connection.
            </p>
        </div>

        <h2>Database Information</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Database Type</th>
                <td><strong><?php echo esc_html($db_info['type']); ?></strong></td>
            </tr>
            <tr>
                <th scope="row">Database Host</th>
                <td><code><?php echo esc_html($db_info['host']); ?></code></td>
            </tr>
            <tr>
                <th scope="row">Database Name</th>
                <td><code><?php echo esc_html($db_info['name']); ?></code></td>
            </tr>
            <tr>
                <th scope="row">Table Prefix</th>
                <td><code><?php echo esc_html($db_info['prefix']); ?></code></td>
            </tr>
            <tr>
                <th scope="row">Plugin Tables</th>
                <td>
                    <?php foreach ($db_info['tables'] as $table): ?>
                        <code><?php echo esc_html($table); ?></code><br>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php if (isset($stats['stats'])): ?>
            <tr>
                <th scope="row">Total Films</th>
                <td><?php echo esc_html($stats['stats']['films']); ?></td>
            </tr>
            <tr>
                <th scope="row">Total Actors</th>
                <td><?php echo esc_html($stats['stats']['actors']); ?></td>
            </tr>
            <tr>
                <th scope="row">Total Brands</th>
                <td><?php echo esc_html($stats['stats']['brands']); ?></td>
            </tr>
            <tr>
                <th scope="row">Total Entries</th>
                <td><?php echo esc_html($stats['stats']['entries']); ?></td>
            </tr>
            <?php endif; ?>
        </table>

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

        <h2>System Requirements</h2>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1;">
            <table class="form-table">
                <tr>
                    <th scope="row">PHP Version</th>
                    <td>
                        <?php echo PHP_VERSION; ?>
                        <?php if (version_compare(PHP_VERSION, '7.4', '>=')): ?>
                            <span style="color: #46b450;">✓ Compatible</span>
                        <?php else: ?>
                            <span style="color: #dc3232;">✗ Requires PHP 7.4+</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">WordPress Database</th>
                    <td>
                        <?php global $wpdb; ?>
                        <?php if ($wpdb && $wpdb->db_server_info()): ?>
                            <span style="color: #46b450;">✓ Connected</span>
                            <br><small><?php echo esc_html($wpdb->db_server_info()); ?></small>
                        <?php else: ?>
                            <span style="color: #dc3232;">✗ Not connected</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">MySQL/MariaDB</th>
                    <td>
                        <span style="color: #46b450;">✓ Using WordPress Database</span>
                        <br><small>No additional database drivers needed</small>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <?php
}
